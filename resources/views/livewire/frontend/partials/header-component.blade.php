<!-- Navigation Component -->
<div x-data="{
    sidebarOpen: false,
    profileDropdownOpen: false,
    languageOpen: false
}"
x-on:keydown.escape.window="sidebarOpen = false; profileDropdownOpen = false"
class="relative">

    {{-- Welcome to Karman Telekom--}}
    <div class="bg-blue-600">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-2 flex justify-between text-md text-white font-semibold font-sans ">
            <div>
                {{__('main.welcome_to_karmantelekom')}}
            </div>

{{--            <div>--}}
{{--                Login--}}
{{--            </div>--}}
        </div>
    </div>

    <!-- Top bar -->
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 items-center justify-between">
            <div class="flex items-center">
                 <!-- Mobile menu button -->
                 <div class="flex flex-col items-center lg:hidden">
                    <button @click="sidebarOpen = true" type="button" class="-m-2 inline-flex flex-col items-center justify-center rounded-md p-1 text-gray-400">
                        <span class="sr-only">Open menu</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="text-[#101720]" width="40" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.75" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-menu-icon lucide-menu"><path d="M2 6h20"/><path d="M2 12h12"/><path d="M2 18h20"/></svg>
                        <span class="text-xs text-[#101720] font-medium -mt-1">Menü</span>
                    </button>
                </div>
                <!-- Logo -->
                <a href="{{ route('main') }}" class="text-2xl font-bold tracking-tight ml-2 lg:ml-0">
                    <img src="{{ asset('assets/images/karmantelekom.png') }}" class="w-32" alt="Logo">
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
{{--                @auth--}}
{{--                    <livewire:frontend.partials.notification-bell />--}}
{{--                @endauth--}}

                <!-- Cart Component -->
                @livewire('frontend.cart.cart-component')

                <!-- Profile/Auth Section -->
{{--                @guest--}}
{{--                    @include('livewire.frontend.partials.guest-menu')--}}
{{--                @else--}}
{{--                    @include('livewire.frontend.partials.auth-menu')--}}
{{--                @endguest--}}

                @auth
                    @include('livewire.frontend.partials.auth-menu')
                @endauth


            </div>
        </div>
    </div>
     <!-- Search for mobile -->
    <div class="lg:hidden px-4 pb-4">
        @livewire('frontend.search-component', [], key('search-mobile-'.time()))
    </div>


    <!-- Bottom bar (Desktop Navigation) -->
    <nav
        x-data="categoryNav()"
        x-init="init()"
        class="border-b pb-1 border-gray-100 bg-white"
    >
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="relative">
                <!-- Left button (desktop only) -->
                <button
                    type="button"
                    class="hidden lg:flex absolute left-0 top-1/2 -translate-y-1/2 z-20 h-9 w-9 items-center justify-center rounded-full border border-gray-200 bg-white shadow-sm hover:bg-gray-50 disabled:opacity-30 disabled:cursor-not-allowed"
                    @click="scrollBy(-320)"
                    :disabled="atStart"
                    aria-label="Scroll left"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-700" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M12.78 15.53a.75.75 0 0 1-1.06 0l-5-5a.75.75 0 0 1 0-1.06l5-5a.75.75 0 1 1 1.06 1.06L8.31 10l4.47 4.47a.75.75 0 0 1 0 1.06z" clip-rule="evenodd"/>
                    </svg>
                </button>

                <!-- Right button (desktop only) -->
                <button
                    type="button"
                    class="hidden lg:flex absolute right-[-2rem] top-1/2 -translate-y-1/2 z-20 h-9 w-9 items-center justify-center rounded-full border border-gray-200 bg-white shadow-sm hover:bg-gray-50 disabled:opacity-30 disabled:cursor-not-allowed"
                    @click="scrollBy(320)"
                    :disabled="atEnd"
                    aria-label="Scroll right"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-700" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M7.22 4.47a.75.75 0 0 1 1.06 0l5 5a.75.75 0 0 1 0 1.06l-5 5a.75.75 0 1 1-1.06-1.06L11.69 10 7.22 5.53a.75.75 0 0 1 0-1.06z" clip-rule="evenodd"/>
                    </svg>
                </button>

                <!-- Scroll container -->
                <div
                    x-ref="scroller"
                    @scroll="update()"
                    class="overflow-x-auto scrollbar-hide"
                >
                    <!-- Add side padding on desktop so arrows don't overlap items -->
                    <div class="flex h-12 items-center gap-3 whitespace-nowrap lg:px-12">
                        <a
                            href="{{ route('products', ['category' => 'all']) }}"
                            class="inline-flex items-center rounded-xl border border-gray-200 px-4 py-2 text-md
                            font-medium text-gray-700 hover:text-blue-600 hover:border-blue-200 hover:bg-blue-50 transition
                            {{ request()->routeIs('products') && request('category') === 'all'
                                ? 'border-blue-300 bg-blue-500 text-white'
                                : 'border-gray-200 text-gray-700 hover:text-blue-600 hover:border-blue-200 hover:bg-blue-50'
                            }}
                            "
                        >
                            {{__('main.all')}}
                        </a>

                        @foreach($categories as $category)
                            @php
                                $isParentActive =
                                    $this->currentCategory &&
                                    (
                                        $this->currentCategory->id === $category->id ||
                                        $this->currentCategory->parent_id === $category->id
                                    );
                            @endphp
                            <div
                                x-data="dropdownFixed()"
                                class="relative"
                                @keydown.escape.window="open = false"
                            >
                                <button
                                    x-ref="btn"
                                    type="button"
                                    @click="toggle()"
                                    class="inline-flex items-center gap-1 rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold  transition
                                    {{ $isParentActive
                                        ? 'border-blue-300 bg-blue-500 text-white'
                                        : 'border-gray-200 text-gray-700 hover:text-blue-600 hover:border-blue-200 hover:bg-blue-50'
                                    }}
                                    "
                                >
                                    <span class="truncate max-w-[160px] font-medium text-md">
                                        {{ $category->translated_name }}
                                    </span>

                                    @if($category->children->isNotEmpty())
                                        <svg class="h-5 w-5  shrink-0
                                        {{ $isParentActive
                                            ? 'border-blue-300 bg-blue-500 text-gray-100'
                                            : 'border-gray-200 text-gray-400 hover:text-blue-600 hover:border-blue-200 hover:bg-blue-50'
                                        }}
                                        " xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.25 4.25a.75.75 0 01-1.06 0L5.23 8.27a.75.75 0 010-1.06z" clip-rule="evenodd" />
                                        </svg>
                                    @endif
                                </button>

                                @if($category->children->isNotEmpty())
                                    <div
                                        x-show="open"
                                        x-cloak
                                        x-transition.opacity
                                        @click.away="open = false"
                                        class="fixed z-50 w-60 rounded-xl bg-white shadow-lg ring-1 ring-black/5 overflow-hidden"
                                        :style="`top:${top}px; left:${left}px;`"
                                    >
                                        <div class="py-2">
                                            @foreach($category->children as $child)
                                                <a
                                                    href="{{ route('products', ['category' => $child->slug]) }}"
                                                    class="block px-4 py-2 text-sm hover:bg-gray-50 hover:text-blue-600 font-medium text-gray-700 text-md"
                                                >
                                                    {{ $child->translated_name }}
                                                </a>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Optional fade edges (desktop only) -->
                <div class="pointer-events-none hidden lg:block absolute inset-y-0 left-0 w-10 bg-gradient-to-r from-white to-transparent"></div>
                <div class="pointer-events-none hidden lg:block absolute inset-y-0 right-[-2rem] w-10 bg-gradient-to-l from-white to-transparent"></div>
            </div>
        </div>

        <script>
            function categoryNav() {
                return {
                    atStart: true,
                    atEnd: false,

                    init() {
                        this.update();
                        // keep buttons correct on resize
                        window.addEventListener('resize', () => this.update());
                    },

                    scrollBy(px) {
                        this.$refs.scroller.scrollBy({ left: px, behavior: 'smooth' });
                        // update after scroll animation starts
                        setTimeout(() => this.update(), 80);
                    },

                    update() {
                        const el = this.$refs.scroller;
                        const max = el.scrollWidth - el.clientWidth;
                        // small tolerance for float rounding
                        this.atStart = el.scrollLeft <= 2;
                        this.atEnd = el.scrollLeft >= (max - 2);
                    }
                }
            }

            function dropdownFixed() {
                return {
                    open: false,
                    top: 0,
                    left: 0,

                    toggle() {
                        if (this.open) {
                            this.open = false;
                            return;
                        }

                        // calculate dropdown position
                        const r = this.$refs.btn.getBoundingClientRect();
                        this.top = r.bottom + 8;

                        // keep dropdown inside viewport (no overflow on right)
                        const dropdownWidth = 240; // w-60 => 15rem => 240px
                        const padding = 12;
                        const maxLeft = window.innerWidth - dropdownWidth - padding;
                        this.left = Math.min(r.left, maxLeft);

                        this.open = true;
                    }
                }
            }
        </script>
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
                <a href="{{ route('main') }}" class="text-2xl font-semibold font-poppins bg-gradient-to-r from-blue-500 to-purple-600 bg-clip-text text-transparent">
                    <img src="{{ asset('assets/images/karmantelekom_logo.png') }}" class="w-24" alt="">
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
                                <div x-show="open"
                                     x-transition:enter="transition ease-out duration-200"
                                     x-transition:enter-start="opacity-0 -translate-y-2"
                                     x-transition:enter-end="opacity-100 translate-y-0"
                                     x-transition:leave="transition ease-in duration-150"
                                     x-transition:leave-start="opacity-100 translate-y-0"
                                     x-transition:leave-end="opacity-0 -translate-y-2"
                                     class="{{ app()->getLocale() == 'ar' ? 'pr-4' : 'pl-4' }} space-y-1 mt-1 relative">

                                    <a href="{{ route('products', ['category' => 'all']) }}"
                                       class="block px-4 py-2 text-sm text-gray-600 hover:text-blue-700 hover:bg-blue-50 rounded-lg transition-all duration-200 group relative">
                                        <div class="flex items-center">
                                            <div class="w-4 h-4 {{ app()->getLocale() == 'ar' ? 'ml-2' : 'mr-2' }} flex items-center justify-center">
                                                <div class="w-0.5 h-4 bg-gray-300 group-hover:bg-blue-400 transition-colors duration-200"></div>
                                            </div>
                                            <span>All</span>
                                        </div>
                                    </a>
                                </div>
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
