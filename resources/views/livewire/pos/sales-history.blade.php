@php
    use App\Enums\SalePaymentMethod;
    use App\Models\Sale;
    use App\Services\Purchasing\LocalizedMoney;
    use App\Services\Reporting\RecordedSalesReport;
    $tenderMatch = function (Sale $sale) use ($tender): int {
        return match ($tender) {
            RecordedSalesReport::TENDER_CASH => $sale->payments->where('method', SalePaymentMethod::CASH)->sum(fn ($payment) => $payment->amountMinor()),
            RecordedSalesReport::TENDER_CARD => $sale->payments->where('method', SalePaymentMethod::CARD)->sum(fn ($payment) => $payment->amountMinor()),
            RecordedSalesReport::TENDER_CREDIT => $sale->creditMinor(),
            default => 0,
        };
    };
@endphp
<section dir="{{ $textDirection }}" lang="{{ $locale }}" aria-labelledby="pos-history-title" class="space-y-4 text-start">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 id="pos-history-title" class="text-xl font-semibold text-gray-900">{{ __('pos.history.title') }}</h1>
            <p class="text-sm text-gray-600">{{ $seesAll ? __('pos.history.scope_all') : __('pos.history.scope_own') }}</p>
        </div>
        @if ($canExport)
            <div class="max-w-md text-end">
                <a href="{{ route('pos.sales.export', [$store, 'from' => $from, 'to' => $to, 'q' => $search, 'tender' => $tender, 'from_at' => $fromAt, 'to_at' => $toAt]) }}" class="inline-flex min-h-11 items-center rounded-lg px-4 text-sm font-semibold text-blue-700 ring-1 ring-blue-200 hover:bg-blue-50">{{ __('reports.export.download') }}</a>
                <p class="mt-1 text-xs text-gray-500">{{ __('reports.export.help') }}</p>
            </div>
        @endif
    </div>

    <form wire:submit="applyFilters" role="search" class="flex flex-wrap items-end gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
        <div class="min-w-0 flex-1 basis-60">
            <label for="pos-history-search" class="block text-sm font-medium text-gray-800">{{ __('pos.history.search') }}</label>
            <input id="pos-history-search" type="search" wire:model="search" maxlength="80" autocomplete="off" placeholder="{{ __('pos.history.search_placeholder') }}" class="mt-1 block min-h-11 w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
        </div>
        <div>
            <label for="pos-history-from" class="block text-sm font-medium text-gray-800">{{ __('pos.history.from') }}</label>
            <input id="pos-history-from" type="date" wire:model="from" dir="ltr" @if ($rangeError) aria-invalid="true" aria-describedby="pos-history-range-error" @endif class="mt-1 block min-h-11 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
        </div>
        <div>
            <label for="pos-history-to" class="block text-sm font-medium text-gray-800">{{ __('pos.history.to') }}</label>
            <input id="pos-history-to" type="date" wire:model="to" dir="ltr" @if ($rangeError) aria-invalid="true" aria-describedby="pos-history-range-error" @endif class="mt-1 block min-h-11 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
        </div>
        <div>
            <label for="pos-history-tender" class="block text-sm font-medium text-gray-800">{{ __('pos.history.tender') }}</label>
            <select id="pos-history-tender" wire:model="tender" class="mt-1 block min-h-11 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">{{ __('pos.history.tender_all') }}</option>
                <option value="{{ RecordedSalesReport::TENDER_CASH }}">{{ __('reports.sales.tender.cash') }}</option>
                <option value="{{ RecordedSalesReport::TENDER_CARD }}">{{ __('reports.sales.tender.card') }}</option>
                <option value="{{ RecordedSalesReport::TENDER_CREDIT }}">{{ __('reports.sales.tender.credit') }}</option>
            </select>
        </div>
        <button type="submit" wire:loading.attr="disabled" class="inline-flex min-h-11 items-center rounded-lg bg-blue-600 px-4 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">{{ __('pos.history.apply') }}</button>
        @if ($isFiltering || $rangeError)
            <button type="button" wire:click="clearFilters" class="inline-flex min-h-11 items-center rounded-lg px-4 text-sm font-semibold text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50">{{ __('pos.history.clear') }}</button>
        @endif
        @if ($rangeError)
            <p id="pos-history-range-error" role="alert" class="basis-full text-sm font-medium text-red-700">{{ __('pos.history.range_'.$rangeError) }}</p>
        @endif
    </form>

    @if ($activeInterval)
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-blue-50 px-4 py-3 text-sm text-blue-950 ring-1 ring-blue-200" role="status">
            <p>
                {{ __('pos.history.interval_active', ['start' => $activeInterval['start'], 'end' => $activeInterval['end']]) }}
                <span class="mt-1 block text-xs text-blue-900/80">{{ __('pos.history.interval_exclusive_note') }}</span>
            </p>
            <button type="button" wire:click="clearInterval" class="inline-flex min-h-11 items-center rounded-lg px-3 text-sm font-semibold text-blue-800 ring-1 ring-blue-300 hover:bg-white">{{ __('pos.history.clear_interval') }}</button>
        </div>
    @endif

    @if ($summary)
        <p class="rounded-xl bg-white px-4 py-3 text-sm text-gray-700 shadow-sm ring-1 ring-gray-200" role="status">
            {{ $isFiltering ? __('pos.history.summary_filtered') : __('pos.history.summary_all') }}
            <span class="font-semibold text-gray-900">{{ __('pos.history.summary_count') }} <span dir="ltr" class="tabular-nums">{{ $summary['count'] }}</span></span>
            ·
            <span class="font-semibold text-gray-900">{{ __('pos.history.summary_total') }} <span dir="ltr" class="tabular-nums">{{ LocalizedMoney::format($summary['total_minor']) }} TRY</span></span>
            @if (isset($summary['tender'], $summary['match_minor']))
                ·
                <span class="font-semibold text-gray-900">{{ __('pos.history.summary_match', ['tender' => __('reports.sales.tender.'.$summary['tender'])]) }} <span dir="ltr" class="tabular-nums">{{ LocalizedMoney::format($summary['match_minor']) }} TRY</span></span>
                <span class="mt-1 block text-xs text-gray-500">{{ __('pos.history.summary_match_note') }}</span>
            @endif
        </p>
    @endif

    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200" wire:loading.class="opacity-60">
        @if ($sales->isEmpty())
            <p class="px-4 py-8 text-center text-sm text-gray-600">{{ $rangeError ? __('pos.history.range_unavailable') : __('pos.history.empty') }}</p>
        @else
            <ul class="divide-y divide-gray-100">
                @foreach ($sales as $sale)
                    <li wire:key="sale-{{ $sale->id }}">
                        <a href="{{ route('pos.sales.show', [$store, $sale]) }}" class="flex min-h-14 flex-wrap items-center gap-x-4 gap-y-1 px-4 py-3 hover:bg-gray-50 focus-visible:bg-blue-50 focus-visible:outline-none">
                            <span dir="ltr" class="font-mono font-semibold text-gray-900">{{ $sale->number }}</span>
                            <time dir="ltr" datetime="{{ $sale->completed_at->toIso8601String() }}" class="text-sm text-gray-600">{{ Sale::displayTime($sale->completed_at) }}</time>
                            <span class="text-sm text-gray-600">{{ trans_choice('pos.cart.count', $sale->lines_count, ['count' => $sale->lines_count]) }}</span>
                            <span class="text-sm text-gray-600">{{ $sale->payments->map(fn ($payment) => $payment->method->label())->unique()->join(' + ') }}</span>
                            @if ($seesAll)
                                <span class="text-sm text-gray-600">{{ $sale->cashier?->name }}</span>
                            @endif
                            @if ($sale->customer)
                                <span class="text-sm text-gray-800">
                                    <span class="sr-only">{{ __('pos.customer.title') }}:</span>
                                    {{ $sale->customer->name }} <bdi dir="ltr" class="font-mono text-gray-600">{{ $sale->customer->maskedPhone() }}</bdi>
                                </span>
                            @endif
                            @if ($sale->creditMinor() > 0)
                                <span class="rounded bg-amber-50 px-1.5 py-0.5 text-xs font-medium text-amber-900 ring-1 ring-amber-200">
                                    {{ __('pos.fields.amount_paid') }} <span dir="ltr" class="tabular-nums">{{ LocalizedMoney::format($sale->paidMinor()) }}</span>
                                    · {{ __('pos.fields.credit') }} <span dir="ltr" class="tabular-nums">{{ LocalizedMoney::format($sale->creditMinor()) }}</span>
                                </span>
                            @endif
                            @if ($tender !== '')
                                <span class="rounded bg-blue-50 px-1.5 py-0.5 text-xs font-medium text-blue-900 ring-1 ring-blue-200">
                                    {{ __('pos.history.row_match', ['tender' => __('reports.sales.tender.'.$tender)]) }}
                                    <span dir="ltr" class="tabular-nums">{{ LocalizedMoney::format($tenderMatch($sale)) }}</span>
                                </span>
                            @endif
                            <span dir="ltr" class="ms-auto font-semibold tabular-nums text-gray-900">{{ LocalizedMoney::format($sale->totalMinor()) }} TRY</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    {{ $sales->links() }}
</section>
