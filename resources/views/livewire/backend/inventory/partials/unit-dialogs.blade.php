{{-- Action dialogs for the unit page. Opening any of them is browser-only; only submitting talks to the server. --}}
@php
    $required = '<span class="font-normal text-red-700">('.e(__('inventory.form.required')).')</span>';
    $label = 'block text-sm font-medium text-gray-800';
    $effect = 'rounded-lg bg-blue-50 px-3 py-2 text-sm text-blue-950 ring-1 ring-inset ring-blue-100';
    $choice = 'flex min-h-11 cursor-pointer items-center gap-3 rounded-lg px-3 py-2 ring-1 ring-gray-200 has-[:checked]:bg-blue-50 has-[:checked]:ring-blue-500';
    $closeDialog = "\$el.closest('dialog').close()";
    $isExternal = $openRepair?->isExternal() ?? false;
    $arrow = $textDirection === 'rtl' ? '←' : '→';
    $descriptionRequired = collect($costCategories)->filter(fn ($category) => $category->requiresDescription())->map(fn ($category) => $category->value)->values();
@endphp

@if ($canInspect)
    <x-operations.dialog id="record-inspection" :title="__('devices.inspection.title')" :direction="$textDirection">
        <form wire:submit="recordInspection" class="space-y-4" novalidate>
            @include('livewire.backend.inventory.partials.stale-form-notice')
            <p class="{{ $effect }}" aria-live="polite">
                <span x-show="$wire.inspectionOutcome === 'ready'">{{ __('devices.inspection.effect_ready', ['from' => $unit->status->label()]) }}</span>
                <span x-show="$wire.inspectionOutcome === 'needs_repair'" x-cloak>{{ __('devices.inspection.effect_needs_repair', ['from' => $unit->status->label()]) }}</span>
                <span x-show="! $wire.inspectionOutcome" x-cloak>{{ __('devices.inspection.effect_choose') }}</span>
            </p>

            <fieldset @error('inspectionOutcome') aria-describedby="inspection-outcome-error" @enderror>
                <legend class="{{ $label }}">{{ __('devices.fields.outcome') }} {!! $required !!}</legend>
                <div class="mt-1 grid gap-2 sm:grid-cols-2">
                    @foreach (\App\Enums\InspectionOutcome::cases() as $outcome)
                        <label class="{{ $choice }}">
                            <input type="radio" wire:model="inspectionOutcome" value="{{ $outcome->value }}" @if ($loop->first) autofocus @endif class="h-4 w-4 border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span class="text-sm font-medium text-gray-900">{{ $outcome->label() }}</span>
                        </label>
                    @endforeach
                </div>
                @error('inspectionOutcome') <p id="inspection-outcome-error" class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </fieldset>

            <div>
                <label for="inspection-condition" class="{{ $label }}">
                    {{ __('devices.fields.grade') }}
                    <span x-show="$wire.inspectionOutcome === 'ready'">{!! $required !!}</span>
                </label>
                <select id="inspection-condition" wire:model="inspectionCondition" aria-describedby="inspection-condition-help @error('inspectionCondition') inspection-condition-error @enderror" @error('inspectionCondition') aria-invalid="true" @enderror @class([$field, $invalid => $errors->has('inspectionCondition')])>
                    <option value="" x-text="$wire.inspectionOutcome === 'ready' ? @js(__('devices.inspection.choose_grade', ['grade' => $unit->condition->label()])) : @js(__('devices.inspection.keep_grade', ['grade' => $unit->condition->label()]))">{{ __('devices.inspection.keep_grade', ['grade' => $unit->condition->label()]) }}</option>
                    @foreach ($usedConditions as $condition)
                        <option value="{{ $condition->value }}">{{ $condition->label() }}</option>
                    @endforeach
                </select>
                <p id="inspection-condition-help" class="mt-1 text-xs text-gray-600">{{ __('devices.inspection.for_parts_hint') }}</p>
                @error('inspectionCondition') <p id="inspection-condition-error" class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="inspection-findings" class="{{ $label }}">
                    {{ __('devices.fields.findings') }}
                    <span x-show="$wire.inspectionOutcome === 'needs_repair'" x-cloak>{!! $required !!}</span>
                </label>
                <textarea id="inspection-findings" rows="3" wire:model="inspectionFindings" @error('inspectionFindings') aria-invalid="true" aria-describedby="inspection-findings-error" @enderror @class(['mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500', $invalid => $errors->has('inspectionFindings')])></textarea>
                @error('inspectionFindings') <p id="inspection-findings-error" class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>

            <div class="flex flex-wrap justify-end gap-2 border-t border-gray-100 pt-4">
                <button type="button" x-on:click="{{ $closeDialog }}" class="{{ $secondaryButton }}">{{ __('devices.actions.close') }}</button>
                <button type="submit" wire:loading.attr="disabled" wire:target="recordInspection" class="{{ $primaryButton }}">
                    <span wire:loading.remove wire:target="recordInspection">{{ __('devices.inspection.submit') }}</span>
                    <span wire:loading wire:target="recordInspection">{{ __('inventory.form.saving') }}</span>
                </button>
            </div>
        </form>
    </x-operations.dialog>
@endif

@if ($canStartRepair)
    <x-operations.dialog id="start-repair" :title="__('devices.repair.title')" :direction="$textDirection">
        <form wire:submit="startRepair" class="space-y-4" novalidate>
            @include('livewire.backend.inventory.partials.stale-form-notice')
            <p class="{{ $effect }}">
                {{ $unit->status->label() }} <span aria-hidden="true">{{ $arrow }}</span><span class="sr-only">{{ __('devices.to') }}</span> {{ \App\Enums\UnitStatus::IN_REPAIR->label() }}.
                {{ __('devices.repair.effect') }}
            </p>

            <fieldset>
                <legend class="{{ $label }}">{{ __('devices.fields.repairer_type') }} {!! $required !!}</legend>
                <div class="mt-1 grid gap-2 sm:grid-cols-2">
                    @foreach (\App\Enums\RepairerType::cases() as $type)
                        <label class="{{ $choice }}">
                            <input type="radio" wire:model="repairerType" value="{{ $type->value }}" @if ($loop->first) autofocus @endif class="h-4 w-4 border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span class="text-sm font-medium text-gray-900">{{ $type->label() }}</span>
                        </label>
                    @endforeach
                </div>
                @error('repairerType') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </fieldset>

            <div>
                <label for="repairer-name" class="{{ $label }}">
                    {{ __('devices.fields.repairer_name') }}
                    <span x-show="$wire.repairerType === 'external'">{!! $required !!}</span>
                </label>
                <input id="repairer-name" type="text" maxlength="120" autocomplete="off" wire:model="repairerName" @error('repairerName') aria-invalid="true" aria-describedby="repairer-name-error" @enderror @class([$field, $invalid => $errors->has('repairerName')])>
                @error('repairerName') <p id="repairer-name-error" class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="repair-problem" class="{{ $label }}">{{ __('devices.fields.problem') }} {!! $required !!}</label>
                <textarea id="repair-problem" rows="3" wire:model="repairProblem" @error('repairProblem') aria-invalid="true" aria-describedby="repair-problem-error" @enderror @class(['mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500', $invalid => $errors->has('repairProblem')])></textarea>
                @error('repairProblem') <p id="repair-problem-error" class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>

            <div class="flex flex-wrap justify-end gap-2 border-t border-gray-100 pt-4">
                <button type="button" x-on:click="{{ $closeDialog }}" class="{{ $secondaryButton }}">{{ __('devices.actions.close') }}</button>
                <button type="submit" wire:loading.attr="disabled" wire:target="startRepair" class="{{ $primaryButton }}">
                    <span wire:loading.remove wire:target="startRepair">{{ __('devices.repair.submit') }}</span>
                    <span wire:loading wire:target="startRepair">{{ __('inventory.form.saving') }}</span>
                </button>
            </div>
        </form>
    </x-operations.dialog>
@endif

@if ($canCloseRepair)
    <x-operations.dialog id="complete-repair" :title="__('devices.complete.title')" :direction="$textDirection" class="max-w-2xl">
        <form wire:submit="completeRepair" class="space-y-4" novalidate>
            @include('livewire.backend.inventory.partials.stale-form-notice')
            <p class="{{ $effect }}">
                {{ $unit->status->label() }} <span aria-hidden="true">{{ $arrow }}</span><span class="sr-only">{{ __('devices.to') }}</span> {{ \App\Enums\UnitStatus::INSPECTION->label() }}.
                {{ __('devices.complete.effect') }}
            </p>

            <div>
                <label for="completion-work" class="{{ $label }}">{{ __('devices.fields.work_performed') }} {!! $required !!}</label>
                <textarea id="completion-work" rows="3" autofocus wire:model="completionWork" @error('completionWork') aria-invalid="true" aria-describedby="completion-work-error" @enderror @class(['mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500', $invalid => $errors->has('completionWork')])></textarea>
                @error('completionWork') <p id="completion-work-error" class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>

            @if ($isExternal)
                @include('livewire.backend.inventory.partials.return-confirmation', ['model' => 'completionReturned'])
            @endif

            @if ($canManageCosts)
                <fieldset class="space-y-3 rounded-lg p-3 ring-1 ring-gray-200">
                    <legend class="px-1 text-sm font-semibold text-gray-900">{{ __('devices.complete.costs') }}</legend>
                    <p class="text-xs text-gray-600">{{ __('devices.complete.costs_help') }}</p>
                    @foreach (['parts' => \App\Enums\DeviceCostCategory::REPAIR_PARTS, 'labor' => \App\Enums\DeviceCostCategory::REPAIR_LABOR] as $prefix => $category)
                        <div class="space-y-2">
                            <div>
                                <label for="{{ $prefix }}-mode" class="{{ $label }}">{{ $category->label() }}</label>
                                <select id="{{ $prefix }}-mode" wire:model="{{ $prefix }}Mode" @class([$field, $invalid => $errors->has($prefix.'Mode')])>
                                    <option value="pending">{{ __('devices.cost.mode_pending') }}</option>
                                    <option value="known">{{ __('devices.cost.mode_known') }}</option>
                                    <option value="unknown">{{ __('devices.cost.mode_unknown') }}</option>
                                </select>
                                @error($prefix.'Mode') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                            </div>
                            <div x-show="$wire.{{ $prefix }}Mode === 'known'" x-cloak>
                                @include('livewire.backend.inventory.partials.amount-field', ['id' => $prefix.'-amount', 'amount' => $prefix.'Amount', 'currency' => $prefix.'Currency'])
                            </div>
                            @if ($prefix === 'parts')
                                <div x-show="$wire.partsMode !== 'pending'" x-cloak>
                                    <label for="parts-description" class="{{ $label }}">{{ __('devices.fields.part_description') }} {!! $required !!}</label>
                                    <input id="parts-description" type="text" maxlength="255" wire:model="partsDescription" @error('partsDescription') aria-invalid="true" aria-describedby="parts-description-error" @enderror @class([$field, $invalid => $errors->has('partsDescription')])>
                                    @error('partsDescription') <p id="parts-description-error" class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                                </div>
                            @endif
                        </div>
                    @endforeach
                    <p class="text-xs text-gray-600">{{ __('devices.cost.no_stock_effect') }}</p>
                </fieldset>
            @else
                <p class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-950 ring-1 ring-inset ring-amber-200">{{ __('devices.complete.costing_pending_note') }}</p>
            @endif

            <div class="flex flex-wrap justify-end gap-2 border-t border-gray-100 pt-4">
                <button type="button" x-on:click="{{ $closeDialog }}" class="{{ $secondaryButton }}">{{ __('devices.actions.close') }}</button>
                <button type="submit" wire:loading.attr="disabled" wire:target="completeRepair" class="{{ $primaryButton }}">
                    <span wire:loading.remove wire:target="completeRepair">{{ __('devices.complete.submit') }}</span>
                    <span wire:loading wire:target="completeRepair">{{ __('inventory.form.saving') }}</span>
                </button>
            </div>
        </form>
    </x-operations.dialog>

    <x-operations.dialog id="cancel-repair" :title="__('devices.cancel.title')" :direction="$textDirection">
        <form wire:submit="cancelRepair" class="space-y-4" novalidate>
            @include('livewire.backend.inventory.partials.stale-form-notice')
            <p class="{{ $effect }}">
                {{ $unit->status->label() }} <span aria-hidden="true">{{ $arrow }}</span><span class="sr-only">{{ __('devices.to') }}</span> {{ \App\Enums\UnitStatus::INSPECTION->label() }}.
                {{ __('devices.cancel.effect') }}
            </p>

            <div>
                <label for="cancel-reason" class="{{ $label }}">{{ __('devices.fields.close_reason') }} {!! $required !!}</label>
                <textarea id="cancel-reason" rows="3" autofocus wire:model="cancelReason" @error('cancelReason') aria-invalid="true" aria-describedby="cancel-reason-error" @enderror @class(['mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500', $invalid => $errors->has('cancelReason')])></textarea>
                @error('cancelReason') <p id="cancel-reason-error" class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>

            @if ($isExternal)
                @include('livewire.backend.inventory.partials.return-confirmation', ['model' => 'cancelReturned'])
            @endif

            <div class="flex flex-wrap justify-end gap-2 border-t border-gray-100 pt-4">
                <button type="button" x-on:click="{{ $closeDialog }}" class="{{ $secondaryButton }}">{{ __('devices.actions.close') }}</button>
                <button type="submit" wire:loading.attr="disabled" wire:target="cancelRepair" class="{{ $primaryButton }}">
                    <span wire:loading.remove wire:target="cancelRepair">{{ __('devices.cancel.submit') }}</span>
                    <span wire:loading wire:target="cancelRepair">{{ __('inventory.form.saving') }}</span>
                </button>
            </div>
        </form>
    </x-operations.dialog>
@endif

@if ($canRecordCost)
    <x-operations.dialog id="record-cost" :title="__('devices.cost.title')" :direction="$textDirection">
        <form wire:submit="recordCost" class="space-y-4" novalidate x-data="{ describe: @js($descriptionRequired) }">
            <p class="{{ $effect }}">{{ __('devices.cost.effect') }}</p>

            <div>
                <label for="cost-category" class="{{ $label }}">{{ __('devices.fields.category') }} {!! $required !!}</label>
                <select id="cost-category" autofocus wire:model="costCategory" @error('costCategory') aria-invalid="true" aria-describedby="cost-category-error" @enderror @class([$field, $invalid => $errors->has('costCategory')])>
                    <option value="">{{ __('inventory.form.choose') }}</option>
                    @foreach ($costCategories as $category)
                        <option value="{{ $category->value }}">{{ $category->label() }}</option>
                    @endforeach
                </select>
                @error('costCategory') <p id="cost-category-error" class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="cost-description" class="{{ $label }}">
                    {{ __('devices.fields.description') }}
                    <span x-show="describe.includes($wire.costCategory)" x-cloak>{!! $required !!}</span>
                </label>
                <input id="cost-description" type="text" maxlength="255" wire:model="costDescription" @error('costDescription') aria-invalid="true" aria-describedby="cost-description-error" @enderror @class([$field, $invalid => $errors->has('costDescription')])>
                @error('costDescription') <p id="cost-description-error" class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>

            <fieldset>
                <legend class="{{ $label }}">{{ __('devices.fields.amount') }} {!! $required !!}</legend>
                <div class="mt-1 grid gap-2 sm:grid-cols-2">
                    <label class="{{ $choice }}"><input type="radio" wire:model="costMode" value="known" class="h-4 w-4 border-gray-300 text-blue-600 focus:ring-blue-500"><span class="text-sm font-medium text-gray-900">{{ __('devices.cost.mode_known') }}</span></label>
                    <label class="{{ $choice }}"><input type="radio" wire:model="costMode" value="unknown" class="h-4 w-4 border-gray-300 text-blue-600 focus:ring-blue-500"><span class="text-sm font-medium text-gray-900">{{ __('devices.cost.mode_unknown') }}</span></label>
                </div>
            </fieldset>
            <div x-show="$wire.costMode === 'known'">
                @include('livewire.backend.inventory.partials.amount-field', ['id' => 'cost-amount', 'amount' => 'costAmount', 'currency' => 'costCurrency'])
            </div>

            @if ($repairs->isNotEmpty())
                <div>
                    <label for="cost-repair" class="{{ $label }}">{{ __('devices.fields.repair') }}</label>
                    <select id="cost-repair" wire:model="costRepairId" @error('costRepairId') aria-invalid="true" aria-describedby="cost-repair-error" @enderror @class([$field, $invalid => $errors->has('costRepairId')])>
                        <option value="">{{ __('devices.cost.no_repair') }}</option>
                        @foreach ($repairs as $repair)
                            <option value="{{ $repair->id }}">#{{ $repair->id }} · {{ $repair->status->label() }} · {{ $repair->started_at->format('Y-m-d') }}</option>
                        @endforeach
                    </select>
                    @error('costRepairId') <p id="cost-repair-error" class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
            @endif

            <p class="text-xs text-gray-600">{{ __('devices.cost.no_stock_effect') }}</p>

            <div class="flex flex-wrap justify-end gap-2 border-t border-gray-100 pt-4">
                <button type="button" x-on:click="{{ $closeDialog }}" class="{{ $secondaryButton }}">{{ __('devices.actions.close') }}</button>
                <button type="submit" wire:loading.attr="disabled" wire:target="recordCost" class="{{ $primaryButton }}">
                    <span wire:loading.remove wire:target="recordCost">{{ __('devices.cost.submit') }}</span>
                    <span wire:loading wire:target="recordCost">{{ __('inventory.form.saving') }}</span>
                </button>
            </div>
        </form>
    </x-operations.dialog>

    <x-operations.dialog id="reverse-cost" :title="__('devices.reversal.title')" :direction="$textDirection">
        <form wire:submit="reverseCost" class="space-y-4" novalidate>
            <div class="rounded-lg bg-gray-50 px-3 py-2 ring-1 ring-inset ring-gray-200">
                <p class="text-xs font-medium text-gray-600">{{ __('devices.reversal.original') }}</p>
                <p class="flex flex-wrap justify-between gap-2 text-sm text-gray-900"><bdi x-text="reversing.label"></bdi> <bdi dir="ltr" class="font-semibold tabular-nums" x-text="reversing.amount"></bdi></p>
            </div>
            <p class="{{ $effect }}">{{ __('devices.reversal.effect') }}</p>

            <div>
                <label for="reversal-reason" class="{{ $label }}">{{ __('devices.fields.reversal_reason') }} {!! $required !!}</label>
                <textarea id="reversal-reason" rows="2" autofocus wire:model="reversalReason" @error('reversalReason') aria-invalid="true" aria-describedby="reversal-reason-error" @enderror @class(['mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500', $invalid => $errors->has('reversalReason')])></textarea>
                @error('reversalReason') <p id="reversal-reason-error" class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>

            <fieldset>
                <legend class="{{ $label }}">{{ __('devices.reversal.replacement') }}</legend>
                <div class="mt-1 grid gap-2 sm:grid-cols-3">
                    @foreach (['none', 'known', 'unknown'] as $mode)
                        <label class="{{ $choice }}"><input type="radio" wire:model="replacementMode" value="{{ $mode }}" class="h-4 w-4 border-gray-300 text-blue-600 focus:ring-blue-500"><span class="text-sm font-medium text-gray-900">{{ __('devices.reversal.mode_'.$mode) }}</span></label>
                    @endforeach
                </div>
            </fieldset>
            <div x-show="$wire.replacementMode === 'known'" x-cloak>
                @include('livewire.backend.inventory.partials.amount-field', ['id' => 'replacement-amount', 'amount' => 'replacementAmount', 'currency' => 'replacementCurrency'])
            </div>
            <div x-show="$wire.replacementMode !== 'none'" x-cloak>
                <label for="replacement-description" class="{{ $label }}">{{ __('devices.fields.description') }}</label>
                <input id="replacement-description" type="text" maxlength="255" wire:model="replacementDescription" @error('replacementDescription') aria-invalid="true" aria-describedby="replacement-description-error" @enderror @class([$field, $invalid => $errors->has('replacementDescription')])>
                @error('replacementDescription') <p id="replacement-description-error" class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>

            <div class="flex flex-wrap justify-end gap-2 border-t border-gray-100 pt-4">
                <button type="button" x-on:click="{{ $closeDialog }}" class="{{ $secondaryButton }}">{{ __('devices.actions.close') }}</button>
                <button type="submit" wire:loading.attr="disabled" wire:target="reverseCost" class="{{ $primaryButton }}">
                    <span wire:loading.remove wire:target="reverseCost">{{ __('devices.reversal.submit') }}</span>
                    <span wire:loading wire:target="reverseCost">{{ __('inventory.form.saving') }}</span>
                </button>
            </div>
        </form>
    </x-operations.dialog>
@endif

@if ($canCorrectIdentity)
    <x-operations.dialog id="correct-identifier" :title="__('devices.correction.title')" :direction="$textDirection">
        <form wire:submit="correctIdentifier" class="space-y-4" novalidate>
            <p class="{{ $effect }}">{{ __('devices.correction.effect') }}</p>

            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <p class="{{ $label }}">{{ __('devices.correction.current') }} <span class="font-normal text-gray-600" x-text="correcting.type"></span></p>
                    <p dir="ltr" class="mt-1 flex min-h-11 items-center rounded-lg bg-gray-50 px-3 text-start font-mono text-base text-gray-700 ring-1 ring-inset ring-gray-200 break-all" x-text="correcting.value"></p>
                </div>
                <div>
                    <label for="new-identifier" class="{{ $label }}">{{ __('devices.correction.proposed') }} {!! $required !!}</label>
                    <input id="new-identifier" type="text" dir="ltr" autofocus autocomplete="off" spellcheck="false" maxlength="64"
                        x-bind:inputmode="correcting.imei ? 'numeric' : 'text'"
                        wire:model="newIdentifierValue"
                        @error('newIdentifierValue') aria-invalid="true" aria-describedby="new-identifier-error" @enderror
                        @class([$field, 'text-start font-mono text-base', $invalid => $errors->has('newIdentifierValue')])>
                    @error('newIdentifierValue') <p id="new-identifier-error" class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label for="correction-reason" class="{{ $label }}">{{ __('devices.fields.correction_reason') }} {!! $required !!}</label>
                <textarea id="correction-reason" rows="2" wire:model="correctionReason" @error('correctionReason') aria-invalid="true" aria-describedby="correction-reason-error" @enderror @class(['mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500', $invalid => $errors->has('correctionReason')])></textarea>
                @error('correctionReason') <p id="correction-reason-error" class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="flex min-h-11 cursor-pointer items-start gap-3 rounded-lg bg-amber-50 px-3 py-2 ring-1 ring-inset ring-amber-200">
                    <input type="checkbox" wire:model="correctionConfirmed" @error('correctionConfirmed') aria-invalid="true" aria-describedby="correction-confirmed-error" @enderror class="mt-0.5 h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    <span class="text-sm text-amber-950">{{ __('devices.correction.confirm') }} {!! $required !!}</span>
                </label>
                @error('correctionConfirmed') <p id="correction-confirmed-error" class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>

            <div class="flex flex-wrap justify-end gap-2 border-t border-gray-100 pt-4">
                <button type="button" x-on:click="{{ $closeDialog }}" class="{{ $secondaryButton }}">{{ __('devices.actions.close') }}</button>
                <button type="submit" wire:loading.attr="disabled" wire:target="correctIdentifier" class="{{ $primaryButton }}">
                    <span wire:loading.remove wire:target="correctIdentifier">{{ __('devices.correction.submit') }}</span>
                    <span wire:loading wire:target="correctIdentifier">{{ __('inventory.form.saving') }}</span>
                </button>
            </div>
        </form>
    </x-operations.dialog>
@endif

@if ($canRemove)
    <x-operations.dialog id="remove-unit" :title="__('inventory.remove.title')" :direction="$textDirection">
        <form wire:submit="remove" class="space-y-4" novalidate>
            @include('livewire.backend.inventory.partials.stale-form-notice')
            <p class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-950 ring-1 ring-inset ring-red-200">
                {{ $unit->status->label() }} <span aria-hidden="true">{{ $arrow }}</span><span class="sr-only">{{ __('devices.to') }}</span> {{ \App\Enums\UnitStatus::REMOVED->label() }}.
                {{ __('inventory.remove.help') }}
            </p>
            <div>
                <label for="removal-reason" class="{{ $label }}">{{ __('inventory.fields.reason') }} {!! $required !!}</label>
                <select id="removal-reason" autofocus wire:model="removalReason" @error('removalReason') aria-invalid="true" aria-describedby="removal-reason-error" @enderror @class([$field, $invalid => $errors->has('removalReason')])>
                    <option value="">{{ __('inventory.form.choose') }}</option>
                    @foreach ($removalReasons as $reason)
                        <option value="{{ $reason->value }}">{{ $reason->label() }}</option>
                    @endforeach
                </select>
                @error('removalReason') <p id="removal-reason-error" class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="removal-note" class="{{ $label }}">{{ __('inventory.fields.note') }} {!! $required !!}</label>
                <textarea id="removal-note" rows="2" wire:model="removalNote" @error('removalNote') aria-invalid="true" aria-describedby="removal-note-error" @enderror @class(['mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500', $invalid => $errors->has('removalNote')])></textarea>
                @error('removalNote') <p id="removal-note-error" class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>
            <div class="flex flex-wrap justify-end gap-2 border-t border-gray-100 pt-4">
                <button type="button" x-on:click="{{ $closeDialog }}" class="{{ $secondaryButton }}">{{ __('devices.actions.close') }}</button>
                <button type="submit" wire:loading.attr="disabled" wire:target="remove" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600 disabled:cursor-wait disabled:opacity-60">
                    <span wire:loading.remove wire:target="remove">{{ __('inventory.remove.submit') }}</span>
                    <span wire:loading wire:target="remove">{{ __('inventory.form.saving') }}</span>
                </button>
            </div>
        </form>
    </x-operations.dialog>
@endif

@if ($canSetUnitPrice)
    <x-operations.dialog id="unit-price" :title="__('pos.pricing.set_unit_price')" :direction="$textDirection">
        <form wire:submit="saveSellingPrice" class="space-y-4" novalidate>
            <p class="{{ $effect }}">{{ __('pos.pricing.unit_help') }}</p>
            <div>
                <label for="unit-selling-price" class="{{ $label }}">{{ __('pos.pricing.unit_price') }} (TRY)</label>
                <input id="unit-selling-price" type="text" inputmode="decimal" dir="ltr" autocomplete="off" wire:model="unitSellingPrice" @error('selling_price') aria-invalid="true" aria-describedby="unit-selling-price-error" @enderror @class([$field, 'font-mono', $invalid => $errors->has('selling_price')])>
                @error('selling_price') <p id="unit-selling-price-error" class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="unit-minimum-price" class="{{ $label }}">{{ __('pos.pricing.minimum_price') }} (TRY)</label>
                <input id="unit-minimum-price" type="text" inputmode="decimal" dir="ltr" autocomplete="off" wire:model="unitMinimumPrice" aria-describedby="unit-minimum-price-help @error('minimum_selling_price') unit-minimum-price-error @enderror" @error('minimum_selling_price') aria-invalid="true" @enderror @class([$field, 'font-mono', $invalid => $errors->has('minimum_selling_price')])>
                <p id="unit-minimum-price-help" class="mt-1 text-xs text-gray-600">{{ __('pos.pricing.minimum_help') }}</p>
                @error('minimum_selling_price') <p id="unit-minimum-price-error" class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>
            <div class="flex flex-wrap justify-end gap-2 border-t border-gray-100 pt-4">
                <button type="button" x-on:click="{{ $closeDialog }}" class="{{ $secondaryButton }}">{{ __('devices.actions.close') }}</button>
                <button type="submit" wire:loading.attr="disabled" wire:target="saveSellingPrice" class="{{ $primaryButton }}">{{ __('pos.pricing.save') }}</button>
            </div>
        </form>
    </x-operations.dialog>
@endif
