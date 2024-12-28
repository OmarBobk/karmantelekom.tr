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
    }
}">
    <nav class="mx-auto flex max-w-7xl items-center justify-between p-6 lg:px-8 bg-white shadow-sm" aria-label="Global">
        <!-- Left side -->
        <div class="flex items-center gap-x-6">
            <button @click="sidebarOpen = true"
                    class="p-2 text-gray-700 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-all duration-200">
                <span class="sr-only">Open sidebar</span>
                <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                </svg>
            </button>
            <span class="text-2xl font-semibold" style="font-family: 'Poppins', sans-serif; background: linear-gradient(to right, #3b82f6, #8b5cf6); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                İndirmGo
            </span>
        </div>

        <!-- Center Navigation -->
        <div class="hidden lg:flex items-center justify-center flex-1 mx-12">
            <div class="flex items-center gap-x-12">
                <a href="#" class="text-md font-medium text-gray-700 hover:text-blue-600 transition-colors duration-200">Home</a>
                <a href="#" class="text-md font-medium text-gray-700 hover:text-blue-600 transition-colors duration-200">Categories</a>
                <a href="#" class="text-md font-medium text-gray-700 hover:text-blue-600 transition-colors duration-200">New Arrivals</a>
                <a href="#" class="text-md font-medium text-gray-700 hover:text-blue-600 transition-colors duration-200">Deals</a>
                <div class="relative group">
                    <button class="text-md font-medium text-gray-700 hover:text-blue-600 transition-colors duration-200 flex items-center gap-x-1">
                        More
                        <svg class="size-4 text-gray-400 group-hover:text-blue-600 transition-colors duration-200" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                        </svg>
                    </button>
                    <!-- More Dropdown -->
                    <div class="absolute left-1/2 -translate-x-1/2 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50">
                        <div class="py-1">
                            <a href="#" class="block px-4 py-2 text-md text-gray-700 hover:bg-gray-100">About Us</a>
                            <a href="#" class="block px-4 py-2 text-md text-gray-700 hover:bg-gray-100">Contact</a>
                            <a href="#" class="block px-4 py-2 text-md text-gray-700 hover:bg-gray-100">Blog</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right side -->
        <div class="flex items-center gap-x-6">
            <!-- Desktop Search -->
            <div class="hidden lg:block relative">
                <div class="relative group">
                    <input type="text"
                           placeholder="Search products..."
                           x-model="searchQuery"
                           @input.debounce.300ms="search()"
                           class="w-72 pl-10 pr-4 py-1.5 rounded-xl border border-gray-200 focus:border-blue-500 focus:ring-4 focus:ring-blue-100 transition-all duration-200 bg-gray-50 group-hover:bg-white text-sm"
                    >
                    <div class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 group-hover:text-gray-600 transition-colors duration-200">
                        <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>

                    <!-- Search Results Dropdown -->
                    <div x-show="searchQuery.length >= 2"
                         x-cloak
                         @click.away="searchQuery = ''; searchResults = []"
                         class="absolute mt-2 w-full bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden z-50">
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

            <!-- Shopping Cart -->
            <div class="relative" x-data="{ cartOpen: false }">
                <button @click="cartOpen = !cartOpen"
                        class="p-2.5 text-gray-700 hover:text-gray-900 hover:bg-gray-100 rounded-xl transition-all duration-200 relative group"
                        aria-label="Shopping cart">
                    <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                    </svg>
                    <!-- Cart Badge -->
                    <div x-show="cartCount > 0"
                         x-transition:enter="transition ease-out duration-300"
                         x-transition:enter-start="opacity-0 scale-50"
                         x-transition:enter-end="opacity-100 scale-100"
                         class="absolute -top-2 -right-1 bg-blue-500 text-white text-xs font-bold rounded-full h-5 min-w-[1.25rem] px-1 flex items-center justify-center group-hover:bg-blue-600 transition-colors duration-200"
                         x-text="cartCount">
                    </div>
                </button>

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
                     class="absolute right-0 mt-3 w-80 bg-white rounded-lg shadow-lg border border-gray-200 z-50">

                    <!-- Cart Header -->
                    <div class="p-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Shopping Cart</h3>
                        <p x-show="cartCount === 0" class="mt-1 text-sm text-gray-500">Your cart is empty</p>
                    </div>

                    <!-- Cart Items -->
                    <div x-show="cartCount > 0" class="max-h-96 overflow-y-auto">
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

            <!-- Mobile Search Button -->
            <button @click="searchOpen = true"
                    class="lg:hidden p-2.5 text-gray-700 hover:text-gray-900 hover:bg-gray-100 rounded-xl transition-all duration-200">
                <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </button>
        </div>
    </nav>

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
            </nav>
        </div>
    </div>
</div>
