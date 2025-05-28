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
                <a href="{{ route('main') }}" class="hidden sm:block text-2xl font-semibold" style="font-family: 'Poppins', sans-serif; background: linear-gradient(to right, #059669, #10b981); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                    <img src="{{ Storage::url('title-logo.svg') }}" class="w-24" alt="">
                </a>
            </div>
            <div class="block sm:hidden">
                <a href="{{ route('main') }}" class="text-4xl font-semibold" style="font-family: 'Poppins', sans-serif; background: linear-gradient(to right, #059669, #10b981); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                    <img src="{{ Storage::url('title-logo.svg') }}" class="w-24" alt="">
                </a>
            </div>

            <!-- Center Section: Search -->
            <div class="flex-auto max-w-xl px-0 sm:px-4 py-4 sm:py-0 order-last sm:order-none">
                @livewire('frontend.search-component', [], key($searchComponentKey))
            </div>

            <!-- Right Section: Search Icon & Language -->
            <div class="flex items-center gap-x-2">

                <!-- Cart Component -->
                <livewire:frontend.cart.cart-component />

                <!-- Profile/Auth Section -->
                @guest
                    @include('livewire.frontend.partials.guest-menu')
                @else
                    @include('livewire.frontend.partials.auth-menu')
                @endguest

                <!-- Favorites -->
                <div class="relative flex items-center gap-x-2">
                    <button class="p-2.5 text-gray-700 hover:text-blue-600 hover:bg-gray-100 rounded-xl transition-all duration-200 h-11 w-11 flex items-center justify-center">
                        <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                        </svg>
                    </button>
                    <span class="hidden lg:inline text-sm text-gray-700 cursor-pointer hover:text-gray-900">Favorites</span>
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
             x-transition:enter-start="{{ app()->getLocale() === 'ar' ? 'translate-x-full' : '-translate-x-full' }}"
             x-transition:enter-end="translate-x-0"
             x-transition:leave="transition ease-in-out duration-300 transform"
             x-transition:leave-start="translate-x-0"
             x-transition:leave-end="{{ app()->getLocale() === 'ar' ? 'translate-x-full' : '-translate-x-full' }}"
             class="fixed inset-y-0 {{ app()->getLocale() == 'ar' ? 'right-0' : 'left-0' }} w-full max-w-xs bg-white shadow-lg overflow-y-auto">

            <!-- Sidebar Header -->
            <div class="flex items-center justify-between py-6 {{app()->getLocale() == 'ar' ? 'pl-8 pr-10' : 'pl-10 pr-8'}} border-b border-gray-100">
                <a href="{{ route('main') }}" class="text-2xl font-semibold font-poppins bg-gradient-to-r from-emerald-500 to-teal-600 bg-clip-text text-transparent">
                    <img src="{{ Storage::url('title-logo.svg') }}" class="w-24" alt="">
                </a>
                <button @click="sidebarOpen = false"
                        class="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-colors duration-200"
                        aria-label="Close menu">
                    <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Navigation Links -->
            <nav class="p-6 space-y-6 flex flex-col justify-between h-[calc(100vh-6rem)]">
                <div class="space-y-4">
                    <div class="space-y-1">
                        <h3 class="px-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">{{__('main.main_menu')}}</h3>
                        <a href="{{route('main')}}" class="flex items-center px-4 py-3 text-base font-medium text-gray-900 rounded-xl hover:bg-gray-50 transition-colors duration-200">
                            <svg class="w-5 h-5 {{ app()->getLocale() == 'ar' ? 'ml-3' : 'mr-3' }} text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                            </svg>
                            {{__('main.home')}}
                        </a>
                        <div x-data="{ open: false }" class="relative">
                            <button @click="open = !open"
                                    class="flex items-center justify-between w-full px-4 py-3 text-base font-medium text-gray-900 rounded-xl transition-all duration-200"
                                    :class="open ? 'bg-emerald-50 text-emerald-700' : 'hover:bg-gray-50'">
                                <div class="flex items-center">
                                    <svg class="w-5 h-5 {{ app()->getLocale() == 'ar' ? 'ml-3' : 'mr-3' }} transition-colors duration-200"
                                         :class="open ? 'text-emerald-500' : 'text-gray-500'"
                                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                    </svg>
                                    {{__('main.categories')}}
                                </div>
                                <svg class="w-4 h-4 transition-all duration-200"
                                     :class="open ? 'text-emerald-500 rotate-90' : 'text-gray-500'"
                                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            <div x-show="open"
                                 x-transition:enter="transition ease-out duration-200"
                                 x-transition:enter-start="opacity-0 -translate-y-2"
                                 x-transition:enter-end="opacity-100 translate-y-0"
                                 x-transition:leave="transition ease-in duration-150"
                                 x-transition:leave-start="opacity-100 translate-y-0"
                                 x-transition:leave-end="opacity-0 -translate-y-2"
                                 class="{{ app()->getLocale() == 'ar' ? 'pr-4' : 'pl-4' }} space-y-1 mt-1">
                                @foreach($categories as $category)
                                    <div x-data="{ open: false }" class="relative">
                                        <button @click="open = !open"
                                                class="flex items-center justify-between w-full px-4 py-2 text-sm text-left rounded-lg transition-all duration-200 group"
                                                :class="open ? 'bg-emerald-50 text-emerald-700' : 'text-gray-700 hover:bg-gray-50'">
                                            <div class="flex items-center">
                                                <div class="w-4 h-4 {{ app()->getLocale() == 'ar' ? 'ml-2' : 'mr-2' }} flex items-center justify-center">
                                                    <div class="w-0.5 h-4 bg-gray-300 group-hover:bg-emerald-400 transition-colors duration-200"></div>
                                                </div>
                                                <span class="font-medium">{{ $category->translated_name }}</span>
                                            </div>
                                            @if($category->children->count() > 0)
                                                <svg class="w-4 h-4 transition-all duration-200 {{ app()->getLocale() == 'ar' ? 'rotate-180' : '' }}"
                                                     :class="open ? 'text-emerald-500 rotate-90' : 'text-gray-500'"
                                                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                </svg>
                                            @endif
                                        </button>
                                        @if($category->children->count() > 0)
                                            <div x-show="open"
                                                 x-transition:enter="transition ease-out duration-200"
                                                 x-transition:enter-start="opacity-0 -translate-y-2"
                                                 x-transition:enter-end="opacity-100 translate-y-0"
                                                 x-transition:leave="transition ease-in duration-150"
                                                 x-transition:leave-start="opacity-100 translate-y-0"
                                                 x-transition:leave-end="opacity-0 -translate-y-2"
                                                 class="{{ app()->getLocale() == 'ar' ? 'pr-4' : 'pl-4' }} space-y-1 mt-1 relative">
                                                <!-- Vertical line connecting parent to children -->
                                                <div class="absolute {{ app()->getLocale() == 'ar' ? 'right-0' : 'left-0' }} top-0 bottom-0 w-0.5 bg-gray-200"></div>

                                                @foreach($category->children as $subcategory)
                                                    <a href="{{ route('products', ['category' => $subcategory->slug]) }}"
                                                       class="block px-4 py-2 text-sm text-gray-600 hover:text-emerald-700 hover:bg-emerald-50 rounded-lg transition-all duration-200 group relative">
                                                        <div class="flex items-center">
                                                            <div class="w-4 h-4 {{ app()->getLocale() == 'ar' ? 'ml-2' : 'mr-2' }} flex items-center justify-center">
                                                                <div class="w-0.5 h-4 bg-gray-300 group-hover:bg-emerald-400 transition-colors duration-200"></div>
                                                            </div>
                                                            <span>{{ $subcategory->translated_name }}</span>
                                                        </div>
                                                        <!-- Horizontal line connecting to vertical line -->
                                                        <div class="absolute {{ app()->getLocale() == 'ar' ? 'right-0' : 'left-0' }} top-1/2 w-4 h-0.5 bg-gray-200 -translate-y-1/2"></div>
                                                    </a>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="space-y-1">
                        <h3 class="px-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">{{__('main.support')}}</h3>
                        <a href="{{route('contactus')}}" class="flex items-center px-4 py-3 text-base font-medium text-gray-900 rounded-xl hover:bg-gray-50 transition-colors duration-200">
                            <svg class="w-5 h-5 {{ app()->getLocale() == 'ar' ? 'ml-3' : 'mr-3' }} text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                            {{__('main.contact_us')}}
                        </a>
                    </div>
                </div>

                <!-- Language Selector -->
                <div class="mt-auto border-t border-gray-100 pt-6">
                    <div class="px-4">
                        <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">{{__('main.language')}}</h3>
                        <x-language-selector :currentLanguage="$currentLanguage" position="bottom" variant="sidebar" />
                    </div>
                </div>
            </nav>
        </div>
    </div>
</div>
