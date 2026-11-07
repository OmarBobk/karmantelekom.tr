@php
    use App\Models\Sale;
    use App\Services\Purchasing\LocalizedMoney;
@endphp
<section dir="{{ $textDirection }}" lang="{{ $locale }}" aria-labelledby="pos-history-title" class="space-y-4 text-start">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 id="pos-history-title" class="text-xl font-semibold text-gray-900">{{ __('pos.history.title') }}</h1>
            <p class="text-sm text-gray-600">{{ $seesAll ? __('pos.history.scope_all') : __('pos.history.scope_own') }}</p>
        </div>
    </div>

    <form wire:submit="applyFilters" role="search" class="flex flex-wrap items-end gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
        <div class="min-w-0 flex-1 basis-60">
            <label for="pos-history-search" class="block text-sm font-medium text-gray-800">{{ __('pos.history.search') }}</label>
            <input id="pos-history-search" type="search" wire:model="search" maxlength="80" autocomplete="off" placeholder="{{ __('pos.history.search_placeholder') }}" class="mt-1 block min-h-11 w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
        </div>
        <div>
            <label for="pos-history-date" class="block text-sm font-medium text-gray-800">{{ __('pos.history.date') }}</label>
            <input id="pos-history-date" type="date" wire:model="date" dir="ltr" class="mt-1 block min-h-11 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
        </div>
        <button type="submit" class="inline-flex min-h-11 items-center rounded-lg bg-blue-600 px-4 text-sm font-semibold text-white hover:bg-blue-700">{{ __('pos.history.apply') }}</button>
        @if ($search !== '' || $date !== '')
            <button type="button" wire:click="clearFilters" class="inline-flex min-h-11 items-center rounded-lg px-4 text-sm font-semibold text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50">{{ __('pos.history.clear') }}</button>
        @endif
    </form>

    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200" wire:loading.class="opacity-60">
        @if ($sales->isEmpty())
            <p class="px-4 py-8 text-center text-sm text-gray-600">{{ __('pos.history.empty') }}</p>
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
                            <span dir="ltr" class="ms-auto font-semibold tabular-nums text-gray-900">{{ LocalizedMoney::format($sale->totalMinor()) }} TRY</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    {{ $sales->links() }}
</section>
