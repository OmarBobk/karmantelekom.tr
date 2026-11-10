<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Network\ApproveReservation;
use App\Actions\Network\PublishUnitListing;
use App\Actions\Network\RequestReservation;
use App\Actions\Network\SetReservationHold;
use App\Actions\Storefront\BranchOrderHolds;
use App\Enums\ReservationStatus;
use App\Enums\StorefrontOrderStatus;
use App\Models\CardTerminal;
use App\Models\CheckoutAttempt;
use App\Models\NetworkListing;
use App\Models\NetworkReservation;
use App\Models\Store;
use App\Models\StorefrontOrder;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Support\InteractsWithBranchOrders;
use Tests\TestCase;

/**
 * Branch confirmation racing a counter payment start and a Store Network approval, from
 * independent PHP processes with their own database connections.
 *
 * The test process runs the first action inside an open transaction, so it holds its locks,
 * starts the competing action in a worker, waits until the database reports the worker blocked,
 * then commits. The worker must then see the committed winner and refuse.
 */
class BranchOrderConcurrencyTest extends TestCase
{
    use DatabaseTruncation;
    use InteractsWithBranchOrders;

    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Concurrency tests need the MySQL/MariaDB test database.');
        }

        $this->setUpBranchOrders(cableStock: 1, cablesOnline: 1);
    }

    protected function tearDown(): void
    {
        $this->truncateTablesForAllConnections();

        parent::tearDown();
    }

    #[Test]
    public function a_confirmation_that_commits_first_refuses_a_counter_payment_start_for_the_last_cable(): void
    {
        $order = $this->pendingOrder(cables: 1, withPhone: false);
        $walkIn = $this->review($this->owner, $this->cableLine(1));
        $terminal = $this->createTerminal();

        $result = $this->whileHolding(fn () => $this->confirm($order), $this->startPayload($walkIn, $terminal));

        $this->assertSame('validation', $result['status']);
        $this->assertStringContainsString('can be sold', $result['errors']['storefront_order'][0]);
        $this->assertNull($walkIn->fresh()->terminal_payment_key);
        $this->assertNull($walkIn->fresh()->frozen_at);
        $this->assertSame(StorefrontOrderStatus::CONFIRMED, $order->fresh()->status);
        $this->assertSame([$this->cable->id => 1], BranchOrderHolds::heldQuantities($this->store->id, [$this->cable->id]));
    }

    #[Test]
    public function a_counter_payment_start_that_commits_first_makes_confirmation_refuse_the_last_cable(): void
    {
        $order = $this->pendingOrder(cables: 1, withPhone: false);
        $walkIn = $this->review($this->owner, $this->cableLine(1));
        $terminal = $this->createTerminal();
        $line = $order->lines()->sole();

        $result = $this->whileHolding(
            fn () => $this->startTerminalPayment($this->owner, $walkIn, $terminal, $walkIn->total),
            ['operation' => 'confirm', 'actor_id' => $this->owner->id, 'store_id' => $this->store->id, 'order_id' => $order->id, 'devices' => [], 'hours' => 48],
        );

        $this->assertSame('validation', $result['status']);
        $this->assertStringContainsString('only 0 are available', $result['errors']['lines.'.$line->id][0]);
        $this->assertNotNull($walkIn->fresh()->terminal_payment_key);
        $this->assertSame(StorefrontOrderStatus::PENDING, $order->fresh()->status);
        $this->assertSame([], BranchOrderHolds::heldQuantities($this->store->id, [$this->cable->id]));
    }

    #[Test]
    public function a_confirmation_that_commits_first_refuses_store_network_approval_of_the_device(): void
    {
        $order = $this->pendingOrder(cables: 0);
        $reservation = $this->pendingReservation();

        $result = $this->whileHolding(
            fn () => $this->confirm($order),
            ['operation' => 'approve_reservation', 'actor_id' => $this->owner->id, 'store_id' => $this->store->id, 'reservation_id' => $reservation->id],
        );

        $this->assertSame('validation', $result['status']);
        $this->assertStringContainsString('held for a confirmed online order', $result['errors']['reservation'][0]);
        $this->assertSame(ReservationStatus::PENDING, $reservation->fresh()->status);
        $this->assertSame($order->id, BranchOrderHolds::unitHolds([$this->phone->id])->sole()->storefront_order_id);
    }

    #[Test]
    public function a_store_network_approval_that_commits_first_refuses_confirmation_of_the_device(): void
    {
        $order = $this->pendingOrder(cables: 0);
        $reservation = $this->pendingReservation();
        $line = $order->lines()->sole();

        $result = $this->whileHolding(
            fn () => app(ApproveReservation::class)($this->owner, $this->store, $reservation->id),
            ['operation' => 'confirm', 'actor_id' => $this->owner->id, 'store_id' => $this->store->id, 'order_id' => $order->id, 'devices' => [$line->id => $this->phone->id], 'hours' => 48],
        );

        $this->assertSame('validation', $result['status']);
        $this->assertStringContainsString('reserved for a Store Network request', $result['errors']['lines.'.$line->id][0]);
        $this->assertSame(ReservationStatus::APPROVED, $reservation->fresh()->status);
        $this->assertSame(StorefrontOrderStatus::PENDING, StorefrontOrder::query()->find($order->id)->status);
        $this->assertTrue(BranchOrderHolds::unitHolds([$this->phone->id])->isEmpty());
    }

    private function pendingReservation(): NetworkReservation
    {
        $otherStore = Store::factory()->create(['code' => 'KT02']);
        app(SetReservationHold::class)($this->owner, $this->store, 60);
        $listing = app(PublishUnitListing::class)($this->owner, $this->store, $this->phone->id, NetworkListing::UNLISTED_REVISION);

        return app(RequestReservation::class)($this->owner, $otherStore, $listing->id, $listing->revision);
    }

    /**
     * @return array<string, mixed>
     */
    private function startPayload(CheckoutAttempt $attempt, CardTerminal $terminal): array
    {
        return [
            'operation' => 'start', 'actor_id' => $this->owner->id, 'store_id' => $this->store->id, 'attempt_key' => $attempt->key,
            'revision' => $attempt->revision, 'request_key' => (string) Str::uuid(), 'terminal_id' => $terminal->id, 'amount' => $attempt->total,
        ];
    }

    /**
     * Run the first action in an open transaction, start the competing worker, wait until it is
     * blocked on a lock the first action holds, then commit and return the worker's outcome.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function whileHolding(callable $first, array $payload): array
    {
        DB::beginTransaction();

        try {
            $first();
            $worker = $this->startWorker($payload);
            $this->waitForBlockedWorker();
        } finally {
            DB::commit();
        }

        return $this->finishWorker($worker);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{process: resource, pipes: array<int, resource>}
     */
    private function startWorker(array $payload): array
    {
        $process = proc_open(
            [PHP_BINARY, base_path('tests/Support/branch-order-worker.php'), json_encode($payload, JSON_THROW_ON_ERROR)],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            base_path(),
            [...getenv(), 'APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => 'dev_store_testing'],
        );

        if (! is_resource($process)) {
            throw new RuntimeException('Could not start the branch order worker process.');
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

        $this->assertSame(0, proc_close($worker['process']), "Worker failed: {$errors}{$output}");

        return json_decode((string) $output, true, flags: JSON_THROW_ON_ERROR);
    }

    private function waitForBlockedWorker(): void
    {
        $deadline = microtime(true) + 30;

        while (microtime(true) < $deadline) {
            if ((int) DB::scalar('select count(*) from information_schema.innodb_lock_waits') >= 1) {
                return;
            }

            usleep(100_000);
        }

        throw new RuntimeException('The worker did not block on a lock within 30 seconds.');
    }
}
