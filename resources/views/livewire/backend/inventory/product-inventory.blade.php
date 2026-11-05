<div dir="{{ $textDirection }}" lang="{{ $locale }}" class="mx-auto max-w-6xl space-y-6 text-start">
    @include('livewire.backend.inventory.partials.breadcrumbs', ['items' => [
        [__('inventory.title'), route('subdomain.inventory')],
        [$store->code, route('subdomain.inventory.store', $store)],
        [$product->name, null],
    ]])

    <div class="flex flex-col gap-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
            <h1 class="text-2xl font-bold text-gray-900">{{ $product->name }}</h1>
            <p class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-sm text-gray-500">
                @if ($product->model)<bdi>{{ $product->model }}</bdi>@endif
                @if ($product->brand)<bdi>{{ $product->brand->name }}</bdi>@endif
                <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700">{{ $product->tracking_mode->label() }}</span>
                @if ($product->sku)<span dir="ltr" class="font-mono text-xs">{{ $product->sku }}</span>@endif
                @unless ($product->is_active)
                    <span class="rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700">{{ __('catalog.statuses.inactive') }}</span>
                @endunless
            </p>
        </div>
        <div class="shrink-0 rounded-lg bg-gray-50 px-5 py-3 text-center sm:text-end">
            <p class="text-xs font-medium text-gray-500">
                {{ $tracksUnits ? __('inventory.product.available_units') : __('inventory.product.quantity_in_store', ['store' => $store->code]) }}
            </p>
            <p dir="ltr" class="font-mono text-3xl font-bold tabular-nums text-gray-900" data-testid="on-hand">{{ $tracksUnits ? $availableUnits : $quantity }}</p>
        </div>
    </div>

    @if ($canViewPurchases)
        <div class="flex flex-col gap-1 rounded-xl bg-gray-50 px-5 py-3 text-sm ring-1 ring-gray-200 sm:flex-row sm:items-center sm:justify-between">
            <p>
                <span class="font-medium text-gray-700">{{ __('purchases.valuation.unavailable') }}</span>
                <span class="block text-xs text-gray-500">{{ __('purchases.valuation.help') }}</span>
            </p>
            @if ($lastPurchase?->receipt?->purchase)
                <a href="{{ route('subdomain.purchases.show', $lastPurchase->receipt->purchase) }}" class="shrink-0 font-medium text-blue-600 hover:text-blue-800">{!! __('purchases.valuation.last_purchase', [
                    'amount' => view('livewire.backend.purchases.partials.money', ['minor' => \App\Services\Purchasing\Money::fromDecimal($lastPurchase->unit_cost), 'currency' => $lastPurchase->receipt->purchase->currency])->render(),
                ]) !!}</a>
            @endif
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-5">
        <div class="space-y-6 lg:col-span-2">
            @if (! $canAdjust)
                <p class="rounded-xl bg-gray-50 px-4 py-3 text-sm text-gray-600 ring-1 ring-gray-200">{{ __('inventory.product.read_only') }}</p>
            @elseif (! $tracksUnits && ! $hasStoreHistory)
                <form wire:submit="recordOpeningBalance" class="space-y-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200" aria-labelledby="opening-title">
                    <div>
                        <h2 id="opening-title" class="text-base font-semibold text-gray-900">{{ __('inventory.opening.title') }}</h2>
                        <p class="text-xs text-gray-500">{{ __('inventory.opening.help') }}</p>
                    </div>
                    <div>
                        <label for="opening-quantity" class="block text-sm font-medium text-gray-700">{{ __('inventory.fields.quantity') }}</label>
                        <input id="opening-quantity" type="number" min="1" step="1" inputmode="numeric" dir="ltr" wire:model="openingQuantity" @error('openingQuantity') aria-invalid="true" aria-describedby="opening-quantity-error" @enderror class="mt-1 block min-h-11 w-full rounded-lg border-gray-300 font-mono shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        @error('openingQuantity') <p id="opening-quantity-error" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="opening-note" class="block text-sm font-medium text-gray-700">{{ __('inventory.fields.note') }} <span class="font-normal text-gray-400">({{ __('inventory.form.optional') }})</span></label>
                        <textarea id="opening-note" rows="2" wire:model="openingNote" @error('openingNote') aria-invalid="true" @enderror class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                        @error('openingNote') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <button type="submit" wire:loading.attr="disabled" wire:target="recordOpeningBalance" class="min-h-11 w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60">
                        <span wire:loading.remove wire:target="recordOpeningBalance">{{ __('inventory.opening.submit') }}</span>
                        <span wire:loading wire:target="recordOpeningBalance">{{ __('inventory.form.saving') }}</span>
                    </button>
                </form>
            @elseif (! $tracksUnits)
                <form wire:submit="adjust" class="space-y-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200" aria-labelledby="adjust-title">
                    <div>
                        <h2 id="adjust-title" class="text-base font-semibold text-gray-900">{{ __('inventory.adjust.title') }}</h2>
                        <p class="text-xs text-gray-500">{{ __('inventory.adjust.help') }}</p>
                    </div>
                    <fieldset>
                        <legend class="text-sm font-medium text-gray-700">{{ __('inventory.fields.direction') }}</legend>
                        <div class="mt-1 grid grid-cols-2 gap-2">
                            @foreach (['adjustment_decrease' => 'red', 'adjustment_increase' => 'green'] as $value => $color)
                                <label @class([
                                    'flex min-h-11 cursor-pointer items-center justify-center rounded-lg border px-3 text-sm font-medium',
                                    'border-red-300 bg-red-50 text-red-700' => $adjustmentType === $value && $color === 'red',
                                    'border-green-300 bg-green-50 text-green-700' => $adjustmentType === $value && $color === 'green',
                                    'border-gray-300 text-gray-700 hover:bg-gray-50' => $adjustmentType !== $value,
                                ])>
                                    <input type="radio" wire:model.live="adjustmentType" value="{{ $value }}" class="sr-only">
                                    {{ \App\Enums\MovementType::from($value)->label() }}
                                </label>
                            @endforeach
                        </div>
                        @error('adjustmentType') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </fieldset>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="adjust-reason" class="block text-sm font-medium text-gray-700">{{ __('inventory.fields.reason') }}</label>
                            <select id="adjust-reason" wire:model="adjustmentReason" @error('adjustmentReason') aria-invalid="true" @enderror class="mt-1 block min-h-11 w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">{{ __('inventory.form.choose') }}</option>
                                @foreach ($adjustmentReasons as $reason)
                                    <option value="{{ $reason->value }}">{{ $reason->label() }}</option>
                                @endforeach
                            </select>
                            @error('adjustmentReason') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="adjust-quantity" class="block text-sm font-medium text-gray-700">{{ __('inventory.fields.quantity') }}</label>
                            <input id="adjust-quantity" type="number" min="1" step="1" inputmode="numeric" dir="ltr" wire:model="adjustmentQuantity" @error('adjustmentQuantity') aria-invalid="true" aria-describedby="adjust-quantity-error" @enderror class="mt-1 block min-h-11 w-full rounded-lg border-gray-300 font-mono shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        @error('adjustmentQuantity') <p id="adjust-quantity-error" class="col-span-2 -mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="adjust-note" class="block text-sm font-medium text-gray-700">
                            {{ __('inventory.fields.note') }}
                            @if ($adjustmentType === 'adjustment_decrease')
                                <span class="font-normal text-red-600">({{ __('inventory.form.required') }})</span>
                            @else
                                <span class="font-normal text-gray-400">({{ __('inventory.form.optional') }})</span>
                            @endif
                        </label>
                        <textarea id="adjust-note" rows="2" wire:model="adjustmentNote" @error('adjustmentNote') aria-invalid="true" @enderror class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                        @error('adjustmentNote') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <button type="submit" wire:loading.attr="disabled" wire:target="adjust" class="min-h-11 w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60">
                        <span wire:loading.remove wire:target="adjust">{{ __('inventory.adjust.submit') }}</span>
                        <span wire:loading wire:target="adjust">{{ __('inventory.form.saving') }}</span>
                    </button>
                </form>
            @else
                <form wire:submit="registerUnit" class="space-y-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200" aria-labelledby="register-title">
                    <div>
                        <h2 id="register-title" class="text-base font-semibold text-gray-900">{{ __('inventory.register.title') }}</h2>
                        <p class="text-xs text-gray-500">{{ __('inventory.register.help') }}</p>
                    </div>
                    <fieldset>
                        <legend class="text-sm font-medium text-gray-700">{{ __('inventory.fields.condition') }}</legend>
                        <div class="mt-1 flex flex-wrap gap-2">
                            @foreach ($conditions as $condition)
                                <label @class([
                                    'flex min-h-11 min-w-11 cursor-pointer items-center justify-center rounded-lg border px-3 text-sm font-medium',
                                    'border-blue-500 bg-blue-50 text-blue-700' => $unitCondition === $condition->value,
                                    'border-gray-300 text-gray-700 hover:bg-gray-50' => $unitCondition !== $condition->value,
                                ])>
                                    <input type="radio" wire:model.live="unitCondition" value="{{ $condition->value }}" class="sr-only">
                                    {{ $condition->label() }}
                                </label>
                            @endforeach
                        </div>
                        @error('unitCondition') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </fieldset>
                    @foreach ([
                        ['imei', __('inventory.fields.imei'), 'numeric', 15],
                        ['secondaryImei', __('inventory.fields.secondary_imei'), 'numeric', 15],
                        ['serial', __('inventory.fields.serial'), 'text', 64],
                    ] as [$property, $label, $inputMode, $maxLength])
                        <div>
                            <label for="unit-{{ $property }}" class="block text-sm font-medium text-gray-700">
                                {{ $label }}
                                @if ($property !== 'imei') <span class="font-normal text-gray-400">({{ __('inventory.form.optional') }})</span> @endif
                            </label>
                            <input id="unit-{{ $property }}" type="text" inputmode="{{ $inputMode }}" maxlength="{{ $maxLength }}" dir="ltr" autocomplete="off" wire:model="{{ $property }}" @error($property) aria-invalid="true" aria-describedby="unit-{{ $property }}-error" @enderror class="mt-1 block min-h-11 w-full rounded-lg border-gray-300 font-mono tracking-wide shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            @error($property) <p id="unit-{{ $property }}-error" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @endforeach
                    <p class="text-xs text-gray-500">{{ __('inventory.register.identifier_help') }}</p>
                    <div>
                        <label for="unit-note" class="block text-sm font-medium text-gray-700">{{ __('inventory.fields.note') }} <span class="font-normal text-gray-400">({{ __('inventory.form.optional') }})</span></label>
                        <textarea id="unit-note" rows="2" wire:model="unitNote" @error('unitNote') aria-invalid="true" @enderror class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                        @error('unitNote') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <button type="submit" wire:loading.attr="disabled" wire:target="registerUnit" class="min-h-11 w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60">
                        <span wire:loading.remove wire:target="registerUnit">{{ __('inventory.register.submit') }}</span>
                        <span wire:loading wire:target="registerUnit">{{ __('inventory.form.saving') }}</span>
                    </button>
                </form>
            @endif
        </div>

        <div class="space-y-6 lg:col-span-3">
            @if ($tracksUnits)
                <section aria-labelledby="units-title" class="rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-4 py-3">
                        <h2 id="units-title" class="text-base font-semibold text-gray-900">{{ __('inventory.units.title') }}</h2>
                        <div class="flex gap-1 rounded-lg bg-gray-100 p-1 text-sm" role="radiogroup" aria-label="{{ __('inventory.units.filter') }}">
                            @foreach (['available', 'removed', 'all'] as $filter)
                                <label @class(['flex min-h-11 cursor-pointer items-center rounded-md px-3', 'bg-white font-medium text-gray-900 shadow-sm' => $unitStatusFilter === $filter, 'text-gray-600' => $unitStatusFilter !== $filter])>
                                    <input type="radio" wire:model.live="unitStatusFilter" value="{{ $filter }}" class="sr-only">
                                    {{ __("inventory.units.filters.{$filter}") }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                    @if ($units->isEmpty())
                        <p class="px-4 py-10 text-center text-sm text-gray-500">{{ __('inventory.units.empty') }}</p>
                    @else
                        <ul class="divide-y divide-gray-100" wire:loading.class="opacity-60" wire:target="unitStatusFilter">
                            @foreach ($units as $unit)
                                <li wire:key="unit-{{ $unit->id }}">
                                    <a href="{{ route('subdomain.inventory.unit', [$store, $unit]) }}" class="flex min-h-11 flex-wrap items-center gap-x-4 gap-y-1 px-4 py-3 hover:bg-gray-50">
                                        <span dir="ltr" class="font-mono text-sm font-medium text-gray-900">{{ $unit->primaryIdentifierValue() }}</span>
                                        <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700">{{ $unit->condition->label() }}</span>
                                        <span @class(['ms-auto rounded-full px-2.5 py-0.5 text-xs font-medium', 'bg-green-50 text-green-700' => $unit->status === \App\Enums\UnitStatus::AVAILABLE, 'bg-gray-100 text-gray-600' => $unit->status !== \App\Enums\UnitStatus::AVAILABLE])>{{ $unit->status->label() }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                        @if ($units->hasPages())
                            <div class="border-t border-gray-100 px-4 py-3">{{ $units->links() }}</div>
                        @endif
                    @endif
                </section>
            @endif

            @include('livewire.backend.inventory.partials.movement-history', ['movements' => $movements, 'showUnit' => $tracksUnits])
        </div>
    </div>
</div>
