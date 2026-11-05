<?php

declare(strict_types=1);

namespace App\Livewire\Backend\Inventory;

use App\Enums\UnitStatus;
use App\Livewire\Backend\Concerns\UsesOperationalLocale;
use App\Models\Store;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Store picker for inventory. Lists only the stores the user may access.
 */
class InventoryStores extends Component
{
    use UsesOperationalLocale;

    public function mount(): void
    {
        $this->authorize('viewAnyInventory', Store::class);
    }

    #[Layout('layouts.backend')]
    public function render(): View
    {
        $this->authorize('viewAnyInventory', Store::class);

        return view('livewire.backend.inventory.inventory-stores', [
            'stores' => Store::query()
                ->accessibleTo(auth()->user())
                ->withSum('inventoryBalances as quantity_on_hand', 'quantity')
                ->withCount(['units as available_units_count' => fn ($units) => $units->where('status', UnitStatus::AVAILABLE)])
                ->orderBy('code')
                ->get(),
            ...$this->localeViewData(),
        ])->title(__('inventory.title'));
    }
}
