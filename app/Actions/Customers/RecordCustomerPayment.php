<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\Actions\Cashbox\CashIntakePosting;
use App\Enums\CashMovementType;
use App\Enums\Currency;
use App\Enums\CustomerPaymentMethod;
use App\Exceptions\Inventory\IdempotencyConflictException;
use App\Exceptions\Purchasing\InvalidAmountException;
use App\Models\CashboxSession;
use App\Models\CashIntake;
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
 * payment, its allocations, any cash receipt number, and the cash movement are written
 * together; debts and sales are never changed. Once the store tracks its cashbox, cash needs the
 * customer's pending intake for the same amount, and the part of the balance a pending intake
 * reserves is not available to other payments. Lock order: the customer, the pending intake,
 * the receipt sequence, then the store row in share mode with its open cashbox session. The request key replays an identical submission by the same actor and
 * conflicts otherwise. A card or transfer reference already recorded for this store and method
 * returns that payment only for an identical submission.
 */
class RecordCustomerPayment
{
    public function __construct(private readonly CustomerAccount $account) {}

    /**
     * @param  array{amount?: mixed, method?: mixed, reference?: mixed, note?: mixed, cash_intake_id?: mixed}  $input  The amount is a canonical decimal;
     *                                                                                                                 cash_intake_id is the pending intake the screen displayed for a cash payment.
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
            'cash_intake_id' => ['nullable', 'integer'],
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
        $cashIntakeId = $method === CustomerPaymentMethod::CASH && isset($validated['cash_intake_id']) ? (int) $validated['cash_intake_id'] : null;

        $payload = [
            'operation' => 'customer_payment',
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'amount' => $amount,
            'method' => $method->value,
            'reference_key' => $referenceKey,
            'note' => $note,
        ];

        if ($cashIntakeId !== null) {
            $payload['cash_intake'] = $cashIntakeId;
        }

        $fingerprint = Idempotency::fingerprint($payload);

        try {
            return DB::transaction(function () use ($actor, $store, $customer, $amount, $method, $reference, $referenceKey, $note, $cashIntakeId, $key, $fingerprint): CustomerPayment {
                Customer::query()->whereKey($customer->id)->lockForUpdate()->firstOrFail();

                $existing = $this->findExisting($store, $key, $method, $referenceKey);

                if ($existing !== null) {
                    return $this->replay($existing, $actor, $fingerprint, $key);
                }

                $intake = $method === CustomerPaymentMethod::CASH
                    ? CashIntake::lockDisplayed(CashIntake::customerSource($customer), $cashIntakeId)
                    : CashIntake::query()->where('pending_source', CashIntake::customerSource($customer))->first();
                $openDebts = $this->account->openDebts($customer);
                $due = CustomerAccount::totalDueMinor($openDebts);
                $postsIntake = $method === CustomerPaymentMethod::CASH && $intake !== null;

                if ($method === CustomerPaymentMethod::CASH) {
                    self::assertCashBound($store, $intake, $amount);
                }

                $reserved = $intake !== null && ! $postsIntake ? $intake->amountMinor() : 0;

                if ($amount > $due - $reserved) {
                    throw ValidationException::withMessages(['amount' => match (true) {
                        $due === 0 => __('pos.customer_accounts.errors.nothing_due'),
                        $reserved > 0 => __('pos.cashbox.errors.amount_exceeds_unreserved', ['available' => LocalizedMoney::format(max(0, $due - $reserved)), 'reserved' => LocalizedMoney::format($reserved)]),
                        default => __('pos.customer_accounts.errors.amount_exceeds_due', ['due' => LocalizedMoney::format($due)]),
                    }]);
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

                if ($method === CustomerPaymentMethod::CASH && $intake === null && CashboxSession::lockTrackingStarted($store)) {
                    throw ValidationException::withMessages(['cashbox' => __('pos.cashbox.errors.cash_intake_required')]);
                }

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

                if ($postsIntake) {
                    CashIntakePosting::post($actor, $store, $intake, CashMovementType::CUSTOMER_PAYMENT_CASH, $amount, ['customer_payment_id' => $payment->id], $now);
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

    /**
     * Once a store tracks its cashbox, cash is recorded only against the customer's pending
     * intake for exactly that amount.
     *
     * @throws ValidationException
     */
    private static function assertCashBound(Store $store, ?CashIntake $intake, int $amount): void
    {
        if ($intake === null) {
            if (CashboxSession::trackingStarted($store)) {
                throw ValidationException::withMessages(['cashbox' => __('pos.cashbox.errors.cash_intake_required')]);
            }

            return;
        }

        if ($intake->amountMinor() !== $amount) {
            throw ValidationException::withMessages(['amount' => __('pos.cashbox.errors.intake_amount_mismatch', [
                'intake' => LocalizedMoney::format($intake->amountMinor()),
                'due' => LocalizedMoney::format($amount),
            ])]);
        }
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
