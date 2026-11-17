<?php

declare(strict_types=1);

namespace App\Livewire\Pos;

use App\Livewire\Pos\Concerns\UsesPosLocale;
use App\Models\Sale;
use App\Models\Store;
use App\Services\Reporting\LocalDateRange;
use App\Services\Reporting\SalesCsv;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as EmptyPaginator;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Completed sales in one store: employees see their own, the owner sees all.
 * Search matches the sale number, a sold device identifier, a product, or a card reference.
 *
 * The period is an inclusive range of Istanbul dates (from/to). The legacy single-day
 * ?date= link applies only when neither from nor to is given; a single bound means that day.
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
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    #[Url(except: '')]
    public string $date = '';

    public function mount(Store $store): void
    {
        $this->storeId = $store->id;
        $this->authorizeHistory();
        $this->normalizeRange();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'from', 'to', 'date'], true)) {
            $this->resetPage();
        }
    }

    public function applyFilters(): void
    {
        $this->search = mb_substr(Str::trim($this->search), 0, 80);
        $this->normalizeRange();
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'from', 'to', 'date');
        $this->resetPage();
    }

    public function render(): View
    {
        $store = $this->authorizeHistory();
        $seesAll = auth()->user()->can('viewAllSales', $store);
        [$from, $to] = $this->range();
        $rangeError = $from === '' ? null : LocalDateRange::error($from, $to);

        if ($rangeError !== null) {
            $sales = new EmptyPaginator([], 0, 25);
            $summary = null;
        } else {
            $filtered = SalesCsv::query(auth()->user(), $store, $this->search, $from, $to);
            $summary = $seesAll ? Sale::recordedSummary($filtered->clone()) : null;
            $sales = $this->page($filtered);
        }

        return view('livewire.pos.sales-history', [
            'store' => $store,
            'sales' => $sales,
            'summary' => $summary,
            'rangeError' => $rangeError,
            'seesAll' => $seesAll,
            'canExport' => $seesAll && $rangeError === null,
            'isFiltering' => Str::trim($this->search) !== '' || $from !== '',
            ...$this->localeViewData(),
        ])->layout('layouts.pos', ['posStore' => $store])->title(__('pos.history.title'));
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function range(): array
    {
        $from = Str::trim($this->from);
        $to = Str::trim($this->to);

        if ($from === '' && $to === '') {
            $legacyDate = Str::trim($this->date);

            return [$legacyDate, $legacyDate];
        }

        return [$from === '' ? $to : $from, $to === '' ? $from : $to];
    }

    private function normalizeRange(): void
    {
        [$this->from, $this->to] = $this->range();
        $this->date = '';
    }

    /**
     * @param  Builder<Sale>  $filtered
     * @return LengthAwarePaginator<int, Sale>
     */
    private function page(Builder $filtered): LengthAwarePaginator
    {
        return $filtered
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
