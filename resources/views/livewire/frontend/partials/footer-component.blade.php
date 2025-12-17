<div class="bg-gray-100">
    <!-- Features Section -->
    <div class=" bg-gray-100 border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 ml-4">
                <!-- Fast Shipping -->
                <div class="flex items-center gap-4 group">
                    <div class="flex-shrink-0 w-14 h-14 rounded-full bg-orange-500 flex items-center justify-center group-hover:scale-110 transition-transform duration-200 shadow-lg">
                        <svg class="w-9 h-9 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="flex-1">
                        <h3 class="font-bold text-gray-900 mb-1 text-sm uppercase tracking-wide">
                            {{ __('main.fast_shipping_title') }}
                        </h3>
                        <p class="text-xs text-gray-600 leading-relaxed">
                            {{ __('main.fast_shipping_description') }}
                        </p>
                    </div>
                </div>

                <!-- Free Shipping -->
                <div class="flex items-center gap-4 group">
                    <div class="flex-shrink-0 w-14 h-14 rounded-full bg-blue-400 flex items-center justify-center group-hover:scale-110 transition-transform duration-200 shadow-lg">
                        <svg class="w-9 h-9 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                        </svg>
                    </div>
                    <div class="flex-1">
                        <h3 class="font-bold text-gray-900 mb-1 text-sm uppercase tracking-wide">
                            {{ __('main.free_shipping_title') }}
                        </h3>
                        <p class="text-xs text-gray-600 leading-relaxed">
                            {{ __('main.free_shipping_description') }}
                        </p>
                    </div>
                </div>

                <!-- Payment Options -->
                <div class="flex items-center gap-4 group">
                    <div class="flex-shrink-0 w-14 h-14 rounded-full bg-pink-500 flex items-center justify-center group-hover:scale-110 transition-transform duration-200 shadow-lg relative">
                        <svg class="w-9 h-9 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                        </svg>
                    </div>

                    <div class="flex-1">
                        <h3 class="font-bold text-gray-900 mb-1 text-sm uppercase tracking-wide">
                            {{ __('main.payment_options_title') }}
                        </h3>
                        <p class="text-xs text-gray-600 leading-relaxed">
                            {{ __('main.payment_options_description') }}
                        </p>
                    </div>
                </div>

                <!-- Secure Shopping -->
                <div class="flex items-center gap-4 group">
                    <div class="flex-shrink-0 w-14 h-14 rounded-full bg-lime-500 flex items-center justify-center group-hover:scale-110 transition-transform duration-200 shadow-lg">
                        <svg class="w-9 h-9 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                    </div>
                    <div class="flex-1">
                        <h3 class="font-bold text-gray-900 mb-1 text-sm uppercase tracking-wide">
                            {{ __('main.secure_shopping_title') }}
                        </h3>
                        <p class="text-xs text-gray-600 leading-relaxed">
                            {{ __('main.secure_shopping_description') }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Footer -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 sm:py-12 py-4 items-center justify-center">
        <!-- Company Info -->
        <div class="flex flex-col justify-between">
            <div class="pr-3">
                <div class="flex">
                    <span class="text-2xl font-semibold bg-gradient-to-r from-blue-500 to-purple-600 bg-clip-text text-transparent">
                        <img src="{{ asset('assets/images/karmantelekom_logo.png') }}" class="" alt="">
                    </span>
                </div>
                <!-- Social Links -->
                <div class="flex justify-between gap-4 my-3">
                    @foreach($socialLinks as $social)
                        <a href="{{ $social['url'] }}" class="text-gray-600 hover:text-blue-600 transition-colors duration-200">
                            <span class="sr-only">{{ $social['name'] }}</span>
                            @if($social['icon'] === 'instagram')
                                <svg class="size-6 transition-transform duration-150 hover:scale-110 hover:text-orange-600" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                            @elseif($social['icon'] === 'facebook')
                                <svg class="size-6 transition-transform duration-150 hover:scale-110 hover:text-blue-500" fill="currentColor" viewBox="0 0 24 24"><path d="M9 8h-3v4h3v12h5v-12h3.642l.358-4h-4v-1.667c0-.955.192-1.333 1.115-1.333h2.885v-5h-3.808c-3.596 0-5.192 1.583-5.192 4.615v3.385z"/></svg>
                            @elseif($social['icon'] === 'tiktok')
                                <!-- Tiktok Icon: Enhanced for clarity, accessibility, and slight animation on hover -->
                                <svg
                                    class="size-7 transition-transform duration-150 hover:scale-110 hover:text-pink-500"
                                    fill="none"
                                    viewBox="0 0 48 48"
                                    aria-hidden="true"
                                    focusable="false"
                                    role="img"
                                >
                                    <title>{{ __('main.tiktok') }}</title>
                                    <desc>{{ __('main.follow_us_on_tiktok') }}</desc>
                                    <g>
                                        <path
                                            d="M41.36 16.12a7.09 7.09 0 01-5.91-2.04V28.7A10.89 10.89 0 1125.49 18v4.37a6.52 6.52 0 105.96 6.49V4h5.95a7.04 7.04 0 003.96 6.37A10.92 10.92 0 0041.36 16.12z"
                                            stroke="currentColor"
                                            stroke-width="2.15"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            fill="currentColor"
                                            opacity="0.93"
                                        />
                                        <circle
                                            cx="36"
                                            cy="11"
                                            r="2.5"
                                            fill="currentColor"
                                            opacity="0.25"
                                            aria-hidden="true"
                                        />
                                    </g>
                                </svg>
                            @elseif($social['icon'] === 'whatsapp')
                                <svg class="size-6 transition-transform duration-150 hover:scale-110 hover:text-green-700" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Company Links -->
        <div>
            <span class="text-lg font-semibold text-gray-900 block mb-4">{{__('main.company')}}</span>
            <div class="flex flex-col space-y-3 mt-4">
                @foreach($companyLinks as $link)
                    <a href="{{ $link['url'] }}" class="text-gray-600 hover:text-blue-600 transition-colors duration-200">{{ __('main.'.strtolower(str_replace(' ', '_', $link['name']))) }}</a>
                @endforeach
            </div>
        </div>

        <!-- Legal Links -->
        <div>
            <span class="text-lg font-semibold text-gray-900 block mb-4">{{__('main.legal')}}</span>
            <div class="flex flex-col space-y-3 mt-4">
                @foreach($legalLinks as $link)
                    <a href="{{ $link['url'] }}" class="text-gray-600 hover:text-blue-600 transition-colors duration-200">
                        {{ __('main.' . $link['name']) }}
                    </a>
                @endforeach
            </div>
        </div>

        <!-- Support Links -->
        <div>
            <span class="text-lg font-semibold text-gray-900 block mb-4">{{__('main.support')}}</span>
            <div class="flex flex-col space-y-3 mt-4">
                @foreach($supportLinks as $link)
                    <a href="{{ $link['url'] }}" class="text-gray-600 hover:text-blue-600 transition-colors duration-200">{{ __('main.'.strtolower(str_replace(' ', '_', $link['name']))) }}</a>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Bottom Footer -->
    <div class="border-t border-gray-200 py-10">
        <div class="max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                <div class="text-sm text-gray-600">
                    <p>{{__('main.copyright')}} © {{ date('Y') }} {{config('app.name')}}. {{__('main.all_rights_reserved')}}.</p>
                </div>
                <div class="flex items-center gap-4">

{{--                    @if($canSwitchCurrency)--}}
{{--                        <div class="relative w-1/2 py-1 px-2 text-sm border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"--}}
{{--                            x-data="{ open: false }">--}}
{{--                            <button--}}
{{--                                @click="open = !open"--}}
{{--                                @click.away="open = false"--}}
{{--                                class="flex w-full justify-between items-center space-x-1 text-sm text-gray-500 hover:text-gray-700"--}}
{{--                            >--}}
{{--                                <span>{{ $currentCurrency }}</span>--}}
{{--                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">--}}
{{--                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />--}}
{{--                                </svg>--}}
{{--                            </button>--}}

{{--                            <div--}}
{{--                                x-show="open"--}}
{{--                                x-transition--}}
{{--                                class="absolute bottom-full mb-2 right-0 w-24 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50"--}}
{{--                            >--}}
{{--                                <div class="py-1">--}}
{{--                                    <button--}}
{{--                                        wire:click="switchCurrency('USD')"--}}
{{--                                        class="block w-full px-4 py-2 text-sm text-left text-gray-700 hover:bg-gray-100"--}}
{{--                                        :class="{ 'bg-gray-50': '{{ $currentCurrency }}' === 'USD' }"--}}
{{--                                    >--}}
{{--                                        USD--}}
{{--                                    </button>--}}
{{--                                    <button--}}
{{--                                        wire:click="switchCurrency('TRY')"--}}
{{--                                        class="block w-full px-4 py-2 text-sm text-left text-gray-700 hover:bg-gray-100"--}}
{{--                                        :class="{ 'bg-gray-50': '{{ $currentCurrency }}' === 'TRY' }"--}}
{{--                                    >--}}
{{--                                        TRY--}}
{{--                                    </button>--}}
{{--                                </div>--}}
{{--                            </div>--}}
{{--                        </div>--}}
{{--                    @endif--}}
                </div>
                <div class="flex items-center gap-4">
                    <span class="text-xs text-gray-500 uppercase tracking-wide">
                        Secure payments
                    </span>
                    <div class="flex items-center gap-3">
                        <img
                            src="{{ asset('assets/images/visa_logo.png') }}"
                            alt="Visa"
                            class="h-7 w-auto object-contain filter grayscale hover:grayscale-0 transition duration-200 drop-shadow-sm"
                        >
                        @if(file_exists(public_path('assets/images/mastercard_logo.png')))
                            <img
                                src="{{ asset('assets/images/mastercard_logo.png') }}"
                                alt="Mastercard"
                                class="h-7 w-auto object-contain filter grayscale hover:grayscale-0 transition duration-200 drop-shadow-sm"
                            >
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
