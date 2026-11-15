<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Inventory\DeviceCostInput;
use App\Actions\Inventory\RecordDeviceCost;
use App\Actions\Inventory\ReverseDeviceCost;
use App\Actions\Network\ApproveReservation;
use App\Actions\Network\CancelReservation;
use App\Actions\Network\PublishUnitListing;
use App\Actions\Network\RejectReservation;
use App\Actions\Network\RequestReservation;
use App\Actions\Network\SetReservationHold;
use App\Enums\RepairStatus;
use App\Enums\ReservationStatus;
use App\Enums\UnitStatus;
use App\Livewire\Backend\Inventory\ProductInventory;
use App\Livewire\Backend\Inventory\StoreInventory;
use App\Livewire\Backend\Reports\OperationalReview;
use App\Livewire\Pos\NetworkReservations;
use App\Models\NetworkListing;
use App\Models\NetworkReservation;
use App\Models\Store;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\InteractsWithPos;
use Tests\TestCase;

class OperationalReviewTest extends TestCase
{
    use InteractsWithPos;
    use RefreshDatabase;

    private Store $other;

    private User $otherCashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCounter();
        $this->useEnglishLocale();
        $this->travelTo(CarbonImmutable::parse('2026-11-12 12:00:00', 'Europe/Istanbul'));
        $this->other = Store::factory()->create(['code' => 'KT02', 'name' => 'Karman Kadikoy']);
        $this->otherCashier = $this->createCashier($this->other, name: 'Kadikoy cashier');
        app(SetReservationHold::class)($this->owner, $this->store, 60);
    }

    #[Test]
    public function display_state_scope_matches_display_state_precedence_and_expiry(): void
    {
        $pending = $this->requestFor($this->phone);
        $this->assertSame('pending', $pending->displayState());
        $this->assertTrue(NetworkReservation::query()->inDisplayState('pending')->whereKey($pending)->exists());

        $approved = app(ApproveReservation::class)($this->owner, $this->store, $pending->id);
        $this->assertSame('approved', $approved->displayState());
        $this->assertTrue(NetworkReservation::query()->inDisplayState('approved')->whereKey($approved)->exists());

        $attempt = $this->review($this->owner, $this->cableLine());
        $approved->forceFill(['claimed_attempt_id' => $attempt->id, 'claimed_at' => now()])->save();
        $this->assertSame('payment_started', $approved->fresh()->displayState());
        $this->assertTrue(NetworkReservation::query()->inDisplayState('payment_started')->whereKey($approved)->exists());
        $this->assertFalse(NetworkReservation::query()->inDisplayState('expired')->whereKey($approved)->exists());

        $approved->forceFill([
            'claimed_attempt_id' => null,
            'claimed_at' => null,
            'dispatched_at' => now(),
            'expires_at' => now()->subMinute(),
        ])->save();
        $this->assertSame('in_transit', $approved->fresh()->displayState());
        $this->assertTrue(NetworkReservation::query()->inDisplayState('in_transit')->whereKey($approved)->exists());
        $this->assertFalse(NetworkReservation::query()->inDisplayState('expired')->whereKey($approved)->exists());

        $approved->forceFill(['received_at' => now(), 'expires_at' => now()->addHour()])->save();
        $this->assertSame('received', $approved->fresh()->displayState());
        $this->assertTrue(NetworkReservation::query()->inDisplayState('received')->whereKey($approved)->exists());

        $approved->forceFill(['expires_at' => now()->subMinute(), 'received_at' => now()])->save();
        $this->assertSame('expired', $approved->fresh()->displayState());
        $this->assertTrue(NetworkReservation::query()->inDisplayState('expired')->whereKey($approved)->exists());
    }

    #[Test]
    public function network_period_counts_use_timestamps_and_incoming_totals_count_each_reservation_once(): void
    {
        $day = ['from' => '2026-11-12', 'to' => '2026-11-12'];
        $listing = app(PublishUnitListing::class)($this->owner, $this->store, $this->phone->id, NetworkListing::UNLISTED_REVISION);
        $reservation = app(RequestReservation::class)($this->otherCashier, $this->other, $listing->id, $listing->revision, null);
        $requestedAt = $reservation->requested_at;
        app(ApproveReservation::class)($this->owner, $this->store, $reservation->id);

        $secondPhone = $this->registerDevice($this->owner, $this->store, $this->usedPhone, self::imei('35693803564406'));
        $secondPhone->update(['selling_price' => '9000.00']);
        $secondListing = app(PublishUnitListing::class)($this->owner, $this->store, $secondPhone->id, NetworkListing::UNLISTED_REVISION);
        $rejected = app(RequestReservation::class)($this->otherCashier, $this->other, $secondListing->id, $secondListing->revision, null);
        app(RejectReservation::class)($this->owner, $this->store, $rejected->id, 'Not available');

        $thirdPhone = $this->registerDevice($this->owner, $this->store, $this->usedPhone, self::imei('35693803564414'));
        $thirdPhone->update(['selling_price' => '8000.00']);
        $thirdListing = app(PublishUnitListing::class)($this->owner, $this->store, $thirdPhone->id, NetworkListing::UNLISTED_REVISION);
        $cancelled = app(RequestReservation::class)($this->otherCashier, $this->other, $thirdListing->id, $thirdListing->revision, null);
        app(ApproveReservation::class)($this->owner, $this->store, $cancelled->id);
        app(CancelReservation::class)($this->otherCashier, $this->other, $cancelled->id, 'Customer left');

        // MySQL auto ON UPDATE on the first timestamp column rewrites requested_at on later
        // row updates; restore the freeze-time values so period "requested" can be asserted.
        NetworkReservation::query()->update(['requested_at' => $requestedAt]);

        $report = Livewire::actingAs($this->owner)->withQueryParams($day)->test(OperationalReview::class);
        $network = $report->viewData('network');
        $incoming = collect($network['incoming'])->first(fn (array $row): bool => $row['store']->is($this->store));
        $outgoing = collect($network['outgoing'])->first(fn (array $row): bool => $row['store']->is($this->other));

        $this->assertNotNull($incoming);
        $this->assertSame(3, $incoming['period']['requested']);
        $this->assertSame(2, $incoming['period']['approved']);
        $this->assertSame(1, $incoming['period']['rejected']);
        $this->assertSame(1, $incoming['period']['cancelled']);
        $this->assertSame(3, $network['period_totals']['requested']);
        $this->assertSame($incoming['period']['requested'], $outgoing['period']['requested']);
        $this->assertSame(0, $incoming['cancelled_by_owning']);
        $this->assertSame(1, $incoming['cancelled_by_requesting']);

        $list = Livewire::actingAs($this->owner)->withQueryParams([
            'tab' => 'incoming',
            'event' => 'approved',
            ...$day,
        ])->test(NetworkReservations::class, ['store' => $this->store]);
        $this->assertSame(2, $list->viewData('reservations')->total());
    }

    #[Test]
    public function istanbul_edges_and_unstored_expiry_and_released_claims_are_counted_by_expires_at(): void
    {
        $listing = app(PublishUnitListing::class)($this->owner, $this->store, $this->phone->id, NetworkListing::UNLISTED_REVISION);
        $reservation = app(RequestReservation::class)($this->otherCashier, $this->other, $listing->id, $listing->revision, null);
        app(ApproveReservation::class)($this->owner, $this->store, $reservation->id);

        $edge = CarbonImmutable::parse('2026-11-12 00:30:00', 'Europe/Istanbul')->utc();
        $reservation->forceFill([
            'expires_at' => $edge,
            'claimed_attempt_id' => null,
            'dispatched_at' => null,
            'received_at' => null,
            'status' => ReservationStatus::APPROVED,
        ])->save();

        $this->travelTo(CarbonImmutable::parse('2026-11-12 12:00:00', 'Europe/Istanbul'));
        $report = Livewire::actingAs($this->owner)->withQueryParams(['from' => '2026-11-12', 'to' => '2026-11-12'])->test(OperationalReview::class);
        $incoming = collect($report->viewData('network')['incoming'])->first(fn (array $row): bool => $row['store']->is($this->store));
        $this->assertSame(1, $incoming['period']['expired']);
        $this->assertSame(1, $incoming['current']['expired']);

        $attempt = $this->review($this->owner, $this->cableLine());
        $reservation->forceFill(['claimed_attempt_id' => $attempt->id, 'claimed_at' => now()])->save();
        $report = Livewire::actingAs($this->owner)->withQueryParams(['from' => '2026-11-12', 'to' => '2026-11-12'])->test(OperationalReview::class);
        $incoming = collect($report->viewData('network')['incoming'])->first(fn (array $row): bool => $row['store']->is($this->store));
        $this->assertSame(0, $incoming['period']['expired']);
        $this->assertSame(1, $incoming['current']['payment_started']);

        $reservation->forceFill(['claimed_attempt_id' => null, 'claimed_at' => null])->save();
        $report = Livewire::actingAs($this->owner)->withQueryParams(['from' => '2026-11-12', 'to' => '2026-11-12'])->test(OperationalReview::class);
        $incoming = collect($report->viewData('network')['incoming'])->first(fn (array $row): bool => $row['store']->is($this->store));
        $this->assertSame(1, $incoming['period']['expired']);
    }

    #[Test]
    public function device_status_and_repair_costs_respect_permissions_currencies_and_unknowns(): void
    {
        $needsRepair = $this->deviceNeedingRepair($this->owner, $this->store, $this->usedPhone, self::imei('35693803564422'));
        $this->startDeviceRepair($this->owner, $needsRepair);
        $completed = $this->deviceNeedingRepair($this->owner, $this->store, $this->usedPhone, self::imei('35693803564430'));
        $this->startDeviceRepair($this->owner, $completed);
        $this->closeDeviceRepair($this->owner, $completed, RepairStatus::COMPLETED, true, [
            DeviceCostInput::fromInput('repair_parts', 'Screen', '100.00', 'TRY', false),
            DeviceCostInput::fromInput('repair_labor', null, null, null, true),
        ]);
        app(RecordDeviceCost::class)(
            $this->owner,
            $this->store,
            $completed->id,
            DeviceCostInput::fromInput('other', 'Shipping', '10.00', 'USD', false),
            $completed->fresh()->repairs()->latest('id')->value('id'),
            (string) Str::uuid(),
        );

        $reversed = DeviceCostInput::fromInput('repair_parts', 'Wrong', '50.00', 'TRY', false);
        $movement = app(RecordDeviceCost::class)($this->owner, $this->store, $completed->id, $reversed, null, (string) Str::uuid());
        $entryId = $completed->fresh()->costEntries()->latest('id')->value('id');
        app(ReverseDeviceCost::class)($this->owner, $this->store, $completed->id, $entryId, 'Not charged', null, (string) Str::uuid());

        $noCost = $this->deviceNeedingRepair($this->owner, $this->store, $this->usedPhone, self::imei('35693803564448'));
        $this->startDeviceRepair($this->owner, $noCost);
        $this->closeDeviceRepair($this->owner, $noCost);

        $report = Livewire::actingAs($this->owner)->withQueryParams(['from' => '2026-11-12', 'to' => '2026-11-12'])->test(OperationalReview::class);
        $devices = $report->viewData('devices');
        $status = collect($devices['status_rows'])->first(fn (array $row): bool => $row['store']->is($this->store));

        $this->assertGreaterThanOrEqual(1, $status['counts'][UnitStatus::IN_REPAIR->value]);
        $this->assertSame(2, $devices['closed_counts'][$this->store->id]['completed']);
        $this->assertNotNull($devices['costs']);

        $costRow = collect($devices['costs']['rows'])->first(fn (array $row): bool => $row['store']->is($this->store));
        $this->assertGreaterThan(0, $costRow['unknown_acquisition']);
        $this->assertArrayNotHasKey('TRY', $costRow['acquisition']);

        $closed = collect($devices['closed_repairs']->items());
        $withCosts = $closed->first(fn (array $item): bool => $item['repair']->inventory_unit_id === $completed->id);
        $this->assertSame(10_000, $withCosts['costs']['known']['TRY']);
        $this->assertSame(1_000, $withCosts['costs']['known']['USD']);
        $this->assertSame(1, $withCosts['costs']['unknown']);
        $pending = $closed->first(fn (array $item): bool => $item['repair']->inventory_unit_id === $noCost->id);
        $this->assertFalse($pending['costs']['has_entries']);

        $this->revokeFromOwners('manage_device_costs');
        $withoutCosts = Livewire::actingAs($this->owner->fresh())->withQueryParams(['from' => '2026-11-12', 'to' => '2026-11-12'])->test(OperationalReview::class);
        $this->assertNull($withoutCosts->viewData('devices')['costs']);
        foreach ($withoutCosts->viewData('devices')['closed_repairs'] as $item) {
            $this->assertNull($item['costs']);
        }
    }

    #[Test]
    public function inventory_status_counts_agree_with_filtered_lists(): void
    {
        $inRepair = $this->deviceNeedingRepair($this->owner, $this->store, $this->usedPhone, self::imei('35693803564455'));
        $this->startDeviceRepair($this->owner, $inRepair);

        $report = Livewire::actingAs($this->owner)->test(OperationalReview::class);
        $status = collect($report->viewData('devices')['status_rows'])->first(fn (array $row): bool => $row['store']->is($this->store));
        $inServiceOnReport = $status['counts'][UnitStatus::INSPECTION->value] + $status['counts'][UnitStatus::IN_REPAIR->value];
        $this->assertGreaterThanOrEqual(1, $inServiceOnReport);

        $storeList = Livewire::actingAs($this->owner)->withQueryParams(['units' => 'in_service'])->test(StoreInventory::class, ['store' => $this->store]);
        $this->assertTrue(
            collect($storeList->viewData('products')->items())->contains(
                fn ($product): bool => (int) $product->id === (int) $this->usedPhone->id
            )
        );

        $productList = Livewire::actingAs($this->owner)->withQueryParams(['units' => 'in_service'])->test(ProductInventory::class, [
            'store' => $this->store,
            'productDefinition' => $this->usedPhone,
        ]);
        $this->assertSame($inServiceOnReport, $productList->viewData('units')->total());
    }

    #[Test]
    public function owner_only_entry_and_section_gates_are_enforced(): void
    {
        Livewire::actingAs($this->owner)->test(OperationalReview::class)->assertSuccessful();

        $cashier = $this->createCashier();
        Livewire::actingAs($cashier)->test(OperationalReview::class)->assertForbidden();

        $this->revokeFromOwners('manage_reservations');
        $this->revokeFromOwners('view_inventory');
        Livewire::actingAs($this->owner->fresh())->test(OperationalReview::class)->assertForbidden();
    }

    #[Test]
    public function invalid_ranges_suppress_period_figures_while_current_remain(): void
    {
        $listing = app(PublishUnitListing::class)($this->owner, $this->store, $this->phone->id, NetworkListing::UNLISTED_REVISION);
        $reservation = app(RequestReservation::class)($this->otherCashier, $this->other, $listing->id, $listing->revision, null);

        $report = Livewire::actingAs($this->owner)->withQueryParams(['from' => '2026-11-13', 'to' => '2026-11-12'])->test(OperationalReview::class);
        $this->assertSame('reversed', $report->viewData('rangeError'));
        $network = $report->viewData('network');
        $this->assertNull($network['period_totals']);
        $incoming = collect($network['incoming'])->first(fn (array $row): bool => $row['store']->is($this->store));
        $this->assertSame(1, $incoming['current']['pending']);
        $this->assertNull($incoming['period']);
    }

    private function requestFor($unit): NetworkReservation
    {
        $listing = app(PublishUnitListing::class)($this->owner, $unit->store, $unit->id, NetworkListing::UNLISTED_REVISION);

        return app(RequestReservation::class)($this->otherCashier, $this->other, $listing->id, $listing->revision, null);
    }
}
