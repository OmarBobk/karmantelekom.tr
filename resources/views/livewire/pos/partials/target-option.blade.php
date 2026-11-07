{{-- One sellable target (a quantity product or a specific unit) offered after a scan or search. --}}
<li wire:key="{{ $keyPrefix }}-{{ $option['definition_id'] }}-{{ $option['unit_id'] ?? 'd' }}-{{ $option['match'] }}">
    <button
        type="button"
        @if ($option['available'])
            wire:click="choose({{ $option['definition_id'] }}, {{ $option['unit_id'] ?? 'null' }})"
        @else
            disabled aria-disabled="true"
        @endif
        class="flex min-h-14 w-full flex-wrap items-center gap-x-3 gap-y-1 px-3 py-2 text-start hover:bg-blue-50 focus-visible:bg-blue-50 focus-visible:outline-none disabled:cursor-not-allowed disabled:bg-gray-50 disabled:hover:bg-gray-50"
    >
        <span class="min-w-0 flex-1">
            <span class="block font-medium text-gray-900">{{ $option['name'] }}@if ($option['model']) <span class="text-gray-600">· {{ $option['model'] }}</span>@endif</span>
            <span class="block text-sm text-gray-700">
                @if ($option['identifier'])
                    <bdi dir="ltr" class="font-mono">{{ $option['identifier'] }}</bdi>
                    @if ($option['condition']) · {{ __('inventory.conditions.'.$option['condition']) }} @endif
                @elseif ($option['stock'] !== null)
                    {{ __('pos.scan.in_stock', ['count' => $option['stock']]) }}
                @endif
                <span class="text-xs text-gray-500">· {{ __('pos.scan.matched_by.'.$option['match']) }}</span>
            </span>
        </span>
        <span class="text-end text-sm">
            @if (! $option['available'])
                <span class="font-semibold text-red-800">{{ \App\Livewire\Pos\PosCounter::unavailableMessage($option) }}</span>
            @elseif ($option['price_minor'] === null)
                <span class="rounded bg-red-50 px-1.5 py-0.5 text-xs font-semibold text-red-800 ring-1 ring-red-200">{{ __('pos.cart.not_priced') }}</span>
            @else
                <span dir="ltr" class="font-semibold tabular-nums text-gray-900">{{ \App\Livewire\Pos\PosCounter::formatMoney($option['price_minor']) }}</span>
            @endif
        </span>
    </button>
</li>
