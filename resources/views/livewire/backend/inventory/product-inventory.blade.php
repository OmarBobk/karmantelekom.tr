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
            @elseif (! $tracksUnits && ! $hasStoreHistory && $setupOpen)
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
                <section class="space-y-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200" aria-labelledby="count-title">
                    <div>
                        <h2 id="count-title" class="text-base font-semibold text-gray-900">{{ __('inventory.count.title') }}</h2>
                        <p class="text-xs text-gray-500">{{ $countInProgress ? __('inventory.count.in_progress_help') : __('inventory.count.help') }}</p>
                    </div>
                    @if (! $countInProgress)
                        <button type="button" wire:click="startCount" wire:loading.attr="disabled" wire:target="startCount" class="min-h-11 w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60">{{ __('inventory.count.start') }}</button>
                    @else
                        <form wire:submit="recordCount" class="space-y-4">
                            @error('count')
                                <div role="alert" class="space-y-2 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800 ring-1 ring-amber-200">
                                    <p>{{ $message }}</p>
                                    <button type="button" wire:click="startCount" class="min-h-11 font-medium text-amber-900 underline">{{ __('inventory.count.restart') }}</button>
                                </div>
                            @enderror
                            <div>
                                <label for="counted-quantity" class="block text-sm font-medium text-gray-700">{{ __('inventory.fields.counted_quantity') }}</label>
                                <input id="counted-quantity" type="number" min="0" step="1" inputmode="numeric" dir="ltr" autofocus wire:model="countedQuantity" @error('countedQuantity') aria-invalid="true" aria-describedby="counted-quantity-error" @else aria-describedby="counted-quantity-help" @enderror class="mt-1 block min-h-11 w-full rounded-lg border-gray-300 font-mono shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                @error('countedQuantity')
                                    <p id="counted-quantity-error" class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @else
                                    <p id="counted-quantity-help" class="mt-1 text-xs text-gray-500">{{ __('inventory.count.quantity_help') }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="count-reason" class="block text-sm font-medium text-gray-700">{{ __('inventory.fields.reason') }} <span class="font-normal text-red-600">({{ __('inventory.form.required') }})</span></label>
                                <textarea id="count-reason" rows="2" required maxlength="{{ \App\Actions\Inventory\InventoryLedger::MAX_NOTE_LENGTH }}" wire:model="countReason" @error('countReason') aria-invalid="true" aria-describedby="count-reason-error" @enderror class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                                @error('countReason') <p id="count-reason-error" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div class="flex gap-2">
                                <button type="submit" wire:loading.attr="disabled" wire:target="recordCount" class="min-h-11 flex-1 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60">
                                    <span wire:loading.remove wire:target="recordCount">{{ __('inventory.count.submit') }}</span>
                                    <span wire:loading wire:target="recordCount">{{ __('inventory.form.saving') }}</span>
                                </button>
                                <button type="button" wire:click="cancelCount" class="min-h-11 rounded-lg px-4 py-2 text-sm font-medium text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50">{{ __('inventory.count.cancel') }}</button>
                            </div>
                        </form>
                    @endif
                </section>
            @elseif (! $setupOpen)
                <p class="rounded-xl bg-gray-50 px-4 py-3 text-sm text-gray-600 ring-1 ring-gray-200" data-testid="setup-completed">{{ __('inventory.setup.completed_product_help') }}</p>
                @include('livewire.backend.inventory.partials.device-reconciliation')
            @else
                <form wire:submit="registerOpeningUnit" class="space-y-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200" aria-labelledby="register-title">
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
                    <button type="submit" wire:loading.attr="disabled" wire:target="registerOpeningUnit" class="min-h-11 w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60">
                        <span wire:loading.remove wire:target="registerOpeningUnit">{{ __('inventory.register.submit') }}</span>
                        <span wire:loading wire:target="registerOpeningUnit">{{ __('inventory.form.saving') }}</span>
                    </button>
                </form>
                @include('livewire.backend.inventory.partials.device-reconciliation')
            @endif

            @if ($canViewPurchases)
                <p class="rounded-xl bg-gray-50 px-4 py-3 text-sm text-gray-600 ring-1 ring-gray-200">
                    {{ __('inventory.receiving.help') }}
                    <a href="{{ route('subdomain.purchases') }}" wire:navigate class="font-medium text-blue-600 hover:text-blue-800">{{ __('inventory.receiving.link') }}</a>
                </p>
            @endif
        </div>

        <div class="space-y-6 lg:col-span-3">
            @if ($tracksUnits)
                <section aria-labelledby="units-title" class="rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-4 py-3">
                        <h2 id="units-title" class="text-base font-semibold text-gray-900">{{ __('inventory.units.title') }}</h2>
                        <div class="flex flex-wrap gap-1 rounded-lg bg-gray-100 p-1 text-sm" role="radiogroup" aria-label="{{ __('inventory.units.filter') }}">
                            @foreach ($product->tracking_mode === \App\Enums\TrackingMode::USED_DEVICE ? ['available', 'in_service', 'review', 'in_transit', 'sold', 'removed', 'all'] : ['available', 'review', 'in_transit', 'sold', 'removed', 'all'] as $filter)
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
                                        <span class="ms-auto rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset {{ $unit->status->badgeClasses() }}">{{ $unit->status->label() }}</span>
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
