<?php

declare(strict_types=1);

namespace App\Livewire\Pos;

use App\Livewire\Pos\Concerns\UsesPosLocale;
use App\Models\Sale;
use App\Models\Store;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Completed sales in one store: employees see their own, the owner sees all.
 * Search matches the sale number, a sold device identifier, a product, or a card reference.
 */
class SalesHistory extends Component
{
    use UsesPosLocale;
    use WithPagination;

    #[Locked]
    public int $storeId;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $date = '';

    public function mount(Store $store): void
    {
        $this->storeId = $store->id;
        $this->authorizeHistory();
    }

    public function applyFilters(): void
    {
        $this->search = mb_substr(Str::trim($this->search), 0, 80);
        $this->date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->date) === 1 ? $this->date : '';
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'date');
        $this->resetPage();
    }

    public function render(): View
    {
        $store = $this->authorizeHistory();

        return view('livewire.pos.sales-history', [
            'store' => $store,
            'sales' => $this->sales($store),
            'seesAll' => auth()->user()->can('viewAllSales', $store),
            ...$this->localeViewData(),
        ])->layout('layouts.pos', ['posStore' => $store])->title(__('pos.history.title'));
    }

    /**
     * @return LengthAwarePaginator<int, Sale>
     */
    private function sales(Store $store): LengthAwarePaginator
    {
        $term = Str::trim($this->search);
        $like = '%'.addcslashes($term, '%_\\').'%';

        return Sale::query()
            ->visibleTo(auth()->user(), $store)
            ->when($term !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('number', 'like', $like)
                ->orWhereHas('lines', fn ($lines) => $lines
                    ->where('unit_identifier', $term)
                    ->orWhereHas('unit.identifiers', fn ($identifiers) => $identifiers->where('value', mb_strtoupper($term)))
                    ->orWhere('product_name', 'like', $like)
                    ->orWhere('sku', $term)
                    ->orWhere('barcode', $term))
                ->orWhereHas('payments', fn ($payments) => $payments->where('terminal_reference', $term))))
            ->when($this->date !== '', function ($query): void {
                $day = CarbonImmutable::createFromFormat('Y-m-d', $this->date, Sale::DISPLAY_TIMEZONE)->startOfDay();
                $query->whereBetween('completed_at', [$day->utc(), $day->endOfDay()->utc()]);
            })
            ->with(['cashier:id,name', 'customer:id,name,phone_normalized', 'payments:id,sale_id,method,amount', 'debt:id,sale_id,original_amount'])
            ->withCount('lines')
            ->latest('completed_at')
            ->latest('id')
            ->paginate(25);
    }

    private function authorizeHistory(): Store
    {
        $store = Store::query()->findOrFail($this->storeId);
        $this->authorize('usePos', $store);

        return $store;
    }
}
