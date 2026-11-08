<?php

declare(strict_types=1);

namespace App\Livewire\Backend\Inventory;

use App\Actions\Inventory\CompleteInventorySetup;
use App\Enums\UnitStatus;
use App\Livewire\Backend\Concerns\UsesOperationalLocale;
use App\Models\InventoryBalance;
use App\Models\ProductDefinition;
use App\Models\Store;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Products with stock history in one store, plus a catalog search to start stocking a new one.
 */
class StoreInventory extends Component
{
    use UsesOperationalLocale;
    use WithPagination;

    #[Locked]
    public Store $store;

    #[Url(except: '')]
    public string $search = '';

    public string $catalogSearch = '';

    public function mount(Store $store): void
    {
        $this->authorize('viewInventory', $store);

        $this->store = $store;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function completeSetup(CompleteInventorySetup $completeInventorySetup): void
    {
        $this->authorize('adjustInventory', $this->store);

        $this->store = $completeInventorySetup(auth()->user(), $this->store);
        $this->dispatch('notify', ['type' => 'success', 'message' => __('inventory.setup.completed_message')]);
    }

    /**
     * Active definitions matching the name/model, or exactly matching a typed SKU or barcode.
     *
     * @return Collection<int, ProductDefinition>
     */
    private function catalogMatches(): Collection
    {
        $identifier = ProductDefinition::normalizeIdentifier($this->catalogSearch);

        if ($identifier === null) {
            return new Collection;
        }

        return ProductDefinition::query()
            ->active()
            ->where(fn (Builder $matches) => $matches
                ->search($identifier)
                ->orWhere('sku', $identifier)
                ->orWhere('barcode', $identifier))
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name', 'model', 'tracking_mode', 'sku', 'barcode']);
    }

    #[Layout('layouts.backend')]
    public function render(): View
    {
        $this->authorize('viewInventory', $this->store);

        $storeId = $this->store->id;

        $products = ProductDefinition::query()
            ->where(fn (Builder $stocked) => $stocked
                ->whereHas('balances', fn (Builder $balances) => $balances->where('store_id', $storeId))
                ->orWhereHas('units', fn (Builder $units) => $units->where('store_id', $storeId)))
            ->search($this->search)
            ->addSelect(['store_quantity' => InventoryBalance::query()
                ->select('quantity')
                ->whereColumn('product_definition_id', 'product_definitions.id')
                ->where('store_id', $storeId)
                ->limit(1)])
            ->withCount(['units as available_units_count' => fn (Builder $units) => $units
                ->where('store_id', $storeId)
                ->where('status', UnitStatus::AVAILABLE)])
            ->with(['brand:id,name', 'category:id,name'])
            ->orderBy('name')
            ->paginate(25);

        return view('livewire.backend.inventory.store-inventory', [
            'products' => $products,
            'catalogMatches' => $this->catalogMatches(),
            'isFiltering' => trim($this->search) !== '',
            'setupOpen' => $this->store->refresh()->inventorySetupIsOpen(),
            'canAdjust' => Gate::allows('adjustInventory', $this->store),
            ...$this->localeViewData(),
        ])->title($this->store->code.' — '.__('inventory.title'));
    }
}
