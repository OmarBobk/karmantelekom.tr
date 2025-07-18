<!-- Desktop Auth Menu -->
<div class="relative flex items-center gap-x-2"
     x-data="{ open: false }"
     @mouseleave="open = false">
    <div class="flex items-center gap-x-2 cursor-pointer"
         @mouseenter="open = true"
         :class="{ 'text-blue-600': open }">
        <div class="p-2 rounded-xl transition-all duration-200 h-11 w-11 flex items-center justify-center group-hover:bg-gray-100"
             :class="{ 'text-blue-600 pr-2.5 hover:bg-gray-100': open, 'text-gray-700 hover:text-blue-600': !open }">

            <svg x-show="!open" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-user-icon lucide-user"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>

            <svg x-show="open" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-6" x-cloak>
                <path fill-rule="evenodd" d="M7.5 6a4.5 4.5 0 1 1 9 0 4.5 4.5 0 0 1-9 0ZM3.751 20.105a8.25 8.25 0 0 1 16.498 0 .75.75 0 0 1-.437.695A18.683 18.683 0 0 1 12 22.5c-2.786 0-5.433-.608-7.812-1.7a.75.75 0 0 1-.437-.695Z" clip-rule="evenodd" />
            </svg>

        </div>
    </div>

    <!-- Dropdown Menu -->
    <div x-show="open"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="absolute right-0 top-full mt-2 w-60 rounded-xl bg-white shadow-lg ring-1 ring-gray-200 z-50 focus:outline-none"
         @mouseenter="open = true"
         x-cloak>
        <div class="p-4">
            <p class="text-md px-[.90rem] font-medium text-transparent bg-clip-text bg-gradient-to-r from-blue-500 to-purple-600">
                {{ Auth::user()->name }}
            </p>
            <div class="mt-3 space-y-1">
                @hasanyrole('admin|salesperson')
                    <a href="{{ route('subdomain.main') }}" class="flex items-center gap-x-3 px-3 py-2 text-sm text-gray-700 rounded-lg hover:gap-x-5 hover:bg-gray-50 transition-colors duration-200">
                        <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        {{__('auth.dashboard')}}
                    </a>
                @else
                    <a href="#" class="flex items-center gap-x-3 px-3 py-2 text-sm text-gray-700 rounded-lg hover:gap-x-5 hover:bg-gray-50 transition-colors duration-200">
                        <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        Profile
                    </a>
                @endrole

                <form method="POST" action="{{ route('logout') }}" class="w-full">
                    @csrf
                    <button type="submit" class="w-full flex items-center gap-x-3 px-3 py-2 text-sm text-red-600 rounded-lg hover:gap-x-5 hover:bg-red-50 transition-colors duration-200">
                        <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                        {{__('auth.logout')}}
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
