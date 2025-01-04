<div class="w-full bg-white pb-8 pt-4" x-data="slider" role="region" aria-label="Product Categories Slider">
    <!-- Category Navigation -->
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="relative" x-data="navigationScroll">
            <!-- Enhanced Shadow indicators for scroll -->
            <div class="absolute left-0 top-0 bottom-0 w-12 z-10 hidden md:flex items-center justify-start"
                 :class="{ 'pointer-events-none': atStart }"
            >
                <div class="absolute inset-0 bg-gradient-to-r from-white via-white to-transparent
                            opacity-0 transition-opacity duration-200"
                     :class="{ 'opacity-90': !atStart }"
                ></div>
                
                <button 
                    @click="scrollTabsLeft"
                    class="relative ml-1 size-8 flex items-center justify-center rounded-full bg-white shadow-md border border-gray-200/50
                           text-gray-400 hover:text-gray-600 transition-all duration-200 
                           opacity-0 hover:bg-gray-50"
                    :class="{ 'opacity-100': !atStart }"
                    x-show="!atStart"
                >
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </button>
            </div>

            <div class="absolute right-0 top-0 bottom-0 w-12 z-10 hidden md:flex items-center justify-end"
                 :class="{ 'pointer-events-none': atEnd }"
            >
                <div class="absolute inset-0 bg-gradient-to-l from-white via-white to-transparent
                            opacity-0 transition-opacity duration-200"
                     :class="{ 'opacity-90': !atEnd }"
                ></div>
                
                <button 
                    @click="scrollTabsRight"
                    class="relative mr-1 size-8 flex items-center justify-center rounded-full bg-white shadow-md border border-gray-200/50
                           text-gray-400 hover:text-gray-600 transition-all duration-200 
                           opacity-0 hover:bg-gray-50"
                    :class="{ 'opacity-100': !atEnd }"
                    x-show="!atEnd"
                >
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>
            </div>

            <!-- Scrollable Navigation -->
            <nav class="relative overflow-x-auto scrollbar-hide -mb-px"
                 role="tablist"
                 aria-label="Product categories"
                 x-ref="nav"
                 @scroll.debounce.50ms="updateScroll"
                 x-cloak
            >
                <div class="flex flex-nowrap border-b border-gray-200" x-cloak>
                    <div class="tabs flex-nowrap whitespace-nowrap">
                        @foreach(['New Arrivals', 'Best Sellers', 'Sale Items', 'Featured', 'Featured1', 'Featured2', 'Featured3', 'Featured4', 'Featured5', 'Featured6', 'Featured7', 'Featured8', 'Featured9', 'Featured10'] as $index => $category)
                            <button
                                x-on:click="$wire.activeCategory = {{ $index }}"
                                wire:click="setActiveCategory({{ $index }})"
                                class="tab tab-lg text-gray-500 hover:text-gray-900 transition-all duration-200 whitespace-nowrap"
                                :class="$wire.activeCategory === {{ $index }} ? '!text-blue-600 !border-b-2 !border-blue-600' : ''"
                                role="tab"
                                :aria-selected="$wire.activeCategory === {{ $index }}"
                                aria-controls="panel-{{ $index }}"
                                id="tab-{{ $index }}"
                                x-cloak
                            >
                                {{ $category }}
                            </button>
                        @endforeach
                    </div>
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
                    class="btn btn-circle btn-ghost hidden lg:flex absolute -left-2 md:-left-6 top-1/2 -translate-y-1/2 
                           opacity-0 group-hover:opacity-100 z-10 bg-white shadow-lg hover:bg-gray-50"
                    :disabled="atStart"
                    aria-label="Scroll left"
                    x-cloak
                >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </button>

                <!-- Right Navigation Button -->
                <button
                    @click="scrollRight"
                    class="btn btn-circle btn-ghost hidden lg:flex absolute -right-2 md:-right-6 top-1/2 -translate-y-1/2 
                           opacity-0 group-hover:opacity-100 z-10"
                    :disabled="atEnd"
                    aria-label="Scroll right"
                    x-cloak
                >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>

                <!-- Slider Content -->
                <div class="relative overflow-hidden h-[440px]">
                    @foreach(['New Arrivals', 'Best Sellers', 'Sale Items', 'Featured', 'Featured1', 'Featured2', 'Featured3', 'Featured4', 'Featured5', 'Featured6', 'Featured7', 'Featured8', 'Featured9', 'Featured10'] as $index => $category)
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
                            class="absolute inset-0 w-full"
                            x-cloak
                        >
                            <div class="relative overflow-hidden" x-cloak>
                                <div
                                    x-ref="slider-{{ $index }}"
                                    class="flex gap-6 overflow-x-auto scrollbar-hide scroll-smooth snap-x snap-mandatory"
                                    @scroll.debounce.50ms="updateScrollButtons({{ $index }})"
                                    x-cloak
                                >
                                    @for($i = 1; $i <= 10; $i++)
                                        <div class="flex-none w-72 snap-start mb-4 pl-2">
                                            <!-- Product Card -->
                                            <div class="card bg-base-100 shadow-xl hover:shadow-2xl transition-shadow duration-300 group/card">
                                                <figure class="relative aspect-w-1 aspect-h-1 w-full overflow-hidden bg-gray-50">
                                                    <img src="{{ asset('assets/images/product-'.$i.'.png') }}"
                                                         alt="Product {{ $i }}"
                                                         class="h-full w-full object-cover object-center group-hover/card:scale-105 transition-transform duration-300">
                                                </figure>
                                                <div class="card-body p-4">
                                                    <div class="flex justify-between items-center">
                                                        <div>
                                                            <p class="text-sm text-gray-500">{{ $category }}</p>
                                                            <h3 class="text-lg font-medium text-gray-900">Product {{ $i }}</h3>
                                                            <p class="text-xl font-semibold text-gray-900">$99.99</p>
                                                        </div>
                                                        <div x-data="{ quantity: 0, showQuantity: false }" 
                                                            @click.away="showQuantity = false; quantity = 0"
                                                            class="flex flex-col items-center justify-between gap-2"
                                                        >
                                                            <!-- Add Button - Back to original position -->
                                                            <button
                                                                @click="showQuantity = true; quantity++"
                                                                x-show="!showQuantity"
                                                                class="btn btn-circle btn-primary btn-sm"
                                                                aria-label="Add to cart"
                                                            >
                                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.5v15m7.5-7.5h-15" />
                                                                </svg>
                                                            </button>

                                                            <!-- Quantity Control - Original size -->
                                                            <div x-show="showQuantity" class="join">
                                                                <button
                                                                    @click="if (quantity > 0) quantity--"
                                                                    class="btn btn-sm join-item bg-gray-50 hover:bg-gray-100 border-gray-200"
                                                                    aria-label="Decrease quantity"
                                                                >
                                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14" />
                                                                    </svg>
                                                                </button>
                                                                <input
                                                                    type="text"
                                                                    x-model="quantity"
                                                                    class="input input-bordered input-sm w-14 join-item text-center bg-white"
                                                                />
                                                                <button
                                                                    @click="quantity++"
                                                                    class="btn btn-sm join-item bg-gray-50 hover:bg-gray-100 border-gray-200"
                                                                    aria-label="Increase quantity"
                                                                >
                                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.5v15m7.5-7.5h-15" />
                                                                    </svg>
                                                                </button>
                                                            </div>

                                                            <!-- Add to Cart Button -->
                                                            <button
                                                                @click="$wire.addToCart({{ $i }}, quantity)"
                                                                x-show="showQuantity"
                                                                class="btn btn-sm w-full mt-0 bg-gray-900 hover:bg-gray-800 text-white"
                                                                aria-label="Add to cart with quantity"
                                                            >
                                                                Add
                                                            </button>
                                                        </div>
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
        console.log('f')
        document.addEventListener('alpine:init', () => {
            Alpine.data('slider', () => ({
                atStart: true,
                atEnd: false,

                init() {
                    this.$nextTick(() => {
                        this.updateScrollButtons(this.$wire.activeCategory);
                        // Add resize observer to handle container width changes
                        const resizeObserver = new ResizeObserver(() => {
                            this.updateScrollButtons(this.$wire.activeCategory);
                        });
                        const activeSlider = this.$refs[`slider-${this.$wire.activeCategory}`];
                        if (activeSlider) {
                            resizeObserver.observe(activeSlider);
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
