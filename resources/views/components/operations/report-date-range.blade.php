@props([
    'id' => 'report-range',
    'legend' => null,
    'note' => null,
    'rangeError' => null,
    'submit' => 'applyRange',
])

<form wire:submit="{{ $submit }}" {{ $attributes->class('rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200') }}>
    <fieldset>
        <legend class="text-sm font-semibold text-gray-900">{{ $legend ?? __('reports.range.legend') }}</legend>
        <div class="mt-2 flex flex-wrap items-end gap-3">
            <div>
                <label for="{{ $id }}-from" class="block text-sm font-medium text-gray-800">{{ __('reports.range.from') }}</label>
                <input id="{{ $id }}-from" type="date" wire:model="from" dir="ltr" required @if ($rangeError) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif class="mt-1 block min-h-11 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            <div>
                <label for="{{ $id }}-to" class="block text-sm font-medium text-gray-800">{{ __('reports.range.to') }}</label>
                <input id="{{ $id }}-to" type="date" wire:model="to" dir="ltr" required @if ($rangeError) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif class="mt-1 block min-h-11 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            <button type="submit" wire:loading.attr="disabled" class="inline-flex min-h-11 items-center rounded-lg bg-blue-600 px-4 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">{{ __('reports.range.apply') }}</button>
            <div class="flex flex-wrap gap-2" role="group" aria-label="{{ __('reports.range.presets') }}">
                <button type="button" wire:click="showToday" wire:loading.attr="disabled" class="inline-flex min-h-11 items-center rounded-lg px-3 text-sm font-semibold text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50 disabled:opacity-60">{{ __('reports.range.today') }}</button>
                <button type="button" wire:click="showYesterday" wire:loading.attr="disabled" class="inline-flex min-h-11 items-center rounded-lg px-3 text-sm font-semibold text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50 disabled:opacity-60">{{ __('reports.range.yesterday') }}</button>
                <button type="button" wire:click="showLastSevenDays" wire:loading.attr="disabled" class="inline-flex min-h-11 items-center rounded-lg px-3 text-sm font-semibold text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50 disabled:opacity-60">{{ __('reports.range.last_7_days') }}</button>
                <button type="button" wire:click="showThisMonth" wire:loading.attr="disabled" class="inline-flex min-h-11 items-center rounded-lg px-3 text-sm font-semibold text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50 disabled:opacity-60">{{ __('reports.range.this_month') }}</button>
            </div>
            <span wire:loading class="text-sm text-gray-500" role="status">{{ __('reports.range.loading') }}</span>
        </div>
        @if ($rangeError)
            <p id="{{ $id }}-error" role="alert" class="mt-2 text-sm font-medium text-red-700">{{ __('reports.range.'.$rangeError) }}</p>
        @endif
        @if ($note)
            <p class="mt-2 text-xs text-gray-500">{{ $note }}</p>
        @endif
    </fieldset>
</form>
