<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Inventory\DeclareQuantityCost;
use App\Actions\Inventory\DeviceCostInput;
use App\Actions\Inventory\RecordDeviceCost;
use App\Actions\Inventory\RecordStockCount;
use App\Actions\Inventory\ReverseDeviceCost;
use App\Actions\Network\ApproveReservation;
use App\Actions\Network\PublishUnitListing;
use App\Actions\Network\RequestReservation;
use App\Actions\Network\SetReservationHold;
use App\Actions\Pos\FinalizeCheckout;
use App\Actions\Pos\ReviewCheckout;
use App\Actions\Purchasing\ReceivePurchase;
use App\Enums\DeviceCostCategory;
use App\Enums\SaleLineCostStatus;
use App\Enums\TrackingMode;
use App\Enums\UnitStatus;
use App\Livewire\Backend\Reports\SalesMargin;
use App\Models\DeviceCostEntry;
use App\Models\InventoryBalance;
use App\Models\NetworkListing;
use App\Models\OperationalSupplier;
use App\Models\ProductDefinition;
use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use App\Services\Inventory\DeviceSaleCost;
use App\Services\Purchasing\Money;
use App\Services\Reporting\LocalDateRange;
use App\Services\Reporting\SalesMarginReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\InteractsWithPos;
use Tests\Support\InteractsWithPurchasing;
use Tests\TestCase;

class SalesMarginReportTest extends TestCase
{
    use InteractsWithPos;
    use InteractsWithPurchasing;
    use RefreshDatabase;

    private User $cashier;

    private OperationalSupplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOperationalRoles();
        $this->owner = $this->createOwner(['name' => 'Omar']);
        $this->store = Store::factory()->create(['code' => 'KT01', 'name' => 'Karman Fatih']);
        $this->cable = ProductDefinition::factory()->priced('49.90')->create([
            'name' => 'USB-C cable',
            'tracking_mode' => TrackingMode::QUANTITY,
            'barcode' => '8690000000017',
            'sku' => 'CBL-USBC-1M',
        ]);
        $this->usedPhone = ProductDefinition::factory()->create([
            'name' => 'iPhone 13',
            'model' => '128 GB',
            'tracking_mode' => TrackingMode::USED_DEVICE,
        ]);
        $this->phoneImei = self::imei('35693803564380');
        $this->phoneSecondImei = self::imei('35693803564398');
        $this->phone = $this->registerDevice($this->owner, $this->store, $this->usedPhone, $this->phoneImei, null, $this->phoneSecondImei);
        $this->phone->update(['selling_price' => '12500.00']);
        $this->cashier = $this->createCashier();
        $this->supplier = OperationalSupplier::factory()->create();
    }

    #[Test]
    public function report_reconciles_known_unknown_and_network_revenue(): void
    {
        $this->receiveCable(10, '10.00');
        $knownSale = $this->sellCable(2);
        $this->assertSame(SaleLineCostStatus::KNOWN, $knownSale->lines()->first()->cost_status);

        app(RecordStockCount::class)(
            $this->owner,
            $this->store,
            $this->cable,
            12,
            RecordStockCount::latestMovementId($this->store, $this->cable),
            'found',
            (string) Str::uuid(),
        );
        $unknownSale = $this->sellCable(1);
        $this->assertSame(SaleLineCostStatus::UNKNOWN, $unknownSale->lines()->first()->cost_status);

        $networkSale = $this->sellNetworkPhone();

        $today = LocalDateRange::date(LocalDateRange::today());
        $report = $this->summary();
        $rows = $this->pagedRows();

        $this->assertSame(3, $report['recorded_sales_count']);
        $this->assertSame(
            $report['recorded_revenue_minor'],
            $report['coverage']['sum'],
        );
        $this->assertSame(
            Money::sumUncapped([
                $report['known_revenue_minor'],
                $report['unknown_revenue_minor'],
                $report['network_revenue_minor'],
            ]),
            $report['recorded_revenue_minor'],
        );
        $this->assertSame(1, $report['network_sale_count']);
        $this->assertSame($networkSale->totalMinor(), $report['network_revenue_minor']);
        $this->assertTrue($rows->contains(
            fn (array $row): bool => $row['classification'] === 'network' && $row['margin_minor'] === null,
        ));
    }

    #[Test]
    public function mixed_sale_never_shows_whole_sale_margin(): void
    {
        $this->receiveCable(5, '10.00');
        // Opening device has no acquisition receipt → unknown device cost beside known quantity cost.
        $this->phone->update(['selling_price' => '2000.00', 'status' => UnitStatus::AVAILABLE]);

        $attempt = $this->review($this->cashier, $this->cableLine(1) + $this->phoneLine($this->phone));
        $terminal = $this->createTerminal();
        $this->approveCard($this->cashier, $attempt, $terminal, '2000.00');
        $sale = app(FinalizeCheckout::class)(
            $this->cashier,
            $this->store,
            $attempt->key,
            $attempt->fresh()->revision,
            '49.90',
            null,
        );

        $row = $this->pagedRows()->firstWhere('sale_id', $sale->id);

        $this->assertSame('mixed_or_unknown', $row['classification']);
        $this->assertNull($row['margin_minor']);
        $this->assertGreaterThan(1, count($row['line_summaries']));
        $this->assertTrue(collect($row['line_summaries'])->contains(fn (array $line): bool => $line['status'] === 'known'));
        $this->assertTrue(collect($row['line_summaries'])->contains(fn (array $line): bool => $line['status'] === 'unknown'));
    }

    #[Test]
    public function device_drilldown_distinguishes_snapshot_exclusion_and_later_reversal(): void
    {
        $imei = self::imei('35693803564384');
        $purchase = $this->postedPurchase($this->owner, $this->store, $this->supplier, [[$this->usedPhone, 1, '1000.00']]);
        $line = $purchase->lines()->sole();
        $receipt = app(ReceivePurchase::class)($this->owner, $purchase, [
            $line->id => [['condition' => 'b', 'imei' => $imei, 'secondary_imei' => null, 'serial' => null]],
        ], null, (string) Str::uuid());
        $unit = $receipt->lines()->sole()->units()->sole()->unit;
        $unit->update(['selling_price' => '2000.00', 'status' => UnitStatus::AVAILABLE]);

        $parts = app(RecordDeviceCost::class)(
            $this->owner,
            $this->store,
            $unit->id,
            DeviceCostInput::fromInput('repair_parts', 'screen', '100.00', 'TRY', false),
            null,
            (string) Str::uuid(),
        );
        app(RecordDeviceCost::class)(
            $this->owner,
            $this->store,
            $unit->id,
            DeviceCostInput::fromInput('other', 'shipping', '25.00', 'TRY', false),
            null,
            (string) Str::uuid(),
        );

        $sale = $this->finalizePhone($unit);
        $saleLine = $sale->lines()->first();
        $entryId = DeviceCostEntry::query()
            ->where('inventory_unit_id', $unit->id)
            ->whereNull('reversed_at')
            ->where('category', DeviceCostCategory::REPAIR_PARTS)
            ->value('id');
        $this->assertNotNull($entryId);

        app(ReverseDeviceCost::class)(
            $this->owner,
            $this->store,
            $unit->id,
            (int) $entryId,
            'fix after sale',
            null,
            (string) Str::uuid(),
        );
        app(RecordDeviceCost::class)(
            $this->owner,
            $this->store,
            $unit->id,
            DeviceCostInput::fromInput('repair_labor', null, '40.00', 'TRY', false),
            null,
            (string) Str::uuid(),
        );

        $saleLine->setRelation('sale', $sale);
        $activity = DeviceSaleCost::laterActivity($saleLine);

        $this->assertTrue(collect($activity['snapshot'])->contains(
            fn (array $fact): bool => $fact['inclusion'] === 'excluded' && $fact['note'] === 'other_excluded',
        ));
        $this->assertTrue(collect($activity['later'])->contains(
            fn (array $change): bool => $change['kind'] === 'reversed_after_sale',
        ));
        $this->assertTrue(collect($activity['later'])->contains(
            fn (array $change): bool => $change['kind'] === 'recorded_after_sale',
        ));
        $this->assertSame('1100.00', $saleLine->fresh()->cost_amount);
    }

    #[Test]
    public function owner_page_loads_and_employee_is_refused(): void
    {
        $this->receiveCable(2, '10.00');
        $this->sellCable(1);

        Livewire::actingAs($this->owner)
            ->test(SalesMargin::class)
            ->assertOk()
            ->assertSee(__('reports.margin.title'));

        Livewire::actingAs($this->cashier)
            ->test(SalesMargin::class)
            ->assertForbidden();
    }

    #[Test]
    public function historical_lines_without_snapshots_count_as_unknown_not_zero(): void
    {
        $this->receiveCable(2, '10.00');
        $sale = $this->sellCable(1);
        DB::table('sale_lines')->where('id', $sale->lines()->first()->id)->update([
            'cost_status' => null,
            'cost_currency' => null,
            'cost_amount' => null,
            'cost_basis' => null,
            'cost_basis_version' => null,
            'cost_unknown_reason' => null,
            'cost_exclusion_reason' => null,
            'pool_quantity_before' => null,
            'pool_cost_total_before' => null,
            'cost_declaration_id' => null,
        ]);

        $report = $this->summary();

        $this->assertSame($sale->totalMinor(), $report['unknown_revenue_minor']);
        $this->assertSame(0, $report['known_cost_minor']);
        $this->assertArrayHasKey('pre_activation', $report['unknown_reasons']);
    }

    #[Test]
    public function coincident_redeclaration_values_do_not_reattach_after_full_depletion_and_receipt(): void
    {
        $this->receiveCable(2, '10.00');
        app(RecordStockCount::class)(
            $this->owner,
            $this->store,
            $this->cable,
            3,
            RecordStockCount::latestMovementId($this->store, $this->cable),
            'found',
            (string) Str::uuid(),
        );
        $declaration = app(DeclareQuantityCost::class)(
            $this->owner,
            $this->store,
            $this->cable,
            3,
            '45.00',
            'declared pool',
            (string) Str::uuid(),
        );

        $firstSale = $this->sellCable(3);
        $this->assertSame($declaration->id, $firstSale->lines()->first()->cost_declaration_id);

        // Same quantity and total as the declaration, but a new receipt-built pool.
        $this->receiveCable(3, '15.00');
        $this->assertNull($this->cableBalance()->active_cost_declaration_id);

        $secondSale = $this->sellCable(3);
        $this->assertNull($secondSale->lines()->first()->cost_declaration_id);
        $this->assertSame('45.00', $secondSale->lines()->first()->cost_amount);
        $this->assertSame($declaration->id, $firstSale->lines()->first()->fresh()->cost_declaration_id);
    }

    #[Test]
    public function partial_depletion_keeps_declaration_until_later_coincident_receipt(): void
    {
        $this->receiveCable(2, '10.00');
        app(RecordStockCount::class)(
            $this->owner,
            $this->store,
            $this->cable,
            3,
            RecordStockCount::latestMovementId($this->store, $this->cable),
            'found',
            (string) Str::uuid(),
        );
        $declaration = app(DeclareQuantityCost::class)(
            $this->owner,
            $this->store,
            $this->cable,
            3,
            '45.00',
            'declared pool',
            (string) Str::uuid(),
        );

        $partial = $this->sellCable(1);
        $this->assertSame($declaration->id, $partial->lines()->first()->cost_declaration_id);
        $this->assertSame($declaration->id, $this->cableBalance()->active_cost_declaration_id);
        $this->assertSame('15.00', $partial->lines()->first()->cost_amount);

        $remainder = $this->sellCable(2);
        $this->assertSame($declaration->id, $remainder->lines()->first()->cost_declaration_id);

        $this->receiveCable(3, '15.00');
        $this->assertNull($this->cableBalance()->active_cost_declaration_id);
        $restarted = $this->sellCable(1);
        $this->assertNull($restarted->lines()->first()->cost_declaration_id);
        $this->assertSame('15.00', $restarted->lines()->first()->cost_amount);
    }

    #[Test]
    public function livewire_permission_grants_gate_cost_amounts_and_source_links(): void
    {
        $this->receiveCable(2, '10.00');
        $this->sellCable(1);

        $imei = self::imei('35693803564385');
        $purchase = $this->postedPurchase($this->owner, $this->store, $this->supplier, [[$this->usedPhone, 1, '1000.00']]);
        $line = $purchase->lines()->sole();
        $receipt = app(ReceivePurchase::class)($this->owner, $purchase, [
            $line->id => [['condition' => 'b', 'imei' => $imei, 'secondary_imei' => null, 'serial' => null]],
        ], null, (string) Str::uuid());
        $unit = $receipt->lines()->sole()->units()->sole()->unit;
        $unit->update(['selling_price' => '2000.00', 'status' => UnitStatus::AVAILABLE]);
        $this->finalizePhone($unit);

        // Quantity-only: revoke device costs through the real permission system.
        $this->revokeFromOwners('manage_device_costs');
        $quantityOnly = Livewire::actingAs($this->owner->fresh())->test(SalesMargin::class);
        $quantitySummary = $quantityOnly->viewData('summary');
        $quantityRows = collect($quantityOnly->viewData('rows')->items());
        $this->assertSame(1000, $quantitySummary['known_cost_minor']);
        $this->assertGreaterThan(0, $quantitySummary['restricted_known_revenue_minor']);
        $this->assertRestrictedDevicePayload($quantityRows, $quantityOnly->html());

        // Device-only after restoring device costs and revoking purchases.
        Role::findByName('admin')->givePermissionTo('manage_device_costs');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->revokeFromOwners('manage_purchases');
        $deviceOnly = Livewire::actingAs($this->owner->fresh())->test(SalesMargin::class);
        $deviceSummary = $deviceOnly->viewData('summary');
        $deviceRows = collect($deviceOnly->viewData('rows')->items());
        $this->assertSame(100_000, $deviceSummary['known_cost_minor']);
        $this->assertGreaterThan(0, $deviceSummary['restricted_known_revenue_minor']);
        $this->assertRestrictedQuantityPayload($deviceRows, $deviceOnly->html());

        // Explicit invalid branch stays selected and is rejected — never silently replaced by all branches.
        $deviceOnly->set('storeId', '99999')
            ->assertViewHas('branchFilterError', 'invalid_store')
            ->assertViewHas('summary', null)
            ->assertSet('storeId', '99999');

        // Valid branch filter scopes to that store; branch/date changes reset pagination.
        $deviceOnly->set('storeId', (string) $this->store->id)
            ->assertViewHas('branchFilterError', null)
            ->assertSet('storeId', (string) $this->store->id);
        $this->assertSame([$this->store->id], collect($deviceOnly->viewData('rows')->items())->pluck('store_id')->unique()->values()->all());
        $deviceOnly->call('setPage', 2)
            ->assertSet('paginators.page', 2)
            ->set('storeId', '')
            ->assertSet('paginators.page', 1)
            ->call('setPage', 2)
            ->call('showYesterday')
            ->assertSet('paginators.page', 1);

        // Costs remain visible without viewInventory; destination links become plain text.
        Role::findByName('admin')->givePermissionTo('manage_purchases');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->revokeFromOwners('view_inventory');
        $noInventory = Livewire::actingAs($this->owner->fresh())->test(SalesMargin::class);
        $noInventorySummary = $noInventory->viewData('summary');
        $this->assertSame(101_000, $noInventorySummary['known_cost_minor']);
        $linked = collect($noInventory->viewData('rows')->items())
            ->flatMap(fn (array $row) => $row['line_summaries'])
            ->first(fn (array $line): bool => ($line['provenance']['type'] ?? null) === 'quantity');
        $this->assertNotNull($linked);
        $this->assertNull($linked['provenance']['inventory_url']);
        $this->assertNotNull($linked['provenance']['inventory_destination']);
        $noInventory->assertSee(__('reports.margin.destination_plain', [
            'destination' => $linked['provenance']['inventory_destination'],
        ]));
        $noInventory->assertDontSee(__('reports.margin.open_inventory'));

        // Revoking both cost permissions closes the page.
        $this->revokeFromOwners('manage_purchases');
        $this->revokeFromOwners('manage_device_costs');
        Livewire::actingAs($this->owner->fresh())->test(SalesMargin::class)->assertForbidden();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function assertRestrictedDevicePayload(Collection $rows, string $html): void
    {
        $deviceRow = $rows->first(fn (array $row): bool => collect($row['line_summaries'])->contains(
            fn (array $line): bool => ($line['unit_identifier'] ?? null) !== null
        ));
        $this->assertNotNull($deviceRow);
        $this->assertTrue(collect($deviceRow['line_summaries'])->contains(
            fn (array $line): bool => $line['status'] === 'restricted'
                && $line['cost_minor'] === null
                && ($line['provenance'] ?? null) === null
        ));
        $encoded = json_encode($deviceRow);
        $this->assertStringNotContainsString('1000.00', (string) $encoded);
        $this->assertStringNotContainsString('"cost_minor":100000', (string) $encoded);
        $this->assertStringNotContainsString('1000.00', $html);
        $this->assertStringNotContainsString('1.000,00', $html);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function assertRestrictedQuantityPayload(Collection $rows, string $html): void
    {
        $quantityRow = $rows->first(fn (array $row): bool => collect($row['line_summaries'])->contains(
            fn (array $line): bool => ($line['unit_identifier'] ?? null) === null
                && ($line['product_name'] ?? null) === 'USB-C cable'
        ));
        $this->assertNotNull($quantityRow);
        $this->assertTrue(collect($quantityRow['line_summaries'])->contains(
            fn (array $line): bool => $line['status'] === 'restricted'
                && $line['cost_minor'] === null
                && ($line['provenance'] ?? null) === null
        ));
        $encoded = json_encode($quantityRow);
        $this->assertStringNotContainsString('"cost_minor":1000', (string) $encoded);
        $this->assertStringNotContainsString('10.00', (string) $encoded);
        $this->assertStringNotContainsString('10.00', $html);
    }

    private function cableBalance(): InventoryBalance
    {
        return InventoryBalance::query()
            ->where('store_id', $this->store->id)
            ->where('product_definition_id', $this->cable->id)
            ->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(): array
    {
        $today = LocalDateRange::date(LocalDateRange::today());

        return SalesMarginReport::summarize(
            $this->owner,
            [$this->store->id],
            $today,
            $today,
            [$this->store->id => true],
            [$this->store->id => true],
        );
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function pagedRows()
    {
        $today = LocalDateRange::date(LocalDateRange::today());

        return SalesMarginReport::paginateSales(
            $this->owner,
            [$this->store->id],
            $today,
            $today,
            [$this->store->id => true],
            [$this->store->id => true],
        )->getCollection();
    }

    private function receiveCable(int $quantity, string $unitCost): void
    {
        $purchase = $this->postedPurchase(
            $this->owner,
            $this->store,
            $this->supplier,
            [[$this->cable, $quantity, $unitCost]],
            ['supplier_invoice_number' => 'INV-'.Str::upper(Str::random(8))],
        );
        app(ReceivePurchase::class)($this->owner, $purchase, [], null, (string) Str::uuid());
    }

    private function sellCable(int $quantity): Sale
    {
        $attempt = $this->review($this->cashier, $this->cableLine($quantity));

        return app(FinalizeCheckout::class)(
            $this->cashier,
            $this->store,
            $attempt->key,
            $attempt->revision,
            Money::toDecimal($quantity * 4990),
            null,
        );
    }

    private function finalizePhone($unit): Sale
    {
        $attempt = $this->review($this->cashier, $this->phoneLine($unit));
        $terminal = $this->createTerminal();
        $this->approveCard($this->cashier, $attempt, $terminal, (string) $unit->selling_price);

        return app(FinalizeCheckout::class)($this->cashier, $this->store, $attempt->key, $attempt->fresh()->revision, null, null);
    }

    private function sellNetworkPhone(): Sale
    {
        $otherStore = Store::factory()->create(['code' => 'KT02', 'name' => 'Karman Kadikoy']);
        $otherCashier = $this->createCashier($otherStore, name: 'Kadikoy cashier');
        app(SetReservationHold::class)($this->owner, $this->store, 60);
        $listing = app(PublishUnitListing::class)($this->owner, $this->store, $this->phone->id, NetworkListing::UNLISTED_REVISION);
        $reservation = app(RequestReservation::class)($otherCashier, $otherStore, $listing->id, 1);
        app(ApproveReservation::class)($this->owner, $this->store, $reservation->id);

        $attempt = app(ReviewCheckout::class)(
            $this->cashier,
            $this->store,
            $this->phoneLine($this->phone),
            reservationId: $reservation->id,
        );
        $terminal = $this->createTerminal();
        $this->approveCard($this->cashier, $attempt, $terminal, '12500.00');

        return app(FinalizeCheckout::class)($this->cashier, $this->store, $attempt->key, $attempt->fresh()->revision, null, null);
    }
}
