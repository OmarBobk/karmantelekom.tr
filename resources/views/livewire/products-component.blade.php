<div class="w-full bg-white py-8">
    <!-- Products Header -->
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold text-gray-900">All Products</h1>
        </div>
    </div>

    <!-- Products Grid -->
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <!-- Loading State -->
        <div wire:loading.delay class="fixed inset-0 bg-black/20 backdrop-blur-sm z-50 flex items-center justify-center">
            <div class="bg-white rounded-lg shadow-xl p-6 max-w-sm mx-auto">
                <div class="flex items-center space-x-4">
                    <svg class="animate-spin size-6 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span class="text-gray-700">Loading products...</span>
                </div>
            </div>
        </div>

        <!-- Products Grid -->
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
            @forelse($products as $product)
                <div class="group/card">
                    <div class="bg-white shadow-lg hover:shadow-2xl transition-shadow duration-300 rounded-lg">
                        <!-- Product Image -->
                        <div class="relative aspect-w-1 aspect-h-1 w-full overflow-hidden">
                            @if($product->images->isNotEmpty())
                                <img src="{{ asset('storage/' . $product->images->firstWhere('is_primary', true)?->image_url) }}"
                                     alt="{{ $product->name }}"
                                     class="h-full w-full object-cover object-center group-hover/card:scale-105 transition-transform duration-300">
                            @else
                                <div class="h-48 bg-gray-200 flex items-center justify-center">
                                    <svg class="size-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                            @endif
                        </div>

                        <!-- Product Info -->
                        <div class="p-4">
                            <div class="mb-2">
                                <p class="text-sm text-gray-500">{{ $product->category->name }}</p>
                                <h3 class="text-lg font-medium text-gray-900">{{ $product->name }}</h3>
                                <p class="text-sm text-gray-500">Code: {{ $product->code }}</p>
                            </div>

                            <div class="flex items-center justify-between">
                                <div>
                                    @if($product->prices->isNotEmpty())
                                        <p class="text-xl font-bold text-gray-900">
                                            {{ $product->prices->first()->currency }} {{ number_format($product->prices->first()->price, 2) }}
                                        </p>
                                    @endif
                                </div>
                                <button wire:click="addToCart({{ $product->id }})" 
                                        class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                    Add to Cart
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full text-center py-12">
                    <div class="mx-auto size-24 flex items-center justify-center rounded-full bg-gray-100">
                        <svg class="size-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                        </svg>
                    </div>
                    <h3 class="mt-4 text-lg font-medium text-gray-900">No products found</h3>
                    <p class="mt-1 text-gray-500">We couldn't find any products matching your criteria.</p>
                </div>
            @endforelse
        </div>

        <!-- Pagination -->
        <div class="mt-8">
            {{ $products->links() }}
        </div>
    </div>
</div> 