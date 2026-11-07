<?php

declare(strict_types=1);

namespace App\Livewire\Pos;

use App\Livewire\Pos\Concerns\UsesPosLocale;
use App\Models\Store;
use App\Services\Customers\CustomerAccount;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Customers of one store who owe money, with the balance due of each, or those whose debts there
 * are fully paid so their payment history stays reachable. Owner only.
 */
class CustomerAccounts extends Component
{
    use UsesPosLocale;
    use WithPagination;

    #[Locked]
    public int $storeId;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    /** Customers who still owe ('open', the default) or who had debts and paid them all ('settled'). */
    #[Url(except: 'open')]
    public string $balance = 'open';

    public function showBalance(string $balance): void
    {
        $this->balance = $balance === 'settled' ? 'settled' : 'open';
        $this->resetPage();
    }

    public function mount(Store $store): void
    {
        $this->storeId = $store->id;
        $this->authorizeAccounts();
    }

    public function applySearch(): void
    {
        $this->search = mb_substr(Str::squish($this->search), 0, 80);
        $this->resetPage();
    }

    public function clearSearch(): void
    {
        $this->reset('search');
        $this->resetPage();
    }

    public function render(CustomerAccount $account): View
    {
        $store = $this->authorizeAccounts();
        $term = Str::squish($this->search);
        $settled = $this->balance === 'settled';

        return view('livewire.pos.customer-accounts', [
            'store' => $store,
            'settled' => $settled,
            'debtors' => ($settled ? $account->settled($store) : $account->debtors($store))
                ->when($term !== '', fn ($query) => $query->where('customers.name', 'like', '%'.addcslashes($term, '%_\\').'%'))
                ->orderBy('customers.name')
                ->orderBy('customers.id')
                ->paginate(25),
            ...$this->localeViewData(),
        ])->layout('layouts.pos', ['posStore' => $store])->title(__('pos.customer_accounts.title'));
    }

    private function authorizeAccounts(): Store
    {
        $store = Store::query()->findOrFail($this->storeId);
        $this->authorize('receiveCustomerPayments', $store);

        return $store;
    }
}
