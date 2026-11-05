<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Purchasing\RecordSupplierPayment;
use App\Actions\Purchasing\ReversePurchase;
use App\Actions\Purchasing\ReverseSupplierPayment;
use App\Enums\Currency;
use App\Enums\SupplierLedgerEntryType;
use App\Enums\TrackingMode;
use App\Exceptions\Inventory\IdempotencyConflictException;
use App\Models\OperationalSupplier;
use App\Models\ProductDefinition;
use App\Models\Purchase;
use App\Models\Store;
use App\Models\SupplierLedgerEntry;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Services\Purchasing\SupplierAccount;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\InteractsWithOperationalInventory;
use Tests\Support\InteractsWithPurchasing;
use Tests\TestCase;

class SupplierPaymentTest extends TestCase
{
    use InteractsWithOperationalInventory;
    use InteractsWithPurchasing;
    use RefreshDatabase;

    private User $owner;

    private Store $storeOne;

    private Store $storeTwo;

    private OperationalSupplier $supplier;

    private ProductDefinition $cable;

    private ProductDefinition $usedPhone;

    private Purchase $purchase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOperationalRoles();
        $this->owner = $this->createOwner();
        $this->storeOne = Store::factory()->create(['code' => 'KT01']);
        $this->storeTwo = Store::factory()->create(['code' => 'KT02']);
        $this->supplier = OperationalSupplier::factory()->create();
        $this->cable = ProductDefinition::factory()->create(['tracking_mode' => TrackingMode::QUANTITY]);
        $this->usedPhone = ProductDefinition::factory()->create(['tracking_mode' => TrackingMode::USED_DEVICE]);

        $this->purchase = $this->postedPurchase($this->owner, $this->storeOne, $this->supplier, [
            [$this->cable, 10, '12.50'],
            [$this->usedPhone, 1, '8000.00'],
        ], ['additional_charges' => '50.00']);
    }

    private function pay(string $amount, string $reference, string $key, ?User $actor = null, array $overrides = []): SupplierPayment
    {
        return app(RecordSupplierPayment::class)($actor ?? $this->owner, $this->purchase, $this->paymentInput($amount, $reference, $overrides), $key);
    }

    #[Test]
    public function a_partial_payment_leaves_5175_due_on_the_invoice_and_the_supplier_account(): void
    {
        $payment = $this->pay('3,000.00', 'EFT-2026-0001', 'pay-1');

        $this->assertTrue($payment->wasRecentlyCreated);
        $this->assertSame('TRY', $payment->currency->value);
        $this->assertSame('3000.00', $this->purchase->refresh()->paid_total);
        $this->assertSame(517_500, $this->purchase->outstandingMinor());

        $entry = SupplierLedgerEntry::query()->where('type', SupplierLedgerEntryType::PAYMENT)->sole();
        $this->assertSame('-3000.00', $entry->amount);
        $this->assertSame($payment->id, $entry->supplier_payment_id);

        $balances = app(SupplierAccount::class)->balances($this->supplier, [$this->storeOne->id, $this->storeTwo->id]);
        $this->assertSame(517_500, $balances['TRY']['balance']);
        $this->assertSame(517_500, $balances['TRY']['open_invoices']);
        $this->assertSame(517_500, $balances['TRY']['stores'][$this->storeOne->id]['balance']);
    }

    #[Test]
    public function payments_above_the_outstanding_balance_are_rejected(): void
    {
        $this->pay('8000.00', 'EFT-1', 'pay-1');

        try {
            $this->pay('175.01', 'EFT-2', 'pay-2');
            $this->fail('An overpayment was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('amount', $exception->errors());
        }

        $this->pay('175.00', 'EFT-3', 'pay-3');
        $this->assertSame(0, $this->purchase->refresh()->outstandingMinor());
    }

    #[Test]
    public function a_same_key_retry_returns_the_original_payment(): void
    {
        $first = $this->pay('3000.00', 'EFT-1', 'pay-1');
        $retry = $this->pay('3000.00', 'EFT-1', 'pay-1');

        $this->assertSame($first->id, $retry->id);
        $this->assertFalse($retry->wasRecentlyCreated);
        $this->assertSame(1, SupplierPayment::count());
        $this->assertSame('3000.00', $this->purchase->refresh()->paid_total);
    }

    #[Test]
    public function a_resubmission_after_reload_with_a_new_key_returns_the_original_payment(): void
    {
        $first = $this->pay('3000.00', 'EFT-1', 'pay-1');
        $resubmitted = $this->pay('3000.00', '  eft-1 ', 'pay-after-reload');

        $this->assertSame($first->id, $resubmitted->id);
        $this->assertFalse($resubmitted->wasRecentlyCreated);
        $this->assertSame(1, SupplierPayment::count());
        $this->assertSame(2, SupplierLedgerEntry::count());
    }

    #[Test]
    public function reusing_a_reference_with_different_details_is_rejected(): void
    {
        $this->pay('3000.00', 'EFT-1', 'pay-1');

        try {
            $this->pay('2000.00', 'EFT-1', 'pay-2');
            $this->fail('A reused reference was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reference', $exception->errors());
        }

        $this->assertSame(1, SupplierPayment::count());
    }

    #[Test]
    public function another_actor_reusing_a_reference_gets_an_error_without_the_original_payment(): void
    {
        $this->pay('3000.00', 'EFT-1', 'pay-1');
        $otherOwner = $this->createOwner();

        try {
            $this->pay('3000.00', 'EFT-1', 'pay-other', $otherOwner);
            $this->fail('Another actor received the original payment.');
        } catch (ValidationException $exception) {
            $message = $exception->errors()['reference'][0];
            $this->assertStringNotContainsString('3000', $message);
            $this->assertStringNotContainsString($this->owner->name, $message);
        }
    }

    #[Test]
    public function the_same_key_with_different_details_is_a_conflict(): void
    {
        $this->pay('3000.00', 'EFT-1', 'pay-1');

        $this->expectException(IdempotencyConflictException::class);
        $this->pay('3000.00', 'EFT-2', 'pay-1');
    }

    #[Test]
    public function references_stay_reserved_after_reversal_and_replays_show_the_reversal(): void
    {
        $payment = $this->pay('3000.00', 'EFT-1', 'pay-1');
        app(ReverseSupplierPayment::class)($this->owner, $this->purchase, $payment->id, 'Bank returned the transfer');

        $replayed = $this->pay('3000.00', 'EFT-1', 'pay-after-reload');
        $this->assertSame($payment->id, $replayed->id);
        $this->assertTrue($replayed->isReversed());

        $this->expectException(ValidationException::class);
        $this->pay('1000.00', 'eft-1', 'pay-3');
    }

    #[Test]
    public function reversing_a_payment_restores_the_balance_with_an_opposite_entry_and_audit_details(): void
    {
        $payment = $this->pay('3000.00', 'EFT-1', 'pay-1');

        $result = app(ReverseSupplierPayment::class)($this->owner, $this->purchase, $payment->id, 'Entered twice');
        $payment->refresh();

        $this->assertTrue($result->applied);
        $this->assertSame('Entered twice', $payment->reversal_reason);
        $this->assertSame($this->owner->id, (int) $payment->reversed_by);
        $this->assertSame(817_500, $this->purchase->refresh()->outstandingMinor());
        $this->assertSame('3000.00', SupplierLedgerEntry::query()->where('type', SupplierLedgerEntryType::PAYMENT_REVERSED)->value('amount'));

        $this->assertFalse(app(ReverseSupplierPayment::class)($this->owner, $this->purchase, $payment->id, 'Again')->applied);
        $this->assertSame(3, SupplierLedgerEntry::count());
    }

    #[Test]
    public function a_payment_of_another_invoice_cannot_be_reversed_through_this_one(): void
    {
        $other = $this->postedPurchase($this->owner, $this->storeTwo, $this->supplier, [[$this->cable, 1, '5.00']], ['supplier_invoice_number' => 'OTHER-1']);
        $otherPayment = app(RecordSupplierPayment::class)($this->owner, $other, $this->paymentInput('5.00', 'CASH-1'), 'other-pay');

        $this->expectException(ModelNotFoundException::class);
        app(ReverseSupplierPayment::class)($this->owner, $this->purchase, $otherPayment->id, 'Wrong invoice');
    }

    #[Test]
    public function ledger_reconciles_with_open_invoices_after_payments_and_reversals_per_currency_and_store(): void
    {
        $paymentOne = $this->pay('3000.00', 'EFT-1', 'pay-1');
        $this->pay('1000.00', 'EFT-2', 'pay-2');
        app(ReverseSupplierPayment::class)($this->owner, $this->purchase, $paymentOne->id, 'Bounced');

        $usd = $this->postedPurchase($this->owner, $this->storeTwo, $this->supplier, [[$this->cable, 4, '2.25']], ['supplier_invoice_number' => 'USD-1'], Currency::USD);
        app(RecordSupplierPayment::class)($this->owner, $usd, $this->paymentInput('1.00', 'USD-PAY'), 'usd-pay');

        $reversed = $this->postedPurchase($this->owner, $this->storeTwo, $this->supplier, [[$this->cable, 1, '99.00']], ['supplier_invoice_number' => 'REV-1']);
        app(ReversePurchase::class)($this->owner, $reversed, 'Duplicate');

        $balances = app(SupplierAccount::class)->balances($this->supplier, [$this->storeOne->id, $this->storeTwo->id]);

        $this->assertSame(717_500, $balances['TRY']['balance']);
        $this->assertSame($balances['TRY']['balance'], $balances['TRY']['open_invoices']);
        $this->assertSame(0, $balances['TRY']['stores'][$this->storeTwo->id]['balance']);
        $this->assertSame(800, $balances['USD']['balance']);
        $this->assertSame(800, $balances['USD']['open_invoices']);
        $this->assertSame(717_500, $this->purchase->refresh()->outstandingMinor());

        $activePayments = $this->purchase->payments()->whereNull('reversed_at')->get()->sum(fn (SupplierPayment $payment) => $payment->amountMinor());
        $this->assertSame($this->purchase->totalMinor() - $activePayments, $this->purchase->outstandingMinor());
    }

    #[Test]
    public function balances_and_statements_include_only_the_given_stores(): void
    {
        $this->postedPurchase($this->owner, $this->storeTwo, $this->supplier, [[$this->cable, 1, '5.00']], ['supplier_invoice_number' => 'KT02-1']);

        $account = app(SupplierAccount::class);
        $balances = $account->balances($this->supplier, [$this->storeOne->id]);

        $this->assertSame(817_500, $balances['TRY']['balance']);
        $this->assertArrayNotHasKey($this->storeTwo->id, $balances['TRY']['stores']);
        $this->assertSame(1, $account->statement($this->supplier, Currency::TRY, [$this->storeOne->id])->total());
    }

    #[Test]
    public function running_balances_use_a_deterministic_order_for_entries_with_the_same_timestamp(): void
    {
        Carbon::setTestNow(now()->startOfSecond());
        $this->pay('3000.00', 'EFT-1', 'pay-1');
        $this->pay('1000.00', 'EFT-2', 'pay-2');

        $page = app(SupplierAccount::class)->statement($this->supplier, Currency::TRY, [$this->storeOne->id]);
        $rows = collect($page->items());

        $this->assertSame([3, 2, 1], $rows->map(fn ($entry) => $entry->id - SupplierLedgerEntry::min('id') + 1)->all());
        $this->assertSame(['4175.00', '5175.00', '8175.00'], $rows->pluck('running_balance')->map(fn ($value) => (string) $value)->all());
        Carbon::setTestNow();
    }

    #[Test]
    public function paying_requires_the_pay_permission(): void
    {
        $this->revokeFromOwners('pay_suppliers');

        $this->expectException(AuthorizationException::class);
        $this->pay('10.00', 'EFT-1', 'pay-1', $this->owner->fresh());
    }

    #[Test]
    public function money_input_rules_apply_to_payments(): void
    {
        foreach (['10.005', '0', 'abc'] as $amount) {
            try {
                $this->pay($amount, 'EFT-'.$amount, 'pay-'.$amount);
                $this->fail("Amount {$amount} was accepted.");
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('amount', $exception->errors());
            }
        }

        $this->assertSame(0, SupplierPayment::count());
    }
}
