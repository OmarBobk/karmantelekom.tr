<div dir="{{ $textDirection }}" lang="{{ $locale }}" class="mx-auto max-w-6xl space-y-6 text-start">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('catalog.title') }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ __('catalog.subtitle') }}</p>
        </div>
        <button
            type="button"
            wire:click="openCreateForm"
            class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700 focus:outline-hidden focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
        >
            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z" />
            </svg>
            {{ __('catalog.actions.create_definition') }}
        </button>
    </div>

    @include('livewire.backend.operational-catalog.partials.tabs')

    <div class="flex flex-col gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200 sm:flex-row sm:items-end">
        <div class="sm:w-48">
            <label for="definition-search-by" class="block text-sm font-medium text-gray-700">{{ __('catalog.search.by') }}</label>
            <select id="definition-search-by" wire:model.live="searchBy" class="mt-1 block min-h-11 w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="name">{{ __('catalog.search.by_name') }}</option>
                <option value="sku">{{ __('catalog.fields.sku') }}</option>
                <option value="barcode">{{ __('catalog.fields.barcode') }}</option>
            </select>
        </div>
        <div class="flex-1">
            <label for="definition-search" class="block text-sm font-medium text-gray-700">
                {{ $searchBy === 'name' ? __('catalog.search.name_label') : __('catalog.search.identifier_label') }}
            </label>
            <div class="relative mt-1">
                <input
                    id="definition-search"
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    @if ($searchBy !== 'name') dir="ltr" inputmode="text" autocomplete="off" @endif
                    placeholder="{{ $searchBy === 'name' ? __('catalog.search.name_placeholder') : __('catalog.search.identifier_placeholder') }}"
                    class="block min-h-11 w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @if ($searchBy !== 'name') font-mono @endif"
                >
                <div wire:loading.flex wire:target="search,searchBy" class="pointer-events-none absolute inset-y-0 end-3 items-center" aria-hidden="true">
                    <svg class="h-4 w-4 animate-spin text-gray-400" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"></path></svg>
                </div>
            </div>
            @if ($searchBy !== 'name')
                <p class="mt-1 text-xs text-gray-500">{{ __('catalog.search.identifier_help') }}</p>
            @endif
        </div>
    </div>

    @if ($definitions->isEmpty())
        <div class="rounded-xl border-2 border-dashed border-gray-300 bg-white px-6 py-12 text-center">
            <h2 class="text-base font-semibold text-gray-900">
                {{ $isFiltering ? __('catalog.empty.no_matches_title') : __('catalog.empty.definitions_title') }}
            </h2>
            <p class="mt-1 text-sm text-gray-500">
                {{ $isFiltering ? __('catalog.empty.no_matches_body') : __('catalog.empty.definitions_body') }}
            </p>
        </div>
    @else
        <div class="hidden overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200 md:block" wire:loading.class="opacity-60" wire:target="search,searchBy,gotoPage,nextPage,previousPage">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-start text-xs font-semibold text-gray-600">{{ __('catalog.fields.name') }}</th>
                        <th scope="col" class="px-4 py-3 text-start text-xs font-semibold text-gray-600">{{ __('catalog.fields.brand') }} / {{ __('catalog.fields.category') }}</th>
                        <th scope="col" class="px-4 py-3 text-start text-xs font-semibold text-gray-600">{{ __('catalog.fields.tracking_mode') }}</th>
                        <th scope="col" class="px-4 py-3 text-start text-xs font-semibold text-gray-600">{{ __('catalog.fields.sku') }} / {{ __('catalog.fields.barcode') }}</th>
                        <th scope="col" class="px-4 py-3 text-start text-xs font-semibold text-gray-600">{{ __('pos.pricing.price_column') }}</th>
                        <th scope="col" class="px-4 py-3 text-start text-xs font-semibold text-gray-600">{{ __('catalog.fields.status') }}</th>
                        <th scope="col" class="px-4 py-3"><span class="sr-only">{{ __('catalog.actions.edit') }}</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($definitions as $definition)
                        <tr wire:key="definition-row-{{ $definition->id }}" class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <p class="text-sm font-medium text-gray-900">{{ $definition->name }}</p>
                                @if ($definition->model)
                                    <p class="text-xs text-gray-500"><bdi>{{ $definition->model }}</bdi></p>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                <bdi>{{ $definition->brand?->name ?? '—' }}</bdi>
                                <span class="text-gray-300">·</span>
                                <bdi>{{ $definition->category?->name ?? '—' }}</bdi>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700">{{ $definition->tracking_mode->label() }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-gray-600">
                                <span dir="ltr" class="block">{{ $definition->sku ?? '—' }}</span>
                                <span dir="ltr" class="block">{{ $definition->barcode ?? '—' }}</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700">
                                @include('livewire.backend.operational-catalog.partials.definition-price', ['definition' => $definition])
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm">
                                @if ($definition->is_active)
                                    <span class="inline-flex rounded-full bg-green-50 px-2.5 py-0.5 text-xs font-medium text-green-700">{{ __('catalog.statuses.active') }}</span>
                                @else
                                    <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600">{{ __('catalog.statuses.inactive') }}</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-end text-sm">
                                <button type="button" wire:click="openEditForm({{ $definition->id }})" class="min-h-11 px-2 font-medium text-blue-600 hover:text-blue-800">{{ __('catalog.actions.edit') }}</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <ul class="space-y-3 md:hidden">
            @foreach ($definitions as $definition)
                <li wire:key="definition-card-{{ $definition->id }}" class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-semibold text-gray-900">{{ $definition->name }}</p>
                            @if ($definition->model)
                                <p class="text-xs text-gray-500"><bdi>{{ $definition->model }}</bdi></p>
                            @endif
                        </div>
                        <span class="shrink-0 rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700">{{ $definition->tracking_mode->label() }}</span>
                    </div>
                    <dl class="mt-3 space-y-1 text-sm text-gray-600">
                        <div class="flex gap-2"><dt class="text-gray-500">{{ __('catalog.fields.sku') }}:</dt><dd dir="ltr" class="font-mono">{{ $definition->sku ?? '—' }}</dd></div>
                        <div class="flex gap-2"><dt class="text-gray-500">{{ __('catalog.fields.barcode') }}:</dt><dd dir="ltr" class="font-mono">{{ $definition->barcode ?? '—' }}</dd></div>
                        <div class="flex gap-2"><dt class="text-gray-500">{{ __('pos.pricing.price_column') }}:</dt><dd>@include('livewire.backend.operational-catalog.partials.definition-price', ['definition' => $definition])</dd></div>
                    </dl>
                    <button type="button" wire:click="openEditForm({{ $definition->id }})" class="mt-4 min-h-11 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('catalog.actions.edit') }}</button>
                </li>
            @endforeach
        </ul>

        {{ $definitions->links() }}
    @endif

    @if ($formOpen)
        <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="definition-form-title" x-data x-on:keydown.escape.window="$wire.closeForm()">
            <div class="fixed inset-0 bg-gray-900/50" wire:click="closeForm"></div>
            <div class="relative flex min-h-full items-center justify-center p-4">
                <form wire:submit="save" dir="{{ $textDirection }}" class="relative w-full max-w-2xl space-y-5 rounded-xl bg-white p-6 text-start shadow-xl">
                    <h2 id="definition-form-title" class="text-lg font-semibold text-gray-900">
                        {{ $editingDefinitionId ? __('catalog.form.edit_title') : __('catalog.form.create_title') }}
                    </h2>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label for="definition-name" class="block text-sm font-medium text-gray-700">{{ __('catalog.fields.name') }}</label>
                            <input id="definition-name" type="text" wire:model="name" x-init="$el.focus()" @error('name') aria-invalid="true" aria-describedby="definition-name-error" @enderror class="mt-1 block min-h-11 w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            @error('name') <p id="definition-name-error" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="definition-model" class="block text-sm font-medium text-gray-700">{{ __('catalog.fields.model') }}</label>
                            <input id="definition-model" type="text" wire:model="model" dir="auto" @error('model') aria-invalid="true" @enderror class="mt-1 block min-h-11 w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            @error('model') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="definition-tracking" class="block text-sm font-medium text-gray-700">{{ __('catalog.fields.tracking_mode') }}</label>
                            <select id="definition-tracking" wire:model="trackingMode" @disabled($trackingModeLocked) aria-describedby="definition-tracking-help" @error('tracking_mode') aria-invalid="true" @enderror class="mt-1 block min-h-11 w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-100 disabled:text-gray-500">
                                @foreach ($trackingModes as $mode)
                                    <option value="{{ $mode->value }}">{{ $mode->label() }}</option>
                                @endforeach
                            </select>
                            <p id="definition-tracking-help" class="mt-1 text-xs text-gray-500">
                                {{ $trackingModeLocked ? __('catalog.form.tracking_locked') : __('catalog.form.tracking_help') }}
                            </p>
                            @error('tracking_mode') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="definition-brand" class="block text-sm font-medium text-gray-700">{{ __('catalog.fields.brand') }}</label>
                            <select id="definition-brand" wire:model="brandId" @error('catalog_brand_id') aria-invalid="true" @enderror class="mt-1 block min-h-11 w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">{{ __('catalog.form.none') }}</option>
                                @foreach ($brands as $brand)
                                    <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                                @endforeach
                            </select>
                            @error('catalog_brand_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="definition-category" class="block text-sm font-medium text-gray-700">{{ __('catalog.fields.category') }}</label>
                            <select id="definition-category" wire:model="categoryId" @error('catalog_category_id') aria-invalid="true" @enderror class="mt-1 block min-h-11 w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">{{ __('catalog.form.none') }}</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                                @endforeach
                            </select>
                            @error('catalog_category_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="definition-sku" class="block text-sm font-medium text-gray-700">{{ __('catalog.fields.sku') }}</label>
                            <input id="definition-sku" type="text" wire:model="sku" dir="ltr" autocomplete="off" @error('sku') aria-invalid="true" @enderror class="mt-1 block min-h-11 w-full rounded-lg border-gray-300 font-mono shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            @error('sku') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="definition-barcode" class="block text-sm font-medium text-gray-700">{{ __('catalog.fields.barcode') }}</label>
                            <input id="definition-barcode" type="text" wire:model="barcode" dir="ltr" autocomplete="off" inputmode="numeric" @error('barcode') aria-invalid="true" @enderror class="mt-1 block min-h-11 w-full rounded-lg border-gray-300 font-mono shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            @error('barcode') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        @if ($canSetPrice)
                            <div class="sm:col-span-2">
                                <label for="definition-price" class="block text-sm font-medium text-gray-700">{{ __('pos.pricing.definition_price') }} <span class="text-gray-500">(TRY)</span></label>
                                <p x-show="$wire.trackingMode === 'used_device'" x-cloak class="mt-1 rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-700 ring-1 ring-gray-200">{{ __('pos.pricing.used_device_help') }}</p>
                                <div x-show="$wire.trackingMode !== 'used_device'">
                                    <input id="definition-price" type="text" inputmode="decimal" wire:model="sellingPrice" dir="ltr" autocomplete="off" aria-describedby="definition-price-help" @error('selling_price') aria-invalid="true" @enderror class="mt-1 block min-h-11 w-full rounded-lg border-gray-300 font-mono shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:max-w-xs">
                                    <p id="definition-price-help" class="mt-1 text-xs text-gray-500">{{ __('pos.pricing.definition_help') }}</p>
                                </div>
                                @error('selling_price') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        @endif

                        <div class="sm:col-span-2">
                            <label for="definition-description" class="block text-sm font-medium text-gray-700">{{ __('catalog.fields.description') }}</label>
                            <textarea id="definition-description" wire:model="description" rows="3" @error('description') aria-invalid="true" @enderror class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                            @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <label class="flex min-h-11 items-center gap-3 sm:col-span-2">
                            <input type="checkbox" wire:model="isActive" class="h-5 w-5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span class="text-sm text-gray-700">{{ __('catalog.form.active') }}</span>
                        </label>
                    </div>

                    <p class="rounded-lg bg-blue-50 px-3 py-2 text-xs text-blue-800">{{ __('catalog.form.no_stock_notice') }}</p>

                    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                        <button type="button" wire:click="closeForm" class="min-h-11 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('catalog.actions.cancel') }}</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="save" class="min-h-11 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60">
                            <span wire:loading.remove wire:target="save">{{ __('catalog.actions.save') }}</span>
                            <span wire:loading wire:target="save">{{ __('catalog.actions.saving') }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
