<!-- Navigation Component -->
<div x-data="{
    sidebarOpen: false,
    profileDropdownOpen: false,
    languageOpen: false
}"
x-on:keydown.escape.window="sidebarOpen = false; profileDropdownOpen = false"
class="relative">

    <!-- Top bar -->
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 items-center justify-between">
            <div class="flex items-center">
                 <!-- Mobile menu button -->
                 <div class="flex items-center lg:hidden">
                    <button @click="sidebarOpen = true" type="button" class="-m-2 inline-flex items-center justify-center rounded-md p-2 text-gray-400">
                        <span class="sr-only">Open menu</span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.75" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-menu-icon lucide-menu"><path d="M4 12h16"/><path d="M4 18h16"/><path d="M4 6h16"/></svg>
                    </button>
                </div>
                <!-- Logo -->
                <a href="{{ route('main') }}" class="text-2xl font-bold tracking-tight ml-4 lg:ml-0">
                    <span class="text-blue-600">İndirim<span class="text-gray-900">Go</span></span>
                </a>
            </div>

            <!-- Center Section: Search -->
            <div class="hidden lg:flex flex-1 max-w-lg mx-8">
                @livewire('frontend.search-component', [], key('search-'.time()))
            </div>

            <!-- Right Section: Icons -->
            <div class="flex items-center justify-end space-x-1 sm:space-x-2">
                <!-- Favorites -->
{{--                <div class="hidden sm:flex">--}}
{{--                    <button class="p-2 text-gray-700 hover:text-blue-600 hover:bg-gray-100 rounded-xl transition-all duration-200 h-11 w-11 flex items-center justify-center">--}}
{{--                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-heart-icon lucide-heart"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>--}}
{{--                    </button>--}}
{{--                </div>--}}

                <!-- Notifications Bell (for authenticated users with admin/salesperson roles) -->
                @hasanyrole('admin|salesperson')
                    <livewire:frontend.partials.notification-bell />
                @endhasanyrole

                <!-- Cart Component -->
                @livewire('frontend.cart.cart-component')

                <!-- Profile/Auth Section -->
                @guest
                    @include('livewire.frontend.partials.guest-menu')
                @else
                    @include('livewire.frontend.partials.auth-menu')
                @endguest


            </div>
        </div>
    </div>
     <!-- Search for mobile -->
    <div class="lg:hidden px-4 pb-4">
        @livewire('frontend.search-component', [], key('search-mobile-'.time()))
    </div>


    <!-- Bottom bar (Desktop Navigation) -->
    <nav class="hidden lg:block border-t border-b border-gray-100">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex h-12 items-center justify-start space-x-8">
                <a href="{{ route('products', ['category' => 'all']) }}" class="flex items-center text-md font-medium text-gray-700 hover:text-blue-600 transition-colors duration-200">
                    All
                </a>
                @foreach($categories as $category)
                    <div x-data="{ open: false }" class="relative py-4">
                        <button @mouseover="open = true" @mouseleave="open = false" class="flex items-center text-md font-medium text-gray-700 hover:text-blue-600 transition-colors duration-200">
                            {{ $category->translated_name }}
                            @if($category->children->isNotEmpty())
                            <svg class="ml-1 h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.25 4.25a.75.75 0 01-1.06 0L5.23 8.27a.75.75 0 010-1.06z" clip-rule="evenodd" />
                            </svg>
                            @endif
                        </button>

                        @if($category->children->isNotEmpty())
                        <div x-show="open"
                            x-cloak
                            @mouseover="open = true" @mouseleave="open = false"
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 translate-y-1"
                            x-transition:enter-end="opacity-100 translate-y-0"
                            x-transition:leave="transition ease-in duration-150"
                            x-transition:leave-start="opacity-100 translate-y-0"
                            x-transition:leave-end="opacity-0 translate-y-1"
                            class="absolute z-20 mt-1 w-56 rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none">
                            <div class="py-1">
                                @foreach($category->children as $child)
                                    <a href="{{ route('products', ['category' => $child->slug]) }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-blue-600">{{ $child->translated_name }}</a>
                                @endforeach
                            </div>
                        </div>
                        @endif
                    </div>
                @endforeach
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
             class="fixed inset-0 bg-gray-900/80 h-screen"
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
             class="fixed inset-y-0 {{ app()->getLocale() == 'ar' ? 'right-0' : 'left-0' }} w-full max-w-xs bg-white shadow-lg overflow-y-auto h-screen">

            <!-- Sidebar Header -->
            <div class="flex items-center justify-between py-6 {{app()->getLocale() == 'ar' ? 'pl-8 pr-10' : 'pl-10 pr-8'}} border-b border-gray-100">
                <a href="{{ route('main') }}" class="text-2xl font-semibold font-poppins bg-gradient-to-r from-blue-500 to-purple-600 bg-clip-text text-transparent">
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
                                    :class="open ? 'bg-blue-50 text-blue-700' : 'hover:bg-gray-50'">
                                <div class="flex items-center">
                                    <svg class="w-5 h-5 {{ app()->getLocale() == 'ar' ? 'ml-3' : 'mr-3' }} transition-colors duration-200"
                                         :class="open ? 'text-blue-500' : 'text-gray-500'"
                                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                    </svg>
                                    {{__('main.categories')}}
                                </div>
                                <svg class="w-4 h-4 transition-all duration-200"
                                     :class="open ? 'text-blue-500 rotate-90' : 'text-gray-500'"
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
                                                :class="open ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-50'">
                                            <div class="flex items-center">
                                                <div class="w-4 h-4 {{ app()->getLocale() == 'ar' ? 'ml-2' : 'mr-2' }} flex items-center justify-center">
                                                    <div class="w-0.5 h-4 bg-gray-300 group-hover:bg-blue-400 transition-colors duration-200"></div>
                                                </div>
                                                <span class="font-medium">{{ $category->translated_name }}</span>
                                            </div>
                                            @if($category->children->count() > 0)
                                                <svg class="w-4 h-4 transition-all duration-200 {{ app()->getLocale() == 'ar' ? 'rotate-180' : '' }}"
                                                     :class="open ? 'text-blue-500 rotate-90' : 'text-gray-500'"
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
                                                       class="block px-4 py-2 text-sm text-gray-600 hover:text-blue-700 hover:bg-blue-50 rounded-lg transition-all duration-200 group relative">
                                                        <div class="flex items-center">
                                                            <div class="w-4 h-4 {{ app()->getLocale() == 'ar' ? 'ml-2' : 'mr-2' }} flex items-center justify-center">
                                                                <div class="w-0.5 h-4 bg-gray-300 group-hover:bg-blue-400 transition-colors duration-200"></div>
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
