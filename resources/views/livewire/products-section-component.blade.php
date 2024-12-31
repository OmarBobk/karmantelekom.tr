<div class="w-full bg-white py-8">
    <!-- Section Header -->
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold text-gray-900">Featured Products</h2>
            <a href="#" class="text-blue-600 hover:text-blue-700 font-medium">View All</a>
        </div>
    </div>

    <!-- Products Grid -->
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-4 md:gap-6 overflow-x-hidden">
            @for($i = 1; $i <= 10; $i++)
                <div class="group/card" x-data="productSlider">
                    <!-- Product Card -->
                    <div class="bg-gray-50 rounded-lg">
                        <!-- Image Slider -->
                        <div class="relative aspect-w-1 aspect-h-1 w-full overflow-hidden h-48"
                             x-on:touchstart="touchStartX = $event.touches[0].clientX"
                             x-on:touchend="
                                touchEndX = $event.changedTouches[0].clientX;
                                if (touchStartX - touchEndX > 50) nextSlide();
                                if (touchEndX - touchStartX > 50) prevSlide();
                             ">
                            @for($imgIndex = 1; $imgIndex <= 3; $imgIndex++)
                                <img src="{{ asset('assets/images/product-'.$i.'-'.$imgIndex.'.png') }}"
                                     alt="Product {{ $i }} - Image {{ $imgIndex }}"
                                     class="absolute h-full w-full object-cover object-center transition-opacity duration-300"
                                     :class="{ 'opacity-100': currentSlide === {{ $imgIndex }}, 'opacity-0': currentSlide !== {{ $imgIndex }} }">
                            @endfor
                            
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
                                <!-- <h3 class="text-lg font-bold text-gray-900">Product {{ $i }}</h3> -->
                                <p class="text-sm text-gray-500 line-clamp-2"><span class="text-lg font-bold text-gray-900">Product {{ $i }} </span> High quality product with amazing features and incredible design that you'll love to have in your collection</p>
                            </div>

                            <div class="flex items-center justify-between">
                                <p class="text-lg font-bold text-blue-600 mt-2">$99.99</p>
                                
                                <!-- Quantity Controls -->
                                <div class="mt-3" @click.away="showQuantity = false; quantity = 0">
                                    <button
                                        @click="showQuantity = true; quantity++"
                                        x-show="!showQuantity"
                                        class="w-full p-1 text-white bg-gradient-to-r from-blue-500 to-blue-600 rounded-full hover:from-blue-600 hover:to-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-200"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                        </svg>
                                    </button>

                                    <div x-show="showQuantity" class="space-y-2">
                                        <div class="flex items-center justify-between">
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
                                            @click="$wire.addToCart({{ $i }}, quantity)"
                                            class="w-full !mt-1 py-1 text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                        >
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-3 w-3 m-auto">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
                                        </svg>

                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endfor
        </div>
    </div>
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