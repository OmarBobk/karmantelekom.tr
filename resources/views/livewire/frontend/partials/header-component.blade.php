<!-- Navigation Component -->
<div x-data="{
    sidebarOpen: false,
    searchOpen: false,
    searchQuery: '',
    searchResults: [],
    isLoading: false,
    profileDropdownOpen: false,
    mobileProfileDropdownOpen: false,
    languageOpen: false,

    init() {
        this.$watch('sidebarOpen', value => {
            document.body.styl2e.overflow = value ? 'hidden' : '';
            document.body.style.touchAction = value ? 'none' : '';
        });
    }
}" class="relative">
    <!-- Main Navigation Bar -->
    <nav class="mx-auto max-w-7xl px-4 lg:px-8 bg-white shadow-sm"
         aria-label="Global"
         :class="{ 'fixed inset-x-0 top-0 z-50 bg-white/95 backdrop-blur-sm': searchOpen }">

        <!-- Desktop Navigation -->
        <div class=" flex items-center justify-between pt-6 pb-2 sm:flex-nowrap flex-wrap">
            <!-- Left Section: Logo & Menu -->
            <div class="flex items-center gap-x-2">
                <button @click="sidebarOpen = true"
                        class="p-2.5 pl-0 text-gray-700 hover:pl-2.5 hover:text-gray-900 hover:bg-gray-100 rounded-xl transition-all duration-200"
                        aria-expanded="false"
                        aria-controls="navigation-menu">
                    <span class="sr-only">Open menu</span>
                    <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                </button>
                <a href="{{ route('main') }}" class="hidden sm:block text-2xl font-semibold" style="font-family: 'Poppins', sans-serif; background: linear-gradient(to right, #3b82f6, #8b5cf6); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                    {{config('app.name')}}
                </a>
            </div>
            <div class="block sm:hidden">
                <a href="{{ route('main') }}" class="text-4xl font-semibold" style="font-family: 'Poppins', sans-serif; background: linear-gradient(to right, #3b82f6, #8b5cf6); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                    {{config('app.name')}}
                </a>
            </div>

            <!-- Center Section: Search -->
            <div class="flex-auto max-w-xl px-0 sm:px-4 py-4 sm:py-0 order-last sm:order-none">
                @include('livewire.frontend.partials.search-bar')
            </div>

            <!-- Right Section: Search Icon & Language -->
            <div class="flex items-center gap-x-2">
                <!-- Language Selector -->
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open"
                            @click.away="open = false"
                            class="p-2.5 text-gray-700 hover:text-blue-600 hover:bg-gray-100 rounded-xl transition-all duration-200 h-11 w-11 flex items-center justify-center">
                        <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129" />
                        </svg>
                    </button>

                    <!-- Language Dropdown -->
                    <div x-show="open"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-95"
                         class="absolute right-0 mt-2 w-48 rounded-xl bg-white shadow-lg ring-1 ring-black ring-opacity-5 z-50"
                         x-cloak>
                        <div class="py-1">
                            <button wire:click="switchLanguage('en')" class="block w-full px-4 py-2 text-sm text-left text-gray-700 hover:bg-gray-100">
                                English
                            </button>
                            <button wire:click="switchLanguage('tr')" class="block w-full px-4 py-2 text-sm text-left text-gray-700 hover:bg-gray-100">
                                Türkçe
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </nav>

    <!-- Sidebar Navigation Menu -->
    <div x-show="sidebarOpen"
         x-cloak
         class="relative z-50"
         role="dialog"
         aria-modal="true">
        <!-- Backdrop -->
        <div x-show="sidebarOpen"
             x-transition:enter="transition-opacity ease-linear duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition-opacity ease-linear duration-300"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 bg-gray-900/80"
             @click="sidebarOpen = false"
             aria-hidden="true">
        </div>

        <!-- Sidebar Panel -->
        <div x-show="sidebarOpen"
             x-transition:enter="transition ease-in-out duration-300 transform"
             x-transition:enter-start="-translate-x-full"
             x-transition:enter-end="translate-x-0"
             x-transition:leave="transition ease-in-out duration-300 transform"
             x-transition:leave-start="translate-x-0"
             x-transition:leave-end="-translate-x-full"
             class="fixed inset-y-0 left-0 w-full max-w-xs bg-white shadow-lg overflow-y-auto">

            <!-- Sidebar Header -->
            <div class="flex items-center justify-between p-4 border-b">
                <a href="{{ route('main') }}" class="text-xl font-semibold font-poppins bg-gradient-to-r from-blue-500 to-purple-600 bg-clip-text text-transparent">
                    İndirimGo
                </a>
                <button @click="sidebarOpen = false"
                        class="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg"
                        aria-label="Close menu">
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

                <!-- Currency Selector -->
                <div class="relative py-1 px-2 text-sm border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                     x-data="{ open: false }">
                    <button
                        @click="open = !open"
                        @click.away="open = false"
                        class="flex justify-between items-center w-full px-4 py-2.5 text-base font-medium text-gray-900 rounded-lg hover:bg-gray-100"
                        aria-expanded="false">
                        <span>{{ $currentCurrency }}</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <div
                        x-show="open"
                        x-transition
                        class="absolute bottom-full mb-2 right-0 w-24 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50"
                        x-cloak>
                        <div class="py-1">
                            <button
                                wire:click="switchCurrency('$')"
                                class="block w-full px-4 py-2 text-sm text-left text-gray-700 hover:bg-gray-100"
                                :class="{ 'bg-gray-50': '{{ $currentCurrency }}' === '$' }">
                                $
                            </button>
                            <button
                                wire:click="switchCurrency('TL')"
                                class="block w-full px-4 py-2 text-sm text-left text-gray-700 hover:bg-gray-100"
                                :class="{ 'bg-gray-50': '{{ $currentCurrency }}' === 'TL' }">
                                TL
                            </button>
                        </div>
                    </div>
                </div>
            </nav>
        </div>
    </div>
</div>
