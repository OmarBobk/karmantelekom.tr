<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Cashbox\CloseCashboxSession;
use App\Actions\Cashbox\OpenCashboxSession;
use App\Actions\Cashbox\StartSaleCashIntake;
use App\Actions\Expenses\StartCashPayout;
use App\Enums\CashboxSessionStatus;
use App\Models\CashboxSession;
use App\Models\CashIntake;
use App\Models\CashMovement;
use App\Models\CashPayout;
use App\Models\CheckoutAttempt;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Expense;
use App\Models\Sale;
use App\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Support\InteractsWithPos;
use Tests\TestCase;

/**
 * Opening and closing the cashbox while cash is being bound or posted, from independent PHP
 * processes with their own MariaDB connections. The test process holds the store row lock (the
 * cashbox's lock point) while the workers start one by one, waits until the database reports
 * each worker blocked, then releases it so they race in that order.
 */
class CashboxConcurrencyTest extends TestCase
{
    use DatabaseTruncation;
    use InteractsWithPos;

    private CheckoutAttempt $attempt;

    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Concurrency tests need the MySQL/MariaDB test database.');
        }

        $this->setUpCounter();
        $this->attempt = $this->review($this->owner, $this->cableLine(2));
    }

    protected function tearDown(): void
    {
        $this->truncateTablesForAllConnections();

        parent::tearDown();
    }

    #[Test]
    public function a_close_racing_a_cash_intake_either_closes_first_or_is_refused_by_the_pending_intake(): void
    {
        $session = $this->openSession();

        [$intake, $close] = $this->raceWhileLocked(Store::query()->whereKey($this->store->id), [
            ['operation' => 'cash_intake', 'attempt_key' => $this->attempt->key, 'revision' => $this->attempt->revision, 'request_key' => (string) Str::uuid()],
            $this->closeRequest($session, '1000'),
        ]);

        $this->assertEqualsCanonicalizing(['ok', 'validation'], [$intake['status'], $close['status']], json_encode([$intake, $close]));
        $session->refresh();

        if ($close['status'] === 'ok') {
            $this->assertSame(['cashbox'], $intake['errors']);
            $this->assertSame([CashboxSessionStatus::CLOSED, 0], [$session->status, CashIntake::query()->count()]);
        } else {
            $this->assertSame(['cashbox'], $close['errors']);
            $this->assertSame([CashboxSessionStatus::OPEN, $session->id], [$session->status, CashIntake::query()->sole()->cashbox_session_id]);
        }
    }

    #[Test]
    public function a_close_racing_a_posting_never_misses_its_movement(): void
    {
        $session = $this->openSession();
        $intake = app(StartSaleCashIntake::class)($this->owner, $this->store, $this->attempt->key, $this->attempt->revision, (string) Str::uuid());

        [$finalize, $close] = $this->raceWhileLocked(Store::query()->whereKey($this->store->id), [
            ['operation' => 'finalize', 'attempt_key' => $this->attempt->key, 'revision' => $this->attempt->revision, 'cash' => '100', 'cash_intake_id' => $intake->id],
            $this->closeRequest($session, '1000'),
        ]);

        $this->assertSame('ok', $finalize['status']);
        $this->assertSame('validation', $close['status']);
        $this->assertCount(1, $close['errors']);
        $this->assertContains($close['errors'][0], ['cashbox', 'counted_closing'], 'Closing first sees the pending intake; closing after the posting sees a changed expected amount.');
        $this->assertSame(1, Sale::query()->count());
        $this->assertSame(['99.80'], CashMovement::query()->pluck('amount')->all());
        $this->assertTrue($session->fresh()->isOpen());

        $closed = app(CloseCashboxSession::class)($this->owner, $this->store, $session->id, '1099.80', '1099.80', null);
        $this->assertSame(['1099.80', '0.00'], [$closed->expected_closing, $closed->closing_difference]);
    }

    #[Test]
    public function cash_writes_without_an_intake_that_lose_the_race_to_the_first_session_are_refused(): void
    {
        $customer = Customer::factory()->atCounter($this->store)->create();
        $this->creditSale($customer, 2, '99.80');
        $this->assertFalse(CashboxSession::trackingStarted($this->store));

        [$open, $finalize, $payment] = $this->raceWhileLocked(Store::query()->whereKey($this->store->id), [
            ['operation' => 'open_cashbox', 'counted' => '0'],
            ['operation' => 'finalize', 'attempt_key' => $this->attempt->key, 'revision' => $this->attempt->revision, 'cash' => '100'],
            ['operation' => 'customer_payment', 'customer_id' => $customer->id, 'input' => ['amount' => '50', 'method' => 'cash'], 'key' => (string) Str::uuid()],
        ]);

        $this->assertSame('ok', $open['status'], json_encode($open));
        $this->assertSame(['validation', ['cashbox']], [$finalize['status'], $finalize['errors'] ?? null], json_encode($finalize));
        $this->assertSame(['validation', ['cashbox']], [$payment['status'], $payment['errors'] ?? null], json_encode($payment));
        $this->assertSame(1, Sale::query()->count());
        $this->assertSame(0, CustomerPayment::query()->count());
        $this->assertSame(0, CashMovement::query()->count());
    }

    #[Test]
    public function a_close_racing_a_cash_payout_either_closes_first_or_is_refused_by_the_reservation(): void
    {
        $session = $this->openSession();

        [$payout, $close] = $this->raceWhileLocked(Store::query()->whereKey($this->store->id), [
            ['operation' => 'start_payout', 'session_id' => $session->id, 'input' => $this->payoutInput('200'), 'request_key' => (string) Str::uuid()],
            $this->closeRequest($session, '1000'),
        ]);

        $this->assertEqualsCanonicalizing(['ok', 'validation'], [$payout['status'], $close['status']], json_encode([$payout, $close]));
        $session->refresh();

        if ($close['status'] === 'ok') {
            $this->assertSame(['cashbox'], $payout['errors']);
            $this->assertSame([CashboxSessionStatus::CLOSED, 0], [$session->status, CashPayout::query()->count()]);
        } else {
            $this->assertSame(['cashbox'], $close['errors']);
            $this->assertSame([CashboxSessionStatus::OPEN, $session->id], [$session->status, CashPayout::query()->sole()->cashbox_session_id]);
        }
    }

    #[Test]
    public function a_close_racing_a_payout_confirmation_never_misses_the_cash_paid_out(): void
    {
        $session = $this->openSession();
        $payout = app(StartCashPayout::class)($this->owner, $this->store, $session->id, $this->payoutInput('200'), (string) Str::uuid());

        [$confirm, $close] = $this->raceWhileLocked(Store::query()->whereKey($this->store->id), [
            ['operation' => 'confirm_payout', 'payout_id' => $payout->id, 'session_id' => $session->id],
            $this->closeRequest($session, '1000'),
        ]);

        $this->assertSame('ok', $confirm['status'], json_encode($confirm));
        $this->assertSame('validation', $close['status']);
        $this->assertContains($close['errors'][0], ['cashbox', 'counted_closing'], 'Closing first sees the pending payout; closing after the confirmation sees a changed expected amount.');
        $this->assertSame([1, 80_000], [Expense::query()->count(), $session->fresh()->expectedMinor()]);
        $this->assertTrue($session->fresh()->isOpen());
    }

    /**
     * @return array<string, string>
     */
    private function payoutInput(string $amount): array
    {
        return ['category' => 'supplies', 'description' => 'Printer paper', 'amount' => $amount, 'expense_date' => now(Sale::DISPLAY_TIMEZONE)->toDateString()];
    }

    private function openSession(): CashboxSession
    {
        return app(OpenCashboxSession::class)($this->owner, $this->store, '1000', null);
    }

    /**
     * @return array<string, mixed>
     */
    private function closeRequest(CashboxSession $session, string $expectedShown): array
    {
        return ['operation' => 'close_cashbox', 'session_id' => $session->id, 'expected_shown' => $expectedShown, 'counted' => $expectedShown];
    }

    /**
     * Each worker starts only after the previous one is blocked, so the lock queue holds them in
     * request order.
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
                $processes[] = $this->startWorker(['actor_id' => $this->owner->id, 'store_id' => $this->store->id, ...$request]);
                $this->waitForBlockedWorkers(count($processes));
            }
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
            if ((int) DB::scalar('select count(distinct requesting_trx_id) from information_schema.innodb_lock_waits') >= $expected) {
                return;
            }

            usleep(100_000);
        }

        throw new RuntimeException("Workers did not block on the held locks within 30 seconds (expected {$expected}).");
    }
}
