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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReversePurchase
{
    public const MAX_REASON_LENGTH = 1000;

    /**
     * Reverse a posted invoice that has not been received and has no active payments.
     *
     * The posted facts stay; an opposite ledger entry cancels the obligation.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $actor, Purchase $purchase, ?string $reason): TransitionResult
    {
        Gate::forUser($actor)->authorize('update', $purchase);

        $reason = self::prepareReason($reason);

        return DB::transaction(function () use ($actor, $purchase, $reason): TransitionResult {
            $locked = Purchase::query()->whereKey($purchase->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === PurchaseStatus::REVERSED) {
                return new TransitionResult($locked, false);
            }

            if ($locked->status !== PurchaseStatus::POSTED) {
                throw ValidationException::withMessages(['reason' => __('purchases.errors.not_posted')]);
            }

            if ($locked->received_at !== null || $locked->receipt()->exists()) {
                throw ValidationException::withMessages(['reason' => __('purchases.errors.reverse_received')]);
            }

            if ($locked->paidTotalMinor() !== 0 || $locked->payments()->whereNull('reversed_at')->exists()) {
                throw ValidationException::withMessages(['reason' => __('purchases.errors.reverse_paid')]);
            }

            $now = now();
            $locked->update([
                'status' => PurchaseStatus::REVERSED,
                'reversed_by' => $actor->id,
                'reversed_at' => $now,
                'reversal_reason' => $reason,
            ]);

            SupplierLedgerEntry::query()->create([
                'operational_supplier_id' => $locked->operational_supplier_id,
                'store_id' => $locked->store_id,
                'purchase_id' => $locked->id,
                'currency' => $locked->currency,
                'type' => SupplierLedgerEntryType::PURCHASE_REVERSED,
                'amount' => Money::toDecimal(-$locked->totalMinor()),
                'user_id' => $actor->id,
                'occurred_at' => $now,
            ]);

            return new TransitionResult($locked, true);
        });
    }

    /**
     * @throws ValidationException
     */
    public static function prepareReason(?string $reason): string
    {
        $reason = Str::trim((string) $reason);

        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => __('operations.validation.required', ['attribute' => __('purchases.fields.reason')])]);
        }

        if (mb_strlen($reason) > self::MAX_REASON_LENGTH) {
            throw ValidationException::withMessages(['reason' => __('operations.validation.max', ['attribute' => __('purchases.fields.reason'), 'max' => self::MAX_REASON_LENGTH])]);
        }

        return $reason;
    }
}
