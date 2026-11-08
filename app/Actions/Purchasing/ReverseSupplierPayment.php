<?php

declare(strict_types=1);

namespace App\Actions\Purchasing;

use App\Enums\PurchaseStatus;
use App\Enums\SupplierLedgerEntryType;
use App\Models\Purchase;
use App\Models\SupplierLedgerEntry;
use App\Models\User;
use App\Services\Purchasing\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ReverseSupplierPayment
{
    /**
     * Reverse an active payment: the payment row stays, an opposite ledger entry restores the balance.
     * A payment handed out from a tracked cashbox is not reversed here, since its cash already left
     * the drawer; correcting it is a separate procedure.
     *
     * @throws AuthorizationException
     * @throws ModelNotFoundException when the payment does not belong to the purchase.
     * @throws ValidationException
     */
    public function __invoke(User $actor, Purchase $purchase, int $paymentId, ?string $reason): TransitionResult
    {
        Gate::forUser($actor)->authorize('pay', $purchase);

        $reason = ReversePurchase::prepareReason($reason);

        return DB::transaction(function () use ($actor, $purchase, $paymentId, $reason): TransitionResult {
            $locked = Purchase::query()->whereKey($purchase->id)->lockForUpdate()->firstOrFail();
            $payment = $locked->payments()->whereKey($paymentId)->lockForUpdate()->firstOrFail();

            if ($payment->isReversed()) {
                return new TransitionResult($payment, false);
            }

            if ($locked->status !== PurchaseStatus::POSTED) {
                throw ValidationException::withMessages(['reason' => __('purchases.errors.not_posted')]);
            }

            if ($payment->isFromCashbox()) {
                throw ValidationException::withMessages(['reason' => __('purchases.cash_payouts.errors.not_reversible')]);
            }

            $now = now();
            $payment->update([
                'reversed_by' => $actor->id,
                'reversed_at' => $now,
                'reversal_reason' => $reason,
            ]);

            $locked->update(['paid_total' => Money::toDecimal($locked->paidTotalMinor() - $payment->amountMinor())]);

            SupplierLedgerEntry::query()->create([
                'operational_supplier_id' => $locked->operational_supplier_id,
                'store_id' => $locked->store_id,
                'purchase_id' => $locked->id,
                'supplier_payment_id' => $payment->id,
                'currency' => $locked->currency,
                'type' => SupplierLedgerEntryType::PAYMENT_REVERSED,
                'amount' => Money::toDecimal($payment->amountMinor()),
                'user_id' => $actor->id,
                'occurred_at' => $now,
            ]);

            return new TransitionResult($payment, true);
        });
    }
}
