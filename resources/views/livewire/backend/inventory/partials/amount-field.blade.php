{{-- Expects: $id, $amount (property name), $currency (property name), $currencies, $field, $invalid. The currency always sits beside the amount. --}}
<div class="grid grid-cols-[1fr_auto] gap-2">
    <div>
        <label for="{{ $id }}" class="block text-sm font-medium text-gray-800">{{ __('devices.fields.amount') }} <span class="font-normal text-red-700">({{ __('inventory.form.required') }})</span></label>
        <input id="{{ $id }}" type="text" inputmode="decimal" dir="ltr" autocomplete="off" placeholder="0.00" wire:model="{{ $amount }}"
            @error($amount) aria-invalid="true" aria-describedby="{{ $id }}-error" @enderror
            @class([$field, 'text-start tabular-nums', $invalid => $errors->has($amount)])>
    </div>
    <div>
        <label for="{{ $id }}-currency" class="block text-sm font-medium text-gray-800">{{ __('purchases.fields.currency') }}</label>
        <select id="{{ $id }}-currency" wire:model="{{ $currency }}" @error($currency) aria-invalid="true" @enderror @class([$field, 'w-28', $invalid => $errors->has($currency)])>
            @foreach ($currencies as $option)
                <option value="{{ $option->value }}">{{ $option->value }}</option>
            @endforeach
        </select>
    </div>
</div>
@error($amount) <p id="{{ $id }}-error" class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
@error($currency) <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
