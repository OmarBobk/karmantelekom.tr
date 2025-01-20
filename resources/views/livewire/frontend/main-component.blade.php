<div>

    <!-- Start Slider Component -->
    <div class="w-full bg-white pb-8 pt-4" x-data="slider" role="region" aria-label="Product Categories Slider">
        <!-- Category Navigation -->
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="relative" x-data="navigationScroll">
                <!-- Enhanced Shadow indicators for scroll -->
                <div class="absolute left-0 bottom-[3px] w-12 z-10 hidden md:flex items-center justify-start"
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

                <div class="absolute right-0 bottom-[3px] w-12 z-10 hidden md:flex items-center justify-end"
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
                        <div class="flex flex-nowrap whitespace-nowrap">
                            
                            @foreach($sections as $index => $section)
                                <button
                                    x-on:click="$wire.activeCategory = {{ $index }}"
                                    wire:click="setActiveCategory({{ $index }})"
                                    class="px-4 py-2 text-base font-medium text-gray-500 hover:text-gray-900 transition-all duration-200 whitespace-nowrap border-b-2 border-transparent"
                                    :class="$wire.activeCategory === {{ $index }} ? '!text-blue-600 !border-blue-600' : ''"
                                    role="tab"
                                    :aria-selected="$wire.activeCategory === {{ $index }}"
                                    aria-controls="panel-{{ $index }}"
                                    id="tab-{{ $index }}"
                                    x-cloak
                                >
                                    {{ $section->name }}
                                </button>

                                <!-- Add loading indicator after the tabs -->
                                <div wire:loading wire:target="setActiveCategory" 
                                    class="absolute inset-x-0 top-full mt-2 flex items-center justify-center">
                                    <div class="bg-white/90 backdrop-blur-sm shadow-lg rounded-lg px-4 py-2 text-sm text-gray-600 flex items-center gap-2">
                                        <svg class="animate-spin size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        Loading products...
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </nav>
            </div>
        </div>

        <!-- Product Panels -->
        <div class="relative mt-8">
            <div class="mx-auto max-w-7xl px-0 lg:px-8">
                <!-- Slider Container with Navigation Buttons -->
                <div class="group relative">
                    <!-- Left Navigation Button -->
                    <button
                        @click="scrollLeft"
                        class="hidden lg:flex items-center justify-center size-10 rounded-full bg-white hover:bg-gray-50 text-gray-600 hover:text-gray-900 shadow-lg border border-gray-200 absolute -left-2 md:-left-6 top-1/2 -translate-y-1/2 opacity-0 group-hover:opacity-100 z-10 transition-all duration-200"
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
                        class="hidden lg:flex items-center justify-center size-10 rounded-full bg-white hover:bg-gray-50 text-gray-600 hover:text-gray-900 shadow-lg border border-gray-200 absolute -right-2 md:-right-6 top-1/2 -translate-y-1/2 opacity-0 group-hover:opacity-100 z-10 transition-all duration-200"
                        :disabled="atEnd"
                        aria-label="Scroll right"
                        x-cloak
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>

                    <!-- Slider Content -->
                    <div class="relative overflow-hidden h-[500px]">
                        @foreach($this->sections as $index => $section)
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
                                        class="flex lg:ronded md:rounded-none sm:rounded-2xl overflow-x-auto scrollbar-hide scroll-smooth snap-x snap-mandatory"
                                        @scroll.debounce.50ms="updateScrollButtons({{ $index }})"
                                        x-cloak
                                    >
                                        @foreach($section->products as $product)
                                            <div class="flex-none w-72 snap-start p-4">
                                                <div class="bg-white rounded-lg shadow-lg hover:shadow-xl transition-shadow duration-300">
                                                    <figure class="relative aspect-w-1 aspect-h-1 w-full overflow-hidden bg-gray-50">
                                                        <img src="{{ Storage::url($product->images->where('is_primary', true)->first()->image_url)}}"
                                                            alt="{{ $product->name }}"
                                                            class="h-full w-full object-cover object-center group-hover/card:scale-105 transition-transform duration-300">
                                                    </figure>
                                                    <div class="p-4">
                                                        <!-- Product Info Header -->
                                                        <div class="mb-3">
                                                            <p class="text-sm text-gray-500">{{ $section->name }}</p>
                                                            <div class="line-clamp-2">
                                                                <span class="text-base font-medium text-gray-900">{{ $product->name }}</span>
                                                                <span class="text-sm text-gray-500">{{ $product->description }}</span>
                                                            </div>
                                                        </div>

                                                        <!-- Price and Quantity Control on same line -->
                                                        <div class="flex items-center justify-between">
                                                            <div>
                                                                @if($product->prices->isNotEmpty())
                                                                    <p class="text-xl font-semibold text-blue-600">
                                                                        {{ money($product->prices->first()->price) }}
                                                                    </p>
                                                                @else
                                                                    <p class="text-xl font-semibold text-gray-400">Price not available</p>
                                                                @endif
                                                            </div>
                                                            
                                                            <!-- Quantity Control -->
                                                            <div x-data="{ quantity: 0, showQuantity: false }" 
                                                                @click.away="showQuantity = false; quantity = 0"
                                                                class="flex items-center gap-2"
                                                            >
                                                                <!-- Add Button -->
                                                                <button
                                                                    @click="showQuantity = true; quantity++"
                                                                    x-show="!showQuantity"
                                                                    class="inline-flex items-center justify-center size-8 rounded-full bg-blue-600 text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200"
                                                                    aria-label="Add to cart"
                                                                >
                                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.5v15m7.5-7.5h-15" />
                                                                    </svg>
                                                                </button>

                                                                <!-- Quantity Controls -->
                                                                <div x-show="showQuantity" class="flex flex-col items-center gap-1">
                                                                    <div class="flex rounded-lg overflow-hidden">
                                                                        <button
                                                                            @click="if (quantity > 0) quantity--"
                                                                            class="inline-flex items-center justify-center px-2 py-1 bg-gray-50 hover:bg-gray-100 border border-gray-200 text-gray-600 hover:text-gray-900"
                                                                            aria-label="Decrease quantity"
                                                                        >
                                                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14" />
                                                                            </svg>
                                                                        </button>
                                                                        <input
                                                                            type="text"
                                                                            x-model="quantity"
                                                                            class="w-12 px-2 py-1 text-center border-t border-b border-gray-200 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                                                                        />
                                                                        <button
                                                                            @click="quantity++"
                                                                            class="inline-flex items-center justify-center px-2 py-1 bg-gray-50 hover:bg-gray-100 border border-gray-200 text-gray-600 hover:text-gray-900"
                                                                            aria-label="Increase quantity"
                                                                        >
                                                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.5v15m7.5-7.5h-15" />
                                                                            </svg>
                                                                        </button>
                                                                    </div>

                                                                    <button
                                                                        @click="$wire.addToCart({{ $product->id }}, quantity)"
                                                                        class="px-3 w-full text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200"
                                                                        aria-label="Add to cart with quantity"
                                                                    >
                                                                        Add
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
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
    <!-- End Slider Component -->

    <!-- Start Products Section Component -->
    <div class="w-full bg-white py-8">
        <!-- Section Header -->
        @foreach($this->contentSections as $section)
            <div class="w-full bg-white py-8">
                <!-- Section Header -->
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-2xl font-bold text-gray-900">{{ $section->name }}</h2>
                        <a href="#" class="text-blue-600 hover:text-blue-700 font-medium">View All</a>
                    </div>
                </div>

                <!-- Products Grid -->
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="h-[340px] grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 overflow-x-hidden">
                        @foreach($section->products as $product)
                            <div class="group/card px-4" x-data="productSlider">
                                <!-- Product Card -->
                                <div class="bg-white rounded-lg shadow-lg hover:shadow-xl transition-shadow duration-300">
                                    <!-- Image Slider -->
                                    <div class="relative aspect-w-1 aspect-h-1 w-full overflow-hidden h-48 bg-gray-100"
                                        x-on:touchstart="touchStartX = $event.touches[0].clientX"
                                        x-on:touchend="
                                            touchEndX = $event.changedTouches[0].clientX;
                                            if (touchStartX - touchEndX > 50) nextSlide();
                                            if (touchEndX - touchStartX > 50) prevSlide();
                                        ">
                                        @foreach($product->images as $index => $image)
                                            <img src="{{ Storage::url($image->image_url) }}"
                                                alt="{{ $product->name }} - Image {{ $index + 1 }}"
                                                class="absolute h-full w-full object-cover object-center transition-opacity duration-300"
                                                :class="{ 'opacity-100': currentSlide === {{ $index + 1 }}, 'opacity-0': currentSlide !== {{ $index + 1 }} }">
                                        @endforeach
                                        
                                        <!-- Navigation Arrows -->
                                        <button @click="prevSlide" 
                                                class="absolute left-2 top-1/2 -translate-y-1/2 p-1 rounded-full bg-white/80 hover:bg-white text-gray-800 opacity-0 group-hover/card:opacity-100 transition-opacity">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                                            </svg>
                                        </button>
                                        
                                        <button @click="nextSlide"
                                                class="absolute right-2 top-1/2 -translate-y-1/2 p-1 rounded-full bg-white/80 hover:bg-white text-gray-800 opacity-0 group-hover/card:opacity-100 transition-opacity">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                            </svg>
                                        </button>

                                        <!-- Slider Controls -->
                                        <div class="absolute bottom-[.125rem] left-0 right-0 flex justify-center gap-1">
                                            @for($dotIndex = 1; $dotIndex <= 3; $dotIndex++)
                                                <button @click="goToSlide({{ $dotIndex }})"
                                                        class="w-2 h-2 rounded-full transition-all duration-200"
                                                        :class="currentSlide === {{ $dotIndex }} ? 'bg-gray-900/50 scale-125' : 'bg-gray-200'">
                                                </button>
                                            @endfor
                                        </div>
                                    </div>

                                    <!-- Product Info -->
                                    <div class="p-4">
                                        <div class="flex items-start gap-2">
                                            <div class="line-clamp-2">
                                                <span class="text-sm font-medium text-gray-900">{{ $product->name }}</span>
                                                <span class="text-sm text-gray-500">{{ $product->description }}</span>
                                            </div>
                                        </div>

                                        <div class="flex items-center justify-between mt-3">
                                            <div>
                                                @if($product->prices->isNotEmpty())
                                                    <p class="text-xl font-semibold text-blue-600">
                                                        {{ money($product->prices->first()->price) }}
                                                    </p>
                                                @else
                                                    <p class="text-xl font-semibold text-gray-400">Price not available</p>
                                                @endif
                                            </div>
                                            
                                            <!-- Quantity Controls -->
                                            <div class="" @click.away="showQuantity = false; quantity = 0" x-cloak>
                                                <button
                                                    @click="showQuantity = true; quantity++"
                                                    x-show="!showQuantity"
                                                    class="w-full p-1 text-white bg-gradient-to-r from-blue-500 to-blue-600 rounded-full hover:from-blue-600 hover:to-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-200"
                                                    x-cloak
                                                >
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor" x-cloak>
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                                    </svg>
                                                </button>

                                                <div x-show="showQuantity" class="space-y-2" x-cloak>
                                                    <div class="flex items-center justify-between" x-cloak>
                                                        <button
                                                            @click="if (quantity > 0) quantity--"
                                                            class="p-1 text-white bg-blue-600 rounded hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                                        >
                                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/>
                                                            </svg>
                                                        </button>
                                                        
                                                        <input
                                                            type="text"
                                                            x-model="quantity"
                                                            class="w-10 h-[1.35rem] text-center border border-gray-300 rounded-md mx-1"
                                                        />
                                                        
                                                        <button
                                                            @click="quantity++"
                                                            class="p-1 text-white bg-blue-600 rounded hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                                        >
                                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                                            </svg>
                                                        </button>
                                                    </div>
                                                    
                                                    <button
                                                        @click="$wire.addToCart({{ $product->id }}, quantity)"
                                                        class="w-full !mt-1 py-1 text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 relative"
                                                        x-cloak
                                                    >
                                                        <span wire:loading.remove wire:target="addToCart({{ $product->id }})">
                                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-3 w-3 m-auto">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
                                                            </svg>
                                                        </span>
                                                        <span wire:loading wire:target="addToCart({{ $product->id }})" class="flex items-center justify-center gap-1">
                                                            <svg class="animate-spin size-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                            </svg>
                                                            Adding...
                                                        </span>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach
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

        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('productSlider', () => ({
                    currentSlide: 1,
                    quantity: 0,
                    showQuantity: false,
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
            });
        </script>

</div>
