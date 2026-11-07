<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\CustomerPaymentAllocation;
use App\Services\Customers\CustomerAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Support\InteractsWithPos;
use Tests\TestCase;

/**
 * Competing customer payments from independent PHP processes, each with its own MariaDB
 * connection. The test process holds the customer row lock while the workers start, waits
 * until the database reports every worker blocked, then releases it so the workers race.
 */
class CustomerPaymentConcurrencyTest extends TestCase
{
    use DatabaseTruncation;
    use InteractsWithPos;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Concurrency tests need the MySQL/MariaDB test database.');
        }

        $this->setUpCounter();
        $this->customer = Customer::factory()->atCounter($this->store)->create();
        $this->creditSale($this->customer, 10, '300', '199');
    }

    protected function tearDown(): void
    {
        $this->truncateTablesForAllConnections();

        parent::tearDown();
    }

    #[Test]
    public function two_payments_competing_for_the_same_balance_settle_it_once(): void
    {
        $results = $this->raceWhileLocked(Customer::query()->whereKey($this->customer->id), [
            $this->paymentRequest('cash-a', ['amount' => '300', 'method' => 'cash']),
            $this->paymentRequest('cash-b', ['amount' => '300', 'method' => 'cash']),
        ]);

        $this->assertEqualsCanonicalizing(['ok', 'validation'], array_column($results, 'status'));
        $this->assertSame(1, CustomerPayment::query()->count());
        $this->assertSame(['300.00'], CustomerPaymentAllocation::query()->pluck('amount')->all());
        $this->assertSame('KT01-CP-000001', CustomerPayment::query()->sole()->receipt_number);
        $this->assertSame(0, CustomerAccount::totalDueMinor(app(CustomerAccount::class)->openDebts($this->customer)));
    }

    #[Test]
    public function competing_retries_of_one_request_key_record_one_payment(): void
    {
        $request = $this->paymentRequest('same-key', ['amount' => '120.50', 'method' => 'card', 'reference' => 'A12345']);

        $results = $this->raceWhileLocked(Customer::query()->whereKey($this->customer->id), [$request, $request]);

        $this->assertSame(['ok', 'ok'], array_column($results, 'status'));
        $this->assertSame($results[0]['payment_id'], $results[1]['payment_id']);
        $this->assertEqualsCanonicalizing([true, false], array_column($results, 'created'));
        $this->assertSame(1, CustomerPaymentAllocation::query()->count());
    }

    /**
     * @param  array<string, string>  $input
     * @return array<string, mixed>
     */
    private function paymentRequest(string $key, array $input): array
    {
        return [
            'operation' => 'customer_payment',
            'actor_id' => $this->owner->id,
            'store_id' => $this->store->id,
            'customer_id' => $this->customer->id,
            'input' => $input,
            'key' => $key,
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
