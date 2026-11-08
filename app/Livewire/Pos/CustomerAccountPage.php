<?php

declare(strict_types=1);

namespace App\Livewire\Pos;

use App\Actions\Cashbox\StartCustomerCashIntake;
use App\Actions\Customers\RecordCustomerPayment;
use App\Enums\CustomerPaymentMethod;
use App\Exceptions\Inventory\IdempotencyConflictException;
use App\Exceptions\Purchasing\InvalidAmountException;
use App\Livewire\Pos\Concerns\UsesPosLocale;
use App\Models\CashboxSession;
use App\Models\CashIntake;
use App\Models\Customer;
use App\Models\Store;
use App\Services\Customers\CustomerAccount;
use App\Services\Purchasing\LocalizedMoney;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * One store customer's open debts, total due, and payment history, with a form to record a
 * payment. Owner only.
 *
 * The request key is issued with the form and kept through failures and retries, so a resent
 * submission replays the recorded payment; it is renewed only after a payment is confirmed.
 */
class CustomerAccountPage extends Component
{
    use UsesPosLocale;

    #[Locked]
    public int $storeId;

    #[Locked]
    public int $customerId;

    #[Locked]
    public string $requestKey = '';

    /** Request key of the next cash intake start; renewed after each confirmed start. */
    #[Locked]
    public string $cashRequestKey = '';

    /**
     * The pending cash intake this page shows. Confirming names it, so a released intake is
     * never swapped for a newer one, and a retry after a lost response replays the payment.
     */
    #[Locked]
    public ?int $displayedCashIntakeId = null;

    public string $amount = '';

    public string $method = 'cash';

    public string $reference = '';

    public string $note = '';

    public function mount(Store $store, Customer $customer): void
    {
        $this->storeId = $store->id;
        $this->customerId = $customer->id;
        $this->requestKey = (string) Str::uuid();
        $this->cashRequestKey = (string) Str::uuid();
        [, $customer] = $this->authorizeAccount();
        $this->displayedCashIntakeId = self::pendingIntakeId($customer);
    }

    public function record(RecordCustomerPayment $record, StartCustomerCashIntake $startCash): void
    {
        [$store, $customer] = $this->authorizeAccount();

        try {
            $amount = LocalizedMoney::toCanonical($this->amount);
        } catch (InvalidAmountException) {
            throw ValidationException::withMessages(['amount' => __('pos.errors.amount_format', ['example' => LocalizedMoney::EXAMPLE])]);
        }

        if ($this->method === CustomerPaymentMethod::CASH->value && CashboxSession::trackingStarted($store)) {
            try {
                $intake = $startCash(auth()->user(), $store, $customer, $amount, $this->cashRequestKey);
            } catch (ValidationException $exception) {
                $this->displayedCashIntakeId = self::pendingIntakeId($customer);

                throw $exception;
            }

            $this->cashRequestKey = (string) Str::uuid();
            $this->displayedCashIntakeId = $intake->isPending() ? $intake->id : self::pendingIntakeId($customer);
            $this->resetValidation();
            $this->notify('info', __('pos.cashbox.customer_intake_title', ['amount' => LocalizedMoney::format($intake->amountMinor())]));

            return;
        }

        $this->submit($record, $store, $customer, $amount, $this->method, null);
    }

    /**
     * Record the cash payment for the intake this page displayed, once the cash was taken. A
     * completed intake is still named, so a retry after a lost response replays its payment.
     */
    public function confirmCash(RecordCustomerPayment $record): void
    {
        [$store, $customer] = $this->authorizeAccount();
        $intake = $this->displayedCashIntakeId === null ? null : CashIntake::query()
            ->where('store_id', $store->id)
            ->where('customer_id', $customer->id)
            ->find($this->displayedCashIntakeId);

        if ($intake === null) {
            $this->displayedCashIntakeId = self::pendingIntakeId($customer);
            $this->notify('error', __('pos.cashbox.errors.intake_changed'));

            return;
        }

        $this->submit($record, $store, $customer, $intake->amount, CustomerPaymentMethod::CASH->value, $intake->id);
    }

    /**
     * @throws ValidationException
     */
    private function submit(RecordCustomerPayment $record, Store $store, Customer $customer, string $amount, string $method, ?int $cashIntakeId): void
    {
        try {
            $payment = $record(auth()->user(), $store, $customer, [
                'amount' => $amount,
                'method' => $method,
                'reference' => $this->reference,
                'note' => $this->note,
                'cash_intake_id' => $cashIntakeId,
            ], $this->requestKey);
        } catch (IdempotencyConflictException) {
            throw ValidationException::withMessages(['amount' => __('pos.customer_accounts.errors.request_conflict')]);
        } catch (ValidationException $exception) {
            if (array_key_exists('cashbox', $exception->errors())) {
                $this->displayedCashIntakeId = self::pendingIntakeId($customer);
            }

            throw $exception;
        }

        $this->displayedCashIntakeId = self::pendingIntakeId($customer);

        $this->reset('amount', 'reference', 'note');
        $this->method = CustomerPaymentMethod::CASH->value;
        $this->requestKey = (string) Str::uuid();
        $this->resetValidation();
        $this->notify('success', $payment->receipt_number === null
            ? __('pos.customer_accounts.recorded', ['amount' => LocalizedMoney::format($payment->amountMinor())])
            : __('pos.customer_accounts.recorded_cash', ['amount' => LocalizedMoney::format($payment->amountMinor()), 'number' => $payment->receipt_number]));
    }

    public function render(CustomerAccount $account): View
    {
        [$store, $customer] = $this->authorizeAccount();
        $openDebts = $account->openDebts($customer);
        $cashSessionOpen = CashboxSession::openFor($store) !== null;

        return view('livewire.pos.customer-account-page', [
            'store' => $store,
            'customer' => $customer,
            'openDebts' => $openDebts,
            'totalDue' => CustomerAccount::totalDueMinor($openDebts),
            'cashIntake' => CashIntake::query()->where('pending_source', CashIntake::customerSource($customer))->first(),
            'cashTracked' => $cashSessionOpen || CashboxSession::trackingStarted($store),
            'cashSessionOpen' => $cashSessionOpen,
            'payments' => $customer->payments()
                ->with(['allocations.debt.sale:id,number', 'receiver:id,name'])
                ->latest('received_at')
                ->latest('id')
                ->limit(50)
                ->get(),
            'methods' => CustomerPaymentMethod::cases(),
            ...$this->localeViewData(),
        ])->layout('layouts.pos', ['posStore' => $store])->title(__('pos.customer_accounts.account_title', ['name' => $customer->name]));
    }

    /**
     * @return array{0: Store, 1: Customer}
     */
    private function authorizeAccount(): array
    {
        $store = Store::query()->findOrFail($this->storeId);
        $this->authorize('receiveCustomerPayments', $store);

        return [$store, $store->customers()->findOrFail($this->customerId)];
    }

    private static function pendingIntakeId(Customer $customer): ?int
    {
        return CashIntake::query()->where('pending_source', CashIntake::customerSource($customer))->value('id');
    }

    private function notify(string $type, string $message): void
    {
        $this->dispatch('notify', ['type' => $type, 'message' => $message]);
    }
}
