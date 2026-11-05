<?php

declare(strict_types=1);

namespace App\Livewire\Backend\Purchases;

use App\Enums\Currency;
use App\Livewire\Backend\Concerns\UsesOperationalLocale;
use App\Models\OperationalSupplier;
use App\Models\Store;
use App\Services\Purchasing\SupplierAccount;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * A supplier's balance per currency across accessible stores, with a running-balance statement.
 */
class SupplierAccountPage extends Component
{
    use UsesOperationalLocale;
    use WithPagination;

    #[Locked]
    public int $supplierId;

    #[Url(except: '')]
    public string $currency = '';

    public function mount(OperationalSupplier $supplier): void
    {
        $this->authorize('viewAccount', $supplier);

        $this->supplierId = $supplier->id;
    }

    public function updatedCurrency(): void
    {
        $this->resetPage('entries');
    }

    #[Layout('layouts.backend')]
    public function render(): View
    {
        $account = app(SupplierAccount::class);
        $supplier = OperationalSupplier::query()->findOrFail($this->supplierId);
        $this->authorize('viewAccount', $supplier);

        $storeIds = $account->accessibleStoreIds(auth()->user());
        $balances = $account->balances($supplier, $storeIds);
        $available = array_map(fn (string $code): Currency => Currency::from($code), array_keys($balances));
        $selected = Currency::tryFrom($this->currency);
        $selected = in_array($selected, $available, true) ? $selected : ($available[0] ?? Currency::TRY);

        return view('livewire.backend.purchases.supplier-account', [
            'supplier' => $supplier,
            'balances' => $balances,
            'currencies' => $available,
            'selectedCurrency' => $selected,
            'entries' => $account->statement($supplier, $selected, $storeIds, pageName: 'entries'),
            'stores' => Store::query()->whereKey($storeIds)->get(['id', 'code', 'name'])->keyBy('id'),
            ...$this->localeViewData(),
        ])->title($supplier->name.' — '.__('purchases.account.title'));
    }
}
