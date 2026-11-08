<?php

declare(strict_types=1);

namespace App\Livewire\Pos;

use App\Actions\Network\SharedListings;
use App\Enums\UnitCondition;
use App\Livewire\Pos\Concerns\UsesPosLocale;
use App\Models\Store;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Store Network discovery: devices other branches have shared, as seen from this branch.
 *
 * Only the filters live in component state. Listing rows are rebuilt by SharedListings on
 * every request and passed to the view, so availability is always current.
 */
class NetworkListings extends Component
{
    use UsesPosLocale;
    use WithPagination;

    #[Locked]
    public int $storeId;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $condition = '';

    #[Url(as: 'branch', except: '')]
    public string $owningStore = '';

    public function mount(Store $store): void
    {
        $this->storeId = $store->id;
        $this->authorizeDiscovery();
    }

    public function applyFilters(): void
    {
        $this->search = mb_substr(Str::trim($this->search), 0, SharedListings::MAX_SEARCH_LENGTH);
        $this->resetPage();
    }

    public function updatedCondition(): void
    {
        $this->resetPage();
    }

    public function updatedOwningStore(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'condition', 'owningStore');
        $this->resetPage();
    }

    public function render(SharedListings $sharedListings): View
    {
        $store = $this->authorizeDiscovery();
        $otherStores = Store::query()->whereKeyNot($store->id)->orderBy('name')->get(['id', 'name', 'code']);
        $condition = UnitCondition::tryFrom($this->condition);
        $owningStoreId = $otherStores->firstWhere('id', (int) $this->owningStore)?->id;

        return view('livewire.pos.network-listings', [
            'store' => $store,
            'listings' => $sharedListings($store, $this->search, $condition, $owningStoreId),
            'otherStores' => $otherStores,
            'conditions' => UnitCondition::cases(),
            'filtered' => $this->search !== '' || $this->condition !== '' || $this->owningStore !== '',
            ...$this->localeViewData(),
        ])->layout('layouts.pos', ['posStore' => $store])->title(__('network.discovery.title'));
    }

    private function authorizeDiscovery(): Store
    {
        $store = Store::query()->findOrFail($this->storeId);
        $this->authorize('discoverListings', $store);

        return $store;
    }
}
