<div dir="{{ $textDirection }}" lang="{{ $locale }}" class="mx-auto max-w-6xl space-y-6 text-start">
    @include('livewire.backend.inventory.partials.breadcrumbs', ['items' => [
        [__('inventory.title'), route('subdomain.inventory')],
        [$store->code, route('subdomain.inventory.store', $store)],
        [$unit->productDefinition->name, route('subdomain.inventory.product', [$store, $unit->productDefinition])],
        [$unit->primaryIdentifierValue() ?? '#'.$unit->id, null],
    ]])

    <div class="grid gap-6 lg:grid-cols-5">
        <div class="space-y-6 lg:col-span-2">
            <section aria-labelledby="unit-title" class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h1 id="unit-title" class="text-xl font-bold text-gray-900">{{ $unit->productDefinition->name }}</h1>
                        <p class="text-sm text-gray-500">{{ $unit->productDefinition->tracking_mode->label() }}</p>
                    </div>
                    <span @class(['shrink-0 rounded-full px-3 py-1 text-sm font-medium', 'bg-green-50 text-green-700' => $unit->status === \App\Enums\UnitStatus::AVAILABLE, 'bg-gray-100 text-gray-700' => $unit->status !== \App\Enums\UnitStatus::AVAILABLE])>
                        {{ $unit->status->label() }}
                    </span>
                </div>

                <dl class="mt-5 space-y-3">
                    @foreach ($unit->identifiers as $identifier)
                        <div>
                            <dt class="text-xs font-medium text-gray-500">
                                {{ $identifier->type->label() }}@if ($identifier->type === \App\Enums\IdentifierType::IMEI && $unit->identifiers->where('type', \App\Enums\IdentifierType::IMEI)->count() > 1) {{ $identifier->position }}@endif
                            </dt>
                            <dd dir="ltr" class="text-start font-mono text-lg tracking-wide text-gray-900">{{ $identifier->value }}</dd>
                        </div>
                    @endforeach
                    <div class="grid grid-cols-2 gap-3 border-t border-gray-100 pt-3">
                        <div>
                            <dt class="text-xs font-medium text-gray-500">{{ __('inventory.fields.condition') }}</dt>
                            <dd class="text-sm font-medium text-gray-900">{{ $unit->condition->label() }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500">{{ __('inventory.fields.store') }}</dt>
                            <dd class="text-sm font-medium text-gray-900"><span dir="ltr" class="font-mono">{{ $store->code }}</span></dd>
                        </div>
                        @if ($unit->removed_at)
                            <div class="col-span-2">
                                <dt class="text-xs font-medium text-gray-500">{{ __('inventory.fields.removed_at') }}</dt>
                                <dd dir="ltr" class="text-start text-sm text-gray-900">{{ $unit->removed_at->format('Y-m-d H:i') }}</dd>
                            </div>
                        @endif
                    </div>
                </dl>
            </section>

            @if ($canRemove)
                <form wire:submit="remove" wire:confirm="{{ __('inventory.remove.confirm') }}" class="space-y-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200" aria-labelledby="remove-title">
                    <div>
                        <h2 id="remove-title" class="text-base font-semibold text-gray-900">{{ __('inventory.remove.title') }}</h2>
                        <p class="text-xs text-gray-500">{{ __('inventory.remove.help') }}</p>
                    </div>
                    <div>
                        <label for="removal-reason" class="block text-sm font-medium text-gray-700">{{ __('inventory.fields.reason') }}</label>
                        <select id="removal-reason" wire:model="removalReason" @error('removalReason') aria-invalid="true" aria-describedby="removal-reason-error" @enderror class="mt-1 block min-h-11 w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">{{ __('inventory.form.choose') }}</option>
                            @foreach ($removalReasons as $reason)
                                <option value="{{ $reason->value }}">{{ $reason->label() }}</option>
                            @endforeach
                        </select>
                        @error('removalReason') <p id="removal-reason-error" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="removal-note" class="block text-sm font-medium text-gray-700">{{ __('inventory.fields.note') }} <span class="font-normal text-red-600">({{ __('inventory.form.required') }})</span></label>
                        <textarea id="removal-note" rows="2" wire:model="removalNote" @error('removalNote') aria-invalid="true" @enderror class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                        @error('removalNote') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <button type="submit" wire:loading.attr="disabled" wire:target="remove" class="min-h-11 w-full rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-60">
                        <span wire:loading.remove wire:target="remove">{{ __('inventory.remove.submit') }}</span>
                        <span wire:loading wire:target="remove">{{ __('inventory.form.saving') }}</span>
                    </button>
                </form>
            @elseif ($unit->status === \App\Enums\UnitStatus::REMOVED)
                <p class="rounded-xl bg-gray-50 px-4 py-3 text-sm text-gray-600 ring-1 ring-gray-200">{{ __('inventory.remove.already_removed') }}</p>
            @endif
        </div>

        <div class="lg:col-span-3">
            @include('livewire.backend.inventory.partials.movement-history', ['movements' => $movements, 'showUnit' => false])
        </div>
    </div>
</div>
