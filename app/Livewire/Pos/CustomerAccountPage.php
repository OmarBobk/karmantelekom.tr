<?php

declare(strict_types=1);

namespace App\Livewire\Pos;

use App\Actions\Customers\RecordCustomerPayment;
use App\Enums\CustomerPaymentMethod;
use App\Exceptions\Inventory\IdempotencyConflictException;
use App\Exceptions\Purchasing\InvalidAmountException;
use App\Livewire\Pos\Concerns\UsesPosLocale;
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

    public string $amount = '';

    public string $method = 'cash';

    public string $reference = '';

    public string $note = '';

    public function mount(Store $store, Customer $customer): void
    {
        $this->storeId = $store->id;
        $this->customerId = $customer->id;
        $this->requestKey = (string) Str::uuid();
        $this->authorizeAccount();
    }

    public function record(RecordCustomerPayment $record): void
    {
        [$store, $customer] = $this->authorizeAccount();

        try {
            $amount = LocalizedMoney::toCanonical($this->amount);
        } catch (InvalidAmountException) {
            throw ValidationException::withMessages(['amount' => __('pos.errors.amount_format', ['example' => LocalizedMoney::EXAMPLE])]);
        }

        try {
            $payment = $record(auth()->user(), $store, $customer, [
                'amount' => $amount,
                'method' => $this->method,
                'reference' => $this->reference,
                'note' => $this->note,
            ], $this->requestKey);
        } catch (IdempotencyConflictException) {
            throw ValidationException::withMessages(['amount' => __('pos.customer_accounts.errors.request_conflict')]);
        }

        $this->reset('amount', 'reference', 'note');
        $this->method = CustomerPaymentMethod::CASH->value;
        $this->requestKey = (string) Str::uuid();
        $this->resetValidation();
        $this->dispatch('notify', ['type' => 'success', 'message' => $payment->receipt_number === null
            ? __('pos.customer_accounts.recorded', ['amount' => LocalizedMoney::format($payment->amountMinor())])
            : __('pos.customer_accounts.recorded_cash', ['amount' => LocalizedMoney::format($payment->amountMinor()), 'number' => $payment->receipt_number])]);
    }

    public function render(CustomerAccount $account): View
    {
        [$store, $customer] = $this->authorizeAccount();
        $openDebts = $account->openDebts($customer);

        return view('livewire.pos.customer-account-page', [
            'store' => $store,
            'customer' => $customer,
            'openDebts' => $openDebts,
            'totalDue' => CustomerAccount::totalDueMinor($openDebts),
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
}
