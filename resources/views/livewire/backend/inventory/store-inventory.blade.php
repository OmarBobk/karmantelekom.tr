<div dir="{{ $textDirection }}" lang="{{ $locale }}" class="mx-auto max-w-6xl space-y-6 text-start">
    @include('livewire.backend.inventory.partials.breadcrumbs', ['items' => [
        [__('inventory.title'), route('subdomain.inventory')],
        [$store->code.' · '.$store->name, null],
    ]])

    <div>
        <h1 class="text-2xl font-bold text-gray-900">
            {{ $store->name }}
            <span dir="ltr" class="ms-2 rounded-md bg-gray-100 px-2 py-0.5 align-middle font-mono text-sm font-medium text-gray-700">{{ $store->code }}</span>
        </h1>
        <p class="mt-1 text-sm text-gray-500">{{ __('inventory.store.subtitle') }}</p>
    </div>

    <livewire:backend.inventory.device-lookup :store="$store" />

    <section aria-labelledby="stock-product-title" class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
        <h2 id="stock-product-title" class="text-base font-semibold text-gray-900">{{ __('inventory.store.find_product_title') }}</h2>
        <p class="text-xs text-gray-500">{{ __('inventory.store.find_product_help') }}</p>
        <div class="relative mt-3">
            <label for="catalog-search" class="sr-only">{{ __('inventory.store.find_product_title') }}</label>
            <input id="catalog-search" type="search" wire:model.live.debounce.300ms="catalogSearch" autocomplete="off" placeholder="{{ __('inventory.store.find_product_placeholder') }}" class="block min-h-11 w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            <div wire:loading.flex wire:target="catalogSearch" class="pointer-events-none absolute inset-y-0 end-3 items-center" aria-hidden="true">
                <svg class="h-4 w-4 animate-spin text-gray-400" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"></path></svg>
            </div>
        </div>
        @if (trim($catalogSearch) !== '')
            @if ($catalogMatches->isEmpty())
                <p class="mt-3 rounded-lg bg-gray-50 px-3 py-3 text-sm text-gray-600" role="status">{{ __('inventory.store.no_catalog_matches') }}</p>
            @else
                <ul class="mt-3 divide-y divide-gray-100 rounded-lg ring-1 ring-gray-200">
                    @foreach ($catalogMatches as $match)
                        <li wire:key="catalog-match-{{ $match->id }}">
                            <a href="{{ route('subdomain.inventory.product', [$store, $match]) }}" class="flex min-h-11 flex-wrap items-center gap-x-3 gap-y-1 px-3 py-2 hover:bg-blue-50">
                                <span class="font-medium text-gray-900">{{ $match->name }}</span>
                                @if ($match->model)
                                    <bdi class="text-sm text-gray-500">{{ $match->model }}</bdi>
                                @endif
                                <span class="text-xs text-gray-500">{{ $match->tracking_mode->label() }}</span>
                                @if ($match->sku)
                                    <span dir="ltr" class="ms-auto font-mono text-xs text-gray-500">{{ $match->sku }}</span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        @endif
    </section>

    <section aria-labelledby="stocked-products-title" class="space-y-3">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <h2 id="stocked-products-title" class="text-lg font-semibold text-gray-900">{{ __('inventory.store.stocked_title') }}</h2>
            <div class="sm:w-72">
                <label for="stock-search" class="sr-only">{{ __('inventory.store.filter_label') }}</label>
                <input id="stock-search" type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('inventory.store.filter_label') }}" class="block min-h-11 w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
        </div>

        @if ($products->isEmpty())
            <div class="rounded-xl border-2 border-dashed border-gray-300 bg-white px-6 py-12 text-center">
                <h3 class="text-base font-semibold text-gray-900">{{ $isFiltering ? __('inventory.store.no_matches_title') : __('inventory.store.empty_title') }}</h3>
                <p class="mt-1 text-sm text-gray-500">{{ $isFiltering ? __('inventory.store.no_matches_body') : __('inventory.store.empty_body') }}</p>
            </div>
        @else
            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200" wire:loading.class="opacity-60" wire:target="search,gotoPage,nextPage,previousPage">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-start text-xs font-semibold text-gray-600">{{ __('inventory.fields.product') }}</th>
                            <th scope="col" class="hidden px-4 py-3 text-start text-xs font-semibold text-gray-600 md:table-cell">{{ __('catalog.fields.tracking_mode') }}</th>
                            <th scope="col" class="px-4 py-3 text-end text-xs font-semibold text-gray-600">{{ __('inventory.fields.on_hand') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($products as $product)
                            @php($onHand = $product->tracking_mode->tracksIndividualItems() ? $product->available_units_count : (int) $product->store_quantity)
                            <tr wire:key="stock-row-{{ $product->id }}" class="relative hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <a href="{{ route('subdomain.inventory.product', [$store, $product]) }}" class="font-medium text-gray-900 after:absolute after:inset-0 hover:text-blue-700">{{ $product->name }}</a>
                                    <p class="text-xs text-gray-500">
                                        @if ($product->model)<bdi>{{ $product->model }}</bdi>@endif
                                        @if ($product->brand) <span class="text-gray-300">·</span> <bdi>{{ $product->brand->name }}</bdi>@endif
                                    </p>
                                </td>
                                <td class="hidden whitespace-nowrap px-4 py-3 text-sm text-gray-600 md:table-cell">{{ $product->tracking_mode->label() }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-end">
                                    <span dir="ltr" @class(['font-mono text-base font-semibold tabular-nums', 'text-gray-900' => $onHand > 0, 'text-gray-400' => $onHand === 0])>{{ $onHand }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $products->links() }}
        @endif
    </section>
</div>
