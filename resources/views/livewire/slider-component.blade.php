<div class="w-full bg-white pb-8 pt-4" x-data="slider" role="region" aria-label="Product Categories Slider">
    <!-- Category Navigation -->
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="relative" x-data="navigationScroll">
            <!-- Enhanced Shadow indicators for scroll -->
            <div class="absolute left-0 top-0 bottom-0 w-12 pointer-events-none z-10
                        bg-gradient-to-r from-white via-white to-transparent
                        opacity-90 transition-opacity duration-300"
                 :class="{ 'hidden': atStart }"
            >
            </div>
            <div class="absolute right-0 top-0 bottom-0 w-12 pointer-events-none z-10
                        bg-gradient-to-l from-white via-white to-transparent
                        opacity-90 transition-opacity duration-300"
                 :class="{ 'hidden': atEnd }"
            >
            </div>

            <!-- Scrollable Navigation -->
            <nav class="relative overflow-x-auto scrollbar-hide -mb-px"
                 role="tablist"
                 aria-label="Product categories"
                 x-ref="nav"
                 @scroll.debounce.50ms="updateScroll"
            >
                <div class="flex flex-nowrap gap-x-8 border-b border-gray-200 pb-px">
                    @foreach(['New Arrivals', 'Best Sellers', 'Sale Items', 'Featured'] as $index => $category)
                        <button
                            wire:click="setActiveCategory({{ $index }})"
                            class="relative whitespace-nowrap pb-4 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 rounded-sm flex-none"
                            role="tab"
                            aria-selected="{{ $activeCategory === $index ? 'true' : 'false' }}"
                            aria-controls="panel-{{ $index }}"
                            id="tab-{{ $index }}"
                        >
                            <span class="@if($activeCategory === $index)
                                         text-blue-600 font-medium
                                       @else
                                         text-gray-600 hover:text-gray-900
                                       @endif
                                       transition-colors duration-200">
                                {{ $category }}
                            </span>
                            <span class="absolute bottom-[-2px] left-0 w-full h-0.5 bg-blue-600 transition-transform duration-300 ease-out
                                        {{ $activeCategory === $index ? 'scale-x-100' : 'scale-x-0' }}">
                            </span>
                        </button>
                    @endforeach
                </div>
            </nav>
        </div>
    </div>

    <!-- Product Panels -->
    <div class="relative mt-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <!-- Slider Container with Navigation Buttons -->
            <div class="group relative">
                <!-- Left Navigation Button -->
                <button
                    @click="scrollLeft"
                    class="hidden lg:flex absolute -left-2 md:-left-6 top-1/2 -translate-y-1/2 h-12 w-12
                           rounded-full bg-white shadow-xl border border-gray-200
                           items-center justify-center z-10
                           hover:bg-gray-50 active:bg-gray-100
                           focus:outline-none focus:ring-2 focus:ring-blue-500
                           transition-all duration-200 ease-in-out
                           opacity-0 group-hover:opacity-100"
                    :disabled="atStart"
                    aria-label="Scroll left"
                >
                    <svg class="w-6 h-6 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/>
                    </svg>
                </button>

                <!-- Right Navigation Button -->
                <button
                    @click="scrollRight"
                    class="hidden lg:flex absolute -right-2 md:-right-6 top-1/2 -translate-y-1/2 h-12 w-12
                           rounded-full bg-white shadow-xl border border-gray-200
                           items-center justify-center z-10
                           hover:bg-gray-50 active:bg-gray-100
                           focus:outline-none focus:ring-2 focus:ring-blue-500
                           transition-all duration-200 ease-in-out
                           opacity-0 group-hover:opacity-100"
                    :disabled="atEnd"
                    aria-label="Scroll right"
                >
                    <svg class="w-6 h-6 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>

                <!-- Slider Content -->
                @foreach(['New Arrivals', 'Best Sellers', 'Sale Items', 'Featured'] as $index => $category)
                    <div
                        id="panel-{{ $index }}"
                        role="tabpanel"
                        aria-labelledby="tab-{{ $index }}"
                        x-show="$wire.activeCategory === {{ $index }}"
                        x-transition:enter="transition ease-out duration-300"
                        x-transition:enter-start="opacity-0 transform translate-x-full"
                        x-transition:enter-end="opacity-100 transform translate-x-0"
                        x-transition:leave="transition ease-in duration-300"
                        x-transition:leave-start="opacity-100 transform translate-x-0"
                        x-transition:leave-end="opacity-0 transform -translate-x-full"
                    >
                        <div class="relative overflow-hidden">
                            <div
                                x-ref="slider-{{ $index }}"
                                class="flex gap-6 overflow-x-auto scrollbar-hide scroll-smooth snap-x snap-mandatory"
                                @scroll.debounce.50ms="updateScrollButtons"
                            >
                                @for($i = 1; $i <= 10; $i++)
                                    <div class="flex-none w-72 snap-start">
                                        <!-- Product Card -->
                                        <div class="group/card relative bg-gray-50 rounded-lg">
                                            <div class="aspect-w-1 aspect-h-1 w-full overflow-hidden h-80">
                                                <img src="{{ asset('assets/images/product-'.$i.'.png') }}"
                                                     alt="Product {{ $i }}"
                                                     class="h-full w-full object-cover object-center group-hover/card:scale-105 transition-transform duration-300">
                                            </div>
                                            <div class="p-4 flex items-center justify-between">
                                                <div>
                                                    <p class="text-sm text-gray-500">{{ $category }}</p>
                                                    <h3 class="text-xl font-semibold text-gray-900">Product {{ $i }}</h3>
                                                    <p class="text-xl font-bold text-blue-600">$99.99</p>
                                                </div>
                                                <div x-data="{ quantity: 0, showQuantity: false }" @click.away="showQuantity = false; quantity = 0">
                                                    <!-- Add Button with + Icon -->
                                                    <button
                                                        @click="showQuantity = true; quantity++"
                                                        x-show="!showQuantity"
                                                        class="ml-2 p-2 text-white bg-gradient-to-r from-blue-500 to-blue-600 rounded-full hover:from-blue-600 hover:to-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-200"
                                                        aria-label="Add to cart"
                                                    >
                                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                                        </svg>

                                                    </button>

                                                    <!-- Quantity Control -->
                                                    <div x-show="showQuantity" class="flex items-center mb-1 relative">
                                                        <button
                                                            @click="if (quantity > 0) quantity--"
                                                            class="p-2 text-white bg-blue-600 rounded hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-200"
                                                            aria-label="Decrease quantity"
                                                        >
                                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14" />
                                                            </svg>

                                                        </button>
                                                        <input
                                                            type="text"
                                                            x-model="quantity"
                                                            class="w-12 h-9 text-center border border-gray-300 rounded-md mx-2"
                                                        />
                                                        <button
                                                            @click="quantity++"
                                                            class="p-2 text-white bg-blue-600 rounded hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-200"
                                                            aria-label="Increase quantity"
                                                        >
                                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                                            </svg>
                                                        </button>
                                                    </div>

                                                    <!-- Add to Cart Button -->
                                                    <button
                                                        @click="$wire.addToCart({{ $i }}, quantity)"
                                                        x-show="showQuantity"
                                                        class="w-full text-sm ml-0 py-1 px-2 text-white bg-blue-600 rounded hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-200"
                                                        aria-label="Add to cart with quantity"
                                                    >
                                                        Add
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endfor
                            </div>
                        </div>
                    </div>
                @endforeach
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
    </style>
    <script>
        console.log('f')
        document.addEventListener('alpine:init', () => {
            Alpine.data('slider', () => ({
                atStart: true,
                atEnd: false,

                init() {
                    this.updateScrollButtons();
                },

                scrollLeft() {
                    const activeSlider = this.$refs[`slider-${this.$wire.activeCategory}`];
                    if (activeSlider) {
                        // Calculate width of two products (including gap)
                        const productWidth = activeSlider.querySelector('.flex-none').offsetWidth;
                        const gap = 24; // This matches the gap-6 (1.5rem = 24px) in your Tailwind class
                        const scrollAmount = (productWidth + gap) * 2;

                        activeSlider.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
                        this.updateScrollButtons();
                    }
                },

                scrollRight() {
                    const activeSlider = this.$refs[`slider-${this.$wire.activeCategory}`];
                    if (activeSlider) {
                        // Calculate width of two products (including gap)
                        const productWidth = activeSlider.querySelector('.flex-none').offsetWidth;
                        const gap = 24; // This matches the gap-6 (1.5rem = 24px) in your Tailwind class
                        const scrollAmount = (productWidth + gap) * 2;

                        activeSlider.scrollBy({ left: scrollAmount, behavior: 'smooth' });
                        this.updateScrollButtons();
                    }
                },

                updateScrollButtons() {
                    const activeSlider = this.$refs[`slider-${this.$wire.activeCategory}`];
                    if (activeSlider) {
                        this.atStart = activeSlider.scrollLeft <= 0;
                        this.atEnd = activeSlider.scrollLeft >= activeSlider.scrollWidth - activeSlider.offsetWidth;
                    }
                }
            }));
        });

        document.addEventListener('alpine:init', () => {
            Alpine.data('navigationScroll', () => ({
                atStart: true,
                atEnd: true,

                init() {
                    this.$nextTick(() => {
                        this.updateScroll();
                    });
                    window.addEventListener('resize', () => this.updateScroll());
                },

                updateScroll() {
                    const nav = this.$refs.nav;
                    if (nav) {
                        // Check scroll position
                        this.atStart = nav.scrollLeft <= 0;
                        this.atEnd = nav.scrollLeft >= nav.scrollWidth - nav.offsetWidth - 1;

                        // Force Alpine to react to the changes
                        this.$nextTick(() => {
                            this.atStart = this.atStart;
                            this.atEnd = this.atEnd;
                        });
                    }
                }
            }));
        });
    </script>

</div>
