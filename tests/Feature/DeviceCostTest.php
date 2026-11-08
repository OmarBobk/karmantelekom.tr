<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Inventory\DeviceCostInput;
use App\Actions\Inventory\RecordDeviceCost;
use App\Actions\Inventory\ReverseDeviceCost;
use App\Actions\Purchasing\ReceivePurchase;
use App\Enums\MovementType;
use App\Enums\TrackingMode;
use App\Enums\UnitStatus;
use App\Livewire\Backend\Inventory\UnitDetail;
use App\Models\DeviceCostEntry;
use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\OperationalSupplier;
use App\Models\ProductDefinition;
use App\Models\PurchaseReceiptUnit;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Support\InteractsWithDeviceLifecycle;
use Tests\Support\InteractsWithOperationalInventory;
use Tests\Support\InteractsWithPurchasing;
use Tests\TestCase;

class DeviceCostTest extends TestCase
{
    use InteractsWithDeviceLifecycle;
    use InteractsWithOperationalInventory;
    use InteractsWithPurchasing;
    use RefreshDatabase;

    private User $owner;

    private Store $store;

    private ProductDefinition $usedPhone;

    private InventoryUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOperationalRoles();
        $this->useEnglishLocale();
        $this->owner = $this->createOwner();
        $this->store = Store::factory()->create(['code' => 'KT01']);
        $this->usedPhone = ProductDefinition::factory()->create(['name' => 'iPhone 13', 'tracking_mode' => TrackingMode::USED_DEVICE]);
        $this->unit = $this->registerDevice($this->owner, $this->store, $this->usedPhone, self::imei('35693803564380'));
    }

    #[Test]
    public function unknown_and_zero_amounts_are_stored_differently_and_exactly(): void
    {
        $this->recordCost('repair_labor', null, '0', 'TRY');
        $this->recordCost('repair_labor', null, null, null, unknown: true);
        $this->recordCost('repair_parts', 'Battery', '1234.56', 'USD');

        $entries = DeviceCostEntry::query()->orderBy('id')->get();
        $this->assertSame(['0.00', null, '1234.56'], $entries->pluck('amount')->all());
        $this->assertSame([0, null, 123456], $entries->map(fn ($entry) => $entry->amountMinor())->all());
        $this->assertNull($entries[1]->currency);
        $this->assertSame(UnitStatus::AVAILABLE, $this->unit->fresh()->status);
        $this->assertSame(0, $this->unit->fresh()->lifecycle_revision);
        $this->assertSame(1, (int) InventoryMovement::query()->sum('quantity_change'));
        $this->assertSame(3, InventoryMovement::query()->where('type', 'device_cost_recorded')->where('quantity_change', 0)->count());
    }

    /**
     * @return array<string, array{0: ?string, 1: ?string, 2: ?string, 3: ?string, 4: string}>
     */
    public static function invalidCosts(): array
    {
        return [
            'three decimals' => ['repair_labor', null, '12.345', 'TRY', 'cost_amount'],
            'negative' => ['repair_labor', null, '-5', 'TRY', 'cost_amount'],
            'not a number' => ['repair_labor', null, 'abc', 'TRY', 'cost_amount'],
            'missing amount' => ['repair_labor', null, '', 'TRY', 'cost_amount'],
            'missing currency' => ['repair_labor', null, '10', '', 'cost_currency'],
            'unsupported currency' => ['repair_labor', null, '10', 'EUR', 'cost_currency'],
            'parts without description' => ['repair_parts', ' ', '10', 'TRY', 'cost_description'],
            'unknown category' => ['shipping', null, '10', 'TRY', 'cost_category'],
        ];
    }

    #[Test]
    #[DataProvider('invalidCosts')]
    public function invalid_costs_are_rejected_on_the_right_field(?string $category, ?string $description, ?string $amount, ?string $currency, string $errorKey): void
    {
        try {
            DeviceCostInput::fromInput($category, $description, $amount, $currency, false);
            $this->fail('Expected a validation error.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($errorKey, $exception->errors());
        }
    }

    #[Test]
    public function the_database_rejects_an_amount_without_currency_and_a_partial_reversal(): void
    {
        $this->recordCost('repair_labor', null, '10', 'TRY');
        $entry = DeviceCostEntry::query()->sole();
        $row = (array) DB::table('device_cost_entries')->where('id', $entry->id)->first();

        $writes = [
            'amount without currency' => fn () => DB::table('device_cost_entries')->insert([...$row, 'id' => null, 'currency' => null]),
            'currency without amount' => fn () => DB::table('device_cost_entries')->insert([...$row, 'id' => null, 'amount' => null]),
            'partial reversal' => fn () => DB::table('device_cost_entries')->where('id', $entry->id)->update(['reversed_at' => now()]),
        ];

        foreach ($writes as $label => $write) {
            try {
                $write();
                $this->fail("The database accepted: {$label}.");
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(1, DeviceCostEntry::count());
        $this->assertFalse($entry->fresh()->isReversed());
    }

    #[Test]
    public function a_correction_reverses_the_original_and_records_the_replacement_atomically(): void
    {
        $this->recordCost('repair_parts', 'Screen', '1500.00', 'TRY');
        $original = DeviceCostEntry::query()->sole();

        $movement = app(ReverseDeviceCost::class)($this->owner, $this->store, $this->unit->id, $original->id, 'Typo in amount', DeviceCostInput::fromInput('repair_parts', 'Screen', '150.00', 'TRY', false, 'replacement_'), 'reverse-1');

        $original->refresh();
        $replacement = DeviceCostEntry::query()->whereKeyNot($original->id)->sole();
        $this->assertTrue($original->isReversed());
        $this->assertSame('Typo in amount', $original->reversal_reason);
        $this->assertSame($movement->id, $original->reversal_movement_id);
        $this->assertSame($movement->id, $replacement->inventory_movement_id);
        $this->assertSame($original->id, $replacement->replaces_entry_id);
        $this->assertSame('150.00', $replacement->amount);
        $this->assertSame(0, $movement->quantity_change);

        try {
            app(ReverseDeviceCost::class)($this->owner, $this->store, $this->unit->id, $original->id, 'Again', null, 'reverse-2');
            $this->fail('A reversed entry cannot be reversed again.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reversal_reason', $exception->errors());
        }

        DeviceCostEntry::creating(fn () => throw new RuntimeException('Late failure'));

        try {
            app(ReverseDeviceCost::class)($this->owner, $this->store, $this->unit->id, $replacement->id, 'Wrong again', DeviceCostInput::fromInput('repair_parts', 'Screen', '160.00', 'TRY', false), 'reverse-3');
            $this->fail('The late failure should propagate.');
        } catch (RuntimeException) {
        }

        $this->assertFalse($replacement->fresh()->isReversed());
        $this->assertSame(2, DeviceCostEntry::count());
    }

    #[Test]
    public function reversing_a_cost_keeps_its_original_recorded_time(): void
    {
        $this->travelTo(now()->subDays(3)->startOfMinute());
        $this->recordCost('repair_labor', null, '200', 'TRY');
        $original = DeviceCostEntry::query()->sole();
        $this->travelBack();

        app(ReverseDeviceCost::class)($this->owner, $this->store, $this->unit->id, $original->id, 'Not charged', null, 'reverse-1');

        $reversed = $original->fresh();
        $this->assertTrue($reversed->isReversed());
        $this->assertTrue($reversed->recorded_at->equalTo($original->recorded_at));
        $this->assertTrue($reversed->reversed_at->greaterThan($original->recorded_at));
    }

    #[Test]
    public function a_cost_from_another_unit_or_repair_cannot_be_targeted(): void
    {
        $other = $this->registerDevice($this->owner, $this->store, $this->usedPhone, self::imei('35693803564381'));
        $this->recordCost('repair_labor', null, '10', 'TRY', unit: $other);
        $otherEntry = DeviceCostEntry::query()->sole();
        $otherRepair = $this->deviceNeedingRepair($this->owner, $this->store, $this->usedPhone, self::imei('35693803564382'));
        $this->startDeviceRepair($this->owner, $otherRepair);

        try {
            app(ReverseDeviceCost::class)($this->owner, $this->store, $this->unit->id, $otherEntry->id, 'Tampered', null, 'tamper-1');
            $this->fail('Expected not found.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reversal_reason', $exception->errors());
        }

        try {
            app(RecordDeviceCost::class)($this->owner, $this->store, $this->unit->id, DeviceCostInput::fromInput('repair_labor', null, '1', 'TRY', false), $otherRepair->repairs()->sole()->id, 'tamper-2');
            $this->fail('Expected not found.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('cost_repair', $exception->errors());
        }

        $this->assertFalse($otherEntry->fresh()->isReversed());
        $this->assertSame(1, DeviceCostEntry::count());
    }

    #[Test]
    public function received_acquisition_cost_is_unchanged_and_shown_beside_device_costs(): void
    {
        $supplier = OperationalSupplier::factory()->create();
        $purchase = $this->postedPurchase($this->owner, $this->store, $supplier, [[$this->usedPhone, 1, '8000.00']]);
        $line = $purchase->lines()->sole();
        $receipt = app(ReceivePurchase::class)($this->owner, $purchase, [$line->id => [['condition' => 'b', 'imei' => self::imei('35693803564383'), 'secondary_imei' => null, 'serial' => null]]], null, 'receipt-1');
        $received = $receipt->lines()->sole()->units()->sole()->unit;

        $this->recordCost('repair_parts', 'Screen', '1500.00', 'TRY', unit: $received);
        $this->recordCost('repair_labor', null, '25.00', 'USD', unit: $received);
        $this->recordCost('other', 'Courier', null, null, unknown: true, unit: $received);

        $this->assertSame('8000.00', PurchaseReceiptUnit::query()->sole()->unit_cost);

        Livewire::actingAs($this->owner)
            ->test(UnitDetail::class, ['store' => $this->store, 'unit' => $received])
            ->assertSee(['8,000.00', '1,500.00', '25.00', 'USD', 'Unknown', 'plus 1 unknown amount', 'Excludes 1 unknown amount', 'Purchase invoice'])
            ->assertViewHas('costEntries', fn ($entries) => $entries->count() === 3);

        Livewire::actingAs($this->owner)
            ->test(UnitDetail::class, ['store' => $this->store, 'unit' => $this->unit])
            ->assertSee('Initial stock setup — cost unknown');
    }

    #[Test]
    public function cost_amounts_and_cost_events_stay_hidden_without_cost_access(): void
    {
        $this->recordCost('repair_parts', 'Screen', '1234.56', 'TRY');
        $entry = DeviceCostEntry::query()->sole();
        $this->revokeFromOwners('manage_device_costs');

        Livewire::actingAs($this->owner)
            ->test(UnitDetail::class, ['store' => $this->store, 'unit' => $this->unit])
            ->assertViewHas('costEntries', null)
            ->assertViewHas('timeline', fn ($timeline) => $timeline->total() === 1 && $timeline->items()[0]->type === MovementType::OPENING_BALANCE)
            ->assertDontSee(['1,234.56', 'Device costs', 'Cost recorded', 'Record cost']);

        $component = fn () => Livewire::actingAs($this->owner)->test(UnitDetail::class, ['store' => $this->store, 'unit' => $this->unit]);
        $component()->set('costCategory', 'repair_labor')->set('costAmount', '1')->set('costCurrency', 'TRY')->call('recordCost')->assertForbidden();
        $component()->set('reversingEntryId', (string) $entry->id)->set('reversalReason', 'x')->call('reverseCost')->assertForbidden();

        $this->assertSame(1, DeviceCostEntry::count());
        $this->assertFalse($entry->fresh()->isReversed());
    }

    #[Test]
    public function the_acquisition_amount_needs_both_purchase_and_cost_access(): void
    {
        $supplier = OperationalSupplier::factory()->create();
        $purchase = $this->postedPurchase($this->owner, $this->store, $supplier, [[$this->usedPhone, 1, '8000.00']]);
        $line = $purchase->lines()->sole();
        $receipt = app(ReceivePurchase::class)($this->owner, $purchase, [$line->id => [['condition' => 'b', 'imei' => self::imei('35693803564383'), 'secondary_imei' => null, 'serial' => null]]], null, 'receipt-1');
        $received = $receipt->lines()->sole()->units()->sole()->unit;
        $invoiceUrl = route('subdomain.purchases.show', $purchase);

        $this->revokeFromOwners('manage_device_costs');
        $this->actingAs($this->owner)->get(route('subdomain.inventory.unit', [$this->store, $received]))
            ->assertOk()
            ->assertSee($invoiceUrl, false)
            ->assertDontSee('8,000.00');
    }

    private function recordCost(string $category, ?string $description, ?string $amount, ?string $currency, bool $unknown = false, ?InventoryUnit $unit = null): void
    {
        $unit ??= $this->unit;

        app(RecordDeviceCost::class)($this->owner, $unit->store, $unit->id, DeviceCostInput::fromInput($category, $description, $amount, $currency, $unknown), null, uniqid('cost-', true));
    }
}
