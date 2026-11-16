<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Network\PublishUnitListing;
use App\Actions\Network\RequestReservation;
use App\Actions\Network\SetReservationHold;
use App\Livewire\Backend\Reports\AuditLog;
use App\Models\NetworkListing;
use App\Models\Store;
use App\Models\User;
use App\Services\Reporting\OperationalAuditCatalog;
use Carbon\CarbonImmutable;
use Database\Seeders\OperationalStoreSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\InteractsWithPos;
use Tests\TestCase;

class AuditLogTest extends TestCase
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
    public function only_allowlisted_operational_events_are_shown_with_curated_summaries(): void
    {
        $listing = app(PublishUnitListing::class)($this->owner, $this->store, $this->phone->id, NetworkListing::UNLISTED_REVISION);
        app(RequestReservation::class)($this->otherCashier, $this->other, $listing->id, $listing->revision, null);

        activity('shop_created')->causedBy($this->owner)->log('Legacy wholesale event');
        activity('order_created')->causedBy($this->owner)->withProperties(['store_id' => $this->store->id])->log('Legacy order');

        $page = Livewire::actingAs($this->owner)->withQueryParams(['from' => '2026-11-12', 'to' => '2026-11-12'])->test(AuditLog::class);
        $rows = collect($page->viewData('rows')->items());

        $this->assertTrue($rows->contains(fn (array $row): bool => $row['summary'] === 'Device published to Store Network'));
        $this->assertTrue($rows->contains(fn (array $row): bool => $row['summary'] === 'Reservation requested'));
        $this->assertFalse($rows->contains(fn (array $row): bool => str_contains($row['summary'], 'Legacy')));
        $page->assertDontSee('Legacy wholesale event');
        $page->assertDontSee('Legacy order');
    }

    #[Test]
    public function branch_and_global_attribution_and_malformed_store_ids_do_not_broaden_access(): void
    {
        activity('store_network')
            ->causedBy($this->owner)
            ->event('listing_published')
            ->withProperties(['store_id' => 'not-a-number', 'inventory_unit_id' => $this->phone->id])
            ->log('Malformed branch event');

        activity('selling_price')
            ->causedBy($this->owner)
            ->event('definition_price_set')
            ->withProperties(['old' => null, 'new' => '10.00', 'currency' => 'TRY', 'store_id' => $this->store->id])
            ->log('Global event wrongly carrying a store');

        activity('storefront')
            ->event('storefront_order_expired')
            ->withProperties(['store_id' => $this->store->id, 'order_id' => 1, 'status' => 'expired'])
            ->log('System expiry');

        $page = Livewire::actingAs($this->owner)->withQueryParams(['from' => '2026-11-12', 'to' => '2026-11-12'])->test(AuditLog::class);
        $rows = collect($page->viewData('rows')->items());

        $this->assertFalse($rows->contains(fn (array $row): bool => $row['summary'] === 'Device published to Store Network' && $row['branch'] === null && str_contains(json_encode($row), 'Malformed')));
        $this->assertFalse($rows->contains(fn (array $row): bool => $row['summary'] === 'Product selling price set'));
        $system = $rows->first(fn (array $row): bool => $row['summary'] === 'Storefront order pickup window ended');
        $this->assertNotNull($system);
        $this->assertSame('System', $system['actor']);
        $this->assertTrue($system['actor_is_system']);
    }

    #[Test]
    public function sensitive_payloads_are_not_rendered_and_unauthorized_filters_are_refused(): void
    {
        activity('cashbox')
            ->causedBy($this->owner)
            ->event('cash_intake_released')
            ->withProperties([
                'store_id' => $this->store->id,
                'cashbox_session_id' => 1,
                'amount' => '450.00',
                'note' => 'secret note',
                'customer_id' => 9,
            ])
            ->log('Cash intake 1 of 450.00 released');

        $page = Livewire::actingAs($this->owner)->withQueryParams(['from' => '2026-11-12', 'to' => '2026-11-12'])->test(AuditLog::class);
        $page->assertSee('Cash intake released');
        $page->assertDontSee('450.00');
        $page->assertDontSee('secret note');
        $page->assertDontSee('Cash intake 1 of');

        $page->set('storeId', '99999')->assertViewHas('filterError', 'invalid_store');
        $page->set('storeId', '')->set('area', 'order_created')->assertViewHas('filterError', 'invalid_area');
        $page->set('area', '')->set('event', 'not_an_event')->assertViewHas('filterError', 'invalid_event');
    }

    #[Test]
    public function owner_only_access_and_area_gates_apply_before_pagination(): void
    {
        Livewire::actingAs($this->createCashier())->test(AuditLog::class)->assertForbidden();

        activity('store_network')
            ->causedBy($this->owner)
            ->event('reservation_requested')
            ->withProperties(['store_id' => $this->store->id, 'inventory_unit_id' => $this->phone->id])
            ->log('Reservation #1: requested');

        $this->revokeFromOwners('manage_reservations');
        $this->revokeFromOwners('manage_cashbox');
        $this->revokeFromOwners('set_selling_prices');
        $this->revokeFromOwners('allocate_storefront_stock');
        $this->revokeFromOwners(OperationalStoreSetupSeeder::MANAGE_STOREFRONT_ORDERS_PERMISSION);
        $this->revokeFromOwners(OperationalStoreSetupSeeder::PUBLISH_STOREFRONT_PERMISSION);
        $this->revokeFromOwners('manage_catalog');
        $this->revokeFromOwners('manage_stores');

        // Owner still has use_pos / resolveCheckoutAttempts via use_pos; create a checkout_resolution event.
        activity('checkout_resolution')
            ->causedBy($this->owner)
            ->event('terminal_payment_cleared')
            ->withProperties(['store_id' => $this->store->id, 'amount' => '10.00', 'outcome' => 'no_charge'])
            ->log('Terminal payment cleared');

        $page = Livewire::actingAs($this->owner->fresh())->withQueryParams(['from' => '2026-11-12', 'to' => '2026-11-12'])->test(AuditLog::class);
        $rows = collect($page->viewData('rows')->items());
        $this->assertTrue($rows->contains(fn (array $row): bool => $row['summary'] === 'Terminal payment cleared'));
        $this->assertFalse($rows->contains(fn (array $row): bool => $row['summary'] === 'Reservation requested'));
        $page->assertDontSee('10.00');
    }

    #[Test]
    public function filters_reset_pagination_and_actor_choices_stay_within_authorized_rows(): void
    {
        for ($i = 0; $i < 30; $i++) {
            activity('store_network')
                ->causedBy($this->owner)
                ->event('reservation_hold_changed')
                ->withProperties(['store_id' => $this->store->id, 'from' => 30, 'to' => 60])
                ->log("Hold {$i}");
        }

        $page = Livewire::actingAs($this->owner)->withQueryParams(['from' => '2026-11-12', 'to' => '2026-11-12'])->test(AuditLog::class);
        $this->assertSame(OperationalAuditCatalog::PER_PAGE, $page->viewData('rows')->count());
        $page->call('setPage', 2)->assertSet('paginators.page', 2);
        $page->set('area', 'store_network')->assertSet('paginators.page', 1);
        $this->assertTrue($page->viewData('actors')->contains(fn (User $user): bool => $user->is($this->owner)));
    }

    #[Test]
    public function catalog_exposes_only_known_log_names(): void
    {
        $this->assertSame([
            'store_network',
            'cashbox',
            'checkout_resolution',
            'selling_price',
            'employee_permission',
            'store_management',
            'store_assignment',
            'storefront',
        ], OperationalAuditCatalog::logNames());
        $this->assertNull(OperationalAuditCatalog::definition('store_network', 'unknown_event'));
        $this->assertNotNull(OperationalAuditCatalog::definition('storefront', 'listing_published'));
        $this->assertNotNull(OperationalAuditCatalog::definition('store_network', 'listing_published'));
    }

    #[Test]
    public function actor_display_and_filter_privacy_respect_authorized_user_morphs(): void
    {
        $listing = app(PublishUnitListing::class)($this->owner, $this->store, $this->phone->id, NetworkListing::UNLISTED_REVISION);
        app(RequestReservation::class)($this->otherCashier, $this->other, $listing->id, $listing->revision, null);

        activity('storefront')
            ->event('storefront_order_expired')
            ->withProperties(['store_id' => $this->store->id, 'order_id' => 9, 'status' => 'expired'])
            ->log('System expiry');

        $deleted = User::factory()->create(['name' => 'Deleted Actor Visible Name']);
        activity('store_network')
            ->causedBy($deleted)
            ->event('reservation_hold_changed')
            ->withProperties(['store_id' => $this->store->id, 'from' => 30, 'to' => 60])
            ->log('Hold by deleted user');
        $deletedId = $deleted->id;
        $deleted->delete();

        $outsider = User::factory()->create(['name' => 'Secret Outsider Never Shown']);
        activity('shop_created')->causedBy($outsider)->log('Unauthorized legacy');

        $page = Livewire::actingAs($this->owner)->withQueryParams(['from' => '2026-11-12', 'to' => '2026-11-12'])->test(AuditLog::class);
        $rows = collect($page->viewData('rows')->items());

        $real = $rows->first(fn (array $row): bool => $row['summary'] === 'Reservation requested');
        $this->assertSame($this->otherCashier->name, $real['actor']);
        $this->assertFalse($real['actor_is_system']);

        $system = $rows->first(fn (array $row): bool => $row['summary'] === 'Storefront order pickup window ended');
        $this->assertSame('System', $system['actor']);
        $this->assertTrue($system['actor_is_system']);

        $missing = $rows->first(fn (array $row): bool => $row['summary'] === 'Reservation hold duration changed');
        $this->assertSame(__('reports.audit.no_actor'), $missing['actor']);

        $activity = Activity::query()->where('event', 'reservation_requested')->latest('id')->first();
        $this->assertSame((new User)->getMorphClass(), $activity->causer_type);

        $actors = $page->viewData('actors');
        $this->assertTrue($actors->contains(fn (User $user): bool => $user->is($this->otherCashier)));
        $this->assertFalse($actors->contains(fn (User $user): bool => $user->id === $outsider->id));
        $page->assertDontSee('Secret Outsider Never Shown');
        $page->assertDontSee('Deleted Actor Visible Name');

        $page->set('actorId', (string) $outsider->id)
            ->assertViewHas('filterError', 'invalid_actor')
            ->assertDontSee('Secret Outsider Never Shown');
        $this->assertFalse(
            collect($page->viewData('actors'))->contains(fn (User $user): bool => $user->is($outsider))
        );
        $this->assertNull($page->viewData('rows'));
        unset($deletedId);
    }
}
