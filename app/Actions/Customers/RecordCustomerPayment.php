<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\Enums\Currency;
use App\Enums\CustomerPaymentMethod;
use App\Exceptions\Inventory\IdempotencyConflictException;
use App\Exceptions\Purchasing\InvalidAmountException;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\CustomerPaymentAllocation;
use App\Models\CustomerPaymentNumberSequence;
use App\Models\Store;
use App\Models\User;
use App\Services\Customers\CustomerAccount;
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

/**
 * Record a store customer's payment toward their open debts, allocated oldest sale first.
 *
 * Payments for one customer are serialized by locking the customer row as the transaction's
 * first statement, so balances read afterwards include every committed payment and debt. The
 * payment, its allocations, and any cash receipt number are written together; debts and sales
 * are never changed. The request key replays an identical submission by the same actor and
 * conflicts otherwise. A card or transfer reference already recorded for this store and method
 * returns that payment only for an identical submission.
 */
class RecordCustomerPayment
{
    public function __construct(private readonly CustomerAccount $account) {}

    /**
     * @param  array{amount?: mixed, method?: mixed, reference?: mixed, note?: mixed}  $input  The amount is a canonical decimal.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     * @throws IdempotencyConflictException when the key was used for a different submission.
     */
    public function __invoke(User $actor, Store $store, Customer $customer, array $input, string $idempotencyKey): CustomerPayment
    {
        Gate::forUser($actor)->authorize('receiveCustomerPayments', $store);

        if ($customer->store_id !== $store->id) {
            throw new AuthorizationException;
        }

        $key = Idempotency::key($idempotencyKey);

        $validated = Validator::make($input, [
            'amount' => ['required', 'string', 'max:32'],
            'method' => ['required', Rule::enum(CustomerPaymentMethod::class)],
            'reference' => ['nullable', 'string', 'max:'.CustomerPayment::MAX_REFERENCE_LENGTH],
            'note' => ['nullable', 'string', 'max:'.CustomerPayment::MAX_NOTE_LENGTH],
        ], [], [
            'amount' => __('pos.customer_accounts.fields.amount'),
            'method' => __('pos.customer_accounts.fields.method'),
            'reference' => __('pos.customer_accounts.fields.reference'),
            'note' => __('pos.customer_accounts.fields.note'),
        ])->validate();

        try {
            $amount = Money::parse($validated['amount'], Money::MAX_TOTAL_AMOUNT);
        } catch (InvalidAmountException) {
            throw ValidationException::withMessages(['amount' => __('pos.customer_accounts.errors.amount_invalid')]);
        }

        $method = CustomerPaymentMethod::from($validated['method']);
        $reference = $method->needsReference() ? Str::trim((string) ($validated['reference'] ?? '')) : '';
        $referenceKey = $method->needsReference() ? CustomerPayment::normalizeReference($reference) : null;

        if ($method->needsReference() && $referenceKey === null) {
            throw ValidationException::withMessages(['reference' => __('pos.customer_accounts.errors.reference_required')]);
        }

        $note = Str::trim((string) ($validated['note'] ?? '')) ?: null;

        $fingerprint = Idempotency::fingerprint([
            'operation' => 'customer_payment',
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'amount' => $amount,
            'method' => $method->value,
            'reference_key' => $referenceKey,
            'note' => $note,
        ]);

        try {
            return DB::transaction(function () use ($actor, $store, $customer, $amount, $method, $reference, $referenceKey, $note, $key, $fingerprint): CustomerPayment {
                Customer::query()->whereKey($customer->id)->lockForUpdate()->firstOrFail();

                $existing = $this->findExisting($store, $key, $method, $referenceKey);

                if ($existing !== null) {
                    return $this->replay($existing, $actor, $fingerprint, $key);
                }

                $openDebts = $this->account->openDebts($customer);
                $due = CustomerAccount::totalDueMinor($openDebts);

                if ($amount > $due) {
                    throw ValidationException::withMessages(['amount' => $due === 0
                        ? __('pos.customer_accounts.errors.nothing_due')
                        : __('pos.customer_accounts.errors.amount_exceeds_due', ['due' => LocalizedMoney::format($due)])]);
                }

                $now = now();

                $payment = CustomerPayment::query()->create([
                    'store_id' => $store->id,
                    'customer_id' => $customer->id,
                    'currency' => Currency::TRY,
                    'amount' => Money::toDecimal($amount),
                    'method' => $method,
                    'receipt_number' => $method === CustomerPaymentMethod::CASH ? CustomerPayment::formatReceiptNumber($store, $this->nextReceiptSequence($store)) : null,
                    'reference' => $referenceKey === null ? null : $reference,
                    'reference_key' => $referenceKey,
                    'note' => $note,
                    'user_id' => $actor->id,
                    'idempotency_key' => $key,
                    'request_fingerprint' => $fingerprint,
                    'received_at' => $now,
                ]);

                $remaining = $amount;

                foreach ($openDebts as $debt) {
                    if ($remaining === 0) {
                        break;
                    }

                    $applied = min($remaining, $debt->outstandingMinor());
                    $remaining -= $applied;

                    CustomerPaymentAllocation::query()->create([
                        'customer_payment_id' => $payment->id,
                        'customer_debt_id' => $debt->id,
                        'amount' => Money::toDecimal($applied),
                    ]);
                }

                return $payment;
            });
        } catch (UniqueConstraintViolationException $exception) {
            $existing = $this->findExisting($store, $key, $method, $referenceKey);

            if ($existing === null) {
                throw $exception;
            }

            return $this->replay($existing, $actor, $fingerprint, $key);
        }
    }

    private function findExisting(Store $store, string $key, CustomerPaymentMethod $method, ?string $referenceKey): ?CustomerPayment
    {
        return CustomerPayment::query()->where('idempotency_key', $key)->first()
            ?? ($referenceKey === null ? null : CustomerPayment::query()
                ->where('store_id', $store->id)
                ->where('method', $method)
                ->where('reference_key', $referenceKey)
                ->first());
    }

    /**
     * @throws IdempotencyConflictException
     * @throws ValidationException
     */
    private function replay(CustomerPayment $existing, User $actor, string $fingerprint, string $key): CustomerPayment
    {
        if (Idempotency::matches($existing->user_id, $existing->request_fingerprint, $actor, $fingerprint)) {
            return $existing;
        }

        if ($existing->idempotency_key === $key) {
            throw new IdempotencyConflictException;
        }

        throw ValidationException::withMessages(['reference' => __('pos.customer_accounts.errors.reference_used')]);
    }

    private function nextReceiptSequence(Store $store): int
    {
        CustomerPaymentNumberSequence::query()->insertOrIgnore([
            'store_id' => $store->id,
            'last_number' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sequence = CustomerPaymentNumberSequence::query()->whereKey($store->id)->lockForUpdate()->firstOrFail();
        $sequence->last_number++;
        $sequence->save();

        return $sequence->last_number;
    }
}
