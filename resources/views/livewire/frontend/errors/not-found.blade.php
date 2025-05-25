<div>
<div class="min-h-[60vh] flex items-center justify-center px-4 py-16 sm:px-6 sm:py-24 lg:px-8">
        <div class="max-w-max mx-auto text-center">
            <div class="flex flex-col items-center">
                <!-- 404 Number -->
                <p class="text-6xl sm:text-8xl font-semibold" style="font-family: 'Poppins', sans-serif; background: linear-gradient(to right, #059669, #10b981); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                    Winds Of Roses</p>
                <h1 class="mt-4 text-3xl font-bold tracking-tight text-gray-900 sm:text-5xl">{{__('main.page_not_found')}}</h1>

                <!-- Error Message -->
                <p class="mt-4 text-base text-gray-500">{{__('main.404_message')}}</p>

                <!-- Action Buttons -->
                <div class="mt-8 flex space-x-3">
                    <a href="{{ route('main') }}"
                       class="inline-flex items-center rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
                        <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                        </svg>
                        {{__('main.back_to_home')}}
                    </a>

                    <a href="{{ route('main') }}"
                       class="inline-flex items-center rounded-md border border-emerald-600 bg-white px-4 py-2 text-sm font-semibold text-emerald-700 shadow-sm hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
                        <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                        {{__('main.contact_support')}}
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
