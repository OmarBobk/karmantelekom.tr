<div
    x-data="{
        show: @entangle('showModal'),
        quantity: 1,
        stopScroll() {
            if (this.show) {
                document.body.classList.add('overflow-hidden');
            } else {
                document.body.classList.remove('overflow-hidden');
            }
        },
        closeAndReset() {
            $wire.closeModal();
            this.quantity = 1;
        }
    }"
    x-init="$watch('show', value => stopScroll())"
    x-show="show"
    x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto "
    x-transition:enter="ease-out duration-300"
    x-transition:leave="ease-in duration-200">

    <!-- Overlay: click away closes modal -->
    <div class="absolute inset-0 w-full h-full bg-black bg-opacity-50 backdrop-blur-sm"
    x-cloak
    x-transition:enter="ease-out duration-300"
    x-transition:leave="ease-in duration-200"
    @click="closeAndReset()"></div>

    <div class="relative bg-white rounded-xl shadow-2xl w-[90%] sm:w-full max-w-6xl h-auto max-h-[90vh] mx-auto flex flex-col md:flex-row overflow-y-auto"
         x-transition:enter="ease-out duration-300"
         x-transition:leave="ease-in duration-200">

        <!-- Close button -->
        <button @click="closeAndReset()" class="absolute right-4 top-4 text-gray-400 hover:text-gray-600 z-10 bg-gray-100 rounded-full p-2">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>

        @if($product)
        <!-- Left: Images -->
        <div class="md:w-1/2 flex flex-col items-center justify-center p-6 bg-gray-50 rounded-l-xl"
             x-data="{
                images: {{ json_encode($product->images->map(fn($img) => ['url' => Storage::url($img->image_url), 'id' => $img->id])) }},
                activeImageIndex: {{ $product->images->search(fn($i) => $i->is_primary) ?: 0 }},
                next() {
                    this.activeImageIndex = (this.activeImageIndex + 1) % this.images.length;
                },
                prev() {
                    this.activeImageIndex = (this.activeImageIndex - 1 + this.images.length) % this.images.length;
                },
                setActive(index) {
                    this.activeImageIndex = index;
                }
             }">
            <!-- Main Image -->
            <div class="relative w-full aspect-square rounded-lg overflow-hidden bg-white flex items-center justify-center shadow-inner">
                <template x-for="(image, index) in images" :key="index">
                    <img x-show="activeImageIndex === index" :src="image.url" alt="{{ $product->name }}" class="object-contain w-full h-full" x-transition:enter="transition-opacity ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
                </template>
                <!-- Prev/Next Buttons -->
                <button @click="prev()" class="absolute left-2 top-1/2 -translate-y-1/2 bg-white/50 hover:bg-white/80 rounded-full p-2 transition">
                    <svg class="w-5 h-5 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                </button>
                <button @click="next()" class="absolute right-2 top-1/2 -translate-y-1/2 bg-white/50 hover:bg-white/80 rounded-full p-2 transition">
                    <svg class="w-5 h-5 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                </button>
            </div>

            <!-- Dots -->
            <div class="flex justify-center mt-4 space-x-2">
                <template x-for="(image, index) in images" :key="index">
                    <button @click="setActive(index)" class="h-2 w-2 rounded-full transition" :class="activeImageIndex === index ? 'bg-blue-600' : 'bg-gray-300 hover:bg-gray-400'"></button>
                </template>
            </div>

            <!-- Thumbnails -->
            <div class="flex mt-4 space-x-2 overflow-x-auto py-2">
                <template x-for="(image, index) in images" :key="index">
                    <button @click="setActive(index)" class="relative bg-white focus:outline-none flex-shrink-0">
                        <img :src="image.url" alt="Thumbnail" class="w-16 h-16 object-cover rounded-md border-2 transition" :class="activeImageIndex === index ? 'border-blue-500' : 'border-transparent'">
                    </button>
                </template>
            </div>
        </div>

        <!-- Right: Details -->
        <div class="md:w-1/2 flex flex-col justify-between p-8">
            <div class="flex flex-col flex-1 justify-between">
                {{-- Breadcrumbs --}}
                @if($product->category)
                    <nav class="mb-2 text-sm text-gray-500 flex items-center space-x-1" aria-label="Breadcrumb">
                        @php
                            $category = $product->category;
                            $parents = collect();
                            while ($category->parent) {
                                $parents->prepend($category->parent);
                                $category = $category->parent;
                            }
                        @endphp
                        @foreach($parents as $parent)
                            <a href="#" class="hover:underline">{{ $parent->translated_name }}</a>
                            <span>/</span>
                        @endforeach
                        <span class="text-blue-600 font-semibold">{{ $product->category->translated_name }}</span>
                    </nav>
                @endif

                <h2 class="text-3xl font-bold mt-2 mb-2 text-gray-900">{{ $product->translated_name }}</h2>

                <div class="flex items-center mb-4">
                    <span class="text-yellow-400 flex items-center">
                        @for($i = 0; $i < 5; $i++)
                            <svg class="w-5 h-5 fill-current" viewBox="0 0 20 20"><polygon points="9.9,1.1 12.3,6.6 18.2,7.3 13.7,11.3 15,17.1 9.9,14.1 4.8,17.1 6.1,11.3 1.6,7.3 7.5,6.6 "/></svg>
                        @endfor
                    </span>
{{--                    <span class="text-sm text-gray-500 ml-2">(12 reviews)</span>--}}
                </div>

                <div class="flex items-center">
                    <span class="text-blue-600 font-medium flex items-center">
                        <svg class="w-4 h-4 mr-1.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                        </svg>
                        {{__('main.in_stock')}}
                    </span>
                </div>

                <!-- Price -->
                @if($product->prices->isNotEmpty())
                    <p class="text-3xl font-bold text-gray-900 my-2">
                        {{ $product->prices->first()->getFormattedPrice() }}
                    </p>
                @endif

                <div class="flex flex-col mb-4">
                    <h4 class="text-lg font-semibold text-gray-900 mb-2">{{__('main.description')}}: </h4>
                    <p class="text-gray-600 mb-6 text-sm">{!! $product->translated_description !!}</p>
                </div>

                <!-- Stock Info -->
                <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                    <h4 class="text-lg font-semibold text-gray-900 mb-3">{{__('main.stock_info')}}:</h4>
                    <ul class="list-none text-gray-600 space-y-2">
                        <li class="flex justify-between items-center text-sm">
                            <span class="font-medium text-gray-700">{{__('main.code')}}: </span>
                            <span class="font-mono text-gray-800 bg-gray-200 px-2 py-0.5 rounded">{{$product->code}}</span>
                        </li>
                        @if($product->serial)
                            <li class="flex justify-between items-center text-sm">
                                <span class="font-medium text-gray-700">{{__('main.serial')}}: </span>
                                <span class="font-mono text-gray-800 bg-gray-200 px-2 py-0.5 rounded">{{$product->serial}}</span>
                            </li>
                        @endif
                    </ul>
                </div>
                <!-- Quantity -->
                <div class="mt-4 sm:mt-2">
                    <label for="quantity" class=" font-medium text-gray-700 mb-2 block flex items-center gap-2">{{__('main.quantity')}}:
                    <div class="inline-flex items-center rounded-lg border border-gray-300">
                        <button @click="quantity = Math.max(1, quantity - 1)" class="p-2 pl-3 hover:bg-gray-100 rounded-l-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" /></svg>
                        </button>
                        <span x-text="quantity" class="px-4 py-2 font-medium "></span>
                        <button @click="quantity++" class="p-2 pr-3  hover:bg-gray-100 rounded-r-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                        </button>
                    </div></label>
                </div>

            </div>

            <div class="mt-4 sm:mt-8">

                <div class="flex flex-col sm:flex-row gap-3">
                    <button
                        @click="$store.cart.addItem({{ json_encode($product) }}, quantity); closeAndReset();"
                        class="flex-1 w-full flex items-center justify-center gap-2 px-4 py-3 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl shadow-md hover:shadow-lg transform hover:-translate-y-px transition-all duration-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                        aria-label="{{ __('main.add_to_cart') }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M3 3h2l.4 2M7 13h10l4-8H5.4" stroke-linecap="round" stroke-linejoin="round"/>
                            <circle cx="7" cy="21" r="1" />
                            <circle cx="17" cy="21" r="1" />
                        </svg>
                        <span>{{ __('main.add_to_cart') }}</span>
                    </button>
                    <button @click="closeAndReset()"
                            class="flex-1 flex items-center justify-center px-4 py-3 bg-white text-gray-600 font-semibold rounded-xl shadow-md border border-gray-300 hover:bg-gray-50 transition focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                        <span>{{__('main.close')}}</span>
                    </button>
                </div>
            </div>
        </div>
        @endif
    </div>
</div>
