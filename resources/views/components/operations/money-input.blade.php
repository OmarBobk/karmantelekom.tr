{{--
    TRY amount field in the Karman financial format ("10.000,50"), formatted locally while typing.
    Bind with wire:model="property"; edits stay local until the next action.
    The property holds the localized text; the Livewire action converts it with LocalizedMoney.
--}}
@props(['id', 'label', 'error', 'placeholder' => null, 'currency' => 'TRY', 'labelClass' => 'block text-sm font-medium text-gray-800', 'inputClass' => ''])

@php
    $model = $attributes->wire('model');
    $hintId = $id.'-format';
    $errorId = $id.'-error';
    $hasError = $errors->has($error);
@endphp

<div
    x-data="moneyInput({ model: @js($model->value()) })"
    {{ $attributes->whereDoesntStartWith('wire:model') }}
>
    <label for="{{ $id }}" class="{{ $labelClass }}">{{ $label }}</label>
    <div dir="ltr" class="relative mt-1">
        <input
            x-ref="input"
            x-on:beforeinput="beforeInput($event)"
            x-on:input="onInput($event)"
            id="{{ $id }}"
            type="text"
            inputmode="decimal"
            autocomplete="off"
            spellcheck="false"
            enterkeyhint="done"
            @if ($placeholder !== null) placeholder="{{ $placeholder }}" @endif
            @if ($hasError) aria-invalid="true" @else x-bind:aria-invalid="invalid ? 'true' : null" @endif
            @if ($hasError) aria-describedby="{{ $errorId }}" @else x-bind:aria-describedby="invalid ? @js($hintId) : null" @endif
            class="block min-h-11 w-full rounded-lg border-gray-300 pe-14 text-start font-mono tabular-nums shadow-sm focus:border-blue-500 focus:ring-blue-500 {{ $inputClass }}"
        >
        <span class="pointer-events-none absolute inset-y-0 end-0 flex items-center pe-3 text-sm font-semibold text-gray-600">{{ $currency }}</span>
    </div>
    <p id="{{ $hintId }}" x-show="invalid && ! @js($hasError)" x-cloak class="mt-1 text-sm text-amber-800">{{ __('pos.errors.amount_format', ['example' => \App\Services\Purchasing\LocalizedMoney::EXAMPLE]) }}</p>
    @if ($hasError)
        <p id="{{ $errorId }}" class="mt-1 text-sm text-red-700" role="alert">{{ $errors->first($error) }}</p>
    @endif
</div>
