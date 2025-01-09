<div x-data="{
    sidebarOpen: false,
    searchOpen: false,
    searchQuery: '',
    searchResults: [],
    isLoading: false,
    cartCount: 1,
    cartOpen: false,

    async search() {
        if (this.searchQuery.length < 2) {
            this.searchResults = [];
            return;
        }

        this.isLoading = true;
        // Simulate API call - Replace this with your actual search endpoint
        await new Promise(resolve => setTimeout(resolve, 300));

        // Example results - Replace with your actual search logic
        this.searchResults = [
            { title: 'Result 1 matching ' + this.searchQuery, url: '#' },
            { title: 'Another result for ' + this.searchQuery, url: '#' },
            { title: 'Third matching result', url: '#' }
        ];
        this.isLoading = false;
    },

    addToCart() {
        this.cartCount++;
        this.$dispatch('notify', {
            message: 'Item added to cart',
            type: 'success'
        });
    },

    removeFromCart() {
        if (this.cartCount > 0) {
            this.cartCount--;
        }
    },

    changeLang(code) {
        this.currentLang = code;
        this.languageOpen = false;
        $wire.changeLanguage(code);
    }
}">

    <!-- Desktop Navigation -->
    <nav class="hidden lg:flex mx-auto max-w-7xl items-center justify-between pt-6 pb-2 px-4 lg:px-8 bg-white shadow-sm" aria-label="Global">
        <!-- Left Section -->
        <div class="flex items-center gap-x-2 sm:gap-x-2">
            <button @click="sidebarOpen = true"
                    class="p-2.5 pl-0 text-gray-700 hover:pl-2.5 hover:text-gray-900 hover:bg-gray-100 rounded-xl transition-all duration-200">
                <span class="sr-only">Open sidebar</span>
                <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                </svg>
            </button>
            <span class="text-2xl font-semibold" style="font-family: 'Poppins', sans-serif; background: linear-gradient(to right, #3b82f6, #8b5cf6); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                İndirmGo
            </span>
        </div>

        <!-- Center Section - Search -->
        <div class="flex-1 max-w-xl px-4">
            <!-- Desktop Search -->
            <div class="hidden lg:block relative">
                <!-- Search Container -->
                <div class="relative flex items-center">
                    <div class="relative w-full">
                        <!-- Search Input Container -->
                        <div class="relative transition-all duration-300 ease-out rounded-lg bg-white shadow-lg border border-gray-200/80">
                            <!-- Search Input Wrapper -->
                            <div class="relative flex items-center w-full h-11">
                                <!-- Search Icon -->
                                <div class="absolute left-0 p-2.5 text-gray-400">
                                    <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                    </svg>
                                </div>

                                <!-- Search Input -->
                                <input type="text" 
                                       placeholder="Search for products, categories..." 
                                       x-model="searchQuery"
                                       @input.debounce.300ms="search()"
                                       class="w-full h-full pl-11 pr-4 bg-transparent text-sm placeholder-gray-400 outline-none focus:outline-none focus:ring-0 border-0 focus:border-0"
                                >

                                <!-- Clear Button -->
                                <button x-show="searchQuery"
                                        @click="searchQuery = ''"
                                        class="absolute right-3 p-1 text-gray-400 hover:text-gray-600 focus:outline-none">
                                    <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Search Results Dropdown -->
                        <div x-show="searchQuery.length >= 2" 
                             x-cloak
                             class="absolute mt-2 w-full bg-white/95 backdrop-blur-sm rounded-xl shadow-xl border border-gray-200/80 overflow-hidden z-50 search-results"
                             @click.away.stop>
                            
                            <!-- Loading State -->
                            <div x-show="isLoading" 
                                 class="p-4">
                                <div class="flex items-center justify-center space-x-2 text-sm text-gray-500">
                                    <svg class="animate-spin size-5 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span>Searching...</span>
                                </div>
                            </div>

                            <!-- Results -->
                            <div x-show="!isLoading && searchResults.length > 0" 
                                 class="max-h-[400px] overflow-y-auto">
                                <template x-for="result in searchResults" :key="result.title">
                                    <a :href="result.url" 
                                       class="flex items-center px-4 py-3 hover:bg-gray-50 transition-colors duration-150 border-b border-gray-100 last:border-0 group">
                                        <!-- Result Icon -->
                                        <div class="flex-shrink-0 size-10 rounded-lg bg-gray-100 flex items-center justify-center text-gray-500 group-hover:bg-blue-50 group-hover:text-blue-600 transition-colors duration-150">
                                            <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                            </svg>
                                        </div>
                                        <!-- Result Content -->
                                        <div class="ml-4 flex-1">
                                            <p class="text-sm font-medium text-gray-900 group-hover:text-blue-600" x-text="result.title"></p>
                                            <p class="text-xs text-gray-500 mt-0.5" x-text="result.category || 'Product'"></p>
                                        </div>
                                    </a>
                                </template>
                            </div>

                            <!-- No Results -->
                            <div x-show="!isLoading && searchResults.length === 0 && searchQuery.length >= 2" 
                                 class="p-4 text-center">
                                <div class="text-gray-500 text-sm">
                                    <svg class="size-6 mx-auto mb-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M12 14a3 3 0 100-6 3 3 0 000 6z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <p>No results found for "<span class="font-medium" x-text="searchQuery"></span>"</p>
                                    <p class="mt-1 text-xs text-gray-400">Try adjusting your search or filter to find what you're looking for.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Mobile Search Button -->
            <button @click="searchOpen = true"
                    class="lg:hidden p-2.5 text-gray-700 hover:text-gray-900 hover:bg-gray-100 rounded-xl transition-all duration-200">
                <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </button>
        </div>

        <!-- Right Section -->
        <div class="flex items-center gap-x-2">
            <!-- Shopping Cart -->
            <div class="relative flex items-center gap-x-2" x-data="{ cartOpen: false }">
                <button @click="cartOpen = !cartOpen"
                        class="p-2.5 text-gray-700 hover:text-gray-900 hover:bg-gray-100 rounded-xl transition-all duration-200 relative group h-11 w-11 flex items-center justify-center"
                        aria-label="Shopping cart">
                    <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                    </svg>
                    <!-- Cart Badge -->
                    <div x-show="cartCount > 0"
                         x-transition:enter="transition ease-out duration-300"
                         x-transition:enter-start="opacity-0 scale-50"
                         x-transition:enter-end="opacity-100 scale-100"
                         class="absolute -top-2 -right-1 bg-blue-500 text-white text-xs font-bold rounded-full h-5 min-w-[1.25rem] px-1 flex items-center justify-center group-hover:bg-blue-600 transition-colors duration-200 z-50"
                         x-text="cartCount">
                    </div>
                </button>
                <span @click="cartOpen = !cartOpen" 
                      class="text-sm text-gray-700 cursor-pointer hover:text-gray-900">Cart</span>

                <!-- Cart Dropdown -->
                <div x-show="cartOpen"
                     x-cloak
                     @click.outside="cartOpen = false"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 scale-95"
                     x-transition:enter-end="opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-100"
                     x-transition:leave-start="opacity-100 scale-100"
                     x-transition:leave-end="opacity-0 scale-95"
                     class="fixed inset-x-4 top-24 mx-auto bg-white rounded-lg shadow-lg border border-gray-200 z-50 lg:absolute lg:inset-auto lg:right-0 lg:top-full lg:w-80"
                     :class="{ 'lg:fixed': window.scrollY > 0 }"
                     @scroll.window="if(window.scrollY > 0) cartOpen = false">

                    <!-- Cart Header -->
                    <div class="p-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Shopping Cart</h3>
                        <p x-show="cartCount === 0" class="mt-1 text-sm text-gray-500">Your cart is empty</p>
                    </div>

                    <!-- Cart Items -->
                    <div x-show="cartCount > 0" class="max-h-[60vh] lg:max-h-96 overflow-y-auto">
                        <div class="p-4 border-b border-gray-200">
                            <div class="flex items-center space-x-4">
                                <div class="flex-shrink-0 size-16 bg-gray-100 rounded-md"></div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900 truncate">Product Name</p>
                                    <p class="text-sm text-gray-500">$99.00</p>
                                </div>
                                <button @click.stop="removeFromCart()" 
                                        class="p-1 text-gray-400 hover:text-gray-500 rounded-full hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-200">
                                    <span class="sr-only">Remove item</span>
                                    <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Cart Footer -->
                    <div x-show="cartCount > 0" class="p-4 border-t border-gray-200">
                        <div class="flex justify-between text-base font-medium text-gray-900 mb-4">
                            <p>Subtotal</p>
                            <p>$99.00</p>
                        </div>
                        <a href="#" 
                           @click.stop
                           class="w-full flex justify-center items-center px-6 py-3 border border-transparent rounded-lg shadow-sm text-base font-medium text-white bg-blue-600 hover:bg-blue-700 transition-colors duration-200">
                            Checkout
                        </a>
                    </div>
                </div>
            </div>

            <!-- Language Selector -->
            <div class="relative flex items-center" x-data="{ 
                languageOpen: false,
                currentLang: @entangle('currentLanguage'),
                languages: [
                    { code: 'EN' },
                    { code: 'TR' },
                    { code: 'DE' },
                    { code: 'ES' },
                    { code: 'FR' },
                    { code: 'IT' },
                    { code: 'JP' },
                    { code: 'AR' },
                    { code: 'KR' }
                ]
            }">
                <button @click="languageOpen = !languageOpen"
                        class="p-2.5 text-gray-700 hover:text-gray-900 hover:bg-gray-100 rounded-xl transition-all duration-200 h-11 w-11 flex items-center justify-center">
                    <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"/>
                    </svg>
                </button>
                <span @click="languageOpen = !languageOpen" 
                      class="text-sm text-gray-700 cursor-pointer hover:text-gray-900" 
                      x-text="currentLang"></span>

                <!-- Language Dropdown -->
                <div x-show="languageOpen"
                     x-cloak
                     @click.outside="languageOpen = false"
                     class="fixed inset-x-4 top-24 mx-auto bg-white rounded-lg shadow-lg border border-gray-200/80 backdrop-blur-sm z-50 lg:absolute lg:inset-auto lg:right-0 lg:top-full lg:w-36"
                     :class="{ 'lg:fixed': window.scrollY > 0 }"
                     @scroll.window="if(window.scrollY > 0) languageOpen = false">
                    
                    <!-- Language Options -->
                    <div class="max-h-[60vh] lg:max-h-96 overflow-y-auto py-1">
                        <template x-for="language in languages" :key="language.code">
                            <button @click="changeLang(language.code)"
                                    class="w-full flex items-center justify-between px-4 py-2 text-sm hover:bg-gray-50 transition-colors duration-150"
                                    :class="currentLang === language.code ? 'text-blue-600 font-medium bg-blue-50/50' : 'text-gray-700'">
                                <span x-text="language.code"></span>
                                <svg x-show="currentLang === language.code" 
                                     class="size-4 text-blue-600" 
                                     fill="none" 
                                     stroke="currentColor" 
                                     viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Currency Selector -->
            <div class="relative flex items-center" x-data="{ 
                currencyOpen: false,
                currentCurrency: @entangle('currentCurrency'),
                currencies: [
                    { code: 'USD' },
                    { code: 'EUR' },
                    { code: 'GBP' },
                    { code: 'JPY' },
                    { code: 'TRY' },
                    { code: 'AUD' },
                    { code: 'CAD' },
                    { code: 'CHF' }
                ]
            }">
                <button @click="currencyOpen = !currencyOpen"
                        class="p-2.5 text-gray-700 hover:text-gray-900 hover:bg-gray-100 rounded-xl transition-all duration-200 h-11 w-11 flex items-center justify-center">
                    <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </button>
                <span @click="currencyOpen = !currencyOpen" 
                      class="text-sm text-gray-700 cursor-pointer hover:text-gray-900" 
                      x-text="currentCurrency"></span>

                <!-- Currency Dropdown -->
                <div x-show="currencyOpen"
                     x-cloak
                     @click.outside="currencyOpen = false"
                     class="fixed inset-x-4 top-24 mx-auto bg-white rounded-lg shadow-lg border border-gray-200/80 backdrop-blur-sm z-50 lg:absolute lg:inset-auto lg:right-0 lg:top-full lg:w-36"
                     :class="{ 'lg:fixed': window.scrollY > 0 }"
                     @scroll.window="if(window.scrollY > 0) currencyOpen = false">
                    
                    <!-- Currency Options -->
                    <div class="max-h-[60vh] lg:max-h-96 overflow-y-auto py-1">
                        <template x-for="currency in currencies" :key="currency.code">
                            <button @click="currentCurrency = currency.code; currencyOpen = false; $wire.changeCurrency(currency.code)"
                                    class="w-full flex items-center justify-between px-4 py-2 text-sm hover:bg-gray-50 transition-colors duration-150"
                                    :class="currentCurrency === currency.code ? 'text-blue-600 font-medium bg-blue-50/50' : 'text-gray-700'">
                                <span x-text="currency.code"></span>
                                <svg x-show="currentCurrency === currency.code" 
                                     class="size-4 text-blue-600" 
                                     fill="none" 
                                     stroke="currentColor" 
                                     viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Mobile Navigation -->
    <div class="lg:hidden">
        <!-- First Line -->
        <div class="flex items-center justify-between px-4 pt-6 pb-2">
            <!-- Left Side -->
            <div class="flex items-center">
                <button @click="sidebarOpen = true"
                        class="p-2.5 pl-0 text-gray-700 hover:text-gray-900 hover:bg-gray-100 rounded-xl transition-all duration-200 h-11 w-11 flex items-center justify-center">
                    <span class="sr-only">Open sidebar</span>
                    <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                </button>
                <span class="text-2xl font-semibold" style="font-family: 'Poppins', sans-serif; background: linear-gradient(to right, #3b82f6, #8b5cf6); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                    İndirmGo
                </span>
            </div>

            <!-- Right Side -->
            <div class="flex items-center ">
                <!-- Shopping Cart -->
                <div class="relative flex items-center gap-x-2" x-data="{ cartOpen: false }">
                    <button @click="cartOpen = !cartOpen"
                            class="p-2.5 text-gray-700 hover:text-gray-900 hover:bg-gray-100 rounded-xl transition-all duration-200 relative group h-11 w-11 flex items-center justify-center"
                            aria-label="Shopping cart">
                        <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                        </svg>
                        <!-- Cart Badge -->
                        <div x-show="cartCount > 0"
                             x-transition:enter="transition ease-out duration-300"
                             x-transition:enter-start="opacity-0 scale-50"
                             x-transition:enter-end="opacity-100 scale-100"
                             class="absolute -top-2 -right-1 bg-blue-500 text-white text-xs font-bold rounded-full h-5 min-w-[1.25rem] px-1 flex items-center justify-center group-hover:bg-blue-600 transition-colors duration-200 z-50"
                             x-text="cartCount">
                        </div>
                    </button>
                    <span @click="cartOpen = !cartOpen" 
                          class="hidden sm:block text-sm text-gray-700 cursor-pointer hover:text-gray-900">Cart</span>

                    <!-- Cart Dropdown - Repositioned for Mobile -->
                    <div x-show="cartOpen"
                         x-cloak
                         @click.outside="cartOpen = false"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-100"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-95"
                         class="fixed inset-x-4 top-24 mx-auto bg-white rounded-lg shadow-lg border border-gray-200 z-50 lg:absolute lg:inset-auto lg:right-0 lg:top-full lg:w-80"
                         :class="{ 'lg:fixed': window.scrollY > 0 }"
                         @scroll.window="if(window.scrollY > 0) cartOpen = false">

                        <!-- Cart Header -->
                        <div class="p-4 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">Shopping Cart</h3>
                            <p x-show="cartCount === 0" class="mt-1 text-sm text-gray-500">Your cart is empty</p>
                        </div>

                        <!-- Cart Items -->
                        <div x-show="cartCount > 0" class="max-h-[60vh] lg:max-h-96 overflow-y-auto">
                            <div class="p-4 border-b border-gray-200">
                                <div class="flex items-center space-x-4">
                                    <div class="flex-shrink-0 size-16 bg-gray-100 rounded-md"></div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-900 truncate">Product Name</p>
                                        <p class="text-sm text-gray-500">$99.00</p>
                                    </div>
                                    <button @click.stop="removeFromCart()" 
                                            class="p-1 text-gray-400 hover:text-gray-500 rounded-full hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-200">
                                        <span class="sr-only">Remove item</span>
                                        <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Cart Footer -->
                        <div x-show="cartCount > 0" class="p-4 border-t border-gray-200">
                            <div class="flex justify-between text-base font-medium text-gray-900 mb-4">
                                <p>Subtotal</p>
                                <p>$99.00</p>
                            </div>
                            <a href="#" 
                               @click.stop
                               class="w-full flex justify-center items-center px-6 py-3 border border-transparent rounded-lg shadow-sm text-base font-medium text-white bg-blue-600 hover:bg-blue-700 transition-colors duration-200">
                                Checkout
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Language Button -->
                <div class="relative" x-data="{ 
                    languageOpen: false,
                    currentLang: @entangle('currentLanguage'),
                    languages: [
                        { code: 'EN' },
                        { code: 'TR' },
                        { code: 'DE' },
                        { code: 'ES' },
                        { code: 'FR' },
                        { code: 'IT' },
                        { code: 'JP' },
                        { code: 'AR' },
                        { code: 'KR' }
                    ]
                }">
                    <button @click="languageOpen = !languageOpen"
                            class="p-2.5 text-gray-700 hover:text-gray-900 hover:bg-gray-100 rounded-xl transition-all duration-200 h-11 w-11 flex items-center justify-center">
                            <span class="text-sm text-gray-700" x-text="currentLang"></span>
                    </button>

                    <!-- Language Dropdown -->
                    <div x-show="languageOpen"
                         x-cloak
                         @click.outside="languageOpen = false"
                         class="fixed right-4 left-auto top-20 mx-auto bg-white rounded-lg shadow-lg border border-gray-200/80 backdrop-blur-sm z-50 w-32"
                         :class="{ 'lg:fixed': window.scrollY > 0 }"
                         @scroll.window="if(window.scrollY > 0) languageOpen = false">
                        
                        <!-- Language Options -->
                        <div class="max-h-[60vh] lg:max-h-96 overflow-y-auto py-1">
                            <template x-for="language in languages" :key="language.code">
                                <button @click="changeLang(language.code)"
                                        class="w-full flex items-center justify-between px-4 py-2 text-sm hover:bg-gray-50 transition-colors duration-150"
                                        :class="currentLang === language.code ? 'text-blue-600 font-medium bg-blue-50/50' : 'text-gray-700'">
                                    <span x-text="language.code"></span>
                                    <svg x-show="currentLang === language.code" 
                                         class="size-4 text-blue-600" 
                                         fill="none" 
                                         stroke="currentColor" 
                                         viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Currency Button -->
                <div class="relative" x-data="{ 
                    currencyOpen: false,
                    currentCurrency: @entangle('currentCurrency'),
                    currencies: [
                        { code: 'USD' },
                        { code: 'EUR' },
                        { code: 'GBP' },
                        { code: 'JPY' },
                        { code: 'TRY' },
                        { code: 'AUD' },
                        { code: 'CAD' },
                        { code: 'CHF' }
                    ]
                }">
                    <button @click="currencyOpen = !currencyOpen"
                            class="p-2.5 text-gray-700 hover:text-gray-900 hover:bg-gray-100 rounded-xl transition-all duration-200 h-11 w-11 flex items-center justify-center">
                        <span class="text-sm text-gray-700" x-text="currentCurrency"></span>
                    </button>
                    

                    <!-- Currency Dropdown -->
                    <div x-show="currencyOpen"
                         x-cloak
                         @click.outside="currencyOpen = false"
                         class="fixed right-4 left-auto top-20 mx-auto bg-white rounded-lg shadow-lg border border-gray-200/80 backdrop-blur-sm z-50 w-36"
                         :class="{ 'lg:fixed': window.scrollY > 0 }"
                         @scroll.window="if(window.scrollY > 0) currencyOpen = false">
                        
                        <!-- Currency Options -->
                        <div class="max-h-[60vh] lg:max-h-96 overflow-y-auto py-1">
                            <template x-for="currency in currencies" :key="currency.code">
                                <button @click="currentCurrency = currency.code; currencyOpen = false; $wire.changeCurrency(currency.code)"
                                        class="w-full flex items-center justify-between px-4 py-2 text-sm hover:bg-gray-50 transition-colors duration-150"
                                        :class="currentCurrency === currency.code ? 'text-blue-600 font-medium bg-blue-50/50' : 'text-gray-700'">
                                    <span x-text="currency.code"></span>
                                    <svg x-show="currentCurrency === currency.code" 
                                         class="size-4 text-blue-600" 
                                         fill="none" 
                                         stroke="currentColor" 
                                         viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Second Line - Search -->
        <div class="px-4 py-2">
            <div class="relative">
                <!-- Search Input Container -->
                <div class="relative transition-all duration-300 ease-out rounded-xl bg-white shadow-lg border border-gray-200/80">
                    <!-- Search Input Wrapper -->
                    <div class="relative flex items-center w-full h-11">
                        <!-- Search Icon -->
                        <div class="absolute left-0 p-2.5 text-gray-400">
                            <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </div>

                        <!-- Search Input -->
                        <input type="text" 
                               placeholder="Search for products, categories..." 
                               x-model="searchQuery"
                               @input.debounce.300ms="search()"
                               class="w-full h-full pl-11 pr-4 bg-transparent text-sm placeholder-gray-400 outline-none focus:outline-none focus:ring-0 border-0 focus:border-0"
                        >

                        <!-- Clear Button -->
                        <button x-show="searchQuery"
                                @click="searchQuery = ''"
                                class="absolute right-3 p-1 text-gray-400 hover:text-gray-600 focus:outline-none">
                            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Search Results Dropdown -->
                <div x-show="searchQuery.length >= 2" 
                     x-cloak
                     class="absolute mt-2 w-full bg-white/95 backdrop-blur-sm rounded-xl shadow-xl border border-gray-200/80 overflow-hidden z-50"
                     @click.away.stop>
                    
                    <!-- Loading State -->
                    <div x-show="isLoading" 
                         class="p-4">
                        <div class="flex items-center justify-center space-x-2 text-sm text-gray-500">
                            <svg class="animate-spin size-5 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span>Searching...</span>
                        </div>
                    </div>

                    <!-- Results -->
                    <div x-show="!isLoading && searchResults.length > 0" 
                         class="max-h-[300px] overflow-y-auto">
                        <template x-for="result in searchResults" :key="result.title">
                            <a :href="result.url" 
                               class="flex items-center px-4 py-3 hover:bg-gray-50 transition-colors duration-150 border-b border-gray-100 last:border-0 group">
                                <!-- Result Icon -->
                                <div class="flex-shrink-0 size-10 rounded-lg bg-gray-100 flex items-center justify-center text-gray-500 group-hover:bg-blue-50 group-hover:text-blue-600 transition-colors duration-150">
                                    <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </div>
                                <!-- Result Content -->
                                <div class="ml-4 flex-1">
                                    <p class="text-sm font-medium text-gray-900 group-hover:text-blue-600" x-text="result.title"></p>
                                    <p class="text-xs text-gray-500 mt-0.5" x-text="result.category || 'Product'"></p>
                                </div>
                            </a>
                        </template>
                    </div>

                    <!-- No Results -->
                    <div x-show="!isLoading && searchResults.length === 0 && searchQuery.length >= 2" 
                         class="p-4 text-center">
                        <div class="text-gray-500 text-sm">
                            <svg class="size-6 mx-auto mb-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M12 14a3 3 0 100-6 3 3 0 000 6z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <p>No results found for "<span class="font-medium" x-text="searchQuery"></span>"</p>
                            <p class="mt-1 text-xs text-gray-400">Try adjusting your search or filter to find what you're looking for.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div x-show="searchOpen"
         x-cloak
         class="fixed inset-0 z-50 bg-gray-900/50 p-4"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        <div class="relative bg-white rounded-lg p-4 max-w-lg mx-auto mt-16"
             @click.away="searchOpen = false">
            <div class="relative">
                <input type="text"
                       placeholder="Search..."
                       x-model="searchQuery"
                       @input.debounce.300ms="search()"
                       class="w-full pl-10 pr-12 py-2 rounded-lg border border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all duration-200"
                       @keydown.escape="searchOpen = false"
                >
                <div class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                    <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
                <button @click="searchQuery = ''"
                        x-show="searchQuery"
                        class="absolute right-3 top-1/2 -translate-y-1/2 p-1 text-gray-400 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-200 rounded-full">
                    <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Mobile Search Results -->
            <div x-show="searchQuery.length >= 2"
                 class="mt-4 bg-white rounded-lg overflow-hidden">
                <!-- Loading State -->
                <div x-show="isLoading" class="p-4 text-sm text-gray-500">
                    <div class="flex items-center justify-center space-x-2">
                        <svg class="animate-spin size-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span>Searching...</span>
                    </div>
                </div>

                <!-- Results -->
                <div x-show="!isLoading && searchResults.length > 0">
                    <template x-for="result in searchResults" :key="result.title">
                        <a :href="result.url"
                           class="block px-4 py-3 hover:bg-gray-50 transition duration-150 border-b border-gray-100 last:border-0">
                            <div class="text-sm text-gray-900" x-text="result.title"></div>
                        </a>
                    </template>
                </div>

                <!-- No Results -->
                <div x-show="!isLoading && searchResults.length === 0 && searchQuery.length >= 2"
                     class="p-4 text-sm text-gray-500 text-center">
                    No results found for "<span x-text="searchQuery"></span>"
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile Navigation Sidebar -->
    <div x-show="sidebarOpen"
         x-cloak
         class="relative z-50">
        <!-- Backdrop -->
        <div x-show="sidebarOpen"
             x-transition:enter="transition-opacity ease-linear duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition-opacity ease-linear duration-300"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 bg-gray-900/80"
             @click="sidebarOpen = false">
        </div>

        <!-- Sidebar Panel -->
        <div x-show="sidebarOpen"
             x-transition:enter="transition ease-in-out duration-300 transform"
             x-transition:enter-start="-translate-x-full"
             x-transition:enter-end="translate-x-0"
             x-transition:leave="transition ease-in-out duration-300 transform"
             x-transition:leave-start="translate-x-0"
             x-transition:leave-end="-translate-x-full"
             class="fixed inset-y-0 left-0 w-full max-w-xs bg-white shadow-lg">

            <!-- Sidebar Header -->
            <div class="flex items-center justify-between p-4 border-b">
                <span class="text-xl font-semibold" style="font-family: 'Poppins', sans-serif; background: linear-gradient(to right, #3b82f6, #8b5cf6); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                    İndirmGo
                </span>
                <button @click="sidebarOpen = false" class="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg">
                    <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Mobile Navigation Links -->
            <nav class="px-4 py-6 space-y-2">
                <a href="#" class="block px-4 py-2.5 text-base font-medium text-gray-900 rounded-lg hover:bg-gray-100">Home</a>
                <a href="#" class="block px-4 py-2.5 text-base font-medium text-gray-900 rounded-lg hover:bg-gray-100">Categories</a>
                <a href="#" class="block px-4 py-2.5 text-base font-medium text-gray-900 rounded-lg hover:bg-gray-100">New Arrivals</a>
                <a href="#" class="block px-4 py-2.5 text-base font-medium text-gray-900 rounded-lg hover:bg-gray-100">Deals</a>
                <div class="border-t border-gray-200 my-4"></div>
                <a href="#" class="block px-4 py-2.5 text-base font-medium text-gray-900 rounded-lg hover:bg-gray-100">About Us</a>
                <a href="#" class="block px-4 py-2.5 text-base font-medium text-gray-900 rounded-lg hover:bg-gray-100">Contact</a>
                <a href="#" class="block px-4 py-2.5 text-base font-medium text-gray-900 rounded-lg hover:bg-gray-100">Blog</a>

                <!-- Language Selector -->
                <div class="relative" x-data="{ 
                    languageOpen: false,
                    currentLang: @entangle('currentLanguage'),
                    languages: [
                        { code: 'EN' },
                        { code: 'TR' },
                        { code: 'DE' },
                        { code: 'ES' },
                        { code: 'FR' },
                        { code: 'IT' },
                        { code: 'JP' },
                        { code: 'AR' },
                        { code: 'KR' }
                    ]
                }">
                    <button @click="languageOpen = !languageOpen"
                            class="w-full flex items-center justify-between px-4 py-2.5 text-base font-medium text-gray-900 rounded-lg hover:bg-gray-100">
                        <div class="flex items-center gap-2">
                            <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"/>
                            </svg>
                            <span>Language</span>
                        </div>
                        <span x-text="currentLang" class="text-gray-600"></span>
                    </button>

                    <!-- Language Dropdown -->
                    <div x-show="languageOpen"
                         x-cloak
                         @click.outside="languageOpen = false"
                         class="absolute inset-x-0 mt-2 bg-white rounded-lg shadow-lg border border-gray-200 z-50 max-h-[60vh] overflow-y-auto">
                        <div class="py-1">
                            <template x-for="language in languages" :key="language.code">
                                <button @click="currentLang = language.code; languageOpen = false; $wire.changeLanguage(language.code)"
                                        class="w-full flex items-center justify-between px-4 py-2 text-sm hover:bg-gray-50 transition-colors duration-150"
                                        :class="currentLang === language.code ? 'text-blue-600 font-medium bg-blue-50/50' : 'text-gray-700'">
                                    <span x-text="language.code"></span>
                                    <svg x-show="currentLang === language.code" 
                                         class="size-4 text-blue-600" 
                                         fill="none" 
                                         stroke="currentColor" 
                                         viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Currency Selector -->
                <div class="relative" x-data="{ 
                    currencyOpen: false,
                    currentCurrency: @entangle('currentCurrency'),
                    currencies: [
                        { code: 'USD' },
                        { code: 'EUR' },
                        { code: 'GBP' },
                        { code: 'JPY' },
                        { code: 'TRY' },
                        { code: 'AUD' },
                        { code: 'CAD' },
                        { code: 'CHF' }
                    ]
                }">
                    <button @click="currencyOpen = !currencyOpen"
                            class="w-full flex items-center justify-between px-4 py-2.5 text-base font-medium text-gray-900 rounded-lg hover:bg-gray-100">
                        <div class="flex items-center gap-2">
                            <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span>Currency</span>
                        </div>
                        <span x-text="currentCurrency" class="text-gray-600"></span>
                    </button>

                    <!-- Currency Dropdown -->
                    <div x-show="currencyOpen"
                         x-cloak
                         @click.outside="currencyOpen = false"
                         class="absolute inset-x-0 mt-2 bg-white rounded-lg shadow-lg border border-gray-200 z-50 max-h-[60vh] overflow-y-auto">
                        <div class="py-1">
                            <template x-for="currency in currencies" :key="currency.code">
                                <button @click="currentCurrency = currency.code; currencyOpen = false; $wire.changeCurrency(currency.code)"
                                        class="w-full flex items-center justify-between px-4 py-2 text-sm hover:bg-gray-50 transition-colors duration-150"
                                        :class="currentCurrency === currency.code ? 'text-blue-600 font-medium bg-blue-50/50' : 'text-gray-700'">
                                    <span x-text="currency.code"></span>
                                    <svg x-show="currentCurrency === currency.code" 
                                         class="size-4 text-blue-600" 
                                         fill="none" 
                                         stroke="currentColor" 
                                         viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>
            </nav>
        </div>
    </div>
</div>
