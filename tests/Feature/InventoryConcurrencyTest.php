<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Inventory\RecordOpeningBalance;
use App\Enums\TrackingMode;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\ProductDefinition;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Support\InteractsWithOperationalInventory;
use Tests\TestCase;

/**
 * Competing writes from independent PHP processes, each with its own database connection.
 *
 * The test process holds the product-definition row lock while both workers start, waits
 * until the database reports both workers blocked on it, then releases it so they race.
 */
class InventoryConcurrencyTest extends TestCase
{
    use DatabaseTruncation;
    use InteractsWithOperationalInventory;

    private User $owner;

    private Store $storeOne;

    private Store $storeTwo;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'mysql' && DB::connection()->getDriverName() !== 'mariadb') {
            $this->markTestSkipped('Concurrency tests need the MySQL/MariaDB test database.');
        }

        $this->setUpOperationalRoles();
        $this->owner = $this->createOwner();
        $this->storeOne = Store::factory()->create(['code' => 'KT01']);
        $this->storeTwo = Store::factory()->create(['code' => 'KT02']);
    }

    protected function tearDown(): void
    {
        $this->truncateTablesForAllConnections();

        parent::tearDown();
    }

    #[Test]
    public function competing_decreases_never_overdraw_stock(): void
    {
        $cable = ProductDefinition::factory()->create(['tracking_mode' => TrackingMode::QUANTITY]);
        app(RecordOpeningBalance::class)($this->owner, $this->storeOne, $cable, 5, null, 'open');

        $results = $this->raceWhileLocked([$cable], [
            ['operation' => 'decrease', 'store_id' => $this->storeOne->id, 'definition_id' => $cable->id, 'quantity' => 4, 'key' => 'worker-a'],
            ['operation' => 'decrease', 'store_id' => $this->storeOne->id, 'definition_id' => $cable->id, 'quantity' => 4, 'key' => 'worker-b'],
        ]);

        $this->assertEqualsCanonicalizing(['ok', 'validation'], array_column($results, 'status'));
        $this->assertSame(1, (int) InventoryBalance::query()->where('store_id', $this->storeOne->id)->value('quantity'));
        $this->assertSame(2, InventoryMovement::count());
    }

    #[Test]
    public function competing_retries_with_one_key_apply_once_and_return_the_same_movement(): void
    {
        $cable = ProductDefinition::factory()->create(['tracking_mode' => TrackingMode::QUANTITY]);
        app(RecordOpeningBalance::class)($this->owner, $this->storeOne, $cable, 10, null, 'open');

        $request = ['operation' => 'decrease', 'store_id' => $this->storeOne->id, 'definition_id' => $cable->id, 'quantity' => 3, 'key' => 'same-submission'];
        $results = $this->raceWhileLocked([$cable], [$request, $request]);

        $this->assertSame(['ok', 'ok'], array_column($results, 'status'));
        $this->assertSame($results[0]['movement_id'], $results[1]['movement_id']);
        $this->assertEqualsCanonicalizing([true, false], array_column($results, 'created'));
        $this->assertSame(7, (int) InventoryBalance::query()->where('store_id', $this->storeOne->id)->value('quantity'));
        $this->assertSame(2, InventoryMovement::count());
    }

    #[Test]
    public function competing_opening_balances_record_only_one(): void
    {
        $cable = ProductDefinition::factory()->create(['tracking_mode' => TrackingMode::QUANTITY]);

        $results = $this->raceWhileLocked([$cable], [
            ['operation' => 'opening', 'store_id' => $this->storeOne->id, 'definition_id' => $cable->id, 'quantity' => 10, 'key' => 'open-a'],
            ['operation' => 'opening', 'store_id' => $this->storeOne->id, 'definition_id' => $cable->id, 'quantity' => 12, 'key' => 'open-b'],
        ]);

        $this->assertEqualsCanonicalizing(['ok', 'validation'], array_column($results, 'status'));
        $this->assertSame(1, InventoryMovement::count());
        $this->assertSame(
            InventoryMovement::query()->value('quantity_after'),
            (int) InventoryBalance::query()->where('store_id', $this->storeOne->id)->value('quantity'),
        );
    }

    #[Test]
    public function competing_registrations_of_one_imei_in_two_stores_register_one_unit(): void
    {
        $phone = ProductDefinition::factory()->create(['tracking_mode' => TrackingMode::USED_DEVICE]);
        $imei = self::imei('35693803564380');

        $results = $this->raceWhileLocked([$phone], [
            ['operation' => 'register', 'store_id' => $this->storeOne->id, 'definition_id' => $phone->id, 'imei' => $imei, 'key' => 'reg-a'],
            ['operation' => 'register', 'store_id' => $this->storeTwo->id, 'definition_id' => $phone->id, 'imei' => $imei, 'key' => 'reg-b'],
        ]);

        $this->assertEqualsCanonicalizing(['ok', 'validation'], array_column($results, 'status'));
        $this->assertSame(1, InventoryUnit::count());
        $this->assertSame(1, InventoryMovement::count());
    }

    #[Test]
    public function a_tracking_mode_change_and_a_stock_write_cannot_both_succeed(): void
    {
        $cable = ProductDefinition::factory()->create(['tracking_mode' => TrackingMode::QUANTITY]);

        $results = $this->raceWhileLocked([$cable], [
            ['operation' => 'opening', 'store_id' => $this->storeOne->id, 'definition_id' => $cable->id, 'quantity' => 10, 'key' => 'open'],
            ['operation' => 'change_tracking_mode', 'definition_id' => $cable->id, 'tracking_mode' => 'used_device'],
        ]);

        $this->assertEqualsCanonicalizing(['ok', 'validation'], array_column($results, 'status'));

        $cable->refresh();
        $openingWon = $results[0]['status'] === 'ok';
        $this->assertSame($openingWon ? TrackingMode::QUANTITY : TrackingMode::USED_DEVICE, $cable->tracking_mode);
        $this->assertSame($openingWon ? 1 : 0, InventoryMovement::count());
    }

    #[Test]
    public function one_key_used_concurrently_for_different_targets_succeeds_once_and_conflicts_once(): void
    {
        $cable = ProductDefinition::factory()->create(['tracking_mode' => TrackingMode::QUANTITY]);
        $charger = ProductDefinition::factory()->create(['tracking_mode' => TrackingMode::QUANTITY]);

        // Different definitions take different locks, so both workers can reach the key insert.
        $results = $this->raceWhileLocked([$cable, $charger], [
            ['operation' => 'opening', 'store_id' => $this->storeOne->id, 'definition_id' => $cable->id, 'quantity' => 4, 'key' => 'shared-key'],
            ['operation' => 'opening', 'store_id' => $this->storeOne->id, 'definition_id' => $charger->id, 'quantity' => 6, 'key' => 'shared-key'],
        ]);

        $this->assertEqualsCanonicalizing(['ok', 'conflict'], array_column($results, 'status'));
        $this->assertSame(1, InventoryMovement::count());

        $winner = InventoryMovement::query()->sole();
        $this->assertSame(1, InventoryBalance::query()->where('quantity', '>', 0)->count());
        $this->assertSame(
            $winner->quantity_after,
            (int) InventoryBalance::query()->where('product_definition_id', $winner->product_definition_id)->value('quantity'),
        );
    }

    /**
     * Start one worker per request while holding the given definition rows locked, then release them.
     *
     * @param  list<ProductDefinition>  $lockedDefinitions
     * @param  list<array<string, mixed>>  $requests
     * @return list<array<string, mixed>>
     */
    private function raceWhileLocked(array $lockedDefinitions, array $requests): array
    {
        $processes = [];

        DB::beginTransaction();

        try {
            ProductDefinition::query()->whereKey(array_map(fn ($definition) => $definition->id, $lockedDefinitions))->lockForUpdate()->get();

            foreach ($requests as $request) {
                $processes[] = $this->startWorker(['actor_id' => $this->owner->id, ...$request]);
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

        $process = proc_open(
            [PHP_BINARY, base_path('tests/Support/inventory-worker.php'), json_encode($payload, JSON_THROW_ON_ERROR)],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            base_path(),
            $environment,
        );

        if (! is_resource($process)) {
            throw new RuntimeException('Could not start the inventory worker process.');
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
            $waiting = (int) DB::scalar('select count(*) from information_schema.innodb_lock_waits');

            if ($waiting >= $expected) {
                return;
            }

            usleep(100_000);
        }

        throw new RuntimeException("Workers did not block on the definition lock within 30 seconds (expected {$expected}).");
    }
}
