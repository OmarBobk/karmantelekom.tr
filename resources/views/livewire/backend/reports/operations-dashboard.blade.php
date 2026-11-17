@php
    use App\Services\Purchasing\LocalizedMoney;
    use App\Services\Reporting\RecordedSalesReport;
    $link = 'inline-flex min-h-11 items-center rounded-lg px-3 text-sm font-medium text-blue-700 hover:bg-blue-50';
    $signedCount = fn (int $delta): string => ($delta > 0 ? '+' : '').$delta;
    $signedMoney = fn (int $delta): string => ($delta > 0 ? '+' : '').LocalizedMoney::format($delta);
    $period = '<bdi dir="ltr">'.e($from).'</bdi>';
    $periodTo = '<bdi dir="ltr">'.e($to).'</bdi>';
@endphp
<div dir="{{ $textDirection }}" lang="{{ $locale }}" class="mx-auto max-w-6xl space-y-6 text-start">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            @if ($branch)
                <a href="{{ route('subdomain.operations.dashboard', ['from' => $from, 'to' => $to]) }}" class="text-sm font-medium text-blue-600 hover:underline">{{ __('reports.all_branches') }}</a>
                <h1 class="mt-1 flex flex-wrap items-center gap-2 text-2xl font-bold tracking-tight text-gray-900">
                    {{ $branch->name }}
                    <span dir="ltr" class="rounded-md bg-gray-100 px-2 py-0.5 font-mono text-sm font-medium text-gray-700">{{ $branch->code }}</span>
                </h1>
                <p class="mt-1 text-sm text-gray-500">{{ __('reports.branch_subtitle') }}</p>
            @else
                <h1 class="text-2xl font-bold tracking-tight text-gray-900">{{ __('reports.title') }}</h1>
                <p class="mt-1 text-sm text-gray-500">{{ __('reports.subtitle') }}</p>
            @endif
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($canViewFinance)
                <a href="{{ route('subdomain.operations.finance', ['from' => $from, 'to' => $to]) }}" class="{{ $link }} ring-1 ring-blue-200">{{ __('reports.open_finance') }}</a>
            @endif
            @if ($canViewReview)
                <a href="{{ route('subdomain.operations.review', ['from' => $from, 'to' => $to]) }}" class="{{ $link }} ring-1 ring-blue-200">{{ __('reports.review.navigation') }}</a>
            @endif
            @if ($canViewAudit)
                <a href="{{ route('subdomain.operations.audit', ['from' => $from, 'to' => $to]) }}" class="{{ $link }} ring-1 ring-blue-200">{{ __('reports.audit.navigation') }}</a>
            @endif
        </div>
    </div>

    @if ($rows->isEmpty())
        <div class="rounded-xl border-2 border-dashed border-gray-300 bg-white px-6 py-12 text-center">
            <h2 class="text-base font-semibold text-gray-900">{{ __('reports.empty_title') }}</h2>
            <p class="mt-1 text-sm text-gray-500">{{ __('reports.empty_body') }}</p>
        </div>
    @else
        <x-operations.report-date-range id="dashboard-range" :range-error="$rangeError" />

        @unless ($rangeError)
            <section aria-labelledby="dashboard-lead-title" class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200" wire:loading.class="opacity-60">
                <div class="border-b border-gray-100 px-4 py-3 sm:px-5">
                    <h2 id="dashboard-lead-title" class="text-base font-semibold text-gray-900">{{ __('reports.sales.lead_title') }}</h2>
                    <p class="mt-1 text-sm text-gray-600">{!! __('reports.sales.period', ['from' => $period, 'to' => $periodTo]) !!}</p>
                    <p class="mt-1 text-xs text-gray-500">{{ __('reports.sales.scope_note') }}</p>
                    @if ($incomplete)
                        <p class="mt-2 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-950 ring-1 ring-amber-200" role="status">{{ __('reports.sales.incomplete') }}</p>
                    @endif
                </div>
                <div class="grid gap-4 p-4 sm:grid-cols-2 sm:p-5 lg:grid-cols-4">
                    <div class="sm:col-span-2">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ __('reports.sales.recorded_total') }}</p>
                        <p dir="ltr" class="mt-1 text-start text-3xl font-bold tabular-nums tracking-tight text-gray-900">{{ LocalizedMoney::format($ownerTotalMinor) }} TRY</p>
                        <p class="mt-2 text-sm text-gray-600">
                            {{ __('reports.sales.previous_period', ['from' => $previousFrom, 'to' => $previousTo]) }}:
                            <span dir="ltr" class="font-semibold tabular-nums text-gray-900">{{ LocalizedMoney::format($ownerPreviousTotalMinor) }} TRY</span>
                            ·
                            <span dir="ltr" @class(['font-semibold tabular-nums', 'text-emerald-800' => $ownerTotalDelta > 0, 'text-red-700' => $ownerTotalDelta < 0, 'text-gray-700' => $ownerTotalDelta === 0])>{{ $signedMoney($ownerTotalDelta) }} TRY</span>
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ __('reports.sales.count') }}</p>
                        <p dir="ltr" class="mt-1 text-start text-3xl font-bold tabular-nums tracking-tight text-gray-900">{{ $ownerCount }}</p>
                        <p class="mt-2 text-sm text-gray-600">
                            {{ __('reports.sales.previous_short') }}:
                            <span dir="ltr" class="font-semibold tabular-nums">{{ $ownerPreviousCount }}</span>
                            ·
                            <span dir="ltr" @class(['font-semibold tabular-nums', 'text-emerald-800' => $ownerCountDelta > 0, 'text-red-700' => $ownerCountDelta < 0, 'text-gray-700' => $ownerCountDelta === 0])>{{ $signedCount($ownerCountDelta) }}</span>
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ __('reports.sales.composition_title') }}</p>
                        <dl class="mt-2 space-y-1 text-sm">
                            <div class="flex items-baseline justify-between gap-3">
                                <dt class="text-gray-600">{{ __('reports.sales.tender.cash') }}</dt>
                                <dd dir="ltr" class="font-semibold tabular-nums text-gray-900">{{ LocalizedMoney::format($ownerComposition['cash']) }}</dd>
                            </div>
                            <div class="flex items-baseline justify-between gap-3">
                                <dt class="text-gray-600">{{ __('reports.sales.tender.card') }}</dt>
                                <dd dir="ltr" class="font-semibold tabular-nums text-gray-900">{{ LocalizedMoney::format($ownerComposition['card']) }}</dd>
                            </div>
                            <div class="flex items-baseline justify-between gap-3">
                                <dt class="text-gray-600">{{ __('reports.sales.tender.credit') }}</dt>
                                <dd dir="ltr" class="font-semibold tabular-nums text-amber-900">{{ LocalizedMoney::format($ownerComposition['credit']) }}</dd>
                            </div>
                        </dl>
                        <p class="mt-2 text-xs text-gray-500">{{ __('reports.sales.composition_note') }}</p>
                    </div>
                </div>
            </section>

            @if ($attention !== [])
                <section aria-labelledby="dashboard-attention-title" class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-amber-200">
                    <div class="border-b border-amber-100 bg-amber-50 px-4 py-3 sm:px-5">
                        <h2 id="dashboard-attention-title" class="text-base font-semibold text-amber-950">{{ __('reports.attention.title') }}</h2>
                        <p class="mt-1 text-sm text-amber-900/80">{{ __('reports.attention.subtitle') }}</p>
                    </div>
                    <ul class="divide-y divide-gray-100">
                        @foreach ($attention as $item)
                            <li wire:key="attention-{{ $item['key'] }}" class="flex flex-wrap items-start justify-between gap-3 px-4 py-3 sm:px-5">
                                <div class="min-w-0">
                                    <p class="font-medium text-gray-900">{{ $item['label'] }}</p>
                                    <p class="mt-0.5 text-sm text-gray-600">{{ $item['detail'] }}</p>
                                </div>
                                <a href="{{ $item['url'] }}" class="{{ $link }} shrink-0 ring-1 ring-amber-200">{{ __('reports.attention.open') }}</a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <section aria-labelledby="dashboard-charts-title" class="space-y-4">
                <div>
                    <h2 id="dashboard-charts-title" class="text-base font-semibold text-gray-900">{{ __('reports.sales.charts_title') }}</h2>
                    <p class="text-sm text-gray-600">{{ __('reports.sales.charts_note') }}</p>
                    <p class="mt-1 text-xs text-gray-500">{{ __('reports.sales.granularity_note', ['granularity' => __('reports.sales.granularity.'.$series['granularity'])]) }}</p>
                </div>

                <div
                    wire:key="dashboard-charts-{{ $from }}-{{ $to }}-{{ $storeId ?? 'all' }}"
                    wire:ignore
                    class="grid gap-4 lg:grid-cols-2"
                    x-data="operationsDashboardCharts(@js($chartPayload))"
                >
                    <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200 sm:p-5">
                        <h3 class="text-sm font-semibold text-gray-900">{{ __('reports.sales.time_chart') }}</h3>
                        <p class="mt-1 text-xs text-gray-500">{{ __('reports.sales.chart_not_profit') }}</p>
                        <div class="relative mt-3 h-64">
                            <canvas x-ref="timeChart" role="img" :aria-label="timeLabel"></canvas>
                        </div>
                    </div>
                    <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200 sm:p-5">
                        <h3 class="text-sm font-semibold text-gray-900">{{ __('reports.sales.branch_chart') }}</h3>
                        <p class="mt-1 text-xs text-gray-500">{{ __('reports.sales.chart_not_profit') }}</p>
                        <div class="relative mt-3 h-64">
                            <canvas x-ref="branchChart" role="img" :aria-label="branchLabel"></canvas>
                        </div>
                    </div>
                </div>

                <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                    <div class="border-b border-gray-100 px-4 py-3">
                        <h3 class="text-sm font-semibold text-gray-900">{{ __('reports.sales.table_alternative') }}</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-xs text-gray-600">
                                <tr>
                                    <th scope="col" class="px-4 py-2 text-start font-medium">{{ __('reports.sales.branch') }}</th>
                                    <th scope="col" class="px-4 py-2 text-end font-medium">{{ __('reports.sales.count') }}</th>
                                    <th scope="col" class="px-4 py-2 text-end font-medium">{{ __('reports.sales.total') }}</th>
                                    <th scope="col" class="px-4 py-2 text-end font-medium">{{ __('reports.sales.previous_short') }}</th>
                                    <th scope="col" class="px-4 py-2 text-end font-medium">{{ __('reports.sales.delta') }}</th>
                                    <th scope="col" class="px-4 py-2 text-end font-medium">{{ __('reports.sales.tender.cash') }}</th>
                                    <th scope="col" class="px-4 py-2 text-end font-medium">{{ __('reports.sales.tender.card') }}</th>
                                    <th scope="col" class="px-4 py-2 text-end font-medium">{{ __('reports.sales.tender.credit') }}</th>
                                    <th scope="col" class="px-4 py-2"><span class="sr-only">{{ __('reports.sales.view_sales') }}</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($rows as $row)
                                    <tr wire:key="dashboard-sales-{{ $row['store']->id }}">
                                        <th scope="row" class="px-4 py-3 text-start font-medium text-gray-900">
                                            @if ($branch)
                                                {{ $row['store']->name }}
                                            @else
                                                <a href="{{ route('subdomain.operations.dashboard.store', [$row['store'], 'from' => $from, 'to' => $to]) }}" aria-label="{{ __('reports.sales.open_branch_label', ['branch' => $row['store']->code]) }}" class="text-blue-700 hover:underline">{{ $row['store']->name }}</a>
                                            @endif
                                            <span dir="ltr" class="ms-1 font-mono text-xs text-gray-500">{{ $row['store']->code }}</span>
                                        </th>
                                        <td dir="ltr" class="px-4 py-3 text-end font-mono tabular-nums text-gray-900">{{ $row['count'] }}</td>
                                        <td class="px-4 py-3 text-end font-semibold text-gray-900"><span dir="ltr" class="tabular-nums">{{ LocalizedMoney::format($row['total_minor']) }} TRY</span></td>
                                        <td class="px-4 py-3 text-end text-gray-600"><span dir="ltr" class="tabular-nums">{{ LocalizedMoney::format($row['previous_total_minor']) }}</span></td>
                                        <td class="px-4 py-3 text-end"><span dir="ltr" @class(['tabular-nums font-medium', 'text-emerald-800' => $row['total_delta'] > 0, 'text-red-700' => $row['total_delta'] < 0])>{{ $signedMoney($row['total_delta']) }}</span></td>
                                        <td class="px-4 py-3 text-end">
                                            <a href="{{ route('pos.sales', [$row['store'], 'from' => $from, 'to' => $to, 'tender' => RecordedSalesReport::TENDER_CASH]) }}" class="{{ $link }} -me-3" aria-label="{{ __('reports.sales.view_tender_label', ['tender' => __('reports.sales.tender.cash'), 'branch' => $row['store']->code]) }}"><span dir="ltr" class="tabular-nums">{{ LocalizedMoney::format($row['cash']) }}</span></a>
                                        </td>
                                        <td class="px-4 py-3 text-end">
                                            <a href="{{ route('pos.sales', [$row['store'], 'from' => $from, 'to' => $to, 'tender' => RecordedSalesReport::TENDER_CARD]) }}" class="{{ $link }} -me-3" aria-label="{{ __('reports.sales.view_tender_label', ['tender' => __('reports.sales.tender.card'), 'branch' => $row['store']->code]) }}"><span dir="ltr" class="tabular-nums">{{ LocalizedMoney::format($row['card']) }}</span></a>
                                        </td>
                                        <td class="px-4 py-3 text-end">
                                            <a href="{{ route('pos.sales', [$row['store'], 'from' => $from, 'to' => $to, 'tender' => RecordedSalesReport::TENDER_CREDIT]) }}" class="{{ $link }} -me-3" aria-label="{{ __('reports.sales.view_tender_label', ['tender' => __('reports.sales.tender.credit'), 'branch' => $row['store']->code]) }}"><span dir="ltr" class="tabular-nums">{{ LocalizedMoney::format($row['credit']) }}</span></a>
                                        </td>
                                        <td class="px-4 py-3 text-end">
                                            <a href="{{ route('pos.sales', [$row['store'], 'from' => $from, 'to' => $to]) }}" aria-label="{{ __('reports.sales.view_sales_label', ['branch' => $row['store']->code]) }}" class="{{ $link }}">{{ __('reports.sales.view_sales') }}</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            @unless ($branch)
                                <tfoot class="border-t-2 border-gray-200 bg-gray-50">
                                    <tr>
                                        <th scope="row" class="px-4 py-3 text-start font-semibold text-gray-900">{{ __('reports.all_branches') }}</th>
                                        <td dir="ltr" class="px-4 py-3 text-end font-mono font-semibold tabular-nums text-gray-900">{{ $ownerCount }}</td>
                                        <td class="px-4 py-3 text-end font-bold text-gray-900"><span dir="ltr" class="tabular-nums">{{ LocalizedMoney::format($ownerTotalMinor) }} TRY</span></td>
                                        <td class="px-4 py-3 text-end font-semibold text-gray-700"><span dir="ltr" class="tabular-nums">{{ LocalizedMoney::format($ownerPreviousTotalMinor) }}</span></td>
                                        <td class="px-4 py-3 text-end font-semibold"><span dir="ltr" class="tabular-nums">{{ $signedMoney($ownerTotalDelta) }}</span></td>
                                        <td class="px-4 py-3 text-end font-semibold"><span dir="ltr" class="tabular-nums">{{ LocalizedMoney::format($ownerComposition['cash']) }}</span></td>
                                        <td class="px-4 py-3 text-end font-semibold"><span dir="ltr" class="tabular-nums">{{ LocalizedMoney::format($ownerComposition['card']) }}</span></td>
                                        <td class="px-4 py-3 text-end font-semibold"><span dir="ltr" class="tabular-nums">{{ LocalizedMoney::format($ownerComposition['credit']) }}</span></td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            @endunless
                        </table>
                    </div>
                </div>

                <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                    <div class="border-b border-gray-100 px-4 py-3">
                        <h3 class="text-sm font-semibold text-gray-900">{{ __('reports.sales.time_table') }}</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-xs text-gray-600">
                                <tr>
                                    <th scope="col" class="px-4 py-2 text-start font-medium">{{ __('reports.sales.bucket') }}</th>
                                    <th scope="col" class="px-4 py-2 text-end font-medium">{{ __('reports.sales.count') }}</th>
                                    <th scope="col" class="px-4 py-2 text-end font-medium">{{ __('reports.sales.total') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($series['buckets'] as $bucket)
                                    <tr wire:key="bucket-{{ $bucket['key'] }}">
                                        <th scope="row" class="px-4 py-2 text-start font-medium text-gray-900">
                                            <a href="{{ $branch ? route('pos.sales', [$branch, 'from' => $bucket['start'], 'to' => $bucket['end_date']]) : route('subdomain.operations.dashboard', ['from' => $bucket['start'], 'to' => $bucket['end_date']]) }}" class="text-blue-700 hover:underline">{{ $bucket['label'] }}</a>
                                        </th>
                                        <td dir="ltr" class="px-4 py-2 text-end font-mono tabular-nums">{{ $bucket['count'] }}</td>
                                        <td class="px-4 py-2 text-end"><span dir="ltr" class="tabular-nums">{{ LocalizedMoney::format($bucket['total_minor']) }} TRY</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        @else
            <p class="rounded-xl bg-white px-4 py-8 text-center text-sm text-gray-600 shadow-sm ring-1 ring-gray-200">{{ __('reports.sales.unavailable') }}</p>
        @endunless

        @if ($inventory->isNotEmpty())
            <section aria-labelledby="dashboard-inventory-title" class="space-y-3">
                <div>
                    <h2 id="dashboard-inventory-title" class="text-base font-semibold text-gray-900">{{ __('reports.inventory.title') }}</h2>
                    <p class="text-sm text-gray-600">{{ __('reports.inventory.note') }}</p>
                    <p class="mt-1 text-xs text-gray-500">{{ __('reports.inventory.out_of_stock_note') }}</p>
                </div>
                <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($inventory as $store)
                        <li wire:key="dashboard-inventory-{{ $store->id }}" class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                            <div class="flex items-start justify-between gap-3">
                                <p class="font-semibold text-gray-900">{{ $store->name }}</p>
                                <span dir="ltr" class="rounded-md bg-gray-100 px-2 py-0.5 font-mono text-xs text-gray-700">{{ $store->code }}</span>
                            </div>
                            <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                                <div>
                                    <dt class="text-xs text-gray-500">{{ __('reports.inventory.quantity_on_hand') }}</dt>
                                    <dd dir="ltr" class="text-start font-mono text-lg font-semibold tabular-nums text-gray-900">{{ (int) $store->quantity_on_hand }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-gray-500">{{ __('reports.inventory.available_units') }}</dt>
                                    <dd dir="ltr" class="text-start font-mono text-lg font-semibold tabular-nums text-gray-900">{{ $store->available_units_count }}</dd>
                                </div>
                            </dl>
                            <div class="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-gray-100 pt-3">
                                <div>
                                    <p class="text-xs text-gray-500">{{ __('reports.inventory.out_of_stock') }}</p>
                                    <p dir="ltr" class="font-mono text-lg font-semibold tabular-nums {{ $store->out_of_stock_count > 0 ? 'text-amber-800' : 'text-gray-900' }}">{{ $store->out_of_stock_count }}</p>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <a href="{{ route('subdomain.inventory.store', $store) }}" aria-label="{{ __('reports.inventory.open_label', ['branch' => $store->code]) }}" class="{{ $link }}">{{ __('reports.inventory.open') }}</a>
                                    <a href="{{ route('subdomain.inventory.store', [$store, 'stock' => 'out']) }}" aria-label="{{ __('reports.inventory.out_of_stock_label', ['branch' => $store->code]) }}" class="inline-flex min-h-11 items-center rounded-lg px-3 text-sm font-medium text-amber-800 hover:bg-amber-50">{{ __('reports.inventory.view_out_of_stock') }}</a>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    @endif
</div>

@script
<script>
Alpine.data('operationsDashboardCharts', (payload) => ({
    payload,
    timeChart: null,
    branchChart: null,
    timeLabel: '',
    branchLabel: '',
    init() {
        this.timeLabel = this.payload.time?.labels?.length
            ? `${this.payload.recordedLabel}: ${this.payload.granularityLabel}`
            : this.payload.recordedLabel;
        this.branchLabel = this.payload.recordedLabel;
        queueMicrotask(() => this.render());
        this._onResize = () => {
            this.timeChart?.resize();
            this.branchChart?.resize();
        };
        window.addEventListener('resize', this._onResize);
    },
    destroy() {
        window.removeEventListener('resize', this._onResize);
        this.timeChart?.destroy();
        this.branchChart?.destroy();
        this.timeChart = null;
        this.branchChart = null;
    },
    render() {
        if (typeof Chart === 'undefined') {
            return;
        }
        this.timeChart?.destroy();
        this.branchChart?.destroy();
        const blue = 'rgb(37, 99, 235)';
        const blueFill = 'rgba(37, 99, 235, 0.12)';
        const formatTick = (value) => new Intl.NumberFormat(this.payload.locale === 'ar' ? 'tr-TR' : 'tr-TR', {
            maximumFractionDigits: 0,
        }).format(value);

        const common = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (context) => {
                            const formatted = context.chart.$karmanFormatted?.[context.dataIndex]
                                ?? `${context.parsed.y} ${this.payload.currency}`;
                            return `${this.payload.recordedLabel}: ${formatted}`;
                        },
                    },
                },
            },
            scales: {
                x: {
                    ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 8 },
                    grid: { display: false },
                },
                y: {
                    beginAtZero: true,
                    ticks: { callback: formatTick },
                    grid: { color: 'rgba(15, 23, 42, 0.06)' },
                },
            },
        };

        const timeType = this.payload.singleDay ? 'bar' : 'line';
        const timeCanvas = this.$refs.timeChart;
        if (timeCanvas) {
            this.timeChart = new Chart(timeCanvas, {
                type: timeType,
                data: {
                    labels: this.payload.time.labels,
                    datasets: [{
                        data: this.payload.time.totals,
                        borderColor: blue,
                        backgroundColor: timeType === 'bar' ? blue : blueFill,
                        fill: timeType === 'line',
                        tension: 0.25,
                        pointRadius: timeType === 'line' ? 3 : 0,
                        maxBarThickness: 36,
                    }],
                },
                options: {
                    ...common,
                    onClick: (_event, elements) => {
                        if (! elements.length) return;
                        const url = this.payload.time.links[elements[0].index];
                        if (url) window.location.href = url;
                    },
                },
            });
            this.timeChart.$karmanFormatted = this.payload.time.formatted;
        }

        const branchCanvas = this.$refs.branchChart;
        if (branchCanvas) {
            this.branchChart = new Chart(branchCanvas, {
                type: 'bar',
                data: {
                    labels: this.payload.branches.labels,
                    datasets: [{
                        data: this.payload.branches.totals,
                        backgroundColor: blue,
                        maxBarThickness: 48,
                    }],
                },
                options: {
                    ...common,
                    onClick: (_event, elements) => {
                        if (! elements.length) return;
                        const url = this.payload.branches.links[elements[0].index];
                        if (url) window.location.href = url;
                    },
                },
            });
            this.branchChart.$karmanFormatted = this.payload.branches.formatted;
        }
    },
}));
</script>
@endscript
