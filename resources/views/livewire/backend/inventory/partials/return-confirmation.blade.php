{{-- Expects: $model (property name), $openRepair, $store. External repairs close only once the device is physically back. --}}
<div>
    <label class="flex min-h-11 cursor-pointer items-start gap-3 rounded-lg bg-amber-50 px-3 py-2 ring-1 ring-inset ring-amber-200">
        <input type="checkbox" wire:model="{{ $model }}" @error($model) aria-invalid="true" aria-describedby="{{ $model }}-error" @enderror class="mt-0.5 h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
        <span class="text-sm text-amber-950">
            {!! __('devices.repair.confirm_returned', ['name' => '<bdi class="font-semibold">'.e($openRepair->repairer_name).'</bdi>', 'store' => '<bdi dir="ltr" class="font-mono">'.e($store->code).'</bdi>']) !!}
            <span class="font-normal text-red-700">({{ __('inventory.form.required') }})</span>
        </span>
    </label>
    @error($model) <p id="{{ $model }}-error" class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
</div>
