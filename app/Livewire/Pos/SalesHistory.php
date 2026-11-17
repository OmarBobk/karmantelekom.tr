<?php

declare(strict_types=1);

namespace App\Livewire\Pos;

use App\Livewire\Pos\Concerns\UsesPosLocale;
use App\Models\Sale;
use App\Models\Store;
use App\Services\Reporting\LocalDateRange;
use App\Services\Reporting\RecordedSalesReport;
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
 * An optional tender filter (cash, card, credit) lists sales that include that part.
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

    #[Url(except: '')]
    public string $tender = '';

    #[Url(as: 'from_at', except: '')]
    public string $fromAt = '';

    #[Url(as: 'to_at', except: '')]
    public string $toAt = '';

    public function mount(Store $store): void
    {
        $this->storeId = $store->id;
        $this->authorizeHistory();
        $this->normalizeRange();
        $this->normalizeInterval();
        $this->normalizeTender();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'from', 'to', 'date', 'tender', 'fromAt', 'toAt'], true)) {
            $this->resetPage();
        }
    }

    public function applyFilters(): void
    {
        $this->search = mb_substr(Str::trim($this->search), 0, 80);
        $this->normalizeRange();
        $this->normalizeInterval();
        $this->normalizeTender();
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'from', 'to', 'date', 'tender', 'fromAt', 'toAt');
        $this->resetPage();
    }

    public function render(): View
    {
        $store = $this->authorizeHistory();
        $seesAll = auth()->user()->can('viewAllSales', $store);
        [$from, $to] = $this->range();
        $fromAt = Str::trim($this->fromAt);
        $toAt = Str::trim($this->toAt);
        $intervalPresent = $fromAt !== '' || $toAt !== '';
        $intervalError = $intervalPresent ? LocalDateRange::intervalError($fromAt, $toAt) : null;
        $rangeError = $intervalError ?? ($from === '' ? null : LocalDateRange::error($from, $to));
        $tender = RecordedSalesReport::isTender($this->tender) ? $this->tender : '';

        if ($rangeError !== null) {
            $sales = new EmptyPaginator([], 0, 25);
            $summary = null;
        } else {
            $filtered = SalesCsv::query(auth()->user(), $store, $this->search, $from, $to, $tender, $fromAt, $toAt);
            $summary = $seesAll
                ? ($tender !== ''
                    ? RecordedSalesReport::tenderSummary($filtered->clone(), $tender)
                    : Sale::recordedSummary($filtered->clone()))
                : null;
            $sales = $this->page($filtered);
        }

        return view('livewire.pos.sales-history', [
            'store' => $store,
            'sales' => $sales,
            'summary' => $summary,
            'rangeError' => $rangeError,
            'seesAll' => $seesAll,
            'canExport' => $seesAll && $rangeError === null,
            'tender' => $tender,
            'fromAt' => $fromAt,
            'toAt' => $toAt,
            'isFiltering' => Str::trim($this->search) !== '' || $from !== '' || $tender !== '' || $intervalPresent,
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

    private function normalizeTender(): void
    {
        $this->tender = RecordedSalesReport::isTender(Str::trim($this->tender)) ? Str::trim($this->tender) : '';
    }

    private function normalizeInterval(): void
    {
        $this->fromAt = Str::trim($this->fromAt);
        $this->toAt = Str::trim($this->toAt);

        if ($this->fromAt === '' && $this->toAt === '') {
            return;
        }

        if (LocalDateRange::intervalError($this->fromAt, $this->toAt) !== null) {
            return;
        }
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
