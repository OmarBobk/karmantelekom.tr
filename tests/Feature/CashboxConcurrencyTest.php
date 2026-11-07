<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Cashbox\CloseCashboxSession;
use App\Actions\Cashbox\OpenCashboxSession;
use App\Actions\Cashbox\StartSaleCashIntake;
use App\Enums\CashboxSessionStatus;
use App\Models\CashboxSession;
use App\Models\CashIntake;
use App\Models\CashMovement;
use App\Models\CheckoutAttempt;
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
 * Closing the cashbox while cash is being bound or posted, from independent PHP processes with
 * their own MariaDB connections. The test process holds the store row lock (the cashbox's lock
 * point) while the workers start, waits until the database reports every worker blocked, then releases it so they race.
 */
class CashboxConcurrencyTest extends TestCase
{
    use DatabaseTruncation;
    use InteractsWithPos;

    private CashboxSession $session;

    private CheckoutAttempt $attempt;

    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Concurrency tests need the MySQL/MariaDB test database.');
        }

        $this->setUpCounter();
        $this->session = app(OpenCashboxSession::class)($this->owner, $this->store, '1000', null);
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
        [$intake, $close] = $this->raceWhileLocked(Store::query()->whereKey($this->store->id), [
            ['operation' => 'cash_intake', 'attempt_key' => $this->attempt->key, 'revision' => $this->attempt->revision, 'request_key' => (string) Str::uuid()],
            $this->closeRequest('1000'),
        ]);

        $this->assertEqualsCanonicalizing(['ok', 'validation'], [$intake['status'], $close['status']], json_encode([$intake, $close]));
        $session = $this->session->fresh();

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
        app(StartSaleCashIntake::class)($this->owner, $this->store, $this->attempt->key, $this->attempt->revision, (string) Str::uuid());

        [$finalize, $close] = $this->raceWhileLocked(Store::query()->whereKey($this->store->id), [
            ['operation' => 'finalize', 'attempt_key' => $this->attempt->key, 'revision' => $this->attempt->revision, 'cash' => '100'],
            $this->closeRequest('1000'),
        ]);

        $this->assertSame('ok', $finalize['status']);
        $this->assertSame('validation', $close['status']);
        $this->assertCount(1, $close['errors']);
        $this->assertContains($close['errors'][0], ['cashbox', 'counted_closing'], 'Closing first sees the pending intake; closing after the posting sees a changed expected amount.');
        $this->assertSame(1, Sale::query()->count());
        $this->assertSame(['99.80'], CashMovement::query()->pluck('amount')->all());
        $this->assertTrue($this->session->fresh()->isOpen());

        $closed = app(CloseCashboxSession::class)($this->owner, $this->store, $this->session->id, '1099.80', '1099.80', null);
        $this->assertSame(['1099.80', '0.00'], [$closed->expected_closing, $closed->closing_difference]);
    }

    /**
     * @return array<string, mixed>
     */
    private function closeRequest(string $expectedShown): array
    {
        return ['operation' => 'close_cashbox', 'session_id' => $this->session->id, 'expected_shown' => $expectedShown, 'counted' => $expectedShown];
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
                $processes[] = $this->startWorker(['actor_id' => $this->owner->id, 'store_id' => $this->store->id, ...$request]);
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
