<div x-data="{
    sidebarOpen: false,
    searchOpen: false,
    searchQuery: '',
    searchResults: [],
    isLoading: false,
    cartCount: 1,
    cartOpen: false,
    profileDropdownOpen: false,
    mobileProfileDropdownOpen: false,

    init() {
        this.$watch('sidebarOpen', value => {
            if (value) {
                document.body.style.overflow = 'hidden';
                document.body.style.touchAction = 'none';
            } else {
                document.body.style.overflow = '';
                document.body.style.touchAction = '';
            }
        });
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
            <a href="{{ route('main') }}" class="text-2xl font-semibold" style="font-family: 'Poppins', sans-serif; background: linear-gradient(to right, #3b82f6, #8b5cf6); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                İndirimGo
            </a>
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

            @guest
                <!-- Profile/Login Dropdown -->
                <div class="relative flex items-center gap-x-2"
                     x-data="{ open: false }"
                     @mouseleave="open = false">
                    <div class="flex items-center gap-x-2 cursor-pointer"
                         @mouseenter="open = true">
                        <div class="p-2.5 text-gray-700 hover:text-blue-600 rounded-xl transition-all duration-200 h-11 w-11 flex items-center justify-center group-hover:bg-gray-100">
                            <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                        </div>
                        <span class="text-sm text-gray-700 hover:text-blue-600 transition-colors duration-200">Login</span>
                    </div>

                    <!-- Dropdown Menu -->
                    <div x-show="open"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-95"
                         class="absolute right-[-100%] top-full mt-2 w-60 rounded-xl bg-white shadow-lg ring-1 ring-gray-200 z-10 focus:outline-none"
                         @mouseenter="open = true"
                         x-cloak>
                        <div class="p-4 space-y-3">
                            <a href="{{ route('login') }}"
                               class="block w-full px-4 py-2.5 text-sm font-medium text-white bg-gradient-to-r from-blue-500 to-purple-600 rounded-lg hover:from-blue-600 hover:to-purple-700 focus:outline-none focus:ring-2 focus:ring-blue-300 transition-all duration-200 text-center">
                                Login
                            </a>
                            <a href="{{ route('register') }}"
                               class="block w-full px-4 py-2.5 text-sm font-medium text-gray-700 bg-gray-50 rounded-lg hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-200 transition-all duration-200 text-center">
                                Register
                            </a>
                        </div>
                    </div>
                </div>
            @endguest

            @auth
                <!-- Profile Dropdown -->
                <div class="relative flex items-center gap-x-2"
                     x-data="{ open: false }"
                     @mouseleave="open = false">
                    <div class="flex items-center gap-x-2 cursor-pointer"
                         @mouseenter="open = true"
                         :class="{ 'text-blue-600': open }">
                        <div class="p-2.5 pr-0 rounded-xl transition-all duration-200 h-11 w-11 flex items-center justify-center group-hover:bg-gray-100"
                             :class="{ 'text-blue-600 pr-2.5': open, 'text-gray-700 hover:text-blue-600': !open }">

                             <svg x-show="!open" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                            </svg>


                            <svg x-show="open" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-6" x-cloak>
                                <path fill-rule="evenodd" d="M7.5 6a4.5 4.5 0 1 1 9 0 4.5 4.5 0 0 1-9 0ZM3.751 20.105a8.25 8.25 0 0 1 16.498 0 .75.75 0 0 1-.437.695A18.683 18.683 0 0 1 12 22.5c-2.786 0-5.433-.608-7.812-1.7a.75.75 0 0 1-.437-.695Z" clip-rule="evenodd" />
                            </svg>

                        </div>
                        <span class="text-sm transition-colors duration-200"
                              :class="{ 'text-blue-600': open, 'text-gray-700 hover:text-blue-600': !open }">Account</span>
                    </div>

                    <!-- Dropdown Menu -->
                    <div x-show="open"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-95"
                         class="absolute right-[-100%] top-full mt-2 w-60 rounded-xl bg-white shadow-lg ring-1 ring-gray-200 z-50 focus:outline-none"
                         @mouseenter="open = true"
                         x-cloak>
                        <div class="p-4">
                            <p class="text-md px-[.90rem] font-medium text-transparent bg-clip-text bg-gradient-to-r from-blue-500 to-purple-600">
                                {{ Auth::user()->name }}
                            </p>
                            <div class="mt-3 space-y-1">

                                @hasanyrole('admin|salesperson')
                                    <a href="{{ route('subdomain.main') }}" class="flex items-center gap-x-3 px-3 py-2 text-sm text-gray-700 rounded-lg hover:gap-x-5 hover:bg-gray-50 transition-colors duration-200">
                                        <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                        </svg>
                                        Dashboard
                                    </a>
                                @else
                                    <a href="{{ route('account') }}" class="flex items-center gap-x-3 px-3 py-2 text-sm text-gray-700 rounded-lg hover:gap-x-5 hover:bg-gray-50 transition-colors duration-200">
                                        <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                        </svg>
                                        Profile
                                    </a>
                                @endrole

                                <a href="#" class="flex items-center gap-x-3 px-3 py-2 text-sm text-gray-700 rounded-lg hover:gap-x-5 hover:bg-gray-50 transition-colors duration-200">
                                    <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                                    </svg>
                                    Downloads
                                </a>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="w-full flex items-center gap-x-3 px-3 py-2 text-sm text-red-600 rounded-lg hover:gap-x-5 hover:bg-red-50 transition-colors duration-200">
                                        <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                        </svg>
                                        Logout
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            @endauth

            <!-- Favorites -->
            <div class="relative flex items-center gap-x-2">
                <button class="p-2.5 text-gray-700 hover:text-gray-900 hover:bg-gray-100 rounded-xl transition-all duration-200 h-11 w-11 flex items-center justify-center">
                    <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                    </svg>
                </button>
                <span class="text-sm text-gray-700 cursor-pointer hover:text-gray-900">Favorites</span>
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
                <a href="{{ route('main') }}" class="text-2xl font-semibold" style="font-family: 'Poppins', sans-serif; background: linear-gradient(to right, #3b82f6, #8b5cf6); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                    İndirmGo
                </a>
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

                <!-- Profile -->

                @guest
                    <!-- Mobile Login Dropdown -->
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open"
                                class="p-2.5 text-gray-700 hover:text-blue-600 rounded-xl transition-all duration-200 h-11 w-11 flex items-center justify-center">
                            <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                        </button>

                        <!-- Mobile Dropdown -->
                        <div x-show="open"
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-150"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95"
                             @click.away="open = false"
                             class="absolute left-[-6rem] mt-2 w-48 rounded-xl bg-white shadow-lg ring-1 ring-gray-200 z-50 focus:outline-none"
                             x-cloak>
                            <div class="p-3 space-y-2">
                                <a href="{{ route('login') }}"
                                   class="block w-full px-4 py-2 text-sm font-medium text-white bg-gradient-to-r from-blue-500 to-purple-600 rounded-lg hover:from-blue-600 hover:to-purple-700 focus:outline-none focus:ring-2 focus:ring-blue-300 transition-all duration-200 text-center">
                                    Login
                                </a>
                                <a href="{{ route('register') }}"
                                   class="block w-full px-4 py-2 text-sm font-medium text-gray-700 bg-gray-50 rounded-lg hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-200 transition-all duration-200 text-center">
                                    Register
                                </a>
                            </div>
                        </div>
                    </div>
                @endguest

                @auth
                    <!-- Mobile Profile Dropdown -->
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open"
                                class="p-2.5 text-gray-700 hover:text-blue-600 rounded-xl transition-all duration-200 h-11 w-11 flex items-center justify-center">
                            <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                        </button>

                        <!-- Mobile Dropdown -->
                        <div x-show="open"
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-150"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95"
                             @click.away="open = false"
                             class="absolute right-[-125%] z-50 mt-2 w-48 rounded-xl bg-white shadow-lg ring-1 ring-gray-200 focus:outline-none"
                             x-cloak>
                            <div class="p-3">
                                <p class="text-sm font-medium text-transparent bg-clip-text bg-gradient-to-r from-blue-500 to-purple-600 px-3 py-2">
                                    {{ Auth::user()->name }}
                                </p>
                                <div class="mt-2 space-y-1">
                                    @hasanyrole('admin|salesperson')
                                        <a href="{{ route('subdomain.main') }}" class="flex items-center gap-x-3 px-3 py-2 text-sm text-gray-700 rounded-lg hover:bg-gray-50 transition-colors duration-200">
                                            <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                            </svg>
                                            Dashboard
                                        </a>
                                    @else
                                        <a href="{{ route('account') }}" class="flex items-center gap-x-3 px-3 py-2 text-sm text-gray-700 rounded-lg hover:gap-x-5 hover:bg-gray-50 transition-colors duration-200">
                                            <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                            </svg>
                                            Profile
                                        </a>
                                    @endrole
                                    <a href="#" class="flex items-center gap-x-3 px-3 py-2 text-sm text-gray-700 rounded-lg hover:bg-gray-50 transition-colors duration-200">
                                        <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                                        </svg>
                                        Downloads
                                    </a>
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button type="submit" class="w-full flex items-center gap-x-3 px-3 py-2 text-sm text-red-600 rounded-lg hover:bg-red-50 transition-colors duration-200">
                                            <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                            </svg>
                                            Logout
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                @endauth

                <!-- Favorites -->
                <div class="relative flex items-center gap-x-2">
                    <button class="p-2.5 text-gray-700 hover:text-gray-900 hover:bg-gray-100 rounded-xl transition-all duration-200 h-11 w-11 flex items-center justify-center">
                        <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                        </svg>
                    </button>
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

    <!-- Navigation Sidebar -->
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
             class="fixed overflow-auto inset-y-0 left-0 w-full max-w-xs bg-white shadow-lg">

            <!-- Sidebar Header -->
            <div class="flex items-center justify-between p-4 border-b">
                <a href="{{ route('main') }}" class="text-xl font-semibold" style="font-family: 'Poppins', sans-serif; background: linear-gradient(to right, #3b82f6, #8b5cf6); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                    İndirmGo
                </a>
                <button @click="sidebarOpen = false" class="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg">
                    <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Navigation Links -->
            <nav class="px-4 py-6 space-y-2">
                <a href="#" class="block px-4 py-2.5 text-base font-medium text-gray-900 rounded-lg hover:bg-gray-100">Home</a>
                <a href="{{route('products')}}" class="block px-4 py-2.5 text-base font-medium text-gray-900 rounded-lg hover:bg-gray-100">Categories</a>
                <a href="#" class="block px-4 py-2.5 text-base font-medium text-gray-900 rounded-lg hover:bg-gray-100">New Arrivals</a>
                <a href="#" class="block px-4 py-2.5 text-base font-medium text-gray-900 rounded-lg hover:bg-gray-100">Deals</a>
                <div class="border-t border-gray-200 my-4"></div>
                <a href="#" class="block px-4 py-2.5 text-base font-medium text-gray-900 rounded-lg hover:bg-gray-100">About Us</a>
                <a href="#" class="block px-4 py-2.5 text-base font-medium text-gray-900 rounded-lg hover:bg-gray-100">Contact</a>
                <a href="#" class="block px-4 py-2.5 text-base font-medium text-gray-900 rounded-lg hover:bg-gray-100">Blog</a>

                <div class="relative py-1 px-2 text-sm border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    x-data="{ open: false }">
                    <a
                        href="#"
                        @click="open = !open"
                        @click.away="open = false"
                        class="flex justify-between items-center px-4 py-2.5 text-base font-medium text-gray-900 rounded-lg hover:bg-gray-100"
                    >
                        <span>{{ $currentCurrency }}</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </a>

                    <div
                        x-show="open"
                        x-transition
                        class="absolute bottom-full mb-2 right-0 w-24 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50"
                    >
                        <div class="py-1">
                            <button
                                wire:click="switchCurrency('$')"
                                class="block w-full px-4 py-2 text-sm text-left text-gray-700 hover:bg-gray-100"
                                :class="{ 'bg-gray-50': '{{ $currentCurrency }}' === '$' }"
                            >
                                $
                            </button>
                            <button
                                wire:click="switchCurrency('TL')"
                                class="block w-full px-4 py-2 text-sm text-left text-gray-700 hover:bg-gray-100"
                                :class="{ 'bg-gray-50': '{{ $currentCurrency }}' === 'TL' }"
                            >
                                TL
                            </button>
                        </div>
                    </div>
                </div>
            </nav>
        </div>
    </div>
</div>
