<?php

declare(strict_types=1);

namespace App\Actions\Purchasing;

use App\Enums\PurchaseStatus;
use App\Enums\SupplierLedgerEntryType;
use App\Enums\SupplierPaymentMethod;
use App\Exceptions\Inventory\IdempotencyConflictException;
use App\Models\Purchase;
use App\Models\SupplierLedgerEntry;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Services\Idempotency;
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
     * Record a partial or full payment against one posted invoice, in the invoice currency.
     *
     * The operator's payment reference identifies the real-world payment: resubmitting the
     * same reference on the same invoice (with the old or a new key) returns the original
     * payment only for the same actor and identical details; anything else is rejected
     * without revealing the existing payment.
     *
     * @param  array{amount?: mixed, paid_on?: mixed, method?: mixed, reference?: mixed, note?: mixed}  $input
     *
     * @throws AuthorizationException
     * @throws ValidationException
     * @throws IdempotencyConflictException when the key was used for a different submission.
     */
    public function __invoke(User $actor, Purchase $purchase, array $input, string $idempotencyKey): SupplierPayment
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

        try {
            return DB::transaction(function () use ($actor, $purchase, $validated, $amount, $reference, $referenceKey, $note, $key, $fingerprint): SupplierPayment {
                $locked = Purchase::query()->whereKey($purchase->id)->lockForUpdate()->firstOrFail();

                $existing = $this->findExisting($locked->id, $key, $referenceKey);

                if ($existing !== null) {
                    return $this->replay($existing, $actor, $fingerprint, $key);
                }

                if ($locked->status !== PurchaseStatus::POSTED) {
                    throw ValidationException::withMessages(['amount' => __('purchases.errors.pay_requires_posted')]);
                }

                $outstanding = $locked->outstandingMinor();

                if ($amount > $outstanding) {
                    throw ValidationException::withMessages(['amount' => __('purchases.errors.amount_exceeds_outstanding', [
                        'outstanding' => Money::format($outstanding).' '.$locked->currency->value,
                    ])]);
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
            // The losing transaction rolled back; resolve against the committed payment.
            $existing = $this->findExisting($purchase->id, $key, $referenceKey);

            if ($existing === null) {
                throw $exception;
            }

            return $this->replay($existing, $actor, $fingerprint, $key);
        }
    }

    private function findExisting(int $purchaseId, string $key, string $referenceKey): ?SupplierPayment
    {
        return SupplierPayment::query()->where('idempotency_key', $key)->first()
            ?? SupplierPayment::query()->where('purchase_id', $purchaseId)->where('reference_key', $referenceKey)->first();
    }

    /**
     * @throws IdempotencyConflictException
     * @throws ValidationException
     */
    private function replay(SupplierPayment $existing, User $actor, string $fingerprint, string $key): SupplierPayment
    {
        if (Idempotency::matches($existing->user_id, $existing->request_fingerprint, $actor, $fingerprint)) {
            return $existing;
        }

        if ($existing->idempotency_key === $key) {
            throw new IdempotencyConflictException;
        }

        throw ValidationException::withMessages(['reference' => __('purchases.errors.reference_used')]);
    }
}
