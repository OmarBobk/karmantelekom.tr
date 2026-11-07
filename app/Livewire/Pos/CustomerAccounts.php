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
 * Customers of one store who owe money, with the balance due of each. Owner only.
 */
class CustomerAccounts extends Component
{
    use UsesPosLocale;
    use WithPagination;

    #[Locked]
    public int $storeId;

    #[Url(as: 'q', except: '')]
    public string $search = '';

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

        return view('livewire.pos.customer-accounts', [
            'store' => $store,
            'debtors' => $account->debtors($store)
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
