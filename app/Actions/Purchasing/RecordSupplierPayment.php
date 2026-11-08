<?php

declare(strict_types=1);

namespace App\Actions\Purchasing;

use App\Enums\CashPayoutStatus;
use App\Enums\Currency;
use App\Enums\PurchaseStatus;
use App\Enums\SupplierLedgerEntryType;
use App\Enums\SupplierPaymentMethod;
use App\Exceptions\Inventory\IdempotencyConflictException;
use App\Models\CashboxSession;
use App\Models\Purchase;
use App\Models\Store;
use App\Models\SupplierCashPayout;
use App\Models\SupplierLedgerEntry;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Services\Idempotency;
use App\Services\Purchasing\LocalizedMoney;
use App\Services\Purchasing\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RecordSupplierPayment
{
    /**
     * Submit a partial or full payment against one posted invoice, in the invoice currency.
     *
     * TRY cash from a branch whose cash tracking started is not recorded at once: it reserves the
     * amount as a pending supplier cash payout on the session the form was shown with, within the
     * drawer cash available and the invoice amount payable, and ConfirmSupplierCashPayout records
     * the payment once the cash is handed out. Every other payment, including TRY cash before the
     * branch's first session, is recorded at once as before. Pending payouts reduce the amount any
     * payment may cover.
     *
     * The operator's payment reference identifies the real-world payment: resubmitting the
     * same reference on the same invoice (with the old or a new key) returns the original
     * payment or pending payout only for the same actor and identical details; anything else is
     * rejected without revealing it. The key is checked against payments and payouts alike, and
     * replays come before any tracking or balance check, so old payments still replay.
     *
     * TRY cash locks the store row exclusively first, as opening, closing, and other payouts do,
     * then the invoice; other payments lock only the invoice.
     *
     * @param  array{amount?: mixed, paid_on?: mixed, method?: mixed, reference?: mixed, note?: mixed}  $input
     * @param  int|null  $sessionId  The open session the form was shown with; used for tracked TRY cash only.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     * @throws IdempotencyConflictException when the key was used for a different submission.
     */
    public function __invoke(User $actor, Purchase $purchase, array $input, string $idempotencyKey, ?int $sessionId = null): SupplierPayment|SupplierCashPayout
    {
        Gate::forUser($actor)->authorize('pay', $purchase);

        $key = Idempotency::key($idempotencyKey);

        $validated = Validator::make($input, [
            'amount' => ['required', 'string', 'max:32'],
            'paid_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'method' => ['required', Rule::enum(SupplierPaymentMethod::class)],
            'reference' => ['required', 'string', 'max:64'],
            'note' => ['nullable', 'string', 'max:1000'],
        ], PurchaseAttributes::messages(), PurchaseAttributes::header())->validate();

        $parser = new PurchaseInput;
        $amount = $parser->amount($validated['amount'], 'amount', __('purchases.fields.amount'));
        $parser->throwIfInvalid();

        $reference = Str::trim($validated['reference']);
        $referenceKey = SupplierPayment::normalizeReference($reference);

        if ($referenceKey === null) {
            throw ValidationException::withMessages(['reference' => __('operations.validation.required', ['attribute' => __('purchases.fields.reference')])]);
        }

        $note = Str::trim((string) ($validated['note'] ?? '')) ?: null;

        $fingerprint = Idempotency::fingerprint([
            'operation' => 'supplier_payment',
            'purchase_id' => $purchase->id,
            'amount' => $amount,
            'paid_on' => $validated['paid_on'],
            'method' => $validated['method'],
            'reference_key' => $referenceKey,
            'note' => $note,
        ]);

        $drawerCash = $purchase->currency === Currency::TRY && $validated['method'] === SupplierPaymentMethod::CASH->value;

        try {
            return DB::transaction(function () use ($actor, $purchase, $validated, $amount, $reference, $referenceKey, $note, $key, $fingerprint, $drawerCash, $sessionId): SupplierPayment|SupplierCashPayout {
                if ($drawerCash) {
                    Store::query()->whereKey($purchase->store_id)->lockForUpdate()->firstOrFail();
                }

                $locked = Purchase::query()->whereKey($purchase->id)->lockForUpdate()->firstOrFail();

                $existing = $this->findExisting($locked->id, $key, $referenceKey);

                if ($existing !== null) {
                    return $this->replay($existing, $actor, $fingerprint, $key);
                }

                if ($locked->status !== PurchaseStatus::POSTED) {
                    throw ValidationException::withMessages(['amount' => __('purchases.errors.pay_requires_posted')]);
                }

                $payable = $locked->payableMinor();

                if ($amount > $payable) {
                    throw ValidationException::withMessages(['amount' => __('purchases.errors.amount_exceeds_outstanding', [
                        'outstanding' => Money::format(max(0, $payable)).' '.$locked->currency->value,
                    ])]);
                }

                if ($drawerCash && CashboxSession::trackingStarted($locked->store)) {
                    return $this->reserve($actor, $locked, $sessionId, $amount, $validated['paid_on'], $reference, $referenceKey, $note, $key, $fingerprint);
                }

                $now = now();

                $payment = SupplierPayment::query()->create([
                    'purchase_id' => $locked->id,
                    'operational_supplier_id' => $locked->operational_supplier_id,
                    'store_id' => $locked->store_id,
                    'currency' => $locked->currency,
                    'amount' => Money::toDecimal($amount),
                    'paid_on' => $validated['paid_on'],
                    'method' => $validated['method'],
                    'reference' => $reference,
                    'reference_key' => $referenceKey,
                    'note' => $note,
                    'user_id' => $actor->id,
                    'idempotency_key' => $key,
                    'request_fingerprint' => $fingerprint,
                ]);

                $locked->update(['paid_total' => Money::toDecimal($locked->paidTotalMinor() + $amount)]);

                SupplierLedgerEntry::query()->create([
                    'operational_supplier_id' => $locked->operational_supplier_id,
                    'store_id' => $locked->store_id,
                    'purchase_id' => $locked->id,
                    'supplier_payment_id' => $payment->id,
                    'currency' => $locked->currency,
                    'type' => SupplierLedgerEntryType::PAYMENT,
                    'amount' => Money::toDecimal(-$amount),
                    'user_id' => $actor->id,
                    'occurred_at' => $now,
                ]);

                return $payment;
            });
        } catch (UniqueConstraintViolationException $exception) {
            // The losing transaction rolled back; resolve against the committed payment or payout.
            $existing = $this->findExisting($purchase->id, $key, $referenceKey);

            if ($existing === null) {
                throw $exception;
            }

            return $this->replay($existing, $actor, $fingerprint, $key);
        }
    }

    /**
     * @throws ValidationException
     */
    private function reserve(User $actor, Purchase $purchase, ?int $sessionId, int $amount, string $paidOn, string $reference, string $referenceKey, ?string $note, string $key, string $fingerprint): SupplierCashPayout
    {
        $session = CashboxSession::openFor($purchase->store);

        if ($session === null) {
            throw ValidationException::withMessages(['cashbox' => __('pos.cashbox.errors.no_open_session')]);
        }

        if ($session->id !== $sessionId) {
            throw ValidationException::withMessages(['cashbox' => __('purchases.cash_payouts.errors.session_changed')]);
        }

        $available = $session->availableForPayoutMinor();

        if ($amount > $available) {
            throw ValidationException::withMessages(['amount' => __('purchases.cash_payouts.errors.exceeds_available', ['available' => LocalizedMoney::format(max(0, $available))])]);
        }

        return SupplierCashPayout::query()->create([
            'store_id' => $purchase->store_id,
            'purchase_id' => $purchase->id,
            'cashbox_session_id' => $session->id,
            'amount' => Money::toDecimal($amount),
            'paid_on' => $paidOn,
            'reference' => $reference,
            'reference_key' => $referenceKey,
            'note' => $note,
            'request_key' => $key,
            'request_fingerprint' => $fingerprint,
            'status' => CashPayoutStatus::PENDING,
            'started_by' => $actor->id,
            'started_at' => now(),
        ]);
    }

    /**
     * The payment or payout this submission repeats: by key first, then by reference on the
     * invoice. A completed payout is found through its payment; a released one frees its
     * reference but keeps its key.
     */
    private function findExisting(int $purchaseId, string $key, string $referenceKey): SupplierPayment|SupplierCashPayout|null
    {
        return SupplierPayment::query()->where('idempotency_key', $key)->first()
            ?? SupplierCashPayout::query()->where('request_key', $key)->first()
            ?? SupplierPayment::query()->where('purchase_id', $purchaseId)->where('reference_key', $referenceKey)->first()
            ?? SupplierCashPayout::query()->pending()->where('purchase_id', $purchaseId)->where('reference_key', $referenceKey)->first();
    }

    /**
     * @throws IdempotencyConflictException
     * @throws ValidationException
     */
    private function replay(SupplierPayment|SupplierCashPayout $existing, User $actor, string $fingerprint, string $key): SupplierPayment|SupplierCashPayout
    {
        [$ownerId, $existingKey] = $existing instanceof SupplierPayment
            ? [$existing->user_id, $existing->idempotency_key]
            : [$existing->started_by, $existing->request_key];

        if (Idempotency::matches($ownerId, $existing->request_fingerprint, $actor, $fingerprint)) {
            return $existing;
        }

        if ($existingKey === $key) {
            throw new IdempotencyConflictException;
        }

        throw ValidationException::withMessages(['reference' => __('purchases.errors.reference_used')]);
    }
}
