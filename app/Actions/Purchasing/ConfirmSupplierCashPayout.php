<?php

declare(strict_types=1);

namespace App\Actions\Purchasing;

use App\Enums\CashMovementDirection;
use App\Enums\CashMovementType;
use App\Enums\CashPayoutStatus;
use App\Enums\PurchaseStatus;
use App\Enums\SupplierLedgerEntryType;
use App\Enums\SupplierPaymentMethod;
use App\Models\CashboxSession;
use App\Models\CashMovement;
use App\Models\Purchase;
use App\Models\Store;
use App\Models\SupplierCashPayout;
use App\Models\SupplierLedgerEntry;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Services\Purchasing\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * Confirm that a pending supplier cash payout was handed to the supplier: record its supplier
 * payment, the ledger entry, and one outgoing cash movement linked to both on its session, and
 * complete the payout, in one transaction.
 *
 * The confirmation names the payout and the session the screen displayed; a payout from another
 * session or invoice is refused, and a released payout is never replaced by another. Confirming
 * a completed payout again returns its payment without writing, so a retry after a lost response
 * is safe. The payment keeps the submission's key, fingerprint, and actor, so resubmitting the
 * original form also returns it. Locks the invoice, then the store row exclusively, then the
 * payout, as RecordSupplierPayment does.
 */
class ConfirmSupplierCashPayout
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $actor, Purchase $purchase, int $payoutId, ?int $sessionId): SupplierPayment
    {
        Gate::forUser($actor)->authorize('pay', $purchase);

        return DB::transaction(function () use ($actor, $purchase, $payoutId, $sessionId): SupplierPayment {
            $locked = Purchase::query()->whereKey($purchase->id)->lockForUpdate()->firstOrFail();
            Store::query()->whereKey($locked->store_id)->lockForUpdate()->firstOrFail();
            $payout = SupplierCashPayout::query()->where('purchase_id', $locked->id)->lockForUpdate()->findOrFail($payoutId);

            if ($payout->cashbox_session_id !== $sessionId) {
                throw ValidationException::withMessages(['cashbox' => __('purchases.cash_payouts.errors.payout_changed')]);
            }

            if ($payout->status === CashPayoutStatus::COMPLETED) {
                return $payout->payment()->firstOrFail();
            }

            if (! $payout->isPending()) {
                throw ValidationException::withMessages(['cashbox' => __('purchases.cash_payouts.errors.payout_not_pending')]);
            }

            $session = CashboxSession::openFor($locked->store);

            if ($session?->id !== $payout->cashbox_session_id) {
                throw new LogicException("Supplier cash payout {$payout->id} is pending on a session that is not open.");
            }

            if ($locked->status !== PurchaseStatus::POSTED || $payout->amountMinor() > $locked->outstandingMinor()) {
                throw new LogicException("Supplier cash payout {$payout->id} no longer fits its invoice.");
            }

            $now = now();

            $payment = SupplierPayment::query()->create([
                'purchase_id' => $locked->id,
                'operational_supplier_id' => $locked->operational_supplier_id,
                'store_id' => $locked->store_id,
                'currency' => $locked->currency,
                'amount' => $payout->amount,
                'paid_on' => $payout->paid_on->toDateString(),
                'method' => SupplierPaymentMethod::CASH,
                'reference' => $payout->reference,
                'reference_key' => $payout->reference_key,
                'note' => $payout->note,
                'user_id' => $payout->started_by,
                'idempotency_key' => $payout->request_key,
                'request_fingerprint' => $payout->request_fingerprint,
                'supplier_cash_payout_id' => $payout->id,
            ]);

            $locked->update(['paid_total' => Money::toDecimal($locked->paidTotalMinor() + $payout->amountMinor())]);

            SupplierLedgerEntry::query()->create([
                'operational_supplier_id' => $locked->operational_supplier_id,
                'store_id' => $locked->store_id,
                'purchase_id' => $locked->id,
                'supplier_payment_id' => $payment->id,
                'currency' => $locked->currency,
                'type' => SupplierLedgerEntryType::PAYMENT,
                'amount' => Money::toDecimal(-$payout->amountMinor()),
                'user_id' => $actor->id,
                'occurred_at' => $now,
            ]);

            CashMovement::query()->create([
                'store_id' => $locked->store_id,
                'cashbox_session_id' => $session->id,
                'type' => CashMovementType::SUPPLIER_PAYMENT_CASH,
                'direction' => CashMovementDirection::OUT,
                'amount' => $payout->amount,
                'supplier_cash_payout_id' => $payout->id,
                'supplier_payment_id' => $payment->id,
                'user_id' => $actor->id,
                'occurred_at' => $now,
            ]);

            $payout->update([
                'status' => CashPayoutStatus::COMPLETED,
                'completed_at' => $now,
            ]);

            return $payment;
        });
    }
}
