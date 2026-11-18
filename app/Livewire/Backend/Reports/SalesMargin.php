<?php

declare(strict_types=1);

namespace App\Livewire\Backend\Reports;

use App\Livewire\Backend\Concerns\UsesOperationalLocale;
use App\Livewire\Backend\Reports\Concerns\UsesReportDateRange;
use App\Models\Store;
use App\Models\User;
use App\Services\Reporting\LocalDateRange;
use App\Services\Reporting\SalesMarginReport;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Owner report: sales margin based on recorded costs, with unknown and Network coverage.
 */
class SalesMargin extends Component
{
    use UsesOperationalLocale;
    use UsesReportDateRange;
    use WithPagination;

    #[Url(as: 'store', except: '')]
    public string $storeId = '';

    public function mount(): void
    {
        $this->authorize('viewSalesMargin', Store::class);
        $this->bootReportDateRange();
        $this->storeId = Str::trim($this->storeId);
    }

    public function updatedStoreId(): void
    {
        $this->storeId = Str::trim($this->storeId);
        $this->resetPage();
    }

    protected function afterRangeChanged(): void
    {
        $this->resetPage();
    }

    #[Layout('layouts.backend')]
    public function render(): View
    {
        $this->authorize('viewSalesMargin', Store::class);

        $user = auth()->user();
        $stores = $this->authorizedStores($user);
        $this->assertSelectedStore($stores);

        $storeIds = $this->selectedStoreIds($stores);
        $canQuantity = [];
        $canDevice = [];

        foreach ($stores as $store) {
            if (! in_array($store->id, $storeIds, true)) {
                continue;
            }

            $canQuantity[$store->id] = $user->can('managePurchases', $store);
            $canDevice[$store->id] = $user->can('manageDeviceCosts', $store);
        }

        $rangeError = LocalDateRange::error($this->from, $this->to);
        $from = LocalDateRange::date($this->from);
        $to = LocalDateRange::date($this->to);
        $valid = $rangeError === null && $from !== null && $to !== null;

        $summary = $valid
            ? SalesMarginReport::summarize($user, $storeIds, $from, $to, $canQuantity, $canDevice)
            : null;

        $rows = $valid
            ? SalesMarginReport::paginateSales($user, $storeIds, $from, $to, $canQuantity, $canDevice, $this->getPage())
            : null;

        $chartPayload = $summary === null ? null : [
            'labels' => [
                __('reports.margin.coverage_known'),
                __('reports.margin.coverage_unknown'),
                __('reports.margin.coverage_network'),
            ],
            'values' => [
                $summary['coverage']['known'] / 100,
                $summary['coverage']['unknown'] / 100,
                $summary['coverage']['network'] / 100,
            ],
            'locale' => $this->localeViewData()['locale'],
        ];

        $chartVersion = $chartPayload === null
            ? 'none'
            : ($summary['chart_fingerprint'] ?? 'none');

        return view('livewire.backend.reports.sales-margin', [
            'stores' => $stores,
            'rangeError' => $rangeError,
            'summary' => $summary,
            'rows' => $rows,
            'chartPayload' => $chartPayload,
            'chartVersion' => $chartVersion,
            'canViewDashboard' => $user->can('viewOperationsDashboard', Store::class),
            'canViewFinance' => $user->can('viewFinanceOverview', Store::class),
            'canViewReview' => $user->can('viewOperationalReview', Store::class),
            'canViewAudit' => $user->can('viewAuditLog', Store::class),
            ...$this->localeViewData(),
        ])->title(__('reports.margin.title'));
    }

    /**
     * @return Collection<int, Store>
     */
    private function authorizedStores(User $user): Collection
    {
        return Store::query()
            ->accessibleTo($user)
            ->orderBy('code')
            ->get()
            ->filter(fn (Store $store): bool => $user->can('viewAllSales', $store)
                && ($user->can('managePurchases', $store) || $user->can('manageDeviceCosts', $store)))
            ->values();
    }

    /**
     * @param  Collection<int, Store>  $stores
     */
    private function assertSelectedStore(Collection $stores): void
    {
        if ($this->storeId === '') {
            return;
        }

        if (! ctype_digit($this->storeId) || ! $stores->contains(fn (Store $store): bool => $store->id === (int) $this->storeId)) {
            $this->storeId = '';
        }
    }

    /**
     * @param  Collection<int, Store>  $stores
     * @return list<int>
     */
    private function selectedStoreIds(Collection $stores): array
    {
        if ($this->storeId !== '' && ctype_digit($this->storeId)) {
            $id = (int) $this->storeId;
            $store = $stores->firstWhere('id', $id);

            if ($store !== null) {
                $this->authorize('viewAllSales', $store);

                return [$id];
            }
        }

        return $stores->modelKeys();
    }
}
