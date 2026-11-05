{{-- Expects: $match (array from FindUnitsByIdentifier), $historical (bool), $first (bool) --}}
@php($status = \App\Enums\UnitStatus::from($match['status']))
<li wire:key="lookup-{{ $historical ? 'h' : 'c' }}-{{ $match['unit_id'] }}">
    <a href="{{ route('subdomain.inventory.unit', [$match['store_id'], $match['unit_id']]) }}"
        @if ($first) data-lookup-result @endif
        class="flex min-h-14 flex-wrap items-center gap-x-4 gap-y-1 px-4 py-3 hover:bg-blue-50 focus:bg-blue-50 focus:outline-2 focus:-outline-offset-2 focus:outline-blue-600">
        <span class="min-w-0 flex-1">
            <span class="block font-medium text-gray-900">
                {{ $match['product_name'] }}
                @if ($match['product_model'])
                    <bdi dir="ltr" class="text-sm font-normal text-gray-600">{{ $match['product_model'] }}</bdi>
                @endif
            </span>
            <span class="mt-0.5 flex flex-wrap items-center gap-x-2 text-sm text-gray-600">
                <span>{{ __('inventory.identifier_types.'.$match['type']) }}@if ($match['type'] === 'imei' && $match['position'] > 1) {{ $match['position'] }}@endif:</span>
                <bdi dir="ltr" class="font-mono text-gray-900">{{ $match['matched_value'] }}</bdi>
                @if ($historical)
                    <span class="text-amber-800">{!! __('devices.lookup.now_recorded_as', ['value' => '<bdi dir="ltr" class="font-mono">'.e($match['current_value']).'</bdi>']) !!}</span>
                @endif
            </span>
        </span>
        <span class="flex items-center gap-2">
            <span dir="ltr" class="rounded-md bg-gray-100 px-2 py-0.5 font-mono text-xs text-gray-800" title="{{ $match['store_name'] }}">{{ $match['store_code'] }}</span>
            <span class="rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset {{ $status->badgeClasses() }}">{{ $status->label() }}</span>
            @if ($historical)
                <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-900">{{ __('devices.lookup.historical_badge') }}</span>
            @endif
        </span>
    </a>
</li>
