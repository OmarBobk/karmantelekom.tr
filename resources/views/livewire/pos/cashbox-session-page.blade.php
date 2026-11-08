@php
    use App\Models\Sale;
    use App\Services\Purchasing\LocalizedMoney;
    $signed = fn (int $minor): string => ($minor > 0 ? '+' : '').LocalizedMoney::format($minor);
    $inCount = (int) ($movementCounts[$inValue] ?? 0);
    $outCount = (int) $movementCounts->sum() - $inCount;
@endphp
<section dir="{{ $textDirection }}" lang="{{ $locale }}" aria-labelledby="cashbox-session-title" class="space-y-4 text-start">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
            <h1 id="cashbox-session-title" class="text-xl font-semibold text-gray-900">{{ __('pos.cashbox.reconciliation.heading') }}</h1>
            <p class="text-sm text-gray-600"><span dir="ltr">{{ Sale::displayTime($session->opened_at) }} – {{ Sale::displayTime($session->closed_at) }}</span> · {{ __('pos.cashbox.reconciliation.read_only') }}</p>
        </div>
        <a href="{{ route('pos.cashbox', $store) }}" class="inline-flex min-h-11 items-center rounded-lg bg-white px-4 text-sm font-semibold text-gray-800 shadow-sm ring-1 ring-gray-300 hover:bg-gray-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">{{ __('pos.cashbox.reconciliation.back') }}</a>
    </div>

    <div class="grid gap-4 md:grid-cols-2">
        <section aria-labelledby="cashbox-session-opening-title" class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
            <h2 id="cashbox-session-opening-title" class="text-base font-semibold text-gray-900">{{ __('pos.cashbox.reconciliation.opening_title') }}</h2>
            <p class="text-sm text-gray-600">{{ __('pos.cashbox.opened_by', ['name' => $session->opener?->name, 'time' => Sale::displayTime($session->opened_at)]) }}</p>
            <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                <dt class="text-gray-600">{{ __('pos.cashbox.columns.expected') }}</dt>
                <dd dir="ltr" class="text-end tabular-nums">{{ $openingExpected === null ? '—' : LocalizedMoney::format($openingExpected) }}</dd>
                <dt class="text-gray-600">{{ __('pos.cashbox.columns.counted') }}</dt>
                <dd dir="ltr" class="text-end font-semibold tabular-nums">{{ LocalizedMoney::format($session->openingCountedMinor()) }}</dd>
                <dt class="text-gray-600">{{ __('pos.cashbox.columns.difference') }}</dt>
                <dd dir="ltr" @class(['text-end tabular-nums', 'text-red-700 font-semibold' => (int) $openingDifference !== 0])>{{ $openingDifference === null ? '—' : $signed($openingDifference) }}</dd>
            </dl>
            @if ($session->opening_note)
                <p class="mt-3 text-sm text-gray-700"><span class="font-medium">{{ __('pos.cashbox.opening_note') }}:</span> <span dir="auto">{{ $session->opening_note }}</span></p>
            @endif
        </section>

        <section aria-labelledby="cashbox-session-closing-title" class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
            <h2 id="cashbox-session-closing-title" class="text-base font-semibold text-gray-900">{{ __('pos.cashbox.reconciliation.closing_title') }}</h2>
            <p class="text-sm text-gray-600">{{ __('pos.cashbox.reconciliation.closed_by', ['name' => $session->closer?->name, 'time' => Sale::displayTime($session->closed_at)]) }}</p>
            <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                <dt class="text-gray-600">{{ __('pos.cashbox.columns.expected') }}</dt>
                <dd dir="ltr" class="text-end tabular-nums" data-expected-closing>{{ LocalizedMoney::format($expectedClosing) }}</dd>
                <dt class="text-gray-600">{{ __('pos.cashbox.columns.counted') }}</dt>
                <dd dir="ltr" class="text-end font-semibold tabular-nums">{{ LocalizedMoney::format($countedClosing) }}</dd>
                <dt class="font-semibold text-gray-900">{{ __('pos.cashbox.columns.difference') }}</dt>
                <dd dir="ltr" @class(['text-end text-lg font-bold tabular-nums', 'text-gray-900' => $closingDifference === 0, 'text-red-700' => $closingDifference !== 0])>{{ $signed($closingDifference) }} TRY</dd>
            </dl>
            @if ($session->closing_note)
                <p class="mt-3 text-sm text-gray-700"><span class="font-medium">{{ __('pos.cashbox.closing_note') }}:</span> <span dir="auto">{{ $session->closing_note }}</span></p>
            @endif
        </section>
    </div>

    <section aria-labelledby="cashbox-session-movements-title" class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
        <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-gray-100 px-4 py-3">
            <h2 id="cashbox-session-movements-title" class="text-base font-semibold text-gray-900">{{ __('pos.cashbox.movements_title') }}</h2>
            <p class="text-xs text-gray-600">{{ __('pos.cashbox.reconciliation.counts', ['in' => $inCount, 'out' => $outCount]) }}</p>
        </div>
        @if ($movements->isEmpty())
            <p class="px-4 py-6 text-center text-sm text-gray-600">{{ __('pos.cashbox.no_movements') }}</p>
        @else
            <div class="relative overflow-x-auto" wire:loading.class="opacity-60" wire:target="gotoPage,nextPage,previousPage">
                <table class="w-full text-sm">
                    <thead class="text-xs text-gray-600">
                        <tr>
                            <th scope="col" class="px-4 py-2 text-start font-medium">{{ __('pos.cashbox.columns.time') }}</th>
                            <th scope="col" class="px-4 py-2 text-start font-medium">{{ __('pos.cashbox.reconciliation.direction') }}</th>
                            <th scope="col" class="px-4 py-2 text-start font-medium">{{ __('pos.cashbox.columns.reference') }}</th>
                            <th scope="col" class="px-4 py-2 text-start font-medium">{{ __('pos.cashbox.columns.employee') }}</th>
                            <th scope="col" class="px-4 py-2 text-end font-medium">{{ __('pos.cashbox.columns.amount') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($movements as $movement)
                            <tr wire:key="session-movement-{{ $movement->id }}">
                                <td class="px-4 py-2"><time dir="ltr" datetime="{{ $movement->occurred_at->toIso8601String() }}" class="text-gray-700">{{ Sale::displayTime($movement->occurred_at) }}</time></td>
                                <td class="px-4 py-2 text-gray-700">{{ __('pos.cashbox.reconciliation.directions.'.$movement->direction->value) }}</td>
                                <td class="px-4 py-2">@include('livewire.pos.partials.cash-movement-source')</td>
                                <td class="px-4 py-2 text-gray-700">{{ $movement->recorder?->name }}</td>
                                <td dir="ltr" @class(['px-4 py-2 text-end font-semibold tabular-nums', 'text-gray-900' => $movement->signedMinor() > 0, 'text-red-700' => $movement->signedMinor() < 0])>{{ $signed($movement->signedMinor()) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3">{{ $movements->links() }}</div>
        @endif
    </section>
</section>
