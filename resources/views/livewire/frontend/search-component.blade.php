<!-- Desktop Search -->
<div class="lg:block relative" x-data="{ isOpen: false }" @click.away="isOpen = false; $wire.resetSearch()">
    <!-- Search Container -->
    <div class="relative flex items-center">
        <div class="relative w-full">
            <!-- Search Input Container -->
            <div class="relative transition-all duration-300 ease-out rounded-sm bg-white shadow-lg border border-gray-200/80">
                <!-- Search Input Wrapper -->
                <div class="relative flex items-center w-full h-11">
                    <!-- Search Icon -->
                    <div class="absolute {{ app()->getLocale() == 'ar' ? 'right-0' : 'left-0' }} p-2.5 text-gray-400">
                        <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>

                    <!-- Search Input -->
                    <input type="text"
                           placeholder="{{__('main.search_for_products')}}..."
                           wire:model.live.debounce.300ms="searchQuery"
                           wire:loading.class="opacity-50"
                           @click="isOpen = true"
                           class="w-full h-full {{ app()->getLocale() == 'ar' ? 'pr-11' : 'pl-11' }} bg-transparent text-sm placeholder-gray-400 outline-none focus:outline-none focus:ring-0 border-0 focus:border-0"
                    >

                    <!-- Clear Button -->
                    <button wire:click="$set('searchQuery', '')"
                            x-show="$wire.searchQuery"
                            class="absolute {{ app()->getLocale() == 'ar' ? 'left-3' : 'right-3' }} p-1 text-gray-400 hover:text-gray-600 focus:outline-none">
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Search Results Dropdown -->
            <div x-show="isOpen && $wire.searchQuery.length >= 2"
                 x-cloak
                 class="absolute mt-2 w-full bg-white/95 backdrop-blur-sm rounded-xl shadow-xl border border-gray-200/80 overflow-hidden z-50 search-results"
                 >

                <!-- Loading State -->
                <div wire:loading
                     class="p-4">
                    <div class="flex items-center justify-center space-x-2 text-sm text-gray-500">
                        <svg class="animate-spin size-5 text-emerald-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span>Searching...</span>
                    </div>
                </div>

                <!-- Results -->
                <div wire:loading.remove
                     x-show="!$wire.isLoading && Array.isArray($wire.searchResults) && $wire.searchResults.length > 0"
                     class="max-h-[570px] overflow-y-auto">

                    <div class="w-full bg-white/30 backdrop-blur-md" x-cloak>
                        <div class="mx-auto max-w-7xl">
                            <div class="relative">
                                <div class="m-4 max-w-7xl">
                                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-y-4">
                                        @foreach($searchResults as $result)
                                        <div>
                                            <div class="group/card mx-2">
                                                <!-- Product Card -->
                                                <div class="bg-white rounded-lg shadow-lg hover:shadow-xl transition-shadow duration-300">
                                                    <div class="flex flex-col relative aspect-w-1 aspect-h-1 w-full overflow-hidden h-40 bg-gray-100 rounded-md">
                                                        <button wire:click="$dispatch('openProductModal', { productId: {{ $result['id'] }} })" class="">
                                                            <img src="{{ $result['image'] }}"
                                                                 alt="{{ $result['title'] }} - Image"
                                                                 class="absolute h-full w-full object-contain object-center transition-opacity duration-300 opacity-100"
                                                                 loading="lazy">
                                                        </button>
                                                    </div>

                                                    <!-- Product Info -->
                                                    <div class="p-2">
                                                        <div class="flex items-start gap-2">
                                                            <button wire:click="$dispatch('openProductModal', { productId: {{ $result['id'] }} })" class="">
                                                                <div class="h-[4.5rem] {{ app()->getLocale() == 'ar' ? 'text-right' : '' }}">
                                                                    <h3 class="text-sm font-medium text-gray-900 hover:text-emerald-600 transition-colors duration-200 line-clamp-1">{{ $result['title'] }}</h3>
                                                                    <p class="text-sm text-gray-500 line-clamp-2">{{ $result['description'] }}</p>
                                                                </div>
                                                            </button>
                                                        </div>

                                                        <div class="flex items-center justify-between mt-3">
                                                            <div>
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
                        </div>
                    </div>
                </div>

                <!-- No Results -->
                <div wire:loading.remove
                     x-show="!$wire.isLoading && Array.isArray($wire.searchResults) && $wire.searchResults.length === 0 && $wire.searchQuery.length >= 2"
                     class="p-4 text-center">
                    <div class="text-gray-500 text-sm">
                        <svg class="size-6 mx-auto mb-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M12 14a3 3 0 100-6 3 3 0 000 6z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <p>No results found for "<span class="font-medium">{{ $searchQuery }}</span>"</p>
                        <p class="mt-1 text-xs text-gray-400">Try adjusting your search or filter to find what you're looking for.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
