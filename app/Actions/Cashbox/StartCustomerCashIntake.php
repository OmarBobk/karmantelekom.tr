<?php

declare(strict_types=1);

namespace App\Actions\Cashbox;

use App\Enums\CashIntakeSource;
use App\Exceptions\Purchasing\InvalidAmountException;
use App\Models\CashIntake;
use App\Models\Customer;
use App\Models\Store;
use App\Models\User;
use App\Services\Customers\CustomerAccount;
use App\Services\Purchasing\LocalizedMoney;
use App\Services\Purchasing\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Bind a customer's cash payment to the open cashbox session before staff take the cash.
 *
 * The intake reserves its amount of the customer's balance: while it is pending, no other
 * payment can take that part, and the customer has no second pending intake. Recording the
 * cash payment for the same amount completes it.
 *
 * Lock order: the customer row (first statement), then the store row in share mode with its open session. The
 * request key makes a resent start return the same intake.
 */
class StartCustomerCashIntake
{
    public function __construct(private readonly CustomerAccount $account) {}

    /**
     * @param  string|null  $amount  Canonical decimal.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $actor, Store $store, Customer $customer, ?string $amount, string $requestKey): CashIntake
    {
        Gate::forUser($actor)->authorize('receiveCustomerPayments', $store);

        if ($customer->store_id !== $store->id) {
            throw new AuthorizationException;
        }

        if (! Str::isUuid($requestKey)) {
            throw ValidationException::withMessages(['amount' => __('pos.customer_accounts.errors.request_conflict')]);
        }

        try {
            $amountMinor = Money::parse((string) $amount, Money::MAX_TOTAL_AMOUNT);
        } catch (InvalidAmountException) {
            throw ValidationException::withMessages(['amount' => __('pos.customer_accounts.errors.amount_invalid')]);
        }

        return DB::transaction(function () use ($actor, $store, $customer, $amountMinor, $requestKey): CashIntake {
            Customer::query()->whereKey($customer->id)->lockForUpdate()->firstOrFail();
            $existing = CashIntake::query()->where('request_key', $requestKey)->first();

            if ($existing !== null) {
                if ($existing->customer_id !== $customer->id || $existing->started_by !== $actor->id || $existing->amountMinor() !== $amountMinor) {
                    throw ValidationException::withMessages(['amount' => __('pos.customer_accounts.errors.request_conflict')]);
                }

                return $existing;
            }

            if (CashIntake::query()->where('pending_source', CashIntake::customerSource($customer))->exists()) {
                throw ValidationException::withMessages(['cashbox' => __('pos.cashbox.errors.intake_already_pending')]);
            }

            $due = CustomerAccount::totalDueMinor($this->account->openDebts($customer));

            if ($amountMinor > $due) {
                throw ValidationException::withMessages(['amount' => $due === 0
                    ? __('pos.customer_accounts.errors.nothing_due')
                    : __('pos.customer_accounts.errors.amount_exceeds_due', ['due' => LocalizedMoney::format($due)])]);
            }

            return CashIntakePosting::bind($actor, $store, CashIntakeSource::CUSTOMER_PAYMENT, $customer->id, $amountMinor, $requestKey);
        });
    }
}
