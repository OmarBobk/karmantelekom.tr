<?php

declare(strict_types=1);

namespace App\Livewire\Backend\Reports;

use App\Enums\UnitStatus;
use App\Livewire\Backend\Concerns\UsesOperationalLocale;
use App\Livewire\Backend\Reports\Concerns\UsesReportDateRange;
use App\Models\Store;
use App\Models\User;
use App\Services\Purchasing\LocalizedMoney;
use App\Services\Purchasing\Money;
use App\Services\Reporting\LocalDateRange;
use App\Services\Reporting\NeedsAttention;
use App\Services\Reporting\OutOfStock;
use App\Services\Reporting\RecordedSalesReport;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Recorded POS sales for an Istanbul date range and current stock, either for every branch
 * the owner may see all sales in, or for one branch. Sales come only from completed Sale
 * records; payments, debt collections, and cashbox movements are not part of these totals.
 */
class OperationsDashboard extends Component
{
    use UsesOperationalLocale;
    use UsesReportDateRange;

    #[Locked]
    public ?int $storeId = null;

    public function mount(?Store $store = null): void
    {
        $this->authorize('viewOperationsDashboard', Store::class);

        if ($store?->exists) {
            $this->authorize('viewAllSales', $store);
            $this->storeId = $store->id;
        }

        $this->bootReportDateRange();
    }

    #[Layout('layouts.backend')]
    public function render(): View
    {
        $this->authorize('viewOperationsDashboard', Store::class);

        $user = auth()->user();
        $stores = $this->stores($user);
        $rangeError = LocalDateRange::error($this->from, $this->to);
        $from = $rangeError === null ? LocalDateRange::date($this->from) : null;
        $to = $rangeError === null ? LocalDateRange::date($this->to) : null;

        $totals = $from !== null ? RecordedSalesReport::totalsByStore($stores->modelKeys(), $from, $to) : [];
        $previousTotals = [];
        $previousFrom = null;
        $previousTo = null;
        $composition = [];
        $series = null;
        $incomplete = false;

        if ($from !== null) {
            [$previousFrom, $previousTo] = LocalDateRange::previousEqual($from, $to);
            $previousTotals = RecordedSalesReport::totalsByStore($stores->modelKeys(), $previousFrom, $previousTo);
            $composition = RecordedSalesReport::compositionByStore($stores->modelKeys(), $from, $to);
            $series = RecordedSalesReport::series($stores->modelKeys(), $from, $to);
            $incomplete = LocalDateRange::includesToday($from, $to);
        }

        $rows = $stores->map(function (Store $store) use ($totals, $previousTotals, $composition): array {
            $current = $totals[$store->id] ?? ['count' => 0, 'total_minor' => 0];
            $previous = $previousTotals[$store->id] ?? ['count' => 0, 'total_minor' => 0];
            $parts = $composition[$store->id] ?? ['cash' => 0, 'card' => 0, 'credit' => 0, 'total' => 0, 'count' => 0];

            return [
                'store' => $store,
                'count' => $current['count'],
                'total_minor' => $current['total_minor'],
                'previous_count' => $previous['count'],
                'previous_total_minor' => $previous['total_minor'],
                'count_delta' => $current['count'] - $previous['count'],
                'total_delta' => $current['total_minor'] - $previous['total_minor'],
                'cash' => $parts['cash'],
                'card' => $parts['card'],
                'credit' => $parts['credit'],
            ];
        });

        $ownerCount = $rows->sum('count');
        $ownerTotalMinor = Money::sumUncapped($rows->pluck('total_minor'));
        $ownerPreviousCount = $rows->sum('previous_count');
        $ownerPreviousTotalMinor = Money::sumUncapped($rows->pluck('previous_total_minor'));
        $ownerComposition = RecordedSalesReport::sumComposition(
            $rows->mapWithKeys(fn (array $row): array => [$row['store']->id => [
                'cash' => $row['cash'],
                'card' => $row['card'],
                'credit' => $row['credit'],
                'total' => $row['total_minor'],
                'count' => $row['count'],
            ]])->all()
        );

        $chartPayload = $rangeError === null ? $this->chartPayload($rows, $series, $from, $to) : null;

        return view('livewire.backend.reports.operations-dashboard', [
            'branch' => $this->storeId === null ? null : $stores->first(),
            'rows' => $rows,
            'rangeError' => $rangeError,
            'previousFrom' => $previousFrom?->toDateString(),
            'previousTo' => $previousTo?->toDateString(),
            'incomplete' => $incomplete,
            'ownerCount' => $ownerCount,
            'ownerTotalMinor' => $ownerTotalMinor,
            'ownerPreviousCount' => $ownerPreviousCount,
            'ownerPreviousTotalMinor' => $ownerPreviousTotalMinor,
            'ownerCountDelta' => $ownerCount - $ownerPreviousCount,
            'ownerTotalDelta' => $ownerTotalMinor - $ownerPreviousTotalMinor,
            'ownerComposition' => $ownerComposition,
            'series' => $series,
            'chartPayload' => $chartPayload,
            'attention' => NeedsAttention::for($user, $stores),
            'inventory' => $this->inventory($stores->filter(fn (Store $store): bool => $user->can('viewInventory', $store))),
            'canViewFinance' => $user->can('viewFinanceOverview', Store::class),
            'canViewReview' => $user->can('viewOperationalReview', Store::class),
            'canViewAudit' => $user->can('viewAuditLog', Store::class),
            ...$this->localeViewData(),
        ])->title($this->storeId === null
            ? __('reports.title')
            : $stores->first()->code.' — '.__('reports.title'));
    }

    /**
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
     * @param  Collection<int, Store>  $stores
     * @return Collection<int, Store>
     */
    private function inventory(Collection $stores): Collection
    {
        if ($stores->isEmpty()) {
            return new Collection;
        }

        $storeIds = $stores->modelKeys();
        $outOfStock = OutOfStock::countsByStore($storeIds);

        return Store::query()
            ->whereKey($storeIds)
            ->withSum('inventoryBalances as quantity_on_hand', 'quantity')
            ->withCount(['units as available_units_count' => fn ($units) => $units->where('status', UnitStatus::AVAILABLE)])
            ->orderBy('code')
            ->get()
            ->each(function (Store $store) use ($outOfStock): void {
                $store->setAttribute('out_of_stock_count', $outOfStock[$store->id] ?? 0);
            });
    }

    /**
     * Authorized chart series only — no hidden storefront or profit datasets.
     *
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $rows
     * @param  array{granularity: string, buckets: list<array<string, mixed>>}|null  $series
     * @return array{time: array<string, mixed>, branches: array<string, mixed>}
     */
    private function chartPayload($rows, ?array $series, $from, $to): array
    {
        $branchLabels = $rows->map(fn (array $row): string => $row['store']->code)->values()->all();
        $branchTotals = $rows->map(fn (array $row): float => round($row['total_minor'] / 100, 2))->values()->all();
        $branchLinks = $rows->map(fn (array $row): string => route('pos.sales', [
            $row['store'],
            'from' => $this->from,
            'to' => $this->to,
        ]))->values()->all();

        $timeLabels = [];
        $timeTotals = [];
        $timeLinks = [];

        foreach ($series['buckets'] ?? [] as $bucket) {
            $timeLabels[] = $bucket['label'];
            $timeTotals[] = round($bucket['total_minor'] / 100, 2);
            $timeLinks[] = $this->storeId === null
                ? route('subdomain.operations.dashboard', ['from' => $bucket['start'], 'to' => $bucket['end_date']])
                : route('pos.sales', [$rows->first()['store'], 'from' => $bucket['start'], 'to' => $bucket['end_date']]);
        }

        return [
            'locale' => $this->localeViewData()['locale'],
            'direction' => $this->localeViewData()['textDirection'],
            'currency' => 'TRY',
            'recordedLabel' => __('reports.sales.total'),
            'notProfit' => __('reports.sales.chart_not_profit'),
            'granularity' => $series['granularity'] ?? LocalDateRange::GRANULARITY_DAY,
            'granularityLabel' => __('reports.sales.granularity.'.($series['granularity'] ?? LocalDateRange::GRANULARITY_DAY)),
            'singleDay' => $from !== null && $from->equalTo($to),
            'time' => [
                'labels' => $timeLabels,
                'totals' => $timeTotals,
                'links' => $timeLinks,
                'formatted' => array_map(
                    fn (array $bucket): string => LocalizedMoney::format($bucket['total_minor']).' TRY',
                    $series['buckets'] ?? []
                ),
            ],
            'branches' => [
                'labels' => $branchLabels,
                'totals' => $branchTotals,
                'links' => $branchLinks,
                'formatted' => $rows->map(fn (array $row): string => LocalizedMoney::format($row['total_minor']).' TRY')->values()->all(),
            ],
        ];
    }
}
