<div
    x-data="{
        show: @entangle('showModal'),
        stopScroll() {
            if (this.show) {
                document.body.classList.add('overflow-hidden');
            } else {
                document.body.classList.remove('overflow-hidden');
            }
        },
        closeAndReset() {
            $wire.closeModal();
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

    <div class="relative bg-white rounded-xl shadow-2xl w-[90%] sm:w-full max-w-4xl h-[80%] mx-auto flex flex-col md:flex-row overflow-auto"
         x-transition:enter="ease-out duration-300"
         x-transition:leave="ease-in duration-200">

        <!-- Close button -->
        <button wire:click="closeModal" class="absolute right-4 top-4 text-gray-400 hover:text-gray-600 z-10 bg-gray-100 rounded-full p-2">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>

        @if($product)
        <!-- Left: Images -->
        <div class="md:w-1/2 flex flex-col items-left p-6 bg-gray-100">
            <div class="w-full h-full aspect-w-1 aspect-h-1 rounded-lg overflow-hidden bg-white flex items-center justify-center">
                @if($selectedImage)
                    <img src="{{ Storage::url($selectedImage) }}" alt="{{ $product->name }}" class="object-contain w-full h-full">
                @else
                    <img src="https://placehold.co/100" alt="{{ $product->name }}" class="object-contain w-full h-full">
                @endif
            </div>
            <!-- Thumbnails -->
            <div class="flex mt-4 space-x-2">
                @foreach($product->images as $img)
                    <button wire:click="selectImage('{{ $img->image_url }}', '{{$img->id}}')" class="relative bg-white focus:outline-none group">
                        <img src="{{ Storage::url($img->image_url) }}"
                             alt="Thumbnail"
                             class="w-16 h-16 object-cover rounded border-2 {{ $selectedImageId === $img->id ? 'border-emerald-500' : 'border-transparent' }}">
                        <span class="absolute inset-0 rounded transition bg-emerald-500 bg-opacity-0 group-hover:bg-opacity-30 {{ $selectedImageId === $img->id ? 'bg-opacity-40' : '' }}"></span>
                    </button>
                @endforeach
            </div>
        </div>

        <!-- Right: Details -->
        <div class="md:w-1/2 flex flex-col justify-between p-6">
            <div>
                {{-- Breadcrumbs --}}
                @if($product->category)
                    <nav class="mb-2 text-xs text-gray-500 flex items-center space-x-1" aria-label="Breadcrumb">
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
                        <span class="text-emerald-600 font-semibold">{{ $product->category->translated_name }}</span>
                    </nav>
                @endif

                <h2 class="text-2xl font-bold mt-1 mb-2">{{ $product->translated_name }}</h2>
                <div class="flex items-center mb-2">
                    <span class="text-yellow-400 flex items-center">
                        @for($i = 0; $i < 5; $i++)
                            <svg class="w-5 h-5 fill-current" viewBox="0 0 20 20"><polygon points="9.9,1.1 12.3,6.6 18.2,7.3 13.7,11.3 15,17.1 9.9,14.1 4.8,17.1 6.1,11.3 1.6,7.3 7.5,6.6 "/></svg>
                        @endfor
                    </span>
                </div>
                <div class="flex items-center mb-4">
                    <span class="text-green-600 font-medium flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        {{__('main.in_stock')}}
                    </span>
                </div>
                <h4 class="font-semibold mb-1">{{__('main.description')}}: </h4>
                <p class="text-gray-700 mb-4">{{ $product->translated_description }}</p>
                <h4 class="font-semibold mb-1">{{__('main.stock_info')}}:</h4>
                <ul class="list-none text-gray-600 mb-6 space-y-1">
                    <li>
                        <span class="font-medium text-gray-700 text-sm mr-1">{{__('main.code')}}: </span>
                        <span class="font-mono text-gray-900 text-sm">{{$product->code}}</span>
                    </li>
                    @if($product->serial)
                        <li>
                            <span class="font-medium text-gray-700 text-sm mr-1">{{__('main.serial')}}: </span>
                            <span class="font-mono text-gray-900 text-sm">{{$product->serial}}</span>
                        </li>
                    @endif
                </ul>
            </div>
            <div class="flex gap-3 mt-6">
                <a
                   href="{{$requestQuoteUrl}}"
                   target="_blank"
                   class="flex-1 flex items-center justify-center px-4 py-2 bg-emerald-600 text-white font-semibold rounded-lg shadow hover:bg-emerald-700 transition">
                    <svg class="w-5 h-5 {{app()->getLocale() == 'ar' ? 'ml-2' : 'mr-2'}}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v4a1 1 0 001 1h3m10-5h2a2 2 0 012 2v10a2 2 0 01-2 2H7a2 2 0 01-2-2V7a2 2 0 012-2h2"/>
                    </svg>
                    <span class="{{app()->getLocale() == 'ar' ? 'text-sm' : ''}}">
                        {{__('main.request_quote')}}
                    </span>
                </a>
                <a
                    href="{{$moreInfoUrl}}"
                    target="_blank"
                    class="flex-1 flex items-center justify-center px-4 py-2 bg-gray-100 text-gray-700 font-semibold rounded-lg shadow hover:bg-gray-200 transition">
                    <svg class="w-5 h-5 {{app()->getLocale() == 'ar' ? 'ml-2' : 'mr-2'}}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 20a8 8 0 100-16 8 8 0 000 16z"/>
                    </svg>
                    <span class="{{app()->getLocale() == 'ar' ? 'text-sm' : ''}}">

                        {{__('main.more_info')}}
                    </span>
                </a>
            </div>
        </div>
        @endif
    </div>
</div>
