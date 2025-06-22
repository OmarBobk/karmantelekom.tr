<div class="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50">
    <!-- Header Section -->
    <div class="bg-white/80 backdrop-blur-sm border-b border-gray-200/50 sticky top-0 z-[999999]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center space-x-3">
                    <div class="w-8 h-8 bg-gradient-to-r from-indigo-500 to-purple-600 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                    </div>
                    <h1 class="text-xl font-bold bg-gradient-to-r from-gray-900 to-gray-600 bg-clip-text text-transparent">
                        Checkout
                    </h1>
                </div>
                <div class="flex items-center space-x-2 text-sm text-gray-500">
                    <span class="hidden sm:inline">Secure checkout powered by</span>
                    <div class="flex items-center space-x-1">
                        <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                        </svg>
                        <span class="font-medium text-green-600">SSL</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Progress Indicator -->
        <div class="mb-8">
            <div class="flex items-center justify-center space-x-4">
                <div class="flex items-center">
                    <div class="w-8 h-8 bg-indigo-600 text-white rounded-full flex items-center justify-center text-sm font-medium">
                        1
                    </div>
                    <span class="ml-2 text-sm font-medium text-indigo-600">Cart</span>
                </div>
                <div class="w-12 h-0.5 bg-indigo-600"></div>
                <div class="flex items-center">
                    <div class="w-8 h-8 bg-indigo-600 text-white rounded-full flex items-center justify-center text-sm font-medium">
                        2
                    </div>
                    <span class="ml-2 text-sm font-medium text-indigo-600">Checkout</span>
                </div>
                <div class="w-12 h-0.5 bg-gray-300"></div>
                <div class="flex items-center">
                    <div class="w-8 h-8 bg-gray-300 text-gray-500 rounded-full flex items-center justify-center text-sm font-medium">
                        3
                    </div>
                    <span class="ml-2 text-sm font-medium text-gray-500">Confirmation</span>
                </div>
            </div>
        </div>

        <!-- Error Messages -->
        @if ($errors->any())
            <div class="mb-6 bg-red-50 border-l-4 border-red-400 rounded-r-lg p-4 shadow-sm">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-red-800">Please correct the following errors</h3>
                        <div class="mt-2 text-sm text-red-700">
                            <ul class="list-disc pl-5 space-y-1">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Success Message -->
        @if (session('success'))
            <div class="mb-6 bg-green-50 border-l-4 border-green-400 rounded-r-lg p-4 shadow-sm">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-green-800">{{ session('success') }}</p>
                    </div>
                </div>
            </div>
        @endif

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
            <!-- Checkout Form -->
            <div class="xl:col-span-2 space-y-6 order-2 sm:order-1">
                <!-- Shop Selection Card -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200/50 overflow-visible">
                    <div class="px-6 py-4 bg-gradient-to-r from-gray-50 to-gray-100/50 border-b border-gray-200/50">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 bg-indigo-100 rounded-xl flex items-center justify-center">
                                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900">Select Shop</h2>
                                <p class="text-sm text-gray-600">Choose where to place your order</p>
                            </div>
                        </div>
                    </div>

                    <div class="p-6 overflow-visible">
                        <div x-data="{
                            open: false,
                            selectedShop: null,
                            searchQuery: '',
                            get filteredShops() {
                                const query = this.searchQuery.toLowerCase();
                                return [
                                    @foreach($shops as $shop)
                                        {
                                            id: {{ $shop->id }},
                                            name: '{{ $shop->name }}',
                                            address: '{{ $shop->address }}',
                                            phone: '{{ $shop->phone ?? '' }}',
                                            visible: query === '' ||
                                                '{{ strtolower($shop->name) }}'.includes(query) ||
                                                '{{ strtolower($shop->address) }}'.includes(query) ||
                                                '{{ strtolower($shop->phone ?? '') }}'.includes(query)
                                        },
                                    @endforeach
                                ];
                            },
                            get visibleShops() {
                                return this.filteredShops.filter(shop => shop.visible);
                            }
                        }" class="relative" style="z-index: 1000;">
                            <label class="block text-sm font-medium text-gray-700 mb-3">
                                Shop Location <span class="text-red-500">*</span>
                            </label>

                            <div class="relative">
                                <button
                                    type="button"
                                    @click="open = !open; if(open) { $nextTick(() => $refs.searchInput.focus()) }"
                                    @click.away="open = false"
                                    class="relative w-full bg-white border-2 border-gray-200 rounded-xl shadow-sm pl-4 pr-12 py-4 text-left cursor-pointer transition-all duration-200 hover:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"
                                    :class="{ 'border-indigo-500 ring-2 ring-indigo-500/20': open, 'border-gray-200': !open }"
                                >
                                    <div class="flex items-center">
                                        <div class="flex-1">
                                            <span x-text="selectedShop ? selectedShop.name : 'Choose a shop'" class="block text-base font-medium text-gray-900"></span>
                                            <span x-text="selectedShop ? selectedShop.address : 'Select your preferred location'" class="block text-sm text-gray-500 mt-1"></span>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <svg class="h-5 w-5 text-gray-400 transition-transform duration-200" :class="{ 'rotate-180': open }" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                    </div>
                                </button>

                                <!-- Debug info -->
                                <div class="mt-2 text-xs text-gray-500">
                                    Available shops: {{ $shops->count() }}
                                </div>

                                <div
                                    x-show="open"
                                    x-transition:enter="transition ease-out duration-200"
                                    x-transition:enter-start="transform opacity-0 scale-95"
                                    x-transition:enter-end="transform opacity-100 scale-100"
                                    x-transition:leave="transition ease-in duration-150"
                                    x-transition:leave-start="transform opacity-100 scale-100"
                                    x-transition:leave-end="transform opacity-0 scale-95"
                                    class="absolute z-[9999] mt-2 w-full bg-white shadow-xl rounded-xl border border-gray-200 max-h-64 overflow-hidden"
                                    style="display: none; position: absolute !important; z-index: 9999 !important;"
                                >
                                    <!-- Search Input -->
                                    <div class="p-3 border-b border-gray-100 bg-gray-50">
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                                </svg>
                                            </div>
                                            <input
                                                x-ref="searchInput"
                                                x-model="searchQuery"
                                                type="text"
                                                placeholder="Search shops..."
                                                class="block w-full pl-10 pr-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"
                                                @keydown.escape="open = false"
                                            >
                                        </div>
                                    </div>

                                    <!-- Shops List -->
                                    <div class="max-h-48 overflow-y-auto">
                                        <template x-for="shop in filteredShops" :key="shop.id">
                                            <button
                                                type="button"
                                                x-show="shop.visible"
                                                @click="selectedShop = shop; $wire.selectedShopId = shop.id; open = false; searchQuery = ''"
                                                class="w-full text-left px-4 py-3 hover:bg-indigo-50 transition-colors duration-150 border-b border-gray-100 last:border-b-0"
                                            >
                                                <div class="flex items-start space-x-3">
                                                    <div class="w-8 h-8 bg-indigo-100 rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5">
                                                        <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                                        </svg>
                                                    </div>
                                                    <div class="flex-1 min-w-0">
                                                        <p class="text-sm font-semibold text-gray-900" x-text="shop.name"></p>
                                                        <p class="text-xs text-gray-500 mt-1" x-text="shop.address"></p>
                                                        <p x-show="shop.phone" class="text-xs text-indigo-600 mt-1" x-text="shop.phone"></p>
                                                    </div>
                                                </div>
                                            </button>
                                        </template>

                                        <!-- No search results -->
                                        <div
                                            x-show="searchQuery !== '' && visibleShops.length === 0"
                                            class="px-4 py-6 text-center"
                                        >
                                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                            </svg>
                                            <h3 class="mt-2 text-sm font-medium text-gray-900">No shops found</h3>
                                            <p class="mt-1 text-sm text-gray-500">Try adjusting your search terms.</p>
                                        </div>

                                        <!-- No shops available -->
                                        <div
                                            x-show="searchQuery === '' && filteredShops.length === 0"
                                            class="px-4 py-6 text-center"
                                        >
                                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                            </svg>
                                            <h3 class="mt-2 text-sm font-medium text-gray-900">No shops available</h3>
                                            <p class="mt-1 text-sm text-gray-500">Please contact your administrator to assign shops.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            @error('selectedShopId')
                                <p class="mt-2 text-sm text-red-600 flex items-center">
                                    <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                    </svg>
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Order Notes Card -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200/50 overflow-hidden">
                    <div class="px-6 py-4 bg-gradient-to-r from-gray-50 to-gray-100/50 border-b border-gray-200/50">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 bg-purple-100 rounded-xl flex items-center justify-center">
                                <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900">Order Notes</h2>
                                <p class="text-sm text-gray-600">Add special instructions or requests</p>
                            </div>
                        </div>
                    </div>

                    <div class="p-6">
                        <div class="relative">
                            <textarea
                                wire:model="orderNotes"
                                rows="4"
                                class="block w-full border-2 border-gray-200 rounded-xl shadow-sm focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 resize-none transition-all duration-200"
                                placeholder="Add any special instructions, delivery preferences, or additional notes for your order..."
                            ></textarea>
                            <div class="absolute bottom-3 right-3 text-xs text-gray-400">
                                <span x-text="(document.getElementById('orderNotes')?.value?.length || 0) + '/1000'"></span>
                            </div>
                            @error('orderNotes')
                                <p class="mt-2 text-sm text-red-600 flex items-center">
                                    <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                    </svg>
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Place Order Button -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200/50 overflow-hidden">
                    <div class="p-6">
                        <button
                            wire:click="placeOrder"
                            wire:loading.attr="disabled"
                            wire:loading.class="opacity-50 cursor-not-allowed"
                            class="w-full group relative flex justify-center items-center px-8 py-4 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white font-semibold rounded-xl shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 transition-all duration-200 focus:outline-none focus:ring-4 focus:ring-indigo-500/30 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none"
                            :disabled="$wire.isProcessing"
                        >
                            <div class="absolute inset-0 bg-gradient-to-r from-indigo-600 to-purple-600 rounded-xl opacity-0 group-hover:opacity-100 transition-opacity duration-200"></div>
                            <div class="relative flex items-center space-x-3">
                                <svg wire:loading wire:target="placeOrder" class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <svg wire:loading.remove wire:target="placeOrder" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                <span wire:loading.remove wire:target="placeOrder" class="text-lg">Place Order</span>
                                <span wire:loading wire:target="placeOrder" class="text-lg">Processing...</span>
                            </div>
                        </button>

                        <div class="mt-4 text-center">
                            <p class="text-xs text-gray-500 flex items-center justify-center space-x-1">
                                <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                                </svg>
                                <span>Secure payment processing</span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Order Summary -->
            <div class="xl:col-span-1 order-1 sm:order-2">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200/50 overflow-hidden sticky top-24">
                    <div class="px-6 py-4 bg-gradient-to-r from-gray-50 to-gray-100/50 border-b border-gray-200/50">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 bg-green-100 rounded-xl flex items-center justify-center">
                                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900">Order Summary</h2>
                                <p class="text-sm text-gray-600">{{ $cart ? $cart->items->count() : 0 }} items</p>
                            </div>
                        </div>
                    </div>

                    <div class="p-6">
                        @if($cart && $cart->items->isNotEmpty())
                            <!-- Cart Items -->
                            <div class="space-y-4 mb-6 max-h-96 overflow-y-auto">
                                @foreach($cart->items as $item)
                                    <div class="flex items-center space-x-4 p-3 bg-gray-50 rounded-xl">
                                        <div class="flex-shrink-0">
                                            <img
                                                src="/storage/{{ $item->product->primaryImageUrl ?? asset('storage/products/default.jpg') }}"
                                                alt="{{ $item->product->name }}"
                                                class="w-12 h-12 rounded-lg object-cover shadow-sm"
                                            >
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-gray-900 truncate">
                                                {{ $item->product->name }}
                                            </p>
                                            <p class="text-xs text-gray-500">
                                                Qty: {{ $item->quantity }} × {{ number_format($item->price, 2) }} TL
                                            </p>
                                        </div>
                                        <div class="flex-shrink-0">
                                            <p class="text-sm font-semibold text-gray-900">
                                                {{ number_format($item->subtotal, 2) }} TL
                                            </p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <!-- Total -->
                            <div class="border-t border-gray-200 pt-4 space-y-3">
                                <div class="flex justify-between text-sm text-gray-600">
                                    <span>Subtotal</span>
                                    <span>{{ number_format($cartTotal, 2) }} TL</span>
                                </div>
                                <div class="flex justify-between text-sm text-gray-600">
                                    <span>Tax</span>
                                    <span>Included</span>
                                </div>
                                <div class="flex justify-between text-sm text-gray-600">
                                    <span>Shipping</span>
                                    <span class="text-green-600">Free</span>
                                </div>
                                <div class="border-t border-gray-200 pt-3">
                                    <div class="flex justify-between text-lg font-bold text-gray-900">
                                        <span>Total</span>
                                        <span>{{ number_format($cartTotal, 2) }} TL</span>
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="text-center py-12">
                                <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <svg class="w-8 h-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                    </svg>
                                </div>
                                <h3 class="text-lg font-medium text-gray-900 mb-2">Your cart is empty</h3>
                                <p class="text-sm text-gray-500 mb-6">Add some items to your cart to continue with checkout.</p>
                                <a href="{{ route('main') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors duration-200">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                    </svg>
                                    Continue Shopping
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
