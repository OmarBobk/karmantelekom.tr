<div>
    <!-- Header Section -->
    <div class="bg-white border-b border-gray-200">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 {{ app()->getLocale() === 'ar' ? 'sm:flex-row-reverse' : '' }}">
                <h1 class="text-2xl font-bold text-gray-900">{{ __('main.catalog') ?? 'Catalog' }} ({{ __('main.wholesale_prices') }})</h1>

                <!-- Custom Sort Filter Card -->
                <button
                    wire:click="$set('sortBy', '{{ $sortBy === 'newest' ? 'oldest' : 'newest' }}')"
                    type="button"
                    class="flex items-center justify-between min-w-[180px] px-6 py-2 border border-blue-400 rounded-lg bg-white hover:bg-blue-50 transition-colors duration-150 shadow-sm group focus:outline-none focus:ring-2 focus:ring-blue-400 {{ app()->getLocale() === 'ar' ? 'flex-row-reverse' : '' }}"
                >
                    <span class="text-base text-gray-800 font-medium">
                        {{ $sortBy === 'newest' ? __('main.newest_to_oldest') : __('main.oldest_to_newest') }}
                    </span>
                    <span class="{{ app()->getLocale() === 'ar' ? 'mr-4' : 'ml-4' }}">
                        @if($sortBy === 'newest')
                            <!-- Up-Down Icon (Newest to Oldest) -->
                            <svg class="w-6 h-6 text-blue-500 group-hover:text-blue-600 transition" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7 7v6m0 0l-2.5-2.5M7 13l2.5-2.5M17 17v-6m0 0l-2.5 2.5M17 11l2.5 2.5" />
                            </svg>
                        @else
                            <!-- Down-Up Icon (Oldest to Newest) -->
                            <svg class="w-6 h-6 text-blue-500 group-hover:text-blue-600 transition" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7 17v-6m0 0l-2.5 2.5M17 11l2.5 2.5M17 7v6m0 0l-2.5-2.5M17 13l2.5-2.5" />
                            </svg>
                        @endif
                    </span>
                </button>
            </div>
        </div>
    </div>

    <!-- Loading State -->
    <div wire:loading.delay wire:loading.class="!flex" class="fixed inset-0 bg-black/20 backdrop-blur-sm z-50 items-center justify-center">
        <div class="bg-white rounded-lg shadow-xl p-6 max-w-sm mx-auto">
            <div class="flex items-center space-x-4 {{ app()->getLocale() === 'ar' ? 'space-x-reverse' : '' }}">
                <svg class="animate-spin size-6 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span class="text-gray-700">{{ __('main.loading') }}...</span>
            </div>
        </div>
    </div>

    <!-- Products Grid -->
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8">
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-y-4">
            @forelse($products as $product)
                @php
                    $firstWholesalePrice = $product->priceTiers->first();
                    $minQty = $product->priceTiers->min('min_qty');
                    $maxQty = $product->priceTiers->max('max_qty');
                @endphp
                <div class="group/card {{ app()->getLocale() === 'ar' ? 'mr-4 ml-3' : 'ml-4 mr-3' }}">
                    <div class="bg-white rounded-lg shadow-lg hover:shadow-xl transition-shadow duration-300 relative">
                        @if($product->tags->isNotEmpty())
                            <span class="absolute top-[37%] z-10 px-3 py-1 text-xs font-semibold w-full text-center"
                                  style="{{ app()->getLocale() === 'ar' ? 'right:0;' : 'left:0;' }} background-color: {{ $product->tags->first()->background_color }}; color: {{ $product->tags->first()->text_color }}; border-color: {{ $product->tags->first()->border_color }};">
                                @if($product->tags->first()->icon)
                                    {{ $product->tags->first()->icon . ' ' }}
                                @endif
                                {{ $product->tags->first()->translated_name }}
                            </span>
                        @endif

                        <!-- Image Slider -->
                        <div class="flex flex-col relative aspect-w-1 aspect-h-1 w-full overflow-hidden h-48 bg-gray-50 rounded-md">
                            <button wire:click="$dispatch('openProductModal', { productId: {{ $product->id }} })" class="">
                                <img src="{{ $product->images->where('is_primary', true)->first()?->image_url
                                            ? Storage::url($product->images->where('is_primary', true)->first()->image_url)
                                            : ($product->images->first()?->image_url
                                                ? Storage::url($product->images->first()->image_url)
                                                : 'https://placehold.co/100') }}"
                                     alt="{{ $product->translated_name }}"
                                     class="absolute h-full w-full object-contain object-center transition-opacity duration-300 opacity-100"
                                     loading="lazy">
                            </button>
                        </div>

                        <!-- Product Info -->
                        <div class="sm:p-4 py-4 px-2">
                            <div class="mb-3">
                                <button wire:click="$dispatch('openProductModal', { productId: {{ $product->id }} })" class="text-left">
                                    <div class="line-clamp-3">
                                        <span class="text-sm font-medium text-gray-900 hover:text-blue-600 transition-colors duration-200">{{ $product->translated_name }}</span>
                                        <span class="text-sm text-gray-500">{!! $product->translated_description !!}</span>
                                    </div>
                                </button>
                            </div>
                            <div class="flex items-center justify-between mt-3">
                                <div>
                                    @if($firstWholesalePrice && $firstWholesalePrice->currency)
                                        <p class="text-xl font-semibold text-blue-600">
                                            {{ number_format($firstWholesalePrice->price, 2) }} {{ $firstWholesalePrice->currency->symbol ?? $firstWholesalePrice->currency->code }}
                                        </p>
                                        @if($product->priceTiers->count() > 1)
                                            <p class="text-xs text-gray-500 mt-1">
                                                {{ __('main.from') ?? 'From' }} {{ __('main.min_qty') ?? 'Min' }}: {{ $minQty }} - {{ __('main.max_qty') ?? 'Max' }}: {{ $maxQty }}
                                            </p>
                                            <p class="text-xs text-blue-600 mt-1 font-medium">
                                                {{ $product->priceTiers->count() }} {{ __('main.price_tiers') ?? 'price tiers' }}
                                            </p>
                                        @else
                                            <p class="text-xs text-gray-500 mt-1">
                                                {{ __('main.min_qty') ?? 'Min' }}: {{ $firstWholesalePrice->min_qty }} - {{ __('main.max_qty') ?? 'Max' }}: {{ $firstWholesalePrice->max_qty }}
                                            </p>
                                        @endif
                                    @else
                                        <p class="text-xl font-semibold text-gray-400">Price not available</p>
                                    @endif
                                </div>
                            </div>

                            <!-- Add to Cart Button -->
                            <div class="mt-auto pt-4">
                                <button
                                    wire:click="$dispatch('openProductModal', { productId: {{ $product->id }} })"
                                    class="w-full flex items-center justify-center gap-2 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl shadow-md hover:shadow-lg transform hover:-translate-y-px transition-all duration-300 focus:outline-none"
                                    aria-label="{{ __('View Details') }}"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                    {{ __('View Details') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full text-center py-12">
                    <div class="mx-auto size-24 flex items-center justify-center rounded-full bg-gray-100">
                        <svg class="size-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                        </svg>
                    </div>
                    <h3 class="mt-4 text-lg font-medium text-gray-900">{{ __('main.no_products_found') }}</h3>
                    <p class="mt-1 text-gray-500">{{ __('main.try_different_search') }}</p>
                </div>
            @endforelse
        </div>

        <!-- Pagination -->
        <div class="mt-8">
            {{ $products->links() }}
        </div>
    </div>

    <style>
        .line-clamp-3 {
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Custom select styling */
        select {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 0.5rem center;
            background-repeat: no-repeat;
            background-size: 1.5em 1.5em;
            padding-right: 2.5rem;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* RTL specific styles */
        [dir="rtl"] select {
            background-position: left 0.5rem center;
            padding-right: 0.75rem;
            padding-left: 2.5rem;
        }
    </style>
</div>
