<!-- Desktop Search -->
<div class=" lg:block relative">
    <!-- Search Container -->
    <div class="relative flex items-center">
        <div class="relative w-full">
            <!-- Search Input Container -->
            <div class="relative transition-all duration-300 ease-out rounded-lg bg-white shadow-lg border border-gray-200/80">
                <!-- Search Input Wrapper -->
                <div class="relative flex items-center w-full h-11">
                    <!-- Search Icon -->
                    <div class="absolute left-0 p-2.5 text-gray-400">
                        <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>

                    <!-- Search Input -->
                    <input type="text"
                           placeholder="Search for products, categories..."
                           x-model="searchQuery"
                           @input.debounce.300ms="search()"
                           class="w-full h-full pl-11 pr-4 bg-transparent text-sm placeholder-gray-400 outline-none focus:outline-none focus:ring-0 border-0 focus:border-0"
                    >

                    <!-- Clear Button -->
                    <button x-show="searchQuery"
                            @click="searchQuery = ''"
                            class="absolute right-3 p-1 text-gray-400 hover:text-gray-600 focus:outline-none">
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Search Results Dropdown -->
            <div x-show="searchQuery.length >= 2"
                 x-cloak
                 class="absolute mt-2 w-full bg-white/95 backdrop-blur-sm rounded-xl shadow-xl border border-gray-200/80 overflow-hidden z-50 search-results"
                 @click.away.stop>

                <!-- Loading State -->
                <div x-show="isLoading"
                     class="p-4">
                    <div class="flex items-center justify-center space-x-2 text-sm text-gray-500">
                        <svg class="animate-spin size-5 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span>Searching...</span>
                    </div>
                </div>

                <!-- Results -->
                <div x-show="!isLoading && searchResults.length > 0"
                     class="max-h-[400px] overflow-y-auto">
                    <template x-for="result in searchResults" :key="result.title">
                        <a :href="result.url"
                           class="flex items-center px-4 py-3 hover:bg-gray-50 transition-colors duration-150 border-b border-gray-100 last:border-0 group">
                            <!-- Result Icon -->
                            <div class="flex-shrink-0 size-10 rounded-lg bg-gray-100 flex items-center justify-center text-gray-500 group-hover:bg-blue-50 group-hover:text-blue-600 transition-colors duration-150">
                                <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </div>
                            <!-- Result Content -->
                            <div class="ml-4 flex-1">
                                <p class="text-sm font-medium text-gray-900 group-hover:text-blue-600" x-text="result.title"></p>
                                <p class="text-xs text-gray-500 mt-0.5" x-text="result.category || 'Product'"></p>
                            </div>
                        </a>
                    </template>
                </div>

                <!-- No Results -->
                <div x-show="!isLoading && searchResults.length === 0 && searchQuery.length >= 2"
                     class="p-4 text-center">
                    <div class="text-gray-500 text-sm">
                        <svg class="size-6 mx-auto mb-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M12 14a3 3 0 100-6 3 3 0 000 6z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <p>No results found for "<span class="font-medium" x-text="searchQuery"></span>"</p>
                        <p class="mt-1 text-xs text-gray-400">Try adjusting your search or filter to find what you're looking for.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
