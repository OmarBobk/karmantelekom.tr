<?php

declare(strict_types=1);

namespace App\Livewire\Backend\Reports;

use App\Livewire\Backend\Concerns\UsesOperationalLocale;
use App\Livewire\Backend\Reports\Concerns\UsesReportDateRange;
use App\Models\Store;
use App\Services\Reporting\LocalDateRange;
use App\Services\Reporting\SalesMarginReport;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Owner report: sales margin based on recorded costs, with unknown and Network coverage.
 */
class SalesMargin extends Component
{
    use UsesOperationalLocale;
    use UsesReportDateRange;

    public function mount(): void
    {
        $this->authorize('viewSalesMargin', Store::class);
        $this->bootReportDateRange();
    }

    protected function afterRangeChanged(): void
    {
        //
    }

    #[Layout('layouts.backend')]
    public function render(): View
    {
        $this->authorize('viewSalesMargin', Store::class);

        $user = auth()->user();
        $stores = Store::query()
            ->accessibleTo($user)
            ->orderBy('code')
            ->get()
            ->filter(fn (Store $store): bool => $user->can('viewAllSales', $store)
                && ($user->can('managePurchases', $store) || $user->can('manageDeviceCosts', $store)))
            ->values();

        $rangeError = LocalDateRange::error($this->from, $this->to);
        $from = LocalDateRange::date($this->from);
        $to = LocalDateRange::date($this->to);

        $report = $rangeError === null && $from !== null && $to !== null
            ? SalesMarginReport::forStores($user, $stores->modelKeys(), $from, $to)
            : null;

        return view('livewire.backend.reports.sales-margin', [
            'stores' => $stores,
            'rangeError' => $rangeError,
            'report' => $report,
            'chartPayload' => $report === null ? null : [
                'labels' => [
                    __('reports.margin.coverage_known'),
                    __('reports.margin.coverage_unknown'),
                    __('reports.margin.coverage_network'),
                ],
                'values' => [
                    $report['coverage']['known'] / 100,
                    $report['coverage']['unknown'] / 100,
                    $report['coverage']['network'] / 100,
                ],
            ],
            'canViewDashboard' => $user->can('viewOperationsDashboard', Store::class),
            'canViewFinance' => $user->can('viewFinanceOverview', Store::class),
            'canViewReview' => $user->can('viewOperationalReview', Store::class),
            'canViewAudit' => $user->can('viewAuditLog', Store::class),
            ...$this->localeViewData(),
        ])->title(__('reports.margin.title'));
    }
}
