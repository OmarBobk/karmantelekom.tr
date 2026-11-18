<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Inventory\DeviceCostInput;
use App\Actions\Inventory\RecordDeviceCost;
use App\Actions\Inventory\RecordStockCount;
use App\Actions\Purchasing\ReceivePurchase;
use App\Enums\QuantityCostStatus;
use App\Enums\SaleLineCostStatus;
use App\Enums\TrackingMode;
use App\Enums\UnitStatus;
use App\Models\DeviceCostEntry;
use App\Models\InventoryBalance;
use App\Models\InventoryCostDeclaration;
use App\Models\OperationalSupplier;
use App\Models\ProductDefinition;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\Store;
use App\Models\User;
use App\Services\Purchasing\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Support\InteractsWithPos;
use Tests\Support\InteractsWithPurchasing;
use Tests\TestCase;

/**
 * Competing quantity-cost writers (receipt, count, declaration, finalize) under real MariaDB locks.
 */
class QuantityCostConcurrencyTest extends TestCase
{
    use DatabaseTruncation;
    use InteractsWithPos;
    use InteractsWithPurchasing;

    private User $cashier;

    private User $rival;

    private OperationalSupplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Concurrency tests need the MySQL/MariaDB test database.');
        }

        // Empty known pools start from receipts only — opening stock would contaminate cost state.
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
        $this->rival = $this->createCashier(name: 'Rival');
        $this->supplier = OperationalSupplier::factory()->create();
    }

    protected function tearDown(): void
    {
        $this->truncateTablesForAllConnections();

        parent::tearDown();
    }

    #[Test]
    public function two_sales_of_the_same_known_pool_never_duplicate_depletion(): void
    {
        $this->receiveCable(2, '10.00');
        $first = $this->review($this->cashier, $this->cableLine(1));
        $second = $this->review($this->rival, $this->cableLine(1));

        $results = $this->raceWhileLocked($this->definitionLock($this->cable), [
            $this->finalizeRequest($this->cashier, $first, '50'),
            $this->finalizeRequest($this->rival, $second, '50'),
        ]);

        $this->assertSame(['ok', 'ok'], array_column($results, 'status'));
        $this->assertSame(2, Sale::query()->count());
        $this->assertSame(2, SaleLine::query()->where('cost_status', SaleLineCostStatus::KNOWN)->count());
        $balance = $this->cableBalance();
        $this->assertSame(0, $balance->quantity);
        $this->assertSame(QuantityCostStatus::EMPTY, $balance->cost_status);
        $this->assertSame(
            '20.00',
            Money::toDecimal(SaleLine::query()->get()->sum(fn (SaleLine $line): int => $line->costAmountMinor() ?? 0)),
        );
    }

    #[Test]
    public function declaration_racing_a_sale_serializes_to_exact_money_and_stock(): void
    {
        $this->receiveCable(5, '10.00');
        app(RecordStockCount::class)(
            $this->owner,
            $this->store,
            $this->cable,
            6,
            RecordStockCount::latestMovementId($this->store, $this->cable),
            'found one',
            (string) Str::uuid(),
        );

        $attempt = $this->review($this->cashier, $this->cableLine(1));

        $results = $this->raceWhileLocked($this->definitionLock($this->cable), [
            [
                'operation' => 'declare',
                'actor_id' => $this->owner->id,
                'store_id' => $this->store->id,
                'definition_id' => $this->cable->id,
                'expected_quantity' => 6,
                'declared_total' => '90.00',
                'reason' => 'race declare',
                'key' => (string) Str::uuid(),
            ],
            $this->finalizeRequest($this->cashier, $attempt, '50'),
        ]);

        $this->assertSame(1, Sale::query()->count());
        $sale = Sale::query()->sole();
        $line = $sale->lines()->sole();
        $balance = $this->cableBalance();
        $statuses = array_column($results, 'status');

        // Declare first then sale: known line cost 15.00, remaining qty 5 total 75.00.
        // Sale first then declare: declare sees qty 5 → validation; line unknown; qty 5 unknown.
        if ($statuses === ['ok', 'ok']) {
            $this->assertSame(1, InventoryCostDeclaration::query()->count());
            $this->assertSame(SaleLineCostStatus::KNOWN, $line->cost_status);
            $this->assertSame('15.00', $line->cost_amount);
            $this->assertSame(5, $balance->quantity);
            $this->assertSame(QuantityCostStatus::KNOWN, $balance->cost_status);
            $this->assertSame('75.00', $balance->cost_total);
        } else {
            $this->assertEqualsCanonicalizing(['validation', 'ok'], $statuses);
            $this->assertSame('ok', $results[1]['status']);
            $this->assertSame(0, InventoryCostDeclaration::query()->count());
            $this->assertSame(SaleLineCostStatus::UNKNOWN, $line->cost_status);
            $this->assertNull($line->cost_amount);
            $this->assertSame(5, $balance->quantity);
            $this->assertSame(QuantityCostStatus::UNKNOWN, $balance->cost_status);
            $this->assertNull($balance->cost_total);
        }
    }

    #[Test]
    public function count_increase_racing_a_sale_serializes_stock_and_cost(): void
    {
        $this->receiveCable(3, '10.00');
        $startedAfter = RecordStockCount::latestMovementId($this->store, $this->cable);
        $attempt = $this->review($this->cashier, $this->cableLine(1));

        $results = $this->raceWhileLocked($this->definitionLock($this->cable), [
            [
                'operation' => 'count',
                'actor_id' => $this->owner->id,
                'store_id' => $this->store->id,
                'definition_id' => $this->cable->id,
                'counted' => 5,
                'count_started_after' => $startedAfter,
                'key' => (string) Str::uuid(),
            ],
            $this->finalizeRequest($this->cashier, $attempt, '50'),
        ]);

        $this->assertEqualsCanonicalizing(['ok', 'ok'], array_column($results, 'status'));
        $this->assertSame(1, Sale::query()->count());
        $line = Sale::query()->sole()->lines()->sole();
        $balance = $this->cableBalance();

        // Count-first: pool becomes unknown at 5, sale removes 1 → qty 4 unknown, line unknown.
        // Sale-first: known share 10.00 from 30, qty 2 known 20; count then sets qty 5 unknown.
        if ($line->cost_status === SaleLineCostStatus::UNKNOWN) {
            $this->assertNull($line->cost_amount);
            $this->assertSame(4, $balance->quantity);
            $this->assertSame(QuantityCostStatus::UNKNOWN, $balance->cost_status);
            $this->assertNull($balance->cost_total);
        } else {
            $this->assertSame(SaleLineCostStatus::KNOWN, $line->cost_status);
            $this->assertSame('10.00', $line->cost_amount);
            $this->assertSame(5, $balance->quantity);
            $this->assertSame(QuantityCostStatus::UNKNOWN, $balance->cost_status);
            $this->assertNull($balance->cost_total);
        }
    }

    #[Test]
    public function receipt_racing_a_sale_serializes_to_known_pool_outcomes(): void
    {
        $this->receiveCable(1, '10.00');
        $purchase = $this->postedPurchase(
            $this->owner,
            $this->store,
            $this->supplier,
            [[$this->cable, 2, '20.00']],
            ['supplier_invoice_number' => 'INV-RACE-'.Str::upper(Str::random(6))],
        );
        $attempt = $this->review($this->cashier, $this->cableLine(1));

        $results = $this->raceWhileLocked($this->definitionLock($this->cable), [
            [
                'operation' => 'receive',
                'actor_id' => $this->owner->id,
                'purchase_id' => $purchase->id,
                'units' => [],
                'key' => (string) Str::uuid(),
            ],
            $this->finalizeRequest($this->cashier, $attempt, '50'),
        ]);

        $this->assertEqualsCanonicalizing(['ok', 'ok'], array_column($results, 'status'));
        $this->assertSame(1, Sale::query()->count());
        $line = Sale::query()->sole()->lines()->sole();
        $balance = $this->cableBalance();
        $this->assertSame(SaleLineCostStatus::KNOWN, $line->cost_status);

        // Receive-first: pool 3 / 50.00, sell 1 → removed floor(5000/3)=16.66, remain 2 / 33.34.
        // Sale-first: sell the only unit for 10.00 empty, then receive 2 / 40.00.
        $this->assertSame(2, $balance->quantity);
        $this->assertSame(QuantityCostStatus::KNOWN, $balance->cost_status);

        if ($line->cost_amount === '16.66') {
            $this->assertSame('33.34', $balance->cost_total);
        } else {
            $this->assertSame('10.00', $line->cost_amount);
            $this->assertSame('40.00', $balance->cost_total);
        }
    }

    #[Test]
    public function device_cost_reverse_racing_finalize_keeps_one_frozen_snapshot(): void
    {
        $imei = self::imei('35693803564386');
        $purchase = $this->postedPurchase(
            $this->owner,
            $this->store,
            $this->supplier,
            [[$this->usedPhone, 1, '1000.00']],
            ['supplier_invoice_number' => 'INV-DEV-'.Str::upper(Str::random(6))],
        );
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
        $entryId = DeviceCostEntry::query()
            ->where('inventory_unit_id', $unit->id)
            ->whereNull('reversed_at')
            ->value('id');

        $attempt = $this->review($this->cashier, $this->phoneLine($unit));
        $terminal = $this->createTerminal();
        $this->approveCard($this->cashier, $attempt, $terminal, '2000.00');
        $attempt = $attempt->fresh();

        $results = $this->raceWhileLocked($this->definitionLock($this->usedPhone), [
            [
                'operation' => 'reverse_device_cost',
                'actor_id' => $this->owner->id,
                'store_id' => $this->store->id,
                'unit_id' => $unit->id,
                'entry_id' => $entryId,
                'reason' => 'race reverse',
                'key' => (string) Str::uuid(),
            ],
            [
                'operation' => 'finalize',
                'actor_id' => $this->cashier->id,
                'store_id' => $this->store->id,
                'attempt_key' => $attempt->key,
                'revision' => $attempt->revision,
                'cash' => null,
            ],
        ]);

        $this->assertContains('ok', array_column($results, 'status'));
        $this->assertSame(1, Sale::query()->count());
        $saleLine = Sale::query()->sole()->lines()->sole();
        $this->assertContains($saleLine->cost_amount, ['1000.00', '1100.00']);
        $this->assertSame(SaleLineCostStatus::KNOWN, $saleLine->cost_status);
        $this->assertSame(1, SaleLine::query()->whereNotNull('cost_status')->count());
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

    private function cableBalance(): InventoryBalance
    {
        return InventoryBalance::query()
            ->where('store_id', $this->store->id)
            ->where('product_definition_id', $this->cable->id)
            ->firstOrFail();
    }

    /**
     * @return Builder<ProductDefinition>
     */
    private function definitionLock(ProductDefinition $definition): Builder
    {
        return ProductDefinition::query()->whereKey($definition->id);
    }

    /**
     * @return array<string, mixed>
     */
    private function finalizeRequest(User $actor, $attempt, ?string $cash): array
    {
        return [
            'operation' => 'finalize',
            'actor_id' => $actor->id,
            'store_id' => $this->store->id,
            'attempt_key' => $attempt->key,
            'revision' => $attempt->revision,
            'cash' => $cash,
        ];
    }

    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $lockedRows
     * @param  list<array<string, mixed>>  $requests
     * @return list<array<string, mixed>>
     */
    private function raceWhileLocked(Builder $lockedRows, array $requests): array
    {
        $processes = [];

        DB::beginTransaction();

        try {
            $lockedRows->lockForUpdate()->get();

            foreach ($requests as $request) {
                $processes[] = $this->startWorker($request);
            }

            $this->waitForBlockedWorkers(count($requests));
        } finally {
            DB::commit();
        }

        return array_map(fn (array $process) => $this->finishWorker($process), $processes);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{process: resource, pipes: array<int, resource>}
     */
    private function startWorker(array $payload): array
    {
        $environment = [
            ...getenv(),
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'mysql',
            'DB_DATABASE' => 'dev_store_testing',
        ];

        $script = match ($payload['operation']) {
            'receive' => base_path('tests/Support/purchasing-worker.php'),
            'declare', 'count' => base_path('tests/Support/inventory-worker.php'),
            default => base_path('tests/Support/pos-worker.php'),
        };

        if (in_array($payload['operation'], ['declare', 'count'], true) && ! isset($payload['actor_id'])) {
            $payload['actor_id'] = $this->owner->id;
        }

        $process = proc_open(
            [PHP_BINARY, $script, json_encode($payload, JSON_THROW_ON_ERROR)],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            base_path(),
            $environment,
        );

        if (! is_resource($process)) {
            throw new RuntimeException('Could not start the cost concurrency worker.');
        }

        return ['process' => $process, 'pipes' => $pipes];
    }

    /**
     * @param  array{process: resource, pipes: array<int, resource>}  $worker
     * @return array<string, mixed>
     */
    private function finishWorker(array $worker): array
    {
        $output = stream_get_contents($worker['pipes'][1]);
        $errors = stream_get_contents($worker['pipes'][2]);
        fclose($worker['pipes'][1]);
        fclose($worker['pipes'][2]);
        $exitCode = proc_close($worker['process']);

        $this->assertSame(0, $exitCode, "Worker failed: {$errors}{$output}");

        return json_decode((string) $output, true, flags: JSON_THROW_ON_ERROR);
    }

    private function waitForBlockedWorkers(int $expected): void
    {
        $deadline = microtime(true) + 30;

        while (microtime(true) < $deadline) {
            if ((int) DB::scalar('select count(*) from information_schema.innodb_lock_waits') >= $expected) {
                return;
            }

            usleep(100_000);
        }

        throw new RuntimeException("Workers did not block on the held locks within 30 seconds (expected {$expected}).");
    }
}
