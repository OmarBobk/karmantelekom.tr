<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Cashbox\CloseCashboxSession;
use App\Actions\Cashbox\OpenCashboxSession;
use App\Actions\Purchasing\ConfirmSupplierCashPayout;
use App\Actions\Purchasing\RecordSupplierPayment;
use App\Actions\Purchasing\ReleaseSupplierCashPayout;
use App\Actions\Purchasing\ReversePurchase;
use App\Actions\Purchasing\ReverseSupplierPayment;
use App\Enums\CashMovementDirection;
use App\Enums\CashMovementType;
use App\Enums\CashPayoutStatus;
use App\Enums\Currency;
use App\Enums\SupplierLedgerEntryType;
use App\Enums\SupplierPaymentMethod;
use App\Enums\TrackingMode;
use App\Livewire\Backend\Purchases\PurchaseShow;
use App\Models\CashboxSession;
use App\Models\CashMovement;
use App\Models\OperationalSupplier;
use App\Models\ProductDefinition;
use App\Models\Purchase;
use App\Models\Store;
use App\Models\SupplierCashPayout;
use App\Models\SupplierLedgerEntry;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\InteractsWithOperationalInventory;
use Tests\Support\InteractsWithPurchasing;
use Tests\TestCase;

class SupplierCashPayoutTest extends TestCase
{
    use InteractsWithOperationalInventory;
    use InteractsWithPurchasing;
    use RefreshDatabase;

    private User $owner;

    private Store $store;

    private OperationalSupplier $supplier;

    private ProductDefinition $cable;

    private Purchase $purchase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOperationalRoles();
        $this->owner = $this->createOwner();
        $this->store = Store::factory()->create(['code' => 'KT01']);
        $this->supplier = OperationalSupplier::factory()->create();
        $this->cable = ProductDefinition::factory()->create(['tracking_mode' => TrackingMode::QUANTITY]);
        $this->purchase = $this->postedPurchase($this->owner, $this->store, $this->supplier, [[$this->cable, 100, '25.00']]);
    }

    #[Test]
    public function cash_before_the_first_session_records_at_once_and_still_replays_after_tracking_starts(): void
    {
        $before = $this->pay('300', 'CASH-OLD', 'old-key');

        $this->assertInstanceOf(SupplierPayment::class, $before);
        $this->assertSame([1, 0, 0], [SupplierPayment::count(), SupplierCashPayout::count(), CashMovement::count()]);

        $session = $this->openSession('1000');
        $replay = $this->pay('300', 'CASH-OLD', 'old-key', $session->id);

        $this->assertSame([$before->id, false], [$replay->id, $replay->wasRecentlyCreated]);
        $this->assertSame([1, 0, 100_000], [SupplierPayment::count(), SupplierCashPayout::count(), $session->availableForPayoutMinor()]);
        $this->assertInstanceOf(SupplierCashPayout::class, $this->pay('100', 'CASH-NEW', 'new-key', $session->id));
    }

    #[Test]
    public function a_confirmed_payout_records_the_payment_ledger_entry_and_one_cash_out_movement(): void
    {
        $session = $this->openSession('1000');
        $usd = $this->postedPurchase($this->owner, $this->store, $this->supplier, [[$this->cable, 10, '5.00']], ['supplier_invoice_number' => 'USD-1'], Currency::USD);

        $this->assertInstanceOf(SupplierPayment::class, app(RecordSupplierPayment::class)($this->owner, $usd, $this->paymentInput('20', 'USD-CASH', ['method' => 'cash']), 'usd-key'));
        $this->assertInstanceOf(SupplierPayment::class, $this->pay('100', 'EFT-1', 'eft-key', null, 'bank_transfer'));

        $payout = $this->pay('400', 'CASH-1', 'cash-key', $session->id);

        $this->assertSame([CashPayoutStatus::PENDING, 60_000, 200_000], [$payout->status, $session->availableForPayoutMinor(), $this->purchase->refresh()->payableMinor()]);
        $this->assertValidationError(fn () => $this->pay('700', 'CASH-2', 'too-much-cash', $session->id), 'amount');
        $this->assertValidationError(fn () => $this->pay('2000.01', 'EFT-2', 'too-much-eft', null, 'bank_transfer'), 'amount');
        $this->assertValidationError(fn () => $this->pay('10', 'CASH-3', 'stale', $session->id + 1), 'cashbox');
        $this->assertValidationError(fn () => app(CloseCashboxSession::class)($this->owner, $this->store, $session->id, '1000', '1000', null), 'cashbox');
        $this->assertValidationError(fn () => $this->confirm($payout, $session->id + 1), 'cashbox');

        $payment = $this->confirm($payout, $session->id);

        $this->assertSame(['400.00', SupplierPaymentMethod::CASH, $payout->id, $this->owner->id], [$payment->amount, $payment->method, $payment->supplier_cash_payout_id, $payment->user_id]);
        $this->assertSame(['500.00', 200_000], [$this->purchase->refresh()->paid_total, $this->purchase->outstandingMinor()]);
        $this->assertSame('-400.00', SupplierLedgerEntry::query()->where('supplier_payment_id', $payment->id)->where('type', SupplierLedgerEntryType::PAYMENT)->sole()->amount);
        $movement = CashMovement::query()->sole();
        $this->assertSame([CashMovementType::SUPPLIER_PAYMENT_CASH, CashMovementDirection::OUT, '400.00', $payout->id, $payment->id], [
            $movement->type, $movement->direction, $movement->amount, $movement->supplier_cash_payout_id, $movement->supplier_payment_id,
        ]);
        $this->assertSame([60_000, 60_000, CashPayoutStatus::COMPLETED], [$session->expectedMinor(), $session->availableForPayoutMinor(), $payout->refresh()->status]);

        $this->assertSame($payment->id, $this->confirm($payout, $session->id)->id);
        $resent = $this->pay('400', 'CASH-1', 'cash-key', $session->id);
        $this->assertSame([$payment->id, false], [$resent->id, $resent->wasRecentlyCreated]);
        $this->assertSame([3, 1, 2], [SupplierPayment::count(), CashMovement::count(), SupplierLedgerEntry::query()->where('type', SupplierLedgerEntryType::PAYMENT)->where('currency', 'TRY')->count()]);

        $this->assertValidationError(fn () => app(ReverseSupplierPayment::class)($this->owner, $this->purchase, $payment->id, 'Wrong invoice'), 'reason');
        $this->assertSame(60_000, app(CloseCashboxSession::class)($this->owner, $this->store, $session->id, '600', '600', null)->refresh()->expectedMinor());
    }

    #[Test]
    public function the_owner_releases_a_payout_that_was_not_handed_out_and_the_screen_never_repeats_the_hand_out(): void
    {
        $session = $this->openSession('1000');
        $page = Livewire::actingAs($this->owner)->test(PurchaseShow::class, ['purchase' => $this->purchase])
            ->set('paymentAmount', '250')->set('paymentMethod', 'cash')->set('paymentReference', 'CASH-9')
            ->call('recordPayment')
            ->assertHasNoErrors()
            ->assertSeeHtml('data-payout-hand-out');
        $payout = SupplierCashPayout::query()->sole();

        Livewire::actingAs($this->owner)->test(PurchaseShow::class, ['purchase' => $this->purchase])
            ->assertSeeHtml('data-payout-verify')
            ->assertDontSeeHtml('data-payout-hand-out');

        $this->assertValidationError(fn () => app(ReversePurchase::class)($this->owner, $this->purchase, 'Duplicate invoice'), 'reason');
        $this->assertValidationError(fn () => app(ReleaseSupplierCashPayout::class)($this->owner, $this->purchase, $payout->id, 'not_handed_out', ' '), 'release_note');

        $page->call('openRelease', $payout->id)
            ->set('releaseResolution', 'not_handed_out')->set('releaseNote', 'Supplier did not come')
            ->call('releasePayout')
            ->assertHasNoErrors()
            ->assertDontSeeHtml('data-payout-hand-out');

        $this->assertSame([CashPayoutStatus::RELEASED, 100_000, 250_000], [$payout->refresh()->status, $session->availableForPayoutMinor(), $this->purchase->refresh()->payableMinor()]);
        $this->assertSame([0, 0], [SupplierPayment::count(), CashMovement::count()]);
        $this->assertValidationError(fn () => $this->confirm($payout, $session->id), 'cashbox');
        $this->assertSame('supplier_cash_payout_released', Activity::query()->where('subject_type', $payout->getMorphClass())->where('subject_id', $payout->id)->sole()->event);
    }

    private function pay(string $amount, string $reference, string $key, ?int $sessionId = null, string $method = 'cash'): SupplierPayment|SupplierCashPayout
    {
        return app(RecordSupplierPayment::class)($this->owner, $this->purchase, $this->paymentInput($amount, $reference, ['method' => $method]), $key, $sessionId);
    }

    private function confirm(SupplierCashPayout $payout, int $sessionId): SupplierPayment
    {
        return app(ConfirmSupplierCashPayout::class)($this->owner, $this->purchase, $payout->id, $sessionId);
    }

    private function openSession(string $counted): CashboxSession
    {
        return app(OpenCashboxSession::class)($this->owner, $this->store, $counted, null);
    }

    private function assertValidationError(callable $action, string $field): void
    {
        try {
            $action();
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors(), json_encode($exception->errors()));

            return;
        }

        $this->fail("Expected a validation error on {$field}.");
    }
}
