<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Inventory\DeclareQuantityCost;
use App\Actions\Inventory\DeviceCostInput;
use App\Actions\Inventory\RecordDeviceCost;
use App\Actions\Inventory\RecordStockCount;
use App\Actions\Pos\FinalizeCheckout;
use App\Actions\Purchasing\ReceivePurchase;
use App\Enums\Currency;
use App\Enums\QuantityCostStatus;
use App\Enums\SaleLineCostStatus;
use App\Enums\TrackingMode;
use App\Enums\UnitStatus;
use App\Models\InventoryBalance;
use App\Models\InventoryCostDeclaration;
use App\Models\OperationalSupplier;
use App\Models\ProductDefinition;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\SaleLineCostFact;
use App\Models\Store;
use App\Models\User;
use App\Services\Inventory\QuantityCostPool;
use App\Services\Purchasing\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\InteractsWithPos;
use Tests\Support\InteractsWithPurchasing;
use Tests\TestCase;

class SalesMarginCostingTest extends TestCase
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
    public function try_receipt_into_empty_pool_makes_known_average_and_sale_snapshot(): void
    {
        $this->receiveCable(10, '10.00');

        $balance = $this->cableBalance();
        $this->assertSame(QuantityCostStatus::KNOWN, $balance->cost_status);
        $this->assertSame('TRY', $balance->cost_currency);
        $this->assertSame('100.00', $balance->cost_total);

        $this->receiveCable(10, '20.00');
        $balance->refresh();
        $this->assertSame('300.00', $balance->cost_total);
        $this->assertSame(20, $balance->quantity);

        $sale = $this->sellCable(7);
        $line = $sale->lines()->first();
        $this->assertSame(SaleLineCostStatus::KNOWN, $line->cost_status);
        $this->assertSame('105.00', $line->cost_amount);
        $this->assertSame(20, $line->pool_quantity_before);
        $this->assertSame('300.00', $line->pool_cost_total_before);

        $balance->refresh();
        $this->assertSame(13, $balance->quantity);
        $this->assertSame('195.00', $balance->cost_total);
    }

    #[Test]
    public function full_depletion_consumes_exact_residual(): void
    {
        $this->receiveCable(3, '10.00');
        $this->sellCable(1);
        $this->sellCable(1);
        $sale = $this->sellCable(1);

        $this->assertSame('10.00', $sale->lines()->first()->cost_amount);
        $balance = $this->cableBalance();
        $this->assertSame(0, $balance->quantity);
        $this->assertSame(QuantityCostStatus::EMPTY, $balance->cost_status);
        $this->assertNull($balance->cost_total);
    }

    #[Test]
    public function count_increase_contaminates_pool_and_sales_stay_unknown_until_declaration(): void
    {
        $this->receiveCable(10, '10.00');
        app(RecordStockCount::class)(
            $this->owner,
            $this->store,
            $this->cable,
            12,
            RecordStockCount::latestMovementId($this->store, $this->cable),
            'found two',
            (string) Str::uuid(),
        );

        $balance = $this->cableBalance();
        $this->assertSame(QuantityCostStatus::UNKNOWN, $balance->cost_status);
        $this->assertSame(12, $balance->quantity);

        $sale = $this->sellCable(2);
        $this->assertSame(SaleLineCostStatus::UNKNOWN, $sale->lines()->first()->cost_status);
        $this->assertSame('pool_unknown', $sale->lines()->first()->cost_unknown_reason);

        app(DeclareQuantityCost::class)(
            $this->owner,
            $this->store,
            $this->cable,
            10,
            '150.00',
            'Owner verified remaining stock cost',
            (string) Str::uuid(),
        );

        $balance->refresh();
        $this->assertSame(QuantityCostStatus::KNOWN, $balance->cost_status);
        $this->assertSame('150.00', $balance->cost_total);

        $later = $this->sellCable(5);
        $this->assertSame(SaleLineCostStatus::KNOWN, $later->lines()->first()->cost_status);
        $this->assertSame('75.00', $later->lines()->first()->cost_amount);
    }

    #[Test]
    public function stale_declaration_preserves_pool_and_requires_review(): void
    {
        $this->receiveCable(5, '10.00');
        app(RecordStockCount::class)(
            $this->owner,
            $this->store,
            $this->cable,
            8,
            RecordStockCount::latestMovementId($this->store, $this->cable),
            'extra',
            (string) Str::uuid(),
        );

        try {
            app(DeclareQuantityCost::class)($this->owner, $this->store, $this->cable, 5, '80.00', 'stale qty', (string) Str::uuid());
            $this->fail('Expected validation failure for stale quantity.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                [__('inventory.cost.errors.quantity_changed', ['quantity' => 8])],
                $exception->errors()['expected_quantity'],
            );
        }

        $this->assertSame(0, InventoryCostDeclaration::query()->count());
        $this->assertSame(QuantityCostStatus::UNKNOWN, $this->cableBalance()->cost_status);
    }

    #[Test]
    public function duplicate_declaration_key_replays(): void
    {
        $this->receiveCable(4, '10.00');
        app(RecordStockCount::class)(
            $this->owner,
            $this->store,
            $this->cable,
            5,
            RecordStockCount::latestMovementId($this->store, $this->cable),
            'extra',
            (string) Str::uuid(),
        );

        $key = (string) Str::uuid();
        $first = app(DeclareQuantityCost::class)($this->owner, $this->store, $this->cable, 5, '90.00', 'declare once', $key);
        $second = app(DeclareQuantityCost::class)($this->owner, $this->store, $this->cable, 5, '90.00', 'declare once', $key);

        $this->assertTrue($first->is($second));
        $this->assertSame(1, InventoryCostDeclaration::query()->count());
    }

    #[Test]
    public function usd_receipt_contaminates_try_known_pool(): void
    {
        $this->receiveCable(5, '10.00', Currency::TRY);
        $this->receiveCable(5, '5.00', Currency::USD);

        $balance = $this->cableBalance();
        $this->assertSame(10, $balance->quantity);
        $this->assertSame(QuantityCostStatus::UNKNOWN, $balance->cost_status);
    }

    #[Test]
    public function device_basis_includes_acquisition_parts_and_labor_and_excludes_other(): void
    {
        $imei = self::imei('35693803564383');
        $purchase = $this->postedPurchase($this->owner, $this->store, $this->supplier, [[$this->usedPhone, 1, '1000.00']]);
        $line = $purchase->lines()->sole();
        $receipt = app(ReceivePurchase::class)($this->owner, $purchase, [
            $line->id => [['condition' => 'b', 'imei' => $imei, 'secondary_imei' => null, 'serial' => null]],
        ], null, (string) Str::uuid());
        $unit = $receipt->lines()->sole()->units()->sole()->unit;
        $unit->update(['selling_price' => '2000.00', 'status' => UnitStatus::AVAILABLE]);

        app(RecordDeviceCost::class)(
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
            DeviceCostInput::fromInput('repair_labor', null, '50.00', 'TRY', false),
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
        $this->assertSame(SaleLineCostStatus::KNOWN, $saleLine->cost_status);
        $this->assertSame('1150.00', $saleLine->cost_amount);

        $facts = $saleLine->costFacts()->get();
        $this->assertTrue($facts->contains(fn (SaleLineCostFact $fact): bool => $fact->inclusion === 'excluded' && $fact->note === 'other_excluded'));
        $this->assertTrue($facts->contains(fn (SaleLineCostFact $fact): bool => $fact->inclusion === 'included' && $fact->fact_type === 'acquisition'));
    }

    #[Test]
    public function missing_acquisition_makes_device_margin_unknown(): void
    {
        $sale = $this->finalizePhone($this->phone);

        $line = $sale->lines()->first();
        $this->assertSame(SaleLineCostStatus::UNKNOWN, $line->cost_status);
        $this->assertSame('missing_acquisition', $line->cost_unknown_reason);
    }

    #[Test]
    public function completed_checkout_replay_keeps_fingerprint_and_snapshot(): void
    {
        $this->receiveCable(5, '10.00');
        $attempt = $this->review($this->cashier, $this->cableLine(1));
        $sale = app(FinalizeCheckout::class)($this->cashier, $this->store, $attempt->key, $attempt->revision, '50', null);
        $fingerprint = $attempt->fresh()->finalize_fingerprint;
        $costAmount = $sale->lines()->first()->cost_amount;

        $replay = app(FinalizeCheckout::class)($this->cashier, $this->store, $attempt->key, $attempt->revision, '50', null);

        $this->assertTrue($sale->is($replay));
        $this->assertSame($fingerprint, $attempt->fresh()->finalize_fingerprint);
        $this->assertSame($costAmount, $replay->lines()->first()->cost_amount);
        $this->assertSame(1, Sale::query()->count());
        $this->assertSame(1, SaleLine::query()->whereNotNull('cost_status')->count());
    }

    #[Test]
    public function share_outbound_uses_floor_and_exact_final_residual(): void
    {
        $this->assertSame(1, QuantityCostPool::shareOutbound(3, 3, 1));
        $this->assertSame(3, QuantityCostPool::shareOutbound(3, 3, 3));
        $this->assertSame(100, QuantityCostPool::shareOutbound(1000, 10, 1));
    }

    private function receiveCable(int $quantity, string $unitCost, Currency $currency = Currency::TRY): void
    {
        $purchase = $this->postedPurchase(
            $this->owner,
            $this->store,
            $this->supplier,
            [[$this->cable, $quantity, $unitCost]],
            ['supplier_invoice_number' => 'INV-'.Str::upper(Str::random(8))],
            $currency,
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

    private function cableBalance(): InventoryBalance
    {
        return InventoryBalance::query()
            ->where('store_id', $this->store->id)
            ->where('product_definition_id', $this->cable->id)
            ->firstOrFail();
    }
}
