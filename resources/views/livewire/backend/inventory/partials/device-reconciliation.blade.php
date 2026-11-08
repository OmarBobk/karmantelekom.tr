{{-- Expects: $store, $reconcileUnit (?InventoryUnit), $conditions; Livewire reconcile* and found* properties. --}}
@php
    $outcome = $reconcileOutcome;
    $unitStatus = $reconcileUnit?->status;
    $reasonField = 'mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500';
@endphp
<section class="space-y-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200" aria-labelledby="reconcile-title">
    <div>
        <h2 id="reconcile-title" class="text-base font-semibold text-gray-900">{{ __('inventory.reconcile.title') }}</h2>
        <p class="text-xs text-gray-500">{{ __('inventory.reconcile.help') }}</p>
    </div>

    <form wire:submit="findDevice" class="flex items-end gap-2">
        <div class="min-w-0 flex-1">
            <label for="reconcile-code" class="block text-sm font-medium text-gray-700">{{ __('inventory.reconcile.code') }}</label>
            <input id="reconcile-code" type="text" dir="ltr" autocomplete="off" maxlength="{{ \App\Actions\Inventory\FindUnitsByIdentifier::MAX_INPUT_LENGTH }}" wire:model="reconcileCode" @error('reconcileCode') aria-invalid="true" aria-describedby="reconcile-code-error" @enderror class="mt-1 block min-h-11 w-full rounded-lg border-gray-300 font-mono tracking-wide shadow-sm focus:border-blue-500 focus:ring-blue-500">
        </div>
        <button type="submit" wire:loading.attr="disabled" wire:target="findDevice" class="min-h-11 shrink-0 rounded-lg bg-white px-4 py-2 text-sm font-medium text-gray-800 ring-1 ring-gray-300 hover:bg-gray-50 disabled:opacity-60">{{ __('inventory.reconcile.find') }}</button>
    </form>
    @error('reconcileCode') <p id="reconcile-code-error" class="-mt-2 text-sm text-red-600">{{ $message }}</p> @enderror

    @if ($outcome !== null)
        <div class="space-y-3 rounded-lg bg-gray-50 p-3 ring-1 ring-gray-200" aria-live="polite" data-testid="reconcile-result">
            @if ($outcome === \App\Actions\Inventory\LocateDeviceForReconciliation::AMBIGUOUS)
                <p class="text-sm text-gray-700">{{ __('inventory.reconcile.ambiguous') }}</p>
                <ul class="space-y-2" data-testid="reconcile-candidates">
                    @foreach ($reconcileCandidates as $candidate)
                        <li wire:key="reconcile-candidate-{{ $candidate->id }}" class="flex flex-wrap items-center gap-2 rounded-lg bg-white p-2 ring-1 ring-gray-200">
                            <span class="min-w-0 flex-1 font-mono text-sm" dir="ltr">
                                @foreach ($candidate->identifiers as $identifier)
                                    <span class="me-2 inline-block">{{ $identifier->type->label() }}: {{ $identifier->value }}</span>
                                @endforeach
                            </span>
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset {{ $candidate->status->badgeClasses() }}">{{ $candidate->status->label() }}</span>
                            <button type="button" wire:click="chooseDevice({{ $candidate->id }})" wire:loading.attr="disabled" wire:target="chooseDevice" class="min-h-11 rounded-lg bg-white px-3 py-2 text-sm font-medium text-gray-800 ring-1 ring-gray-300 hover:bg-gray-50 disabled:opacity-60">{{ __('inventory.reconcile.choose') }}</button>
                        </li>
                    @endforeach
                </ul>
            @elseif ($outcome === \App\Actions\Inventory\LocateDeviceForReconciliation::UNIT && $reconcileUnit)
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('subdomain.inventory.unit', [$store, $reconcileUnit]) }}" class="font-mono text-sm font-medium text-blue-700 hover:text-blue-900" dir="ltr">{{ $reconcileUnit->primaryIdentifierValue() }}</a>
                    <span class="rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset {{ $unitStatus->badgeClasses() }}">{{ $unitStatus->label() }}</span>
                </div>
                @php
                    $action = match (true) {
                        $unitStatus === \App\Enums\UnitStatus::REMOVED => 'markFound',
                        in_array($unitStatus, [\App\Enums\UnitStatus::AVAILABLE, \App\Enums\UnitStatus::INSPECTION], true) => 'markMissing',
                        default => null,
                    };
                @endphp
                @if ($action)
                    <form wire:submit="{{ $action }}" class="space-y-3">
                        <p class="text-sm text-gray-700">{{ __("inventory.reconcile.{$action}_help") }}</p>
                        <div>
                            <label for="reconcile-reason" class="block text-sm font-medium text-gray-700">{{ __('inventory.fields.reason') }} <span class="font-normal text-red-600">({{ __('inventory.form.required') }})</span></label>
                            <textarea id="reconcile-reason" rows="2" required wire:model="reconcileReason" @error('reconcileReason') aria-invalid="true" aria-describedby="reconcile-reason-error" @enderror class="{{ $reasonField }}"></textarea>
                            @error('reconcileReason') <p id="reconcile-reason-error" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <button type="submit" wire:loading.attr="disabled" wire:target="{{ $action }}" @class([
                            'min-h-11 w-full rounded-lg px-4 py-2 text-sm font-medium disabled:opacity-60',
                            'bg-red-600 text-white hover:bg-red-700' => $action === 'markMissing',
                            'bg-blue-600 text-white hover:bg-blue-700' => $action === 'markFound',
                        ])>{{ __("inventory.reconcile.{$action}") }}</button>
                    </form>
                @else
                    <p class="text-sm text-gray-700">{{ $unitStatus === \App\Enums\UnitStatus::REVIEW ? __('inventory.reconcile.in_review') : __('inventory.reconcile.no_action', ['status' => $unitStatus->label()]) }}</p>
                @endif
            @elseif ($outcome === \App\Actions\Inventory\LocateDeviceForReconciliation::OTHER_PRODUCT && $reconcileUnit)
                <p class="text-sm text-gray-700">
                    {!! __('inventory.reconcile.other_product', ['product' => '<bdi>'.e($reconcileUnit->productDefinition->name).'</bdi>']) !!}
                    <a href="{{ route('subdomain.inventory.unit', [$store, $reconcileUnit]) }}" class="font-medium text-blue-700 hover:text-blue-900">{{ __('inventory.reconcile.open_device') }}</a>
                </p>
            @elseif ($outcome === \App\Actions\Inventory\LocateDeviceForReconciliation::OTHER_BRANCH)
                <p class="text-sm text-amber-800">{{ __('inventory.reconcile.other_branch') }}</p>
            @elseif ($outcome === \App\Actions\Inventory\LocateDeviceForReconciliation::INVALID)
                <p class="text-sm text-gray-700">{{ __('inventory.reconcile.invalid') }}</p>
            @elseif ($outcome === \App\Actions\Inventory\LocateDeviceForReconciliation::NOT_FOUND)
                <form wire:submit="recordFoundDevice" class="space-y-3">
                    <p class="text-sm text-gray-700">{{ __('inventory.reconcile.not_found_help') }}</p>
                    <fieldset>
                        <legend class="text-sm font-medium text-gray-700">{{ __('inventory.fields.condition') }}</legend>
                        <div class="mt-1 flex flex-wrap gap-2">
                            @foreach ($conditions as $condition)
                                <label @class([
                                    'flex min-h-11 min-w-11 cursor-pointer items-center justify-center rounded-lg border px-3 text-sm font-medium',
                                    'border-blue-500 bg-white text-blue-700' => $foundCondition === $condition->value,
                                    'border-gray-300 bg-white text-gray-700 hover:bg-gray-50' => $foundCondition !== $condition->value,
                                ])>
                                    <input type="radio" wire:model.live="foundCondition" value="{{ $condition->value }}" class="sr-only">
                                    {{ $condition->label() }}
                                </label>
                            @endforeach
                        </div>
                        @error('foundCondition') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </fieldset>
                    @foreach ([
                        ['foundImei', __('inventory.fields.imei'), 'numeric', 15],
                        ['foundSecondaryImei', __('inventory.fields.secondary_imei'), 'numeric', 15],
                        ['foundSerial', __('inventory.fields.serial'), 'text', 64],
                    ] as [$property, $label, $inputMode, $maxLength])
                        <div>
                            <label for="reconcile-{{ $property }}" class="block text-sm font-medium text-gray-700">{{ $label }} <span class="font-normal text-gray-400">({{ __('inventory.form.optional') }})</span></label>
                            <input id="reconcile-{{ $property }}" type="text" inputmode="{{ $inputMode }}" maxlength="{{ $maxLength }}" dir="ltr" autocomplete="off" wire:model="{{ $property }}" @error($property) aria-invalid="true" aria-describedby="reconcile-{{ $property }}-error" @enderror class="mt-1 block min-h-11 w-full rounded-lg border-gray-300 font-mono tracking-wide shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            @error($property) <p id="reconcile-{{ $property }}-error" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @endforeach
                    <div>
                        <label for="reconcile-found-reason" class="block text-sm font-medium text-gray-700">{{ __('inventory.fields.reason') }} <span class="font-normal text-red-600">({{ __('inventory.form.required') }})</span></label>
                        <textarea id="reconcile-found-reason" rows="2" required wire:model="reconcileReason" @error('reconcileReason') aria-invalid="true" aria-describedby="reconcile-found-reason-error" @enderror class="{{ $reasonField }}"></textarea>
                        @error('reconcileReason') <p id="reconcile-found-reason-error" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <button type="submit" wire:loading.attr="disabled" wire:target="recordFoundDevice" class="min-h-11 w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60">{{ __('inventory.reconcile.record_found') }}</button>
                </form>
            @endif
            <button type="button" wire:click="clearReconciliation" class="min-h-11 text-sm font-medium text-gray-600 hover:text-gray-900">{{ __('inventory.reconcile.clear') }}</button>
        </div>
    @endif
</section>
