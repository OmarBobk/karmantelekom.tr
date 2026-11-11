<?php

declare(strict_types=1);

namespace App\Livewire\Backend\Reports;

use App\Enums\UnitStatus;
use App\Livewire\Backend\Concerns\UsesOperationalLocale;
use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use App\Services\Purchasing\Money;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Recorded POS sales for an Istanbul date range and current stock, either for every branch
 * the owner may see all sales in, or for one branch. Sales come only from completed Sale
 * records; payments, debt collections, and cashbox movements are not part of these totals.
 */
class OperationsDashboard extends Component
{
    use UsesOperationalLocale;

    #[Locked]
    public ?int $storeId = null;

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    public function mount(?Store $store = null): void
    {
        $this->authorize('viewOperationsDashboard', Store::class);

        if ($store?->exists) {
            $this->authorize('viewAllSales', $store);
            $this->storeId = $store->id;
        }

        $this->from = Str::trim($this->from);
        $this->to = Str::trim($this->to);

        if ($this->from === '' && $this->to === '') {
            $this->showToday();
        }
    }

    public function applyRange(): void
    {
        $this->from = Str::trim($this->from);
        $this->to = Str::trim($this->to);
    }

    public function showToday(): void
    {
        $this->from = $this->to = CarbonImmutable::now(Sale::DISPLAY_TIMEZONE)->toDateString();
    }

    #[Layout('layouts.backend')]
    public function render(): View
    {
        $this->authorize('viewOperationsDashboard', Store::class);

        $user = auth()->user();
        $stores = $this->stores($user);
        $rangeError = Sale::localRangeError($this->from, $this->to);
        $totals = $rangeError === null
            ? Sale::recordedTotalsByStore($stores->modelKeys(), Sale::localDate($this->from), Sale::localDate($this->to))
            : [];

        $rows = $stores->map(fn (Store $store): array => [
            'store' => $store,
            'count' => $totals[$store->id]['count'] ?? 0,
            'total_minor' => $totals[$store->id]['total_minor'] ?? 0,
        ]);

        return view('livewire.backend.reports.operations-dashboard', [
            'branch' => $this->storeId === null ? null : $stores->first(),
            'rows' => $rows,
            'rangeError' => $rangeError,
            'ownerCount' => $rows->sum('count'),
            'ownerTotalMinor' => Money::sumUncapped($rows->pluck('total_minor')),
            'inventory' => $this->inventory($stores->filter(fn (Store $store): bool => $user->can('viewInventory', $store))),
            ...$this->localeViewData(),
        ])->title($this->storeId === null
            ? __('reports.title')
            : $stores->first()->code.' — '.__('reports.title'));
    }

    /**
     * The branch shown, or every accessible branch where the user may see all sales.
     *
     * @return Collection<int, Store>
     */
    private function stores(User $user): Collection
    {
        if ($this->storeId !== null) {
            $store = Store::query()->findOrFail($this->storeId);
            $this->authorize('viewAllSales', $store);

            return new Collection([$store]);
        }

        return Store::query()
            ->accessibleTo($user)
            ->orderBy('code')
            ->get()
            ->filter(fn (Store $store): bool => $user->can('viewAllSales', $store))
            ->values();
    }

    /**
     * Current quantity on hand and available devices, with the inventory screen's definitions.
     *
     * @param  Collection<int, Store>  $stores
     * @return Collection<int, Store>
     */
    private function inventory(Collection $stores): Collection
    {
        if ($stores->isEmpty()) {
            return new Collection;
        }

        return Store::query()
            ->whereKey($stores->modelKeys())
            ->withSum('inventoryBalances as quantity_on_hand', 'quantity')
            ->withCount(['units as available_units_count' => fn ($units) => $units->where('status', UnitStatus::AVAILABLE)])
            ->orderBy('code')
            ->get();
    }
}
