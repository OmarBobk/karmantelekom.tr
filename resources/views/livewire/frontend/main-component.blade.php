<div
    x-data="{
        direction: '{{ $direction }}',
        init() {
            this.$watch('direction', value => {
                document.documentElement.dir = value;
                document.documentElement.lang = '{{ App::getLocale() }}';
            });

            // Listen for direction updates
            Livewire.on('updateDirection', (data) => {
                this.direction = data.direction;
            });
        }
    }"
    x-init="init()"
    :dir="direction"
>
    <!-- Loading Overlay -->
    <div
        x-data="{ show: false }"
        x-show="show"
        x-on:currency-switching.window="show = true"
        x-on:currency-switched.window="setTimeout(() => show = false, 300)"
        class="fixed inset-0 z-50 bg-gray-900/50 backdrop-blur-sm transition-opacity"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        style="display: none;"
        role="alert"
        aria-live="polite"
        aria-busy="true"
    >
        <div class="fixed left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2">
            <div class="bg-white/90 backdrop-blur-sm shadow-xl rounded-lg px-6 py-4 flex items-center gap-3 border border-gray-200/50">
                <div class="relative">
                    <svg class="animate-spin size-6 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span class="sr-only">Loading</span>
                </div>
                <span class="text-sm font-medium text-gray-900">Updating prices...</span>
            </div>
        </div>
    </div>

    <!-- Marquee Component -->
{{--    <x-marquee />--}}


    <!-- Start Slider Component -->
    <div
        class="w-full bg-white" x-data="{
        activeCategory: $wire.activeCategory,
        atStart: true,
        atEnd: false,
        direction: '{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}',
        init() {
            this.$nextTick(() => {
                this.updateScrollButtons(this.activeCategory);
            });
        },
        updateScrollButtons(index) {
            const slider = this.$refs[`slider-${index}`];
            if (slider) {
                if (this.direction === 'rtl') {
                    this.atStart = Math.abs(slider.scrollLeft) >= slider.scrollWidth - slider.clientWidth - 1;
                    this.atEnd = slider.scrollLeft >= -1;
                } else {
                    this.atStart = slider.scrollLeft <= 0;
                    this.atEnd = slider.scrollLeft + slider.clientWidth >= slider.scrollWidth;
                }
            }
        },
        scrollLeft(index) {
            const slider = this.$refs[`slider-${index}`];
            if (slider) {
                const productCard = slider.querySelector('.flex-none');
                const cardWidth = productCard.offsetWidth;
                const gap = 16;

                if (this.direction === 'rtl') {
                    slider.scrollBy({
                        left: cardWidth + gap,
                        behavior: 'smooth'
                    });
                } else {
                    slider.scrollBy({
                        left: -(cardWidth + gap),
                        behavior: 'smooth'
                    });
                }

                setTimeout(() => {
                    this.updateScrollButtons(index);
                }, 300);
            }
        },
        scrollRight(index) {
            const slider = this.$refs[`slider-${index}`];
            if (slider) {
                const productCard = slider.querySelector('.flex-none');
                const cardWidth = productCard.offsetWidth;
                const gap = 16;

                if (this.direction === 'rtl') {
                    slider.scrollBy({
                        left: -(cardWidth + gap),
                        behavior: 'smooth'
                    });
                } else {
                    slider.scrollBy({
                        left: cardWidth + gap,
                        behavior: 'smooth'
                    });
                }

                setTimeout(() => {
                    this.updateScrollButtons(index);
                }, 300);
            }
        }
    }" role="region" aria-label="Product Categories Slider">
        <!-- Category Navigation -->
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="relative" x-data="navigationScroll">
                <!-- Enhanced Shadow indicators for scroll -->
                <div class="absolute {{ app()->getLocale() === 'ar' ? 'right-0' : 'left-0' }} bottom-[3px] w-12 z-10 hidden md:flex items-center justify-start"
                    :class="{ 'pointer-events-none': atStart }"
                >
                    <div class="absolute inset-0 bg-gradient-to-r from-white via-white to-transparent
                                opacity-0 transition-opacity duration-200"
                        :class="{ 'opacity-90': !atStart }"
                    ></div>
                </div>

                <div class="absolute {{ app()->getLocale() === 'ar' ? 'left-0' : 'right-0' }} bottom-[3px] w-12 z-10 hidden md:flex items-center justify-end"
                    :class="{ 'pointer-events-none': atEnd }"
                >
                    <div class="absolute inset-0 bg-gradient-to-l from-white via-white to-transparent
                                opacity-0 transition-opacity duration-200"
                        :class="{ 'opacity-90': !atEnd }"
                    ></div>
                </div>

                <!-- Scrollable Navigation -->
                <nav class="relative overflow-x-auto scrollbar-hide -mb-px"
                    role="tablist"
                    aria-label="Product categories"
                    x-ref="nav"
                    @scroll.debounce.50ms="updateScroll"
                    x-cloak
                >
                    <div class="flex whitespace-nowrap border-b border-gray-200" x-cloak>
                        @if(empty($sections))
                            <p class="text-gray-500 px-4 py-2">No sections found</p>
                        @else
                            @forelse($sections as $index => $section)
                                <button
                                    @click="$wire.activeCategory = {{ $index }}"
                                    class="flex items-center gap-2 {{ $index === 0 ? 'px-0 mr-3': 'px-3' }} py-3 text-sm font-medium border-b-2 whitespace-nowrap transition-all duration-200"
                                    :class="{
                                'border-blue-500 text-blue-600': $wire.activeCategory === {{ $index }},
                                'border-transparent text-gray-500 hover:text-gray-900 hover:border-gray-300': $wire.activeCategory !== {{ $index }}
                            }"
                                    role="tab"
                                    :aria-selected="$wire.activeCategory === {{ $index }}"
                                    aria-controls="panel-{{ $index }}"
                                    id="tab-{{ $index }}"
                                    x-cloak
                                >
                                    <span>{{ $section->translated_name }}</span>
                                    <span

                                        class="flex items-center ml-2 px-2 py-1 justify-center text-xs font-semibold rounded-full"
                                        :class="{
                                    'bg-blue-100 text-blue-700': $wire.activeCategory === {{ $index }},
                                    'bg-gray-100 text-gray-600': $wire.activeCategory !== {{ $index }}
                                }"
                                    >
                                {{ count($section->products) }}
                            </span>
                                </button>
                            @empty
                                <p class="text-gray-500 px-4 py-2">No sections found</p>
                            @endforelse
                        @endif
                    </div>
                </nav>
            </div>
        </div>

        <!-- Product Panels -->
        <div class="relative mt-4">
            <div class="mx-auto max-w-7xl px-0 lg:px-8">
                <!-- Slider Container with Navigation Buttons -->
                <div class="group relative">
                    <!-- Left Navigation Button -->
                    <button
                        @click="scrollLeft($wire.activeCategory)"
                        class="hidden lg:flex items-center justify-center size-10 rounded-full bg-white hover:bg-gray-50 text-gray-600 hover:text-gray-900 shadow-lg border border-gray-200 absolute {{ app()->getLocale() === 'ar' ? '-right-2 md:-right-6' : '-left-2 md:-left-6' }} top-1/2 -translate-y-1/2 opacity-0 group-hover:opacity-100 z-10 transition-all duration-200
                            focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                        :class="{ 'pointer-events-none opacity-50': {{ app()->getLocale() === 'ar' ? 'atEnd' : 'atStart' }} }"
                        aria-label="Scroll products left"
                    >
                        <svg class="w-6 h-6 {{ app()->getLocale() === 'ar' ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </button>

                    <!-- Right Navigation Button -->
                    <button
                        @click="scrollRight($wire.activeCategory)"
                        class="hidden lg:flex items-center justify-center size-10 rounded-full bg-white hover:bg-gray-50 text-gray-600 hover:text-gray-900 shadow-lg border border-gray-200 absolute {{ app()->getLocale() === 'ar' ? '-left-2 md:-left-6' : '-right-2 md:-right-6' }} top-1/2 -translate-y-1/2 opacity-0 group-hover:opacity-100 z-10 transition-all duration-200
                            focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                        :class="{ 'pointer-events-none opacity-50': {{ app()->getLocale() === 'ar' ? 'atStart' : 'atEnd' }} }"
                        aria-label="Scroll products right"
                    >
                        <svg class="w-6 h-6 {{ app()->getLocale() === 'ar' ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>

                    <!-- Slider Content -->
                    <div class="relative h-[580px]">
                        @if(!empty($this->sections))
                        @forelse($this->sections as $index => $section)
                            <div
                                id="panel-{{ $index }}"
                                role="tabpanel"
                                aria-labelledby="tab-{{ $index }}"
                                x-show="$wire.activeCategory === {{ $index }}"
                                x-transition:enter="transition ease-out duration-300"
                                x-transition:enter-start="opacity-0 transform {{ app()->getLocale() === 'ar' ? '-translate-x-full' : 'translate-x-full' }}"
                                x-transition:enter-end="opacity-100 transform translate-x-0"
                                x-transition:leave="transition ease-in duration-300"
                                x-transition:leave-start="opacity-100 transform translate-x-0"
                                x-transition:leave-end="opacity-0 transform {{ app()->getLocale() === 'ar' ? 'translate-x-full' : '-translate-x-full' }}"
                                class="absolute inset-0 w-full"
                                x-cloak
                            >
                                <div class="relative" x-cloak>
                                    <div
                                        x-ref="slider-{{ $index }}"
                                        class="flex lg:rounded md:rounded-none sm:rounded-2xl overflow-x-auto scrollbar-hide scroll-smooth snap-x snap-mandatory h-[580px]"
                                        @scroll.debounce.50ms="updateScrollButtons($wire.activeCategory)"
                                        role="list"
                                        aria-label="Products in {{ $section->name }}"
                                    >
                                        @foreach($section->products as $product)
                                            <!-- Enhanced Product Card -->
                                            <div class="flex-none w-72 snap-center p-4">
                                                <div class="bg-white rounded-2xl shadow-xl hover:shadow-2xl transform hover:scale-105 transition-all duration-300 border border-gray-100 relative group">
                                                    <!-- Wishlist Button (top-right) -->
{{--                                                    <button--}}
{{--                                                        wire:click="toggleWishlist({{ $product->id }})"--}}
{{--                                                        class="absolute top-4 right-4 p-2 rounded-full bg-white/80 hover:bg-blue-50 shadow transition z-20"--}}
{{--                                                        aria-label="Add to wishlist"--}}
{{--                                                    >--}}
{{--                                                        <svg class="w-5 h-5 text-rose-400" fill="currentColor" viewBox="0 0 20 20">--}}
{{--                                                            <path d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z"/>--}}
{{--                                                        </svg>--}}
{{--                                                    </button>--}}

                                                    <!-- Product Image -->
                                                    <figure class="relative flex items-center justify-center aspect-square w-full bg-gradient-to-br from-blue-50 to-purple-100 rounded-t-2xl border-b-2 border-blue-100 overflow-hidden">
                                                        <button wire:click="$dispatch('openProductModal', { productId: {{ $product->id }} })" class="w-full h-full flex items-center justify-center">
                                                            <img src="{{ $product->images->where('is_primary', true)->first()?->image_url
                                                                    ? Storage::url($product->images->where('is_primary', true)->first()->image_url)
                                                                    : 'https://placehold.co/100' }}"
                                                                alt="{{ $product->name }}"
                                                                class="h-full w-auto object-contain transition-transform duration-300 group-hover:scale-110"
                                                                loading="lazy"
                                                            >
                                                        </button>
                                                    </figure>

                                                    <!-- Product Info -->
                                                    <div class="p-5 flex flex-col gap-2">
                                                        <!-- Star Rating (above name) -->
{{--                                                        <div class="flex items-center gap-1 mb-1">--}}
{{--                                                            @for ($i = 1; $i <= 5; $i++)--}}
{{--                                                                <svg class="w-4 h-4 {{ $i <= ($product->rating ?? 4) ? 'text-yellow-400' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 20 20">--}}
{{--                                                                    <polygon points="9.9,1.1 12.3,6.6 18.2,7.3 13.6,11.3 15,17.1 9.9,14.1 4.8,17.1 6.2,11.3 1.6,7.3 7.5,6.6"/>--}}
{{--                                                                </svg>--}}
{{--                                                            @endfor--}}
{{--                                                            <span class="text-xs text-gray-500 ml-1">({{ $product->reviews_count ?? 12 }})</span>--}}
{{--                                                        </div>--}}

                                                        <p class="text-xs font-semibold text-blue-600 uppercase tracking-wide">{{ $section->translated_name }}</p>
                                                        <button wire:click="$dispatch('openProductModal', { productId: {{ $product->id }} })" class="text-left">
                                                            <h3 class="text-lg font-extrabold text-gray-900 group-hover:text-blue-700 transition-colors duration-200 line-clamp-1">
                                                                {{ $product->translated_name }}
                                                            </h3>
                                                            <p class="text-sm text-gray-500 line-clamp-2">{{ $product->translated_description }}</p>
                                                        </button>

                                                        <!-- Price & Discount -->
                                                        <div class="flex items-center gap-2 mt-2">
                                                            @if(App\Facades\Settings::get('product_prices') == 'enabled' && $product->prices->isNotEmpty())
                                                                <span class="text-2xl font-bold text-blue-600">
                                                                    {{ $product->prices->first()->getFormattedPrice() }}
                                                                </span>
                                                                @if($product->prices->first()->old_price && $product->prices->first()->old_price > $product->prices->first()->price)
                                                                    <span class="text-base text-gray-400 line-through">
                                                                        {{ number_format($product->prices->first()->old_price, 2) }} ₺
                                                                    </span>
                                                                    <span class="ml-2 px-2 py-0.5 rounded-full bg-rose-100 text-rose-600 text-xs font-bold">
                                                                        -{{ round(100 - ($product->prices->first()->price / $product->prices->first()->old_price * 100)) }}%
                                                                    </span>
                                                                @endif
                                                            @else
                                                                <span class="text-base text-gray-400">Price not available</span>
                                                            @endif
                                                        </div>

                                                        <!-- Add to Cart Button -->
                                                        <button
                                                            @click="$store.cart.addItem({{ json_encode($product) }}, 1);"
                                                            class="mt-4 w-full flex items-center justify-center gap-2 px-4 py-3 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl shadow-md hover:shadow-lg transform hover:-translate-y-px transition-all duration-300 focus:outline-none "
                                                            aria-label="{{ __('Add to cart') }}"
                                                        >
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                                <path d="M3 3h2l.4 2M7 13h10l4-8H5.4" stroke-linecap="round" stroke-linejoin="round"/>
                                                                <circle cx="7" cy="21" r="1" />
                                                                <circle cx="17" cy="21" r="1" />
                                                            </svg>
                                                            {{ __('Add to Cart') }}
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-12">
                                <p class="text-gray-500">No products found in this section</p>
                            </div>
                        @endforelse
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <style>
            .scrollbar-hide {
                -ms-overflow-style: none;  /* IE and Edge */
                scrollbar-width: none;     /* Firefox */
            }

            .scrollbar-hide::-webkit-scrollbar {
                display: none;            /* Chrome, Safari and Opera */
            }

            /* Override DaisyUI tab focus styles */
            .tab:focus-visible {
                outline: unset;
                outline-offset: 2px;  /* Positive value for outward outline */
                outline-color: #3b82f6; /* Match your blue accent color */
            }
            .tab:focus {
                outline: unset;
            }
        </style>
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('slider', () => ({
                    atStart: true,
                    atEnd: false,

                    init() {
                        this.$nextTick(() => {
                            this.updateScrollButtons(this.$wire.activeCategory);
                        });

                        // Add listener for sections-updated event
                        Livewire.on('sections-updated', () => {
                            // Dispatch event to show loading state
                            window.dispatchEvent(new CustomEvent('currency-switching'));

                            // Wait for DOM to be updated
                            this.$nextTick(() => {
                                // Update scroll buttons after sections are updated
                                this.updateScrollButtons(this.$wire.activeCategory);

                                // Dispatch event to hide loading state
                                window.dispatchEvent(new CustomEvent('currency-switched'));
                            });
                        });

                        // Add direction change listener
                        Livewire.on('updateDirection', (data) => {
                            this.$nextTick(() => {
                                // Reinitialize slider for RTL support
                                this.initializeSlider();
                            });
                        });
                    },

                    initializeSlider() {
                        this.$nextTick(() => {
                            this.updateScrollButtons(this.$wire.activeCategory);
                            const activeSlider = this.$refs[`slider-${this.$wire.activeCategory}`];
                            if (activeSlider) {
                                activeSlider.scrollLeft = 0; // Reset scroll position
                                this.updateScrollButtons(this.$wire.activeCategory);
                            }
                        });
                    },

                    scrollLeft() {
                        const activeSlider = this.$refs[`slider-${this.$wire.activeCategory}`];
                        if (activeSlider) {
                            const productWidth = activeSlider.querySelector('.flex-none').offsetWidth;
                            const gap = 24; // gap-6 = 24px
                            const scrollAmount = (productWidth + gap) * 2;
                            activeSlider.scrollBy({ left: -scrollAmount, behavior: 'smooth' });

                            // Update buttons after scroll animation
                            setTimeout(() => {
                                this.updateScrollButtons(this.$wire.activeCategory);
                            }, 300);
                        }
                    },

                    scrollRight() {
                        const activeSlider = this.$refs[`slider-${this.$wire.activeCategory}`];
                        if (activeSlider) {
                            const productWidth = activeSlider.querySelector('.flex-none').offsetWidth;
                            const gap = 24; // gap-6 = 24px
                            const scrollAmount = (productWidth + gap) * 2;
                            activeSlider.scrollBy({ left: scrollAmount, behavior: 'smooth' });

                            // Update buttons after scroll animation
                            setTimeout(() => {
                                this.updateScrollButtons(this.$wire.activeCategory);
                            }, 300);
                        }
                    },

                    updateScrollButtons(categoryIndex) {
                        const activeSlider = this.$refs[`slider-${categoryIndex}`];
                        if (activeSlider) {
                            // Calculate scroll positions with a small buffer for rounding errors
                            const buffer = 1;
                            this.atStart = activeSlider.scrollLeft <= buffer;
                            this.atEnd = Math.ceil(activeSlider.scrollLeft + activeSlider.offsetWidth + buffer) >= activeSlider.scrollWidth;

                            // Force Alpine to react to the changes
                            this.$nextTick(() => {
                                this.atStart = this.atStart;
                                this.atEnd = this.atEnd;
                            });
                        }
                    }
                }));
            });

            document.addEventListener('alpine:init', () => {
                Alpine.data('navigationScroll', () => ({
                    atStart: true,
                    atEnd: false,

                    init() {
                        this.$nextTick(() => {
                            this.updateScroll();
                        });
                        window.addEventListener('resize', () => this.updateScroll());

                        // Add direction change listener
                        Livewire.on('updateDirection', (data) => {
                            this.$nextTick(() => {
                                this.updateScroll();
                            });
                        });
                    },

                    updateScroll() {
                        const nav = this.$refs.nav;
                        if (nav) {
                            this.atStart = nav.scrollLeft <= 0;
                            this.atEnd = Math.abs(nav.scrollLeft + nav.offsetWidth - nav.scrollWidth) < 1;
                        }
                    },

                    scrollTabsLeft() {
                        const nav = this.$refs.nav;
                        if (nav) {
                            nav.scrollBy({
                                left: -200,
                                behavior: 'smooth'
                            });
                        }
                    },

                    scrollTabsRight() {
                        const nav = this.$refs.nav;
                        if (nav) {
                            nav.scrollBy({
                                left: 200,
                                behavior: 'smooth'
                            });
                        }
                    }
                }));
            });
        </script>

    </div>
    <!-- End Slider Component -->

    <!-- Start Products Section Component -->
    <div class="w-full bg-gray-50 pb-8" x-cloak>
        @if(!empty($this->contentSections))
        @foreach($this->contentSections as $section)
            <div class="w-full bg-white py-8 {{ !$loop->last ? 'border-b border-gray-100' : '' }}">
                <!-- Section Header -->
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center gap-3">
                            <h2 class="text-2xl font-bold text-gray-900">{{ $section->translated_name }}</h2>
                            <span class="px-3 py-1 text-sm font-medium text-blue-700 bg-blue-50 rounded-full">
                                {{ count($section->products) }} {{ __('main.products') }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Products Grid with Swiper -->
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="relative" x-data="productSwiper">
                        @if($section->scrollable)
                            <!-- Navigation Buttons -->
                            <button
                                @click="swiper.slidePrev()"
                                class="absolute left-0 top-1/2 -translate-y-1/2 z-10 size-10 flex items-center justify-center rounded-full bg-white shadow-lg border border-gray-200 text-gray-600 hover:text-gray-900 transition-all duration-200 -translate-x-1/2 opacity-0 group-hover:opacity-100"
                                :class="{ 'opacity-0 pointer-events-none': atStart, 'opacity-100': !atStart }"
                            >
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                                </svg>
                            </button>

                            <button
                                @click="swiper.slideNext()"
                                class="absolute right-0 top-1/2 -translate-y-1/2 z-10 size-10 flex items-center justify-center rounded-full bg-white shadow-lg border border-gray-200 text-gray-600 hover:text-gray-900 transition-all duration-200 translate-x-1/2 opacity-0 group-hover:opacity-100"
                                :class="{ 'opacity-0 pointer-events-none': atEnd, 'opacity-100': !atEnd }"
                            >
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </button>
                        @endif
                        <!-- Swiper Container -->
                        <div
                            x-cloak
                            class="{{ $section->scrollable ? 'swiper-container overflow-hidden pb-5 px-4' : 'mx-auto max-w-7xl' }}"
                        >
                            <div class="{{ $section->scrollable ? 'swiper-wrapper' : 'grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-y-4' }}">
                                @foreach($section->products as $product)
                                    <div class="{{ $section->scrollable ? 'swiper-slide' : '' }}">
                                        <!-- Product Card -->
                                        <div class="{{ $section->scrollable ? 'group/card' : 'group/card ml-4 mr-3' }}" x-data="productSlider">
                                            <div class="bg-white rounded-lg shadow-lg hover:shadow-xl transition-shadow duration-300 relative flex flex-col h-full">
                                                @if($product->tag() != null)
                                                    <span
                                                        class="absolute top-3 z-10 px-3 py-1 text-xs font-bold rounded-full shadow-md animate-pulse"
                                                        style="{{ app()->getLocale() === 'AR' ? 'right:0;' : 'left:0;' }} background-color: {{ $product->tag()->background_color }}; color: {{ $product->tag()->text_color }}; border-color: {{ $product->tag()->border_color }};">
                                                                {{ $product->tag()->icon . ' ' . $product->tag()->translated_name}}
                                                            </span>
                                                @endif

                                                <!-- Image Slider -->
                                                <div class="flex flex-col relative aspect-w-1 aspect-h-1 w-full overflow-hidden h-48 bg-gray-50 rounded-md">
                                                    <button wire:click="$dispatch('openProductModal', { productId: {{ $product->id }} })" class="">
                                                        <img src="{{ $product->images->where('is_primary', true)->first()?->image_url
                                                                    ? Storage::url($product->images->where('is_primary', true)->first()->image_url)
                                                                    : 'https://placehold.co/100' }}"
                                                             alt="{{ $product->name }} - Image {{ $index + 1 }}"
                                                             class="absolute h-full w-full object-contain lg:object-contain md:object-contain object-center transition-opacity duration-300 opacity-100"
                                                             loading="lazy">
                                                    </button>
                                                </div>


                                                <!-- Product Info -->
                                                <div class="p-4 flex flex-col flex-grow">
                                                    <div class="flex-grow">
                                                        <div class="mb-3">
                                                            <button wire:click="$dispatch('openProductModal', { productId: {{ $product->id }} })" class="text-left">
                                                                <div class="h-[4.5rem] {{ app()->getLocale() == 'ar' ? 'text-right' : '' }}">
                                                                    <h3 class="text-sm font-medium text-gray-900 hover:text-blue-600 transition-colors duration-200 line-clamp-1">{{ $product->translated_name }}</h3>
                                                                    <p class="text-sm text-gray-500 line-clamp-2">{{ $product->translated_description }}</p>
                                                                </div>
                                                            </button>
                                                        </div>
                                                        <!-- Price & Discount -->
                                                        <div class="flex items-center gap-2 mt-2">
                                                            @if(App\Facades\Settings::get('product_prices') == 'enabled' && $product->prices->isNotEmpty())
                                                                <span class="text-2xl font-bold text-blue-600">
                                                                    {{ $product->prices->first()->getFormattedPrice() }}
                                                                </span>
                                                                @if($product->prices->first()->old_price && $product->prices->first()->old_price > $product->prices->first()->price)
                                                                    <span class="text-base text-gray-400 line-through">
                                                                        {{ number_format($product->prices->first()->old_price, 2) }} ₺
                                                                    </span>
                                                                    <span class="ml-2 px-2 py-0.5 rounded-full bg-rose-100 text-rose-600 text-xs font-bold">
                                                                        -{{ round(100 - ($product->prices->first()->price / $product->prices->first()->old_price * 100)) }}%
                                                                    </span>
                                                                @endif
                                                            @else
                                                                <span class="text-base text-gray-400">Price not available</span>
                                                            @endif
                                                        </div>

                                                    </div>

                                                    <!-- Add to Cart Button -->
                                                    <div class="mt-4">
                                                        <button
                                                            @click="$store.cart.addItem({{ json_encode($product) }}, 1);"
                                                            class="w-full flex items-center justify-center gap-2 py-1 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-xl shadow-md hover:shadow-lg transform hover:-translate-y-px transition-all duration-300 focus:outline-none"
                                                            aria-label="{{ __('Add to cart') }}"
                                                        >
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                                <path d="M3 3h2l.4 2M7 13h10l4-8H5.4" stroke-linecap="round" stroke-linejoin="round"/>
                                                                <circle cx="7" cy="21" r="1" />
                                                                <circle cx="17" cy="21" r="1" />
                                                            </svg>
                                                            {{ __('Add to Cart') }}
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
        @endif
    </div>
    <!-- End Products Section Component -->

    <style>
        /* Existing styles */
        .scrollbar-hide {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        .scrollbar-hide::-webkit-scrollbar {
            display: none;
        }

        /* Added styles */
        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
        }
    </style>

    @assets
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
        <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    @endassets

    @script

        <script>
            Alpine.data('productSlider', () => ({
                currentSlide: 1,
                showProductModal: false,
                touchStartX: 0,
                touchEndX: 0,

                nextSlide() {
                    this.currentSlide = this.currentSlide === 3 ? 1 : this.currentSlide + 1;
                },

                prevSlide() {
                    this.currentSlide = this.currentSlide === 1 ? 3 : this.currentSlide - 1;
                },

                goToSlide(slide) {
                    this.currentSlide = slide;
                }
            }));

            Alpine.data('productSwiper', () => ({
                swiper: null,
                atStart: true,
                atEnd: false,

                init() {
                    this.$nextTick(() => {
                        this.initSwiper();
                    });

                    // Listen for Livewire updates
                    Livewire.on('content-sections-updated', () => {
                        // Dispatch event to show loading state
                        window.dispatchEvent(new CustomEvent('currency-switching'));

                        // Wait for DOM to be updated
                        this.$nextTick(() => {
                            this.reinitializeSwiper();

                            // Dispatch event to hide loading state
                            window.dispatchEvent(new CustomEvent('currency-switched'));
                        });
                    });
                },

                reinitializeSwiper() {
                    if (this.swiper) {
                        this.swiper.destroy(true, true);
                        this.swiper = null;
                    }

                    requestAnimationFrame(() => {
                        this.initSwiper();
                    });
                },

                initSwiper() {
                    const container = this.$el.querySelector('.swiper-container');
                    if (!container) return;

                    this.swiper = new Swiper(container, {
                        slidesPerView: 1,
                        spaceBetween: 24,
                        grabCursor: true,
                        breakpoints: {
                            320: {
                                slidesPerView: 2
                            },
                            480: {
                                slidesPerView: 3
                            },
                            640: {
                                slidesPerView: 2,
                            },
                            768: {
                                slidesPerView: 3,
                            },
                            1024: {
                                slidesPerView: 4,
                            },
                            1280: {
                                slidesPerView: 6,
                            }
                        },
                        on: {
                            slideChange: () => {
                                this.updateNavigation();
                            },
                            reachBeginning: () => {
                                this.atStart = true;
                            },
                            reachEnd: () => {
                                this.atEnd = true;
                            },
                            init: () => {
                                this.updateNavigation();
                            }
                        }
                    });
                },

                updateNavigation() {
                    if (this.swiper) {
                        this.atStart = this.swiper.isBeginning;
                        this.atEnd = this.swiper.isEnd;
                    }
                }
            }));

            // Remove the global event listener since we're handling it in the Alpine component
            Livewire.on('content-sections-updated', () => {
                // This is now handled within each productSwiper instance
            });
        </script>
    @endscript

    <!-- Product Modal -->
    <livewire:frontend.product-modal-component />

</div>
