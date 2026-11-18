@php
    use App\Services\Purchasing\LocalizedMoney;
    $link = 'inline-flex min-h-11 items-center rounded-lg px-3 text-sm font-medium text-blue-700 hover:bg-blue-50';
@endphp
<div dir="{{ $textDirection }}" lang="{{ $locale }}" class="mx-auto max-w-6xl space-y-6 text-start">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">{{ __('reports.margin.title') }}</h1>
            <p class="mt-1 text-sm text-gray-600">{{ __('reports.margin.subtitle') }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($canViewDashboard)
                <a href="{{ route('subdomain.operations.dashboard', ['from' => $from, 'to' => $to]) }}" class="{{ $link }} ring-1 ring-blue-200">{{ __('reports.navigation') }}</a>
            @endif
            @if ($canViewFinance)
                <a href="{{ route('subdomain.operations.finance', ['from' => $from, 'to' => $to]) }}" class="{{ $link }} ring-1 ring-blue-200">{{ __('reports.finance.navigation') }}</a>
            @endif
            @if ($canViewReview)
                <a href="{{ route('subdomain.operations.review', ['from' => $from, 'to' => $to]) }}" class="{{ $link }} ring-1 ring-blue-200">{{ __('reports.review.navigation') }}</a>
            @endif
            @if ($canViewAudit)
                <a href="{{ route('subdomain.operations.audit', ['from' => $from, 'to' => $to]) }}" class="{{ $link }} ring-1 ring-blue-200">{{ __('reports.audit.navigation') }}</a>
            @endif
        </div>
    </div>

    <div class="flex flex-wrap items-end gap-4">
        <x-operations.report-date-range id="margin-range" :range-error="$rangeError" />
        <div class="min-w-[12rem]">
            <label for="margin-store" class="block text-sm font-medium text-gray-700">{{ __('reports.margin.branch_filter') }}</label>
            <select id="margin-store" wire:model.live="storeId" class="mt-1 block min-h-11 w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">{{ __('reports.margin.branch_all') }}</option>
                @foreach ($stores as $store)
                    <option value="{{ $store->id }}">{{ $store->code }} — {{ $store->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <p class="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-950 ring-1 ring-amber-200">
        {{ __('reports.margin.scope_note') }}
    </p>

    @if ($rangeError)
        {{-- Date errors are shown by the shared range control. --}}
    @elseif ($branchFilterError)
        <p class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-900 ring-1 ring-red-200" role="alert">
            {{ __('reports.margin.errors.'.$branchFilterError) }}
        </p>
    @elseif ($summary === null)
        <p class="text-sm text-gray-600">{{ __('reports.margin.empty_period') }}</p>
    @else
        <section class="space-y-4" aria-labelledby="margin-lead">
            <h2 id="margin-lead" class="text-lg font-semibold text-gray-900">{{ __('reports.margin.lead_title') }}</h2>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                    <p class="text-sm text-gray-600">{{ __('reports.margin.recorded_sales') }}</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums text-gray-900"><bdi dir="ltr">{{ $summary['recorded_sales_count'] }}</bdi></p>
                    <p class="mt-1 text-sm tabular-nums text-gray-700"><bdi dir="ltr">{{ LocalizedMoney::format($summary['recorded_revenue_minor']) }}</bdi> TRY</p>
                </div>
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                    <p class="text-sm text-gray-600">{{ __('reports.margin.known_margin') }}</p>
                    @if ($summary['can_see_quantity_costs'] || $summary['can_see_device_costs'])
                        <p class="mt-1 text-2xl font-semibold tabular-nums text-gray-900"><bdi dir="ltr">{{ LocalizedMoney::format($summary['known_margin_minor']) }}</bdi> TRY</p>
                        <p class="mt-1 text-sm text-gray-600">{{ __('reports.margin.known_detail', [
                            'revenue' => LocalizedMoney::format($summary['visible_known_revenue_minor']),
                            'cost' => LocalizedMoney::format($summary['known_cost_minor']),
                            'lines' => $summary['known_line_count'],
                            'sales' => $summary['known_sale_count'],
                        ]) }}</p>
                        @if ($summary['restricted_known_revenue_minor'] > 0)
                            <p class="mt-1 text-sm text-amber-800">{{ __('reports.margin.restricted_partial', [
                                'amount' => LocalizedMoney::format($summary['restricted_known_revenue_minor']),
                            ]) }}</p>
                        @endif
                    @else
                        <p class="mt-1 text-sm text-gray-600">{{ __('reports.margin.restricted') }}</p>
                    @endif
                </div>
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                    <p class="text-sm text-gray-600">{{ __('reports.margin.unknown_revenue') }}</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums text-gray-900"><bdi dir="ltr">{{ LocalizedMoney::format($summary['unknown_revenue_minor']) }}</bdi> TRY</p>
                    <p class="mt-1 text-sm text-gray-600">{{ trans_choice('reports.margin.unknown_lines', $summary['unknown_line_count'], ['count' => $summary['unknown_line_count']]) }}</p>
                </div>
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                    <p class="text-sm text-gray-600">{{ __('reports.margin.network_excluded') }}</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums text-gray-900"><bdi dir="ltr">{{ LocalizedMoney::format($summary['network_revenue_minor']) }}</bdi> TRY</p>
                    <p class="mt-1 text-sm text-gray-600">{{ trans_choice('reports.margin.network_sales', $summary['network_sale_count'], ['count' => $summary['network_sale_count']]) }}</p>
                </div>
            </div>
            <p class="text-sm text-gray-600">{{ __('reports.margin.reconcile_note') }}</p>
        </section>

        <section
            class="space-y-3"
            aria-labelledby="margin-coverage"
            wire:key="margin-chart-{{ $from }}-{{ $to }}-{{ $storeId !== '' ? $storeId : 'all' }}-{{ $chartVersion }}"
        >
            <h2 id="margin-coverage" class="text-lg font-semibold text-gray-900">{{ __('reports.margin.coverage_title') }}</h2>
            <p class="text-sm text-gray-600">{{ __('reports.margin.coverage_note') }}</p>
            <div class="grid gap-4 lg:grid-cols-2">
                <div
                    class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200"
                    wire:ignore
                    x-data="salesMarginCoverageChart(@js($chartPayload))"
                >
                    <div class="relative h-48">
                        <canvas x-ref="coverageChart" aria-hidden="true"></canvas>
                    </div>
                </div>
                <ul class="space-y-2 text-sm text-gray-800">
                    <li>{{ __('reports.margin.coverage_known') }}: <bdi dir="ltr">{{ LocalizedMoney::format($summary['coverage']['known']) }}</bdi> TRY</li>
                    <li>{{ __('reports.margin.coverage_unknown') }}: <bdi dir="ltr">{{ LocalizedMoney::format($summary['coverage']['unknown']) }}</bdi> TRY</li>
                    <li>{{ __('reports.margin.coverage_network') }}: <bdi dir="ltr">{{ LocalizedMoney::format($summary['coverage']['network']) }}</bdi> TRY</li>
                    <li class="font-medium">{{ __('reports.margin.coverage_sum') }}: <bdi dir="ltr">{{ LocalizedMoney::format($summary['coverage']['sum']) }}</bdi> TRY</li>
                </ul>
            </div>
            @if ($summary['unknown_reasons'] !== [])
                <div class="rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-700">
                    <p class="font-medium">{{ __('reports.margin.unknown_reasons') }}</p>
                    <ul class="mt-2 list-disc ps-5">
                        @foreach ($summary['unknown_reasons'] as $reason => $count)
                            <li>{{ __('reports.margin.reasons.'.$reason) }} — {{ $count }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </section>

        <section class="space-y-3" aria-labelledby="margin-sales">
            <h2 id="margin-sales" class="text-lg font-semibold text-gray-900">{{ __('reports.margin.sales_list') }}</h2>
            @if ($rows === null || $rows->total() === 0)
                <p class="text-sm text-gray-600">{{ __('reports.margin.empty_period') }}</p>
            @else
                <div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-start text-gray-600">
                            <tr>
                                <th class="px-3 py-2 font-medium">{{ __('reports.margin.col_sale') }}</th>
                                <th class="px-3 py-2 font-medium">{{ __('reports.margin.col_branch') }}</th>
                                <th class="px-3 py-2 font-medium">{{ __('reports.margin.col_class') }}</th>
                                <th class="px-3 py-2 font-medium">{{ __('reports.margin.col_revenue') }}</th>
                                <th class="px-3 py-2 font-medium">{{ __('reports.margin.col_margin') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr class="border-t border-gray-100">
                                    <td class="px-3 py-2 tabular-nums">
                                        @if ($row['sale_url'])
                                            <a href="{{ $row['sale_url'] }}" class="text-blue-700 hover:underline"><bdi dir="ltr">{{ $row['number'] }}</bdi></a>
                                        @else
                                            <bdi dir="ltr">{{ $row['number'] }}</bdi>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2">{{ $row['store_code'] }}</td>
                                    <td class="px-3 py-2">{{ __('reports.margin.class.'.$row['classification']) }}</td>
                                    <td class="px-3 py-2 tabular-nums"><bdi dir="ltr">{{ LocalizedMoney::format($row['revenue_minor']) }}</bdi></td>
                                    <td class="px-3 py-2 tabular-nums">
                                        @if ($row['margin_minor'] === null)
                                            —
                                        @else
                                            <bdi dir="ltr">{{ LocalizedMoney::format($row['margin_minor']) }}</bdi>
                                        @endif
                                    </td>
                                </tr>
                                @if (($row['line_summaries'] ?? []) !== [])
                                    <tr class="border-t border-gray-50 bg-gray-50/60">
                                        <td colspan="5" class="px-3 py-2 text-xs text-gray-700">
                                            <ul class="space-y-2">
                                                @foreach ($row['line_summaries'] as $line)
                                                    <li>
                                                        <p>
                                                            {{ __('reports.margin.line_label', ['number' => $line['line_number']]) }}
                                                            — {{ $line['product_name'] }}
                                                            @if ($line['product_model']) ({{ $line['product_model'] }}) @endif
                                                            @if ($line['unit_identifier'])
                                                                · <bdi dir="ltr">{{ $line['unit_identifier'] }}</bdi>
                                                            @endif
                                                            — {{ __('reports.margin.line_status.'.$line['status']) }}
                                                            · <bdi dir="ltr">{{ LocalizedMoney::format($line['revenue_minor']) }}</bdi>
                                                            @if ($line['cost_minor'] !== null)
                                                                · {{ __('reports.margin.line_cost') }} <bdi dir="ltr">{{ LocalizedMoney::format($line['cost_minor']) }}</bdi>
                                                                · {{ __('reports.margin.line_margin') }} <bdi dir="ltr">{{ LocalizedMoney::format($line['margin_minor']) }}</bdi>
                                                            @elseif ($line['reason'] === 'restricted')
                                                                · {{ __('reports.margin.restricted') }}
                                                            @elseif ($line['reason'] !== null)
                                                                · {{ __('reports.margin.reasons.'.$line['reason']) }}
                                                            @endif
                                                        </p>
                                                        @if (($line['provenance']['type'] ?? null) === 'quantity')
                                                            <div class="mt-1 rounded-lg bg-white p-2 ring-1 ring-gray-200">
                                                                <p class="font-medium">{{ __('reports.margin.quantity_provenance') }}</p>
                                                                <ul class="mt-1 list-disc ps-4">
                                                                    <li>{{ __('reports.margin.pool_before', [
                                                                        'qty' => $line['provenance']['pool_quantity_before'] ?? '—',
                                                                        'total' => $line['provenance']['pool_cost_total_before'] ?? '—',
                                                                    ]) }}</li>
                                                                    <li>{{ __('reports.margin.removed_cost') }}:
                                                                        <bdi dir="ltr">{{ $line['provenance']['removed_cost'] ?? '—' }}</bdi>
                                                                    </li>
                                                                    @if ($line['provenance']['declaration'] !== null)
                                                                        <li>{{ __('reports.margin.declaration_evidence', [
                                                                            'qty' => $line['provenance']['declaration']['quantity'],
                                                                            'total' => $line['provenance']['declaration']['declared_total'],
                                                                            'reason' => $line['provenance']['declaration']['reason'],
                                                                        ]) }}</li>
                                                                    @else
                                                                        <li>{{ __('reports.margin.no_matching_declaration') }}</li>
                                                                    @endif
                                                                    @if ($line['provenance']['inventory_url'])
                                                                        <li><a href="{{ $line['provenance']['inventory_url'] }}" class="text-blue-700 hover:underline">{{ __('reports.margin.open_inventory') }}</a></li>
                                                                    @elseif ($line['provenance']['inventory_destination'] ?? null)
                                                                        <li>{{ __('reports.margin.destination_plain', ['destination' => $line['provenance']['inventory_destination']]) }}</li>
                                                                    @endif
                                                                </ul>
                                                            </div>
                                                        @elseif (($line['provenance']['type'] ?? null) === 'device')
                                                            <div class="mt-1 rounded-lg bg-white p-2 ring-1 ring-gray-200">
                                                                <p class="font-medium">{{ __('reports.margin.device_drilldown', ['number' => $line['line_number']]) }}</p>
                                                                <ul class="mt-1 list-disc ps-4">
                                                                    @foreach ($line['provenance']['snapshot'] as $fact)
                                                                        <li>
                                                                            {{ __('reports.margin.fact_snapshot') }}:
                                                                            {{ __('reports.margin.fact_inclusion.'.$fact['inclusion']) }}
                                                                            @if ($fact['category'])
                                                                                · {{ __('devices.cost_categories.'.$fact['category']) }}
                                                                            @endif
                                                                            @if ($fact['amount'] !== null)
                                                                                · <bdi dir="ltr">{{ $fact['amount'] }}</bdi> {{ $fact['currency'] }}
                                                                            @endif
                                                                            @if ($fact['note'] && $fact['note'] !== 'other_excluded' && $fact['note'] !== 'entry_cursor')
                                                                                · {{ __('reports.margin.fact_notes.'.$fact['note']) }}
                                                                            @elseif ($fact['note'] === 'other_excluded')
                                                                                · {{ __('reports.margin.fact_notes.other_excluded') }}
                                                                            @endif
                                                                        </li>
                                                                    @endforeach
                                                                    @foreach ($line['provenance']['later'] as $change)
                                                                        <li>
                                                                            {{ __('reports.margin.fact_later.'.$change['kind']) }}
                                                                            @if (($change['category'] ?? null))
                                                                                · {{ __('devices.cost_categories.'.$change['category']) }}
                                                                            @endif
                                                                            @if (($change['amount'] ?? null) !== null)
                                                                                · <bdi dir="ltr">{{ $change['amount'] }}</bdi> {{ $change['currency'] ?? '' }}
                                                                            @endif
                                                                        </li>
                                                                    @endforeach
                                                                    @if ($line['provenance']['unit_url'])
                                                                        <li><a href="{{ $line['provenance']['unit_url'] }}" class="text-blue-700 hover:underline">{{ __('reports.margin.open_device') }}</a></li>
                                                                    @elseif ($line['provenance']['unit_destination'] ?? null)
                                                                        <li>{{ __('reports.margin.destination_plain', ['destination' => $line['provenance']['unit_destination']]) }}</li>
                                                                    @endif
                                                                </ul>
                                                            </div>
                                                        @endif
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div>{{ $rows->links() }}</div>
            @endif
        </section>
    @endif
</div>

@script
<script>
Alpine.data('salesMarginCoverageChart', (payload) => ({
    payload,
    chart: null,
    init() {
        queueMicrotask(() => this.render());
        this._onResize = () => this.chart?.resize();
        window.addEventListener('resize', this._onResize);
    },
    destroy() {
        window.removeEventListener('resize', this._onResize);
        this.chart?.destroy();
        this.chart = null;
    },
    render() {
        if (typeof Chart === 'undefined' || ! this.$refs.coverageChart || ! this.payload) {
            return;
        }
        this.chart?.destroy();
        this.chart = new Chart(this.$refs.coverageChart, {
            type: 'doughnut',
            data: {
                labels: this.payload.labels,
                datasets: [{
                    data: this.payload.values,
                    backgroundColor: ['#1d4ed8', '#b45309', '#6b7280'],
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' },
                },
            },
        });
    },
}));
</script>
@endscript
