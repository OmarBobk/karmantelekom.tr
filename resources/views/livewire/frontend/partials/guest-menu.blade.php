<!-- Desktop Guest Menu -->
<div class="relative flex items-center gap-x-2"
     x-data="{ open: false }"
     @mouseleave="open = false">
    <div class="flex items-center gap-x-2 cursor-pointer"
         @mouseenter="open = true">
        <div class="p-2 text-gray-700 hover:text-blue-600 hover:bg-gray-100 rounded-xl transition-all duration-200 h-11 w-11 flex items-center justify-center group-hover:bg-gray-100">
            <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
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
         class="absolute right-[-100%] top-full mt-2 w-60 rounded-xl bg-white shadow-lg ring-1 ring-gray-200 z-10 focus:outline-none"
         @mouseenter="open = true"
         x-cloak>
        <div class="p-4 space-y-3">
            <a href="{{ route('login') }}"
               class="block w-full px-4 py-2.5 text-sm font-medium text-white bg-gradient-to-r from-blue-500 to-purple-600 rounded-lg hover:from-blue-600 hover:to-purple-700 focus:outline-none focus:ring-2 focus:ring-blue-300 transition-all duration-200 text-center">
                Login
            </a>
            <a href="{{ route('register') }}"
               class="block w-full px-4 py-2.5 text-sm font-medium text-gray-700 bg-gray-50 rounded-lg hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-200 transition-all duration-200 text-center">
                Register
            </a>
        </div>
    </div>
</div>
