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

    <x-operations.report-date-range id="margin-range" :range-error="$rangeError" />

    <p class="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-950 ring-1 ring-amber-200">
        {{ __('reports.margin.scope_note') }}
    </p>

    @if ($rangeError)
        {{-- Date errors are shown by the shared range control. --}}
    @elseif ($report === null)
        <p class="text-sm text-gray-600">{{ __('reports.margin.empty_period') }}</p>
    @else
        <section class="space-y-4" aria-labelledby="margin-lead">
            <h2 id="margin-lead" class="text-lg font-semibold text-gray-900">{{ __('reports.margin.lead_title') }}</h2>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                    <p class="text-sm text-gray-600">{{ __('reports.margin.recorded_sales') }}</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums text-gray-900"><bdi dir="ltr">{{ $report['recorded_sales_count'] }}</bdi></p>
                    <p class="mt-1 text-sm tabular-nums text-gray-700"><bdi dir="ltr">{{ LocalizedMoney::format($report['recorded_revenue_minor']) }}</bdi> TRY</p>
                </div>
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                    <p class="text-sm text-gray-600">{{ __('reports.margin.known_margin') }}</p>
                    @if ($report['can_see_quantity_costs'] || $report['can_see_device_costs'])
                        <p class="mt-1 text-2xl font-semibold tabular-nums text-gray-900"><bdi dir="ltr">{{ LocalizedMoney::format($report['known_margin_minor']) }}</bdi> TRY</p>
                        <p class="mt-1 text-sm text-gray-600">{{ __('reports.margin.known_detail', [
                            'revenue' => LocalizedMoney::format($report['visible_known_revenue_minor']),
                            'cost' => LocalizedMoney::format($report['known_cost_minor']),
                            'lines' => $report['known_line_count'],
                            'sales' => $report['known_sale_count'],
                        ]) }}</p>
                        @if ($report['restricted_known_revenue_minor'] > 0)
                            <p class="mt-1 text-sm text-amber-800">{{ __('reports.margin.restricted_partial', [
                                'amount' => LocalizedMoney::format($report['restricted_known_revenue_minor']),
                            ]) }}</p>
                        @endif
                    @else
                        <p class="mt-1 text-sm text-gray-600">{{ __('reports.margin.restricted') }}</p>
                    @endif
                </div>
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                    <p class="text-sm text-gray-600">{{ __('reports.margin.unknown_revenue') }}</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums text-gray-900"><bdi dir="ltr">{{ LocalizedMoney::format($report['unknown_revenue_minor']) }}</bdi> TRY</p>
                    <p class="mt-1 text-sm text-gray-600">{{ trans_choice('reports.margin.unknown_lines', $report['unknown_line_count'], ['count' => $report['unknown_line_count']]) }}</p>
                </div>
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                    <p class="text-sm text-gray-600">{{ __('reports.margin.network_excluded') }}</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums text-gray-900"><bdi dir="ltr">{{ LocalizedMoney::format($report['network_revenue_minor']) }}</bdi> TRY</p>
                    <p class="mt-1 text-sm text-gray-600">{{ trans_choice('reports.margin.network_sales', $report['network_sale_count'], ['count' => $report['network_sale_count']]) }}</p>
                </div>
            </div>
            <p class="text-sm text-gray-600">{{ __('reports.margin.reconcile_note') }}</p>
        </section>

        <section class="space-y-3" aria-labelledby="margin-coverage" wire:key="margin-chart-{{ $from }}-{{ $to }}-{{ $report['coverage']['sum'] }}">
            <h2 id="margin-coverage" class="text-lg font-semibold text-gray-900">{{ __('reports.margin.coverage_title') }}</h2>
            <p class="text-sm text-gray-600">{{ __('reports.margin.coverage_note') }}</p>
            <div class="grid gap-4 lg:grid-cols-2">
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                    <canvas id="margin-coverage-chart" height="180" aria-hidden="true"></canvas>
                </div>
                <ul class="space-y-2 text-sm text-gray-800">
                    <li>{{ __('reports.margin.coverage_known') }}: <bdi dir="ltr">{{ LocalizedMoney::format($report['coverage']['known']) }}</bdi> TRY</li>
                    <li>{{ __('reports.margin.coverage_unknown') }}: <bdi dir="ltr">{{ LocalizedMoney::format($report['coverage']['unknown']) }}</bdi> TRY</li>
                    <li>{{ __('reports.margin.coverage_network') }}: <bdi dir="ltr">{{ LocalizedMoney::format($report['coverage']['network']) }}</bdi> TRY</li>
                    <li class="font-medium">{{ __('reports.margin.coverage_sum') }}: <bdi dir="ltr">{{ LocalizedMoney::format($report['coverage']['sum']) }}</bdi> TRY</li>
                </ul>
            </div>
            @if ($report['unknown_reasons'] !== [])
                <div class="rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-700">
                    <p class="font-medium">{{ __('reports.margin.unknown_reasons') }}</p>
                    <ul class="mt-2 list-disc ps-5">
                        @foreach ($report['unknown_reasons'] as $reason => $count)
                            <li>{{ __('reports.margin.reasons.'.$reason) }} — {{ $count }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </section>

        <section class="space-y-3" aria-labelledby="margin-sales">
            <h2 id="margin-sales" class="text-lg font-semibold text-gray-900">{{ __('reports.margin.sales_list') }}</h2>
            @if ($report['rows'] === [])
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
                            @foreach ($report['rows'] as $row)
                                <tr class="border-t border-gray-100">
                                    <td class="px-3 py-2 tabular-nums"><bdi dir="ltr">{{ $row['number'] }}</bdi></td>
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
                                            <ul class="space-y-1">
                                                @foreach ($row['line_summaries'] as $line)
                                                    <li>
                                                        {{ __('reports.margin.line_label', ['number' => $line['line_number']]) }}
                                                        — {{ __('reports.margin.line_status.'.$line['status']) }}
                                                        · <bdi dir="ltr">{{ LocalizedMoney::format($line['revenue_minor']) }}</bdi>
                                                        @if ($line['cost_minor'] !== null)
                                                            · {{ __('reports.margin.line_cost') }} <bdi dir="ltr">{{ LocalizedMoney::format($line['cost_minor']) }}</bdi>
                                                            · {{ __('reports.margin.line_margin') }} <bdi dir="ltr">{{ LocalizedMoney::format($line['margin_minor']) }}</bdi>
                                                        @elseif ($line['reason'] !== null && $line['reason'] !== 'restricted')
                                                            · {{ __('reports.margin.reasons.'.$line['reason']) }}
                                                        @elseif ($line['reason'] === 'restricted')
                                                            · {{ __('reports.margin.restricted') }}
                                                        @endif
                                                    </li>
                                                @endforeach
                                            </ul>
                                            @foreach (($row['device_drilldowns'] ?? []) as $lineNumber => $drilldown)
                                                <div class="mt-2 rounded-lg bg-white p-2 ring-1 ring-gray-200">
                                                    <p class="font-medium">{{ __('reports.margin.device_drilldown', ['number' => $lineNumber]) }}</p>
                                                    <ul class="mt-1 list-disc ps-4">
                                                        @foreach ($drilldown['snapshot'] as $fact)
                                                            <li>
                                                                {{ __('reports.margin.fact_snapshot') }}:
                                                                {{ __('reports.margin.fact_inclusion.'.$fact['inclusion']) }}
                                                                @if ($fact['category']) · {{ $fact['category'] }} @endif
                                                                @if ($fact['amount'] !== null)
                                                                    · <bdi dir="ltr">{{ $fact['amount'] }}</bdi> {{ $fact['currency'] }}
                                                                @endif
                                                                @if ($fact['note']) · {{ $fact['note'] }} @endif
                                                            </li>
                                                        @endforeach
                                                        @foreach ($drilldown['later'] as $change)
                                                            <li>
                                                                {{ __('reports.margin.fact_later.'.$change['kind']) }}
                                                                @if (($change['category'] ?? null)) · {{ $change['category'] }} @endif
                                                                @if (($change['amount'] ?? null) !== null)
                                                                    · <bdi dir="ltr">{{ $change['amount'] }}</bdi> {{ $change['currency'] ?? '' }}
                                                                @endif
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                </div>
                                            @endforeach
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endif
</div>

@if ($chartPayload !== null)
    @script
    <script>
        const payload = @json($chartPayload);
        const canvas = document.getElementById('margin-coverage-chart');
        if (canvas && window.Chart) {
            const existing = Chart.getChart(canvas);
            if (existing) existing.destroy();
            new Chart(canvas, {
                type: 'doughnut',
                data: {
                    labels: payload.labels,
                    datasets: [{
                        data: payload.values,
                        backgroundColor: ['#1d4ed8', '#b45309', '#6b7280'],
                    }],
                },
                options: {
                    plugins: {
                        legend: { position: 'bottom' },
                    },
                },
            });
        }
    </script>
    @endscript
@endif
