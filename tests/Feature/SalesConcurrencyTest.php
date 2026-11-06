<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CardApprovalStatus;
use App\Enums\CheckoutAttemptStatus;
use App\Enums\MovementType;
use App\Enums\UnitStatus;
use App\Models\CardApproval;
use App\Models\CheckoutAttempt;
use App\Models\InventoryMovement;
use App\Models\ProductDefinition;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\SalePayment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Support\InteractsWithPos;
use Tests\TestCase;

/**
 * Competing checkouts from independent PHP processes, each with its own MariaDB connection.
 *
 * The test process holds row locks while the workers start, waits until the database
 * reports every worker blocked, then releases the locks so the workers race.
 */
class SalesConcurrencyTest extends TestCase
{
    use DatabaseTruncation;
    use InteractsWithPos;

    private User $cashier;

    private User $rival;

    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Concurrency tests need the MySQL/MariaDB test database.');
        }

        $this->setUpCounter(cableStock: 1);
        $this->cashier = $this->createCashier();
        $this->rival = $this->createCashier(name: 'Rival');
    }

    protected function tearDown(): void
    {
        $this->truncateTablesForAllConnections();

        parent::tearDown();
    }

    #[Test]
    public function two_cashiers_competing_for_the_last_unit_of_stock_sell_it_once(): void
    {
        $first = $this->review($this->cashier, $this->cableLine(1));
        $second = $this->review($this->rival, $this->cableLine(1));

        $results = $this->raceWhileLocked($this->definitionLock($this->cable), [
            $this->finalizeRequest($this->cashier, $first, '50'),
            $this->finalizeRequest($this->rival, $second, '50'),
        ]);

        $this->assertEqualsCanonicalizing(['ok', 'validation'], array_column($results, 'status'));
        $this->assertSame(0, $this->cableStock());
        $this->assertSame(1, Sale::query()->count());
        $this->assertSame(1, InventoryMovement::query()->where('type', MovementType::SALE)->count());
        $this->assertSame(['unavailable'], CheckoutAttempt::query()->where('status', CheckoutAttemptStatus::OPEN)->pluck('failure_code')->all());
    }

    #[Test]
    public function two_cashiers_competing_for_the_same_phone_sell_it_once(): void
    {
        $first = $this->review($this->cashier, $this->phoneLine());
        $second = $this->review($this->rival, $this->phoneLine());

        $results = $this->raceWhileLocked($this->definitionLock($this->usedPhone), [
            $this->finalizeRequest($this->cashier, $first, '12500'),
            $this->finalizeRequest($this->rival, $second, '12500'),
        ]);

        $this->assertEqualsCanonicalizing(['ok', 'validation'], array_column($results, 'status'));
        $this->assertSame(UnitStatus::SOLD, $this->phone->fresh()->status);
        $this->assertSame(1, SaleLine::query()->where('inventory_unit_id', $this->phone->id)->count());
        $this->assertSame($this->phone->lifecycle_revision + 1, $this->phone->fresh()->lifecycle_revision);
    }

    #[Test]
    public function a_sale_and_an_inspection_of_the_same_phone_cannot_both_succeed(): void
    {
        $attempt = $this->review($this->cashier, $this->phoneLine());

        $results = $this->raceWhileLocked($this->definitionLock($this->usedPhone), [
            $this->finalizeRequest($this->cashier, $attempt, '12500'),
            [
                'operation' => 'inspect',
                'actor_id' => $this->owner->id,
                'store_id' => $this->store->id,
                'unit_id' => $this->phone->id,
                'expected_status' => UnitStatus::AVAILABLE->value,
                'expected_revision' => $this->phone->lifecycle_revision,
                'key' => 'inspect-race',
            ],
        ]);

        $statuses = array_column($results, 'status');
        $saleWon = $statuses[0] === 'ok';

        $this->assertSame(1, count(array_filter($statuses, fn (string $status): bool => $status === 'ok')));
        $this->assertSame($saleWon ? UnitStatus::SOLD : UnitStatus::INSPECTION, $this->phone->fresh()->status);
        $this->assertSame($saleWon ? 1 : 0, Sale::query()->count());
        $this->assertSame($this->phone->lifecycle_revision + 1, $this->phone->fresh()->lifecycle_revision);
    }

    #[Test]
    public function a_sale_and_a_stock_decrease_never_take_stock_below_zero(): void
    {
        $attempt = $this->review($this->cashier, $this->cableLine(1));

        $results = $this->raceWhileLocked($this->definitionLock($this->cable), [
            $this->finalizeRequest($this->cashier, $attempt, '50'),
            ['operation' => 'decrease', 'actor_id' => $this->owner->id, 'store_id' => $this->store->id, 'definition_id' => $this->cable->id, 'quantity' => 1, 'key' => 'damaged'],
        ]);

        $this->assertEqualsCanonicalizing(['ok', 'validation'], array_column($results, 'status'));
        $this->assertSame(0, $this->cableStock());
        $this->assertSame($results[0]['status'] === 'ok' ? 1 : 0, Sale::query()->count());
    }

    #[Test]
    public function a_price_change_racing_a_sale_either_lands_after_it_or_rejects_it(): void
    {
        $attempt = $this->review($this->cashier, $this->cableLine(1));

        $results = $this->raceWhileLocked($this->definitionLock($this->cable), [
            $this->finalizeRequest($this->cashier, $attempt, '60'),
            ['operation' => 'set_price', 'actor_id' => $this->owner->id, 'store_id' => $this->store->id, 'definition_id' => $this->cable->id, 'price' => '59.90'],
        ]);

        $this->assertSame('ok', $results[1]['status']);
        $this->assertSame('59.90', $this->cable->fresh()->selling_price);

        if ($results[0]['status'] === 'ok') {
            $this->assertSame('49.90', Sale::query()->sole()->total);
        } else {
            $this->assertSame('validation', $results[0]['status']);
            $this->assertSame(0, Sale::query()->count());
            $this->assertSame('price_changed', $attempt->fresh()->failure_code);
        }
    }

    #[Test]
    public function the_same_attempt_finalized_twice_at_once_returns_one_sale(): void
    {
        $attempt = $this->review($this->cashier, $this->phoneLine());
        $this->approveCard($this->cashier, $attempt, $this->createTerminal(), '12500');
        $request = $this->finalizeRequest($this->cashier, $attempt->fresh(), null);

        $results = $this->raceWhileLocked(CheckoutAttempt::query()->whereKey($attempt->id), [$request, $request]);

        $this->assertSame(['ok', 'ok'], array_column($results, 'status'));
        $this->assertSame($results[0]['sale_id'], $results[1]['sale_id']);
        $this->assertSame(1, Sale::query()->count());
        $this->assertSame(1, SalePayment::query()->count());
        $this->assertSame(CardApprovalStatus::CAPTURED, CardApproval::query()->sole()->status);
    }

    #[Test]
    public function a_changed_retry_racing_the_original_is_a_conflict(): void
    {
        $attempt = $this->review($this->cashier, $this->cableLine(1));

        $results = $this->raceWhileLocked(CheckoutAttempt::query()->whereKey($attempt->id), [
            $this->finalizeRequest($this->cashier, $attempt, '50'),
            $this->finalizeRequest($this->cashier, $attempt, '100'),
        ]);

        $this->assertEqualsCanonicalizing(['ok', 'conflict'], array_column($results, 'status'));
        $this->assertSame(1, Sale::query()->count());
    }

    #[Test]
    public function one_terminal_reference_cannot_be_recorded_for_two_attempts(): void
    {
        $terminal = $this->createTerminal();
        $first = $this->review($this->cashier, $this->cableLine(1));
        $second = $this->review($this->rival, $this->phoneLine());
        $this->startTerminalPayment($this->cashier, $first, $terminal, '49.90');
        $this->startTerminalPayment($this->rival, $second, $terminal, '49.90');

        $results = $this->raceWhileLocked(CheckoutAttempt::query()->whereKey([$first->id, $second->id]), [
            ['operation' => 'approve', 'actor_id' => $this->cashier->id, 'store_id' => $this->store->id, 'attempt_key' => $first->key, 'revision' => $first->revision, 'terminal_id' => $terminal->id, 'reference' => 'SAME-1', 'amount' => '49.90'],
            ['operation' => 'approve', 'actor_id' => $this->rival->id, 'store_id' => $this->store->id, 'attempt_key' => $second->key, 'revision' => $second->revision, 'terminal_id' => $terminal->id, 'reference' => 'SAME-1', 'amount' => '49.90'],
        ]);

        $this->assertEqualsCanonicalizing(['ok', 'validation'], array_column($results, 'status'), json_encode($results));
        $this->assertSame(1, CardApproval::query()->count());
        $this->assertSame(1, CheckoutAttempt::query()->pendingTerminalPayment()->count());
    }

    #[Test]
    public function a_cash_completion_racing_a_terminal_payment_start_never_sells_with_an_unverified_charge(): void
    {
        $terminal = $this->createTerminal();
        $attempt = $this->review($this->cashier, $this->cableLine(1));

        $results = $this->raceWhileLocked(CheckoutAttempt::query()->whereKey($attempt->id), [
            $this->finalizeRequest($this->cashier, $attempt, '50'),
            ['operation' => 'start', 'actor_id' => $this->cashier->id, 'store_id' => $this->store->id, 'attempt_key' => $attempt->key, 'revision' => $attempt->revision, 'terminal_id' => $terminal->id, 'amount' => '49.90'],
        ]);

        $this->assertEqualsCanonicalizing(['ok', 'validation'], array_column($results, 'status'), json_encode($results));
        $attempt->refresh();

        if ($results[0]['status'] === 'ok') {
            $this->assertSame(CheckoutAttemptStatus::COMPLETED, $attempt->status);
            $this->assertFalse($attempt->hasStartedTerminalPayment());
            $this->assertSame(0, $this->cableStock());
        } else {
            $this->assertSame(CheckoutAttemptStatus::OPEN, $attempt->status);
            $this->assertTrue($attempt->hasPendingTerminalPayment());
            $this->assertSame(0, Sale::query()->count());
            $this->assertSame(1, $this->cableStock());
        }
    }

    #[Test]
    public function a_late_failure_rolls_back_while_a_competing_checkout_waits_and_then_sells(): void
    {
        $failing = $this->review($this->cashier, $this->cableLine(1));
        $competing = $this->review($this->rival, $this->cableLine(1));

        $results = $this->raceWhileLocked($this->definitionLock($this->cable), [
            [...$this->finalizeRequest($this->cashier, $failing, '50'), 'fail_late' => true],
            $this->finalizeRequest($this->rival, $competing, '50'),
        ]);

        $this->assertContains($results[0]['status'], ['error', 'validation']);
        $this->assertSame('ok', $results[1]['status']);
        $this->assertSame(1, Sale::query()->count());
        $this->assertSame(1, SaleLine::query()->count());
        $this->assertSame(1, SalePayment::query()->count());
        $this->assertSame(0, $this->cableStock());
        $this->assertSame(CheckoutAttemptStatus::OPEN, $failing->fresh()->status);
        $this->assertContains($failing->fresh()->failure_code, ['system_error', 'unavailable']);
        $this->assertSame('KT01-000001', Sale::query()->sole()->number);
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
    private function finalizeRequest(User $actor, CheckoutAttempt $attempt, ?string $cash): array
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
     * Start one worker per request while holding the given rows locked, then release them.
     *
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

        $process = proc_open(
            [PHP_BINARY, base_path('tests/Support/pos-worker.php'), json_encode($payload, JSON_THROW_ON_ERROR)],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            base_path(),
            $environment,
        );

        if (! is_resource($process)) {
            throw new RuntimeException('Could not start the POS worker process.');
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
