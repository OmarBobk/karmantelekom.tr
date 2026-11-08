<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Cashbox\OpenCashboxSession;
use App\Enums\PurchaseStatus;
use App\Enums\TrackingMode;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\InventoryUnitIdentifier;
use App\Models\OperationalSupplier;
use App\Models\ProductDefinition;
use App\Models\Purchase;
use App\Models\PurchaseReceipt;
use App\Models\Store;
use App\Models\SupplierCashPayout;
use App\Models\SupplierLedgerEntry;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Support\InteractsWithOperationalInventory;
use Tests\Support\InteractsWithPurchasing;
use Tests\TestCase;

/**
 * Competing receipts, payments, and postings from independent PHP processes, each with its
 * own MariaDB connection.
 *
 * The test process holds row locks while the workers start, waits until the database
 * reports every worker blocked, then releases the locks so the workers race.
 */
class PurchasingConcurrencyTest extends TestCase
{
    use DatabaseTruncation;
    use InteractsWithOperationalInventory;
    use InteractsWithPurchasing;

    private User $owner;

    private Store $store;

    private OperationalSupplier $supplier;

    private ProductDefinition $cable;

    private ProductDefinition $usedPhone;

    private Purchase $purchase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Concurrency tests need the MySQL/MariaDB test database.');
        }

        $this->setUpOperationalRoles();
        $this->owner = $this->createOwner();
        $this->store = Store::factory()->create(['code' => 'KT01']);
        $this->supplier = OperationalSupplier::factory()->create();
        $this->cable = ProductDefinition::factory()->create(['tracking_mode' => TrackingMode::QUANTITY]);
        $this->usedPhone = ProductDefinition::factory()->create(['tracking_mode' => TrackingMode::USED_DEVICE]);

        $this->purchase = $this->postedPurchase($this->owner, $this->store, $this->supplier, [
            [$this->cable, 10, '12.50'],
            [$this->usedPhone, 1, '8000.00'],
        ], ['additional_charges' => '50.00']);
    }

    protected function tearDown(): void
    {
        $this->truncateTablesForAllConnections();

        parent::tearDown();
    }

    /**
     * @return array<int, list<array<string, string>>>
     */
    private function phoneUnits(string $imei): array
    {
        $line = $this->purchase->lines()->where('product_definition_id', $this->usedPhone->id)->sole();

        return [$line->id => [['condition' => 'b', 'imei' => $imei]]];
    }

    #[Test]
    public function competing_receipts_with_different_keys_receive_once(): void
    {
        $purchaseLock = Purchase::query()->whereKey($this->purchase->id);

        $results = $this->raceWhileLocked($purchaseLock, [
            ['operation' => 'receive', 'purchase_id' => $this->purchase->id, 'units' => $this->phoneUnits(self::imei('35693803564380')), 'key' => 'receipt-a'],
            ['operation' => 'receive', 'purchase_id' => $this->purchase->id, 'units' => $this->phoneUnits(self::imei('35693803564381')), 'key' => 'receipt-b'],
        ]);

        $this->assertEqualsCanonicalizing(['ok', 'already_received'], array_column($results, 'status'));
        $this->assertSame(1, PurchaseReceipt::count());
        $this->assertSame(1, InventoryUnit::count());
        $this->assertSame(10, (int) InventoryBalance::query()->where('product_definition_id', $this->cable->id)->value('quantity'));
        $this->assertSame(2, InventoryMovement::count());
    }

    #[Test]
    public function competing_retries_of_one_receipt_key_return_the_same_receipt(): void
    {
        $request = ['operation' => 'receive', 'purchase_id' => $this->purchase->id, 'units' => $this->phoneUnits(self::imei('35693803564380')), 'key' => 'receipt-same'];

        $results = $this->raceWhileLocked(Purchase::query()->whereKey($this->purchase->id), [$request, $request]);

        $this->assertSame(['ok', 'ok'], array_column($results, 'status'));
        $this->assertSame($results[0]['receipt_id'], $results[1]['receipt_id']);
        $this->assertEqualsCanonicalizing([true, false], array_column($results, 'created'));
        $this->assertSame(2, InventoryMovement::count());
    }

    #[Test]
    public function competing_payments_never_exceed_the_outstanding_balance(): void
    {
        $results = $this->raceWhileLocked(Purchase::query()->whereKey($this->purchase->id), [
            ['operation' => 'pay', 'purchase_id' => $this->purchase->id, 'input' => $this->paymentInput('5000.00', 'EFT-A'), 'key' => 'pay-a'],
            ['operation' => 'pay', 'purchase_id' => $this->purchase->id, 'input' => $this->paymentInput('5000.00', 'EFT-B'), 'key' => 'pay-b'],
        ]);

        $this->assertEqualsCanonicalizing(['ok', 'validation'], array_column($results, 'status'));
        $this->assertSame('5000.00', $this->purchase->refresh()->paid_total);
        $this->assertSame(1, SupplierPayment::count());
        $this->assertSame(317_500, SupplierLedgerEntry::query()->get()->sum(fn (SupplierLedgerEntry $entry) => $entry->amountMinor()));
    }

    #[Test]
    public function the_same_payment_submitted_twice_with_new_keys_is_recorded_once(): void
    {
        $input = $this->paymentInput('3000.00', 'EFT-2026-0001');

        $results = $this->raceWhileLocked(Purchase::query()->whereKey($this->purchase->id), [
            ['operation' => 'pay', 'purchase_id' => $this->purchase->id, 'input' => $input, 'key' => 'tab-one'],
            ['operation' => 'pay', 'purchase_id' => $this->purchase->id, 'input' => $input, 'key' => 'tab-two'],
        ]);

        $this->assertSame(['ok', 'ok'], array_column($results, 'status'));
        $this->assertSame($results[0]['payment_id'], $results[1]['payment_id']);
        $this->assertSame(1, SupplierPayment::count());
        $this->assertSame(517_500, $this->purchase->refresh()->outstandingMinor());
    }

    #[Test]
    public function competing_supplier_cash_payouts_on_two_invoices_never_reserve_more_than_the_drawer_holds(): void
    {
        $session = app(OpenCashboxSession::class)($this->owner, $this->store, '1000', null);
        $second = $this->postedPurchase($this->owner, $this->store, $this->supplier, [[$this->cable, 100, '12.50']], ['supplier_invoice_number' => 'INV-1002']);
        $cash = fn (Purchase $purchase, string $reference) => [
            'operation' => 'pay', 'purchase_id' => $purchase->id, 'session_id' => $session->id, 'key' => $reference,
            'input' => $this->paymentInput('700.00', $reference, ['method' => 'cash']),
        ];

        $results = $this->raceWhileLocked(Store::query()->whereKey($this->store->id), [$cash($this->purchase, 'CASH-A'), $cash($second, 'CASH-B')]);

        $this->assertEqualsCanonicalizing(['ok', 'validation'], array_column($results, 'status'));
        $this->assertSame(['payout', 1, 0], [collect($results)->firstWhere('status', 'ok')['kind'], SupplierCashPayout::count(), SupplierPayment::count()]);
        $this->assertSame(['amount'], collect($results)->firstWhere('status', 'validation')['errors']);
        $this->assertSame(30_000, $session->refresh()->availableForPayoutMinor());
    }

    #[Test]
    public function a_cash_reservation_queued_behind_a_transfer_on_the_same_invoice_sees_the_transfer(): void
    {
        $session = app(OpenCashboxSession::class)($this->owner, $this->store, '1000', null);

        $results = $this->raceWhileLocked(Purchase::query()->whereKey($this->purchase->id), [
            ['operation' => 'pay', 'purchase_id' => $this->purchase->id, 'key' => 'eft', 'input' => $this->paymentInput('8000.00', 'EFT-1')],
            ['operation' => 'pay', 'purchase_id' => $this->purchase->id, 'key' => 'cash', 'session_id' => $session->id, 'input' => $this->paymentInput('500.00', 'CASH-1', ['method' => 'cash'])],
        ]);

        $this->assertSame(['ok', 'validation'], array_column($results, 'status'));
        $this->assertSame(['amount'], $results[1]['errors']);
        $this->assertSame([1, 0, 17_500], [SupplierPayment::count(), SupplierCashPayout::count(), $this->purchase->refresh()->payableMinor()]);
    }

    #[Test]
    public function a_receipt_losing_a_device_identity_race_after_stock_writes_rolls_back_completely(): void
    {
        $otherPhone = ProductDefinition::factory()->create(['tracking_mode' => TrackingMode::USED_DEVICE]);
        $imei = self::imei('35693803564380');

        // The receipt writes cable stock before the phone unit; the registration uses another
        // definition, so the two only collide on the system-wide IMEI unique index.
        $results = $this->raceWhileLocked(ProductDefinition::query()->whereKey([$this->usedPhone->id, $otherPhone->id]), [
            ['operation' => 'receive', 'purchase_id' => $this->purchase->id, 'units' => $this->phoneUnits($imei), 'key' => 'receipt'],
            ['operation' => 'register', 'store_id' => $this->store->id, 'definition_id' => $otherPhone->id, 'imei' => $imei, 'key' => 'register'],
        ]);

        $this->assertEqualsCanonicalizing(['ok', 'validation'], array_column($results, 'status'));
        $this->assertSame(1, InventoryUnitIdentifier::query()->where('value', $imei)->count());

        $receiptWon = $results[0]['status'] === 'ok';
        $this->assertSame($receiptWon ? 1 : 0, PurchaseReceipt::count());
        $this->assertSame($receiptWon ? 10 : 0, (int) InventoryBalance::query()->where('product_definition_id', $this->cable->id)->value('quantity'));
        $this->assertSame($receiptWon ? 2 : 1, InventoryMovement::count());
        $this->assertSame($receiptWon, $this->purchase->refresh()->received_at !== null);
    }

    #[Test]
    public function posting_and_a_tracking_mode_change_cannot_both_succeed(): void
    {
        $draft = $this->draftPurchase($this->owner, $this->store, $this->supplier, [[$this->cable, 1, '5.00']], ['supplier_invoice_number' => 'RACE-1']);

        $results = $this->raceWhileLocked(ProductDefinition::query()->whereKey($this->cable->id), [
            ['operation' => 'post', 'purchase_id' => $draft->id, 'revision' => $draft->revision],
            ['operation' => 'change_tracking_mode', 'definition_id' => $this->cable->id, 'tracking_mode' => 'serialized'],
        ]);

        $this->assertEqualsCanonicalizing(['ok', 'validation'], array_column($results, 'status'));

        $postWon = $results[0]['status'] === 'ok';
        $this->assertSame($postWon ? PurchaseStatus::POSTED : PurchaseStatus::DRAFT, $draft->refresh()->status);
        $this->assertSame($postWon ? TrackingMode::QUANTITY : TrackingMode::SERIALIZED, $this->cable->refresh()->tracking_mode);
    }

    /**
     * Start one worker per request while holding the given rows locked, then release them.
     *
     * Each worker is blocked before the next starts, so the workers queue on the held rows in
     * request order, holding whatever locks they took before blocking.
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
                $processes[] = $this->startWorker(['actor_id' => $this->owner->id, ...$request]);
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
            [PHP_BINARY, base_path('tests/Support/purchasing-worker.php'), json_encode($payload, JSON_THROW_ON_ERROR)],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            base_path(),
            $environment,
        );

        if (! is_resource($process)) {
            throw new RuntimeException('Could not start the purchasing worker process.');
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
        $this->assertJson((string) $output, "Worker failed: {$errors}{$output}");

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
