<div class="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50 dark:from-gray-900 dark:to-gray-800">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <!-- Header Section -->
        <div class="mb-8">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between space-y-4 lg:space-y-0">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                        Shop Management
                    </h1>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                        Manage your shops, view performance metrics, and track sales activities
                    </p>
                </div>

                <div class="flex flex-col sm:flex-row gap-3">
                        @can('create', App\Models\Shop::class)
                            <button
                                wire:click="create"
                            class="inline-flex items-center justify-center px-6 py-3 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-medium rounded-xl shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                            >
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                            Add New Shop
                            </button>
                        @endcan
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-100 dark:bg-blue-900 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Shops</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $shops->total() }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-100 dark:bg-green-900 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Active Shops</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $shops->where('monthly_orders_count', '>', 0)->count() }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-purple-100 dark:bg-purple-900 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Salespeople</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $salespeople->count() }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-orange-100 dark:bg-orange-900 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Monthly Orders</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $shops->sum('monthly_orders_count') }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search and Filters -->
        <div class="bg-gradient-to-br from-white via-blue-50/30 to-indigo-50/30 dark:from-gray-800 dark:via-blue-900/20 dark:to-indigo-900/20 rounded-3xl shadow-xl border border-white/50 dark:border-gray-700/50 backdrop-blur-sm p-8 mb-8 relative overflow-hidden">
            <!-- Background Pattern -->
            <div class="absolute inset-0 bg-gradient-to-br from-blue-500/5 via-transparent to-indigo-500/5 dark:from-blue-400/10 dark:via-transparent dark:to-indigo-400/10"></div>
            <div class="absolute top-0 right-0 w-32 h-32 bg-gradient-to-br from-blue-400/10 to-indigo-400/10 rounded-full blur-3xl"></div>
            <div class="absolute bottom-0 left-0 w-24 h-24 bg-gradient-to-tr from-purple-400/10 to-pink-400/10 rounded-full blur-2xl"></div>

            <div class="relative z-10 space-y-8">
                <!-- Header -->
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center shadow-lg">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.207A1 1 0 013 6.5V4z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">Search & Filters</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Find and filter your shops</p>
                    </div>
                </div>

                <!-- Search Bar -->
                <div class="relative group">
                    <div class="absolute inset-y-0 left-0 pl-5 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400 group-focus-within:text-blue-500 transition-colors duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Search shops by name, phone, or address..."
                        class="block w-full pl-14 pr-4 py-4 bg-white/70 dark:bg-gray-800/70 backdrop-blur-sm border border-gray-200/50 dark:border-gray-600/50 rounded-2xl text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500/50 transition-all duration-300 hover:bg-white/90 dark:hover:bg-gray-800/90"
                    >
                </div>

                <!-- Filters Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Created At Filter -->
                    <div class="group">
                        <label for="createdAtFilter" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3 flex items-center">
                            <svg class="w-4 h-4 mr-2 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            Created At
                        </label>
                        <div class="relative">
                            <select
                                wire:model.live="createdAtFilter"
                                id="createdAtFilter"
                                class="w-full px-4 py-3 bg-white/70 dark:bg-gray-800/70 backdrop-blur-sm border border-gray-200/50 dark:border-gray-600/50 rounded-2xl text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500/50 transition-all duration-300 hover:bg-white/90 dark:hover:bg-gray-800/90 appearance-none cursor-pointer"
                            >
                                <option value="">All Time</option>
                                <option value="today">Today</option>
                                <option value="yesterday">Yesterday</option>
                                <option value="this_week">This Week</option>
                                <option value="last_week">Last Week</option>
                                <option value="this_month">This Month</option>
                                <option value="last_month">Last Month</option>
                                <option value="this_year">This Year</option>
                                <option value="last_year">Last Year</option>
                            </select>
                            <div class="absolute inset-y-0 right-0 flex items-center pr-4 pointer-events-none">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Salesperson Filter -->
                    <div class="group">
                        <label for="salespersonFilter" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3 flex items-center">
                            <svg class="w-4 h-4 mr-2 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                            Salesperson
                        </label>
                        <div class="relative">
                            <select
                                wire:model.live="salespersonFilter"
                                id="salespersonFilter"
                                class="w-full px-4 py-3 bg-white/70 dark:bg-gray-800/70 backdrop-blur-sm border border-gray-200/50 dark:border-gray-600/50 rounded-2xl text-gray-900 dark:text-white focus:ring-2 focus:ring-purple-500/50 focus:border-purple-500/50 transition-all duration-300 hover:bg-white/90 dark:hover:bg-gray-800/90 appearance-none cursor-pointer"
                            >
                                <option value="">All Salespeople</option>
                                <option value="unassigned">Unassigned</option>
                                @foreach($salespeople as $salesperson)
                                    <option value="{{ $salesperson->id }}">{{ $salesperson->name }}</option>
                                @endforeach
                            </select>
                            <div class="absolute inset-y-0 right-0 flex items-center pr-4 pointer-events-none">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Per Page Selector -->
                    <div class="group">
                        <label for="perPage" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3 flex items-center">
                            <svg class="w-4 h-4 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                            Per Page
                        </label>
                        <div class="relative">
                            <select
                                wire:model.live="perPage"
                                id="perPage"
                                class="w-full px-4 py-3 bg-white/70 dark:bg-gray-800/70 backdrop-blur-sm border border-gray-200/50 dark:border-gray-600/50 rounded-2xl text-gray-900 dark:text-white focus:ring-2 focus:ring-green-500/50 focus:border-green-500/50 transition-all duration-300 hover:bg-white/90 dark:hover:bg-gray-800/90 appearance-none cursor-pointer"
                            >
                                <option value="10">10 per page</option>
                                <option value="25">25 per page</option>
                                <option value="50">50 per page</option>
                                <option value="100">100 per page</option>
                            </select>
                            <div class="absolute inset-y-0 right-0 flex items-center pr-4 pointer-events-none">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Active Filters Indicator -->
        @if($search || $createdAtFilter || $salespersonFilter)
            <div class="mb-6 bg-gradient-to-r from-blue-50 via-indigo-50 to-purple-50 dark:from-blue-900/20 dark:via-indigo-900/20 dark:to-purple-900/20 border border-blue-200/50 dark:border-blue-700/50 rounded-2xl p-6 backdrop-blur-sm relative overflow-hidden">
                <!-- Background Pattern -->
                <div class="absolute inset-0 bg-gradient-to-r from-blue-500/5 via-indigo-500/5 to-purple-500/5"></div>
                <div class="absolute top-0 right-0 w-20 h-20 bg-gradient-to-br from-blue-400/10 to-indigo-400/10 rounded-full blur-xl"></div>

                <div class="relative z-10">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center space-x-3">
                            <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-lg flex items-center justify-center shadow-md">
                                <svg class="h-4 w-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.207A1 1 0 013 6.5V4z"/>
                                </svg>
                            </div>
                            <div>
                                <h4 class="text-sm font-bold text-gray-900 dark:text-white">Active Filters</h4>
                                <p class="text-xs text-gray-600 dark:text-gray-400">Applied filters are shown below</p>
                            </div>
                        </div>
                        <button
                            wire:click="clearAllFilters"
                            class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-red-500 to-pink-500 hover:from-red-600 hover:to-pink-600 text-white text-sm font-medium rounded-xl shadow-md hover:shadow-lg transform hover:-translate-y-0.5 transition-all duration-200"
                        >
                            <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                            Clear All
                        </button>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        @if($search)
                            <div class="inline-flex items-center px-4 py-2 bg-white/80 dark:bg-gray-800/80 backdrop-blur-sm border border-blue-200/50 dark:border-blue-600/50 rounded-xl shadow-sm">
                                <svg class="w-4 h-4 mr-2 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                                <span class="text-sm font-medium text-blue-800 dark:text-blue-200">Search: "{{ $search }}"</span>
                            </div>
                        @endif
                        @if($createdAtFilter)
                            <div class="inline-flex items-center px-4 py-2 bg-white/80 dark:bg-gray-800/80 backdrop-blur-sm border border-green-200/50 dark:border-green-600/50 rounded-xl shadow-sm">
                                <svg class="w-4 h-4 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                <span class="text-sm font-medium text-green-800 dark:text-green-200">Created: {{ ucfirst(str_replace('_', ' ', $createdAtFilter)) }}</span>
                            </div>
                        @endif
                        @if($salespersonFilter)
                            @if($salespersonFilter === 'unassigned')
                                <div class="inline-flex items-center px-4 py-2 bg-white/80 dark:bg-gray-800/80 backdrop-blur-sm border border-purple-200/50 dark:border-purple-600/50 rounded-xl shadow-sm">
                                    <svg class="w-4 h-4 mr-2 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                    </svg>
                                    <span class="text-sm font-medium text-purple-800 dark:text-purple-200">Salesperson: Unassigned</span>
                                </div>
                            @else
                                @php
                                    $selectedSalesperson = $salespeople->firstWhere('id', $salespersonFilter);
                                @endphp
                                <div class="inline-flex items-center px-4 py-2 bg-white/80 dark:bg-gray-800/80 backdrop-blur-sm border border-purple-200/50 dark:border-purple-600/50 rounded-xl shadow-sm">
                                    <svg class="w-4 h-4 mr-2 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                    </svg>
                                    <span class="text-sm font-medium text-purple-800 dark:text-purple-200">Salesperson: {{ $selectedSalesperson ? $selectedSalesperson->name : 'Unknown' }}</span>
                                </div>
                            @endif
                        @endif
                    </div>
                </div>
            </div>
        @endif

        <!-- Flash Messages -->
                @if (session()->has('success'))
            <div class="mb-6 p-4 rounded-xl bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800">
                <div class="flex items-center">
                    <svg class="h-5 w-5 text-green-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <p class="text-sm font-medium text-green-800 dark:text-green-200">{{ session('success') }}</p>
                </div>
                    </div>
                @endif

                @if (session()->has('error'))
            <div class="mb-6 p-4 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800">
                <div class="flex items-center">
                    <svg class="h-5 w-5 text-red-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <p class="text-sm font-medium text-red-800 dark:text-red-200">{{ session('error') }}</p>
                </div>
                    </div>
                @endif

                 <!-- Shops Table -->
         <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
             <div class="overflow-x-auto">
                 <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                     <thead class="bg-gradient-to-r from-gray-50 to-gray-100 dark:from-gray-700 dark:to-gray-800">
                         <tr>
                             <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                                 <div class="flex items-center">
                                     <span class="mr-2">#</span>
                                 </div>
                             </th>
                             <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors duration-200" wire:click="sortBy('name')">
                                 <div class="flex items-center space-x-2">
                                     <span>Shop Name</span>
                                     @if($sortField === 'name')
                                         <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                             <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" transform="{{ $sortDirection === 'asc' ? 'rotate(180 12 12)' : '' }}"/>
                                         </svg>
                                     @else
                                         <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                             <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/>
                                         </svg>
                                     @endif
                                 </div>
                             </th>
                             <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                                 Contact Info
                             </th>
                             <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                                 Location
                             </th>
                             <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                                 Social Links
                             </th>
                             <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                                 Salesperson
                             </th>
                             <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors duration-200" wire:click="sortBy('created_at')">
                                 <div class="flex items-center space-x-2">
                                     <span>Created At</span>
                                     @if($sortField === 'created_at')
                                         <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                             <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" transform="{{ $sortDirection === 'asc' ? 'rotate(180 12 12)' : '' }}"/>
                                         </svg>
                                     @else
                                         <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                             <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/>
                                         </svg>
                                     @endif
                                 </div>
                             </th>
                             <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors duration-200" wire:click="sortBy('monthly_orders_count')">
                                 <div class="flex items-center space-x-2">
                                     <span>Monthly Orders</span>
                                     @if($sortField === 'monthly_orders_count')
                                         <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                             <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" transform="{{ $sortDirection === 'asc' ? 'rotate(180 12 12)' : '' }}"/>
                                         </svg>
                                     @else
                                         <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                             <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/>
                                         </svg>
                                     @endif
                                 </div>
                             </th>
                             <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                                 Actions
                             </th>
                         </tr>
                     </thead>
                                 <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                     @forelse($shops as $shop)
                         <tr class="hover:bg-gradient-to-r hover:from-blue-50 hover:to-indigo-50 dark:hover:from-gray-700 dark:hover:to-gray-600 transition-all duration-200 ease-in-out group">
                             <td class="px-6 py-6 whitespace-nowrap">
                                 <div class="flex items-center">
                                     <div class="w-8 h-8 bg-gradient-to-r from-blue-500 to-indigo-500 rounded-lg flex items-center justify-center text-white text-sm font-semibold">
                                         {{ $loop->iteration + ($shops->currentPage() - 1) * $shops->perPage() }}
                                     </div>
                                 </div>
                             </td>
                             <td class="px-6 py-6 whitespace-nowrap">
                                 <div class="flex items-center">
                                     <div class="flex-shrink-0 h-12 w-12">
                                         <div class="h-12 w-12 bg-gradient-to-r from-blue-100 to-indigo-100 dark:from-blue-900 dark:to-indigo-900 rounded-xl flex items-center justify-center">
                                             <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                             </svg>
                                         </div>
                                     </div>
                                     <div class="ml-4">
                                         <div class="text-sm font-semibold text-gray-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors duration-200">
                                             {{ $shop->name }}
                                         </div>
                                         <div class="text-sm text-gray-500 dark:text-gray-400">
                                             Owner:
                                             @if($shop->owner)
                                                 <a
                                                     href="{{ route('subdomain.users') }}?search={{ $shop->owner->name }}"
                                                     class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 font-medium hover:underline transition-colors duration-200"
                                                     title="View {{ $shop->owner->name }}'s profile"
                                                 >
                                                     {{ $shop->owner->name }}
                                                 </a>
                                             @else
                                                 <span class="text-gray-400 dark:text-gray-500 italic">N/A</span>
                                             @endif
                                         </div>
                                     </div>
                                 </div>
                             </td>
                             <td class="px-6 py-6 whitespace-nowrap">
                                 <div class="flex items-center">
                                     <div class="flex-shrink-0 h-8 w-8 mr-3">
                                         <div class="h-8 w-8 bg-green-100 dark:bg-green-900 rounded-lg flex items-center justify-center">
                                             <svg class="h-4 w-4 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                                             </svg>
                                         </div>
                                     </div>
                                     <div class="text-sm text-gray-900 dark:text-white">
                                         @if($shop->phone)
                                             <a href="tel:{{ $shop->phone }}" class="hover:text-blue-600 dark:hover:text-blue-400 transition-colors duration-200">
                                                 {{ $shop->phone }}
                                             </a>
                                         @else
                                             <span class="text-gray-400 dark:text-gray-500 italic">No phone number</span>
                                         @endif
                                     </div>
                                 </div>
                             </td>
                             <td class="px-6 py-6 whitespace-nowrap">
                                 <div x-data="{ expanded: false }" class="text-sm text-gray-900 dark:text-white max-w-[250px]">
                                     @if($shop->address)
                                         <div class="flex items-start">
                                             <div class="flex-shrink-0 h-8 w-8 mr-3 mt-0.5">
                                                 <div class="h-8 w-8 bg-purple-100 dark:bg-purple-900 rounded-lg flex items-center justify-center">
                                                     <svg class="h-4 w-4 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                         <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                                         <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                     </svg>
                                                 </div>
                                             </div>
                                             <div class="flex-1">
                                                 <div
                                                     @click="expanded = !expanded"
                                                     class="cursor-pointer hover:text-blue-600 dark:hover:text-blue-400 transition-colors duration-200"
                                                 >
                                                     <span
                                                         x-show="!expanded"
                                                         class="truncate block max-w-[200px]"
                                                         style="display: block;"
                                                     >
                                                         {{ $shop->address }}
                                                     </span>
                                                     <span
                                                         x-show="expanded"
                                                         class="block whitespace-normal break-words max-w-[200px]"
                                                         style="display: none;"
                                                     >
                                                         {{ $shop->address }}
                                                     </span>
                                                 </div>
                                                 <div x-show="!expanded" class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                     Click to expand
                                                 </div>
                                             </div>
                                         </div>
                                     @else
                                         <div class="flex items-center">
                                             <div class="flex-shrink-0 h-8 w-8 mr-3">
                                                 <div class="h-8 w-8 bg-gray-100 dark:bg-gray-700 rounded-lg flex items-center justify-center">
                                                     <svg class="h-4 w-4 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                         <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636m12.728 12.728L18.364 5.636M5.636 18.364l12.728-12.728"/>
                                                     </svg>
                                                 </div>
                                             </div>
                                             <span class="text-gray-400 dark:text-gray-500 italic">No address</span>
                                         </div>
                                     @endif
                                 </div>
                             </td>
                                                         <td class="px-6 py-6 whitespace-nowrap">
                                 <div x-data="{ showAll: false }" class="text-sm">
                                     @if(count($shop->links ?? []) > 0)
                                         <div class="flex flex-wrap gap-2">
                                             @foreach($shop->links as $title => $value)
                                                 <div x-show="showAll || {{ $loop->index }} < 3">
                                                     <a href="{{ $value }}"
                                                        target="_blank"
                                                        class="inline-flex items-center px-3 py-2 rounded-lg text-xs font-medium transition-all duration-200 hover:scale-105 hover:shadow-md"
                                                        :class="{
                                                            'bg-blue-100 text-blue-700 hover:bg-blue-200 dark:bg-blue-900 dark:text-blue-200 dark:hover:bg-blue-800': '{{ strtolower($title) }}' === 'facebook',
                                                            'bg-pink-100 text-pink-700 hover:bg-pink-200 dark:bg-pink-900 dark:text-pink-200 dark:hover:bg-pink-800': '{{ strtolower($title) }}' === 'instagram',
                                                            'bg-sky-100 text-sky-700 hover:bg-sky-200 dark:bg-sky-900 dark:text-sky-200 dark:hover:bg-sky-800': '{{ strtolower($title) }}' === 'twitter',
                                                            'bg-green-100 text-green-700 hover:bg-green-200 dark:bg-green-900 dark:text-green-200 dark:hover:bg-green-800': '{{ strtolower($title) }}' === 'website',
                                                            'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600': !['facebook', 'instagram', 'twitter', 'website'].includes('{{ strtolower($title) }}')
                                                        }"
                                                     >
                                                         @switch(strtolower($title))
                                                             @case('facebook')
                                                                 <svg class="h-4 w-4 mr-2" fill="currentColor" viewBox="0 0 24 24">
                                                                     <path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"/>
                                                                 </svg>
                                                                 @break
                                                             @case('instagram')
                                                                 <svg class="h-4 w-4 mr-2" fill="currentColor" viewBox="0 0 24 24">
                                                                     <path d="M12 2c2.717 0 3.056.01 4.122.06 1.065.05 1.79.217 2.428.465.66.254 1.216.598 1.772 1.153.509.5.902 1.105 1.153 1.772.247.637.415 1.363.465 2.428.047 1.066.06 1.405.06 4.122 0 2.717-.01 3.056-.06 4.122-.05 1.065-.218 1.79-.465 2.428a4.883 4.883 0 01-1.153 1.772c-.5.509-1.105.902-1.772 1.153-.637.247-1.363.415-2.428.465-1.066.047-1.405.06-4.122.06-2.717 0-3.056-.01-4.122-.06-1.065-.05-1.79-.218-2.428-.465a4.89 4.89 0 01-1.772-1.153 4.904 4.904 0 01-1.153-1.772c-.248-.637-.415-1.363-.465-2.428C2.013 15.056 2 14.717 2 12c0-2.717.01-3.056.06-4.122.05-1.066.217-1.79.465-2.428a4.88 4.88 0 011.153-1.772A4.897 4.897 0 015.45 2.525c.638-.248 1.362-.415 2.428-.465C8.944 2.013 9.283 2 12 2zm0 5a5 5 0 100 10 5 5 0 000-10zm6.5-.25a1.25 1.25 0 10-2.5 0 1.25 1.25 0 002.5 0zM12 9a3 3 0 110 6 3 3 0 010-6z"/>
                                                                 </svg>
                                                                 @break
                                                             @case('twitter')
                                                                 <svg class="h-4 w-4 mr-2" fill="currentColor" viewBox="0 0 24 24">
                                                                     <path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/>
                                                                 </svg>
                                                                 @break
                                                             @case('website')
                                                                 <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                     <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/>
                                                                 </svg>
                                                                 @break
                                                             @default
                                                                 <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                     <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                                                                 </svg>
                                                         @endswitch
                                                         {{ ucfirst(str_replace('_', ' ', $title)) }}
                                                     </a>
                                                 </div>
                                             @endforeach
                                         </div>

                                                                                 @if(count($shop->links) > 3)
                                             <button
                                                 x-show="!showAll"
                                                 @click="showAll = true"
                                                 class="mt-2 text-xs text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 transition-colors duration-200 font-medium"
                                             >
                                                 +{{ count($shop->links) - 3 }} more
                                             </button>
                                             <button
                                                 x-show="showAll"
                                                 @click="showAll = false"
                                                 class="mt-2 text-xs text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 transition-colors duration-200 font-medium"
                                             >
                                                 Show less
                                             </button>
                                         @endif
                                     @else
                                         <div class="flex items-center">
                                             <div class="flex-shrink-0 h-8 w-8 mr-3">
                                                 <div class="h-8 w-8 bg-gray-100 dark:bg-gray-700 rounded-lg flex items-center justify-center">
                                                     <svg class="h-4 w-4 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                         <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636m12.728 12.728L18.364 5.636M5.636 18.364l12.728-12.728"/>
                                                     </svg>
                                                 </div>
                                             </div>
                                             <span class="text-gray-400 dark:text-gray-500 italic">No links</span>
                                         </div>
                                     @endif
                                 </div>
                             </td>
                             <td class="px-6 py-6 whitespace-nowrap">
                                 <div class="text-sm text-gray-900 dark:text-white">
                                     @if($shop->salesperson && $shop->salesperson->id !== 0)
                                         <div class="flex items-center">
                                             <div class="flex-shrink-0 h-10 w-10">
                                                 <img class="h-10 w-10 rounded-full ring-2 ring-green-200 dark:ring-green-800" src="{{ $shop->salesperson->profile_photo_url }}" alt="{{ $shop->salesperson->name }}">
                                             </div>
                                             <div class="ml-3">
                                                 <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                                     {{ $shop->salesperson->name }}
                                                 </p>
                                                 <p class="text-xs text-green-600 dark:text-green-400 font-medium">
                                                     Assigned
                                                 </p>
                                             </div>
                                         </div>
                                     @else
                                         <div class="flex items-center">
                                             <div class="flex-shrink-0 h-10 w-10 mr-3">
                                                 <div class="h-10 w-10 bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center">
                                                     <svg class="h-5 w-5 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                         <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                                     </svg>
                                                 </div>
                                             </div>
                                             <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                                 Unassigned
                                             </span>
                                         </div>
                                     @endif
                                 </div>
                             </td>
                             <td class="px-6 py-6 whitespace-nowrap">
                                 <div class="flex items-center">
                                     <div class="flex-shrink-0 h-8 w-8 mr-3">
                                         <div class="h-8 w-8 bg-indigo-100 dark:bg-indigo-900 rounded-lg flex items-center justify-center">
                                             <svg class="h-4 w-4 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                             </svg>
                                         </div>
                                     </div>
                                     <div class="text-sm text-gray-900 dark:text-white">
                                         <div class="font-medium">{{ $shop->created_at->format('M d, Y') }}</div>
                                         <div class="text-gray-500 dark:text-gray-400">{{ $shop->created_at->format('g:i A') }}</div>
                                     </div>
                                 </div>
                             </td>
                             <td class="px-6 py-6 whitespace-nowrap">
                                 @if($shop->monthly_orders_count > 0)
                                     <div class="flex items-center">
                                         <div class="flex-shrink-0 h-8 w-8 mr-3">
                                             <div class="h-8 w-8 bg-green-100 dark:bg-green-900 rounded-lg flex items-center justify-center">
                                                 <svg class="h-4 w-4 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                     <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                 </svg>
                                             </div>
                                         </div>
                                         <span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                             {{ $shop->monthly_orders_count }} orders
                                         </span>
                                     </div>
                                 @else
                                     <div class="flex items-center">
                                         <div class="flex-shrink-0 h-8 w-8 mr-3">
                                             <div class="h-8 w-8 bg-gray-100 dark:bg-gray-700 rounded-lg flex items-center justify-center">
                                                 <svg class="h-4 w-4 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                     <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636m12.728 12.728L18.364 5.636M5.636 18.364l12.728-12.728"/>
                                                 </svg>
                                             </div>
                                         </div>
                                         <span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                             No orders
                                         </span>
                                     </div>
                                 @endif
                             </td>
                             <td class="px-6 py-6 whitespace-nowrap text-sm font-medium">
                                 <div class="flex items-center space-x-2">
                                     <a
                                         href="{{ route('subdomain.shop', $shop) }}"
                                         class="inline-flex items-center px-3 py-2 border border-transparent text-sm font-medium rounded-lg text-blue-700 bg-blue-100 hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-blue-900 dark:text-blue-200 dark:hover:bg-blue-800 transition-all duration-200 hover:scale-105"
                                         title="View Shop"
                                     >
                                         <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                             <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                             <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                         </svg>
                                         View
                                     </a>
                                     <button
                                         type="button"
                                         wire:click="edit({{ $shop->id }})"
                                         class="inline-flex items-center px-3 py-2 border border-transparent text-sm font-medium rounded-lg text-indigo-700 bg-indigo-100 hover:bg-indigo-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:bg-indigo-900 dark:text-indigo-200 dark:hover:bg-indigo-800 transition-all duration-200 hover:scale-105"
                                         title="Edit Shop"
                                     >
                                         <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                             <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                         </svg>
                                         Edit
                                     </button>
                                     <button
                                         type="button"
                                         wire:click="delete({{ $shop->id }})"
                                         wire:confirm="Are you sure you want to delete this shop?"
                                         class="inline-flex items-center px-3 py-2 border border-transparent text-sm font-medium rounded-lg text-red-700 bg-red-100 hover:bg-red-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 dark:bg-red-900 dark:text-red-200 dark:hover:bg-red-800 transition-all duration-200 hover:scale-105"
                                         title="Delete Shop"
                                     >
                                         <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                             <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                         </svg>
                                         Delete
                                     </button>
                                 </div>
                             </td>
                        </tr>
                                         @empty
                         <tr>
                             <td colspan="9" class="px-6 py-12 whitespace-nowrap text-center">
                                 <div class="flex flex-col items-center justify-center">
                                     <div class="w-24 h-24 bg-gradient-to-br from-blue-100 to-indigo-100 dark:from-blue-900 dark:to-indigo-900 rounded-full flex items-center justify-center mb-6">
                                         <svg class="h-12 w-12 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                             <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                         </svg>
                                     </div>
                                     <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">No shops found</h3>
                                     <p class="text-gray-500 dark:text-gray-400 mb-6 max-w-md">
                                         {{ $search ? 'No shops match your search criteria. Try adjusting your search terms.' : 'Get started by creating your first shop to manage your business operations.' }}
                                     </p>
                                     @if(!$search && auth()->user()->can('create', App\Models\Shop::class))
                                         <button
                                             wire:click="create"
                                             class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-medium rounded-xl shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                                         >
                                             <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                             </svg>
                                             Create Your First Shop
                                         </button>
                                     @endif
                                 </div>
                             </td>
                         </tr>
                     @endforelse
                 </tbody>
             </table>
         </div>
     </div>

                 <!-- Pagination -->
         <div class="mt-8">
             {{ $shops->links() }}
         </div>
     </div>

                <!-- Create Shop Modal -->
     <x-modal wire:model="showCreateModal" maxWidth="2xl">
         <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl">
             <div class="px-8 py-6 border-b border-gray-200 dark:border-gray-700">
                 <div class="flex items-center justify-between">
                     <h3 class="text-xl font-semibold text-gray-900 dark:text-white">
                            Create New Shop
                     </h3>
                     <button
                         wire:click="$set('showCreateModal', false)"
                         class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors duration-200"
                     >
                         <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                             <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                         </svg>
                     </button>
                 </div>
             </div>

             <form wire:submit="save" class="p-8 space-y-6">
                 <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                         <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                             Shop Name
                         </label>
                         <input
                             wire:model="name"
                             type="text"
                             id="name"
                             class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200"
                             placeholder="Enter shop name"
                             required
                         >
                         @error('name')
                             <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                         @enderror
                            </div>

                            <div>
                         <label for="phone" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                             Phone Number
                         </label>
                         <input
                             wire:model="phone"
                             type="text"
                             id="phone"
                             class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200"
                             placeholder="Enter phone number"
                             required
                         >
                         @error('phone')
                             <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                         @enderror
                     </div>
                            </div>

                            <div>
                     <label for="address" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                         Address
                     </label>
                     <textarea
                         wire:model="address"
                         id="address"
                         rows="3"
                         class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200"
                         placeholder="Enter shop address"
                         required
                     ></textarea>
                     @error('address')
                         <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                     @enderror
                            </div>

                            <div>
                     <div class="flex items-center justify-between mb-4">
                         <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                             Social Links
                         </label>
                                    <button
                                        type="button"
                                        wire:click="addLink"
                             class="inline-flex items-center px-3 py-2 border border-transparent text-sm font-medium rounded-lg text-blue-700 bg-blue-100 hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-blue-900 dark:text-blue-200 dark:hover:bg-blue-800 transition duration-200"
                                    >
                             <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                        </svg>
                                        Add Link
                                    </button>
                                </div>

                                <!-- New Link Form -->
                     <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                             <input
                                            wire:model="newLink.type"
                                            type="text"
                                            placeholder="Link Type (e.g., Facebook, Instagram)"
                                 class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200"
                             >
                             @error('newLink.type')
                                 <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                             @enderror
                                    </div>
                                    <div>
                             <input
                                            wire:model="newLink.url"
                                            type="url"
                                            placeholder="URL"
                                 class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200"
                             >
                             @error('newLink.url')
                                 <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                             @enderror
                                    </div>
                                </div>

                                <!-- Added Links -->
                                @if(count($links) > 0)
                         <div class="space-y-3">
                                        @foreach($links as $type => $url)
                                 <div class="flex items-center space-x-3 p-4 bg-gray-50 dark:bg-gray-700 rounded-xl">
                                     <div class="flex-1 grid grid-cols-1 md:grid-cols-2 gap-4">
                                                    <div>
                                             <input
                                                            type="text"
                                                            value="{{ ucwords(str_replace('_', ' ', $type)) }}"
                                                 class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-100 dark:bg-gray-600 text-gray-900 dark:text-white"
                                                            readonly
                                             >
                                                    </div>
                                                    <div>
                                             <input
                                                            type="url"
                                                            value="{{ $url }}"
                                                 class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-100 dark:bg-gray-600 text-gray-900 dark:text-white"
                                                            readonly
                                             >
                                                    </div>
                                                </div>
                                                <button
                                                    type="button"
                                                    wire:click="removeLink('{{ $type }}')"
                                         class="inline-flex items-center p-2 border border-transparent text-sm font-medium rounded-lg text-red-700 bg-red-100 hover:bg-red-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 dark:bg-red-900 dark:text-red-200 dark:hover:bg-red-800 transition duration-200"
                                                >
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                    </svg>
                                                </button>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            <div>
                     <label for="salesperson_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                         Assign Salesperson
                     </label>
                     <select
                         wire:model="salesperson_id"
                         id="salesperson_id"
                         class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200"
                     >
                                    <option value="">Select a salesperson</option>
                                    @foreach($salespeople as $salesperson)
                                        <option value="{{ $salesperson->id }}">{{ $salesperson->name }}</option>
                                    @endforeach
                     </select>
                     @error('salesperson_id')
                         <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                     @enderror
                            </div>

                 <div class="flex justify-end space-x-4 pt-6 border-t border-gray-200 dark:border-gray-700">
                     <button
                         type="button"
                         wire:click="$set('showCreateModal', false)"
                         class="px-6 py-3 border border-gray-300 dark:border-gray-600 rounded-xl text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-200"
                     >
                                    Cancel
                     </button>
                     <button
                         type="submit"
                         class="px-6 py-3 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-medium rounded-xl shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                     >
                                    Create Shop
                     </button>
                            </div>
                        </form>
                    </div>
                </x-modal>

                <!-- Edit Shop Modal -->
     <x-modal wire:model="showEditModal" maxWidth="2xl">
         <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl">
             <div class="px-8 py-6 border-b border-gray-200 dark:border-gray-700">
                 <div class="flex items-center justify-between">
                     <h3 class="text-xl font-semibold text-gray-900 dark:text-white">
                            Edit Shop
                     </h3>
                     <button
                         wire:click="$set('showEditModal', false)"
                         class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors duration-200"
                     >
                         <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                             <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                         </svg>
                     </button>
                 </div>
             </div>

             <form wire:submit="update" class="p-8 space-y-6">
                 <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                         <label for="edit_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                             Shop Name
                         </label>
                         <input
                             wire:model="name"
                             type="text"
                             id="edit_name"
                             class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200"
                             placeholder="Enter shop name"
                             required
                         >
                         @error('name')
                             <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                         @enderror
                            </div>

                            <div>
                         <label for="edit_phone" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                             Phone Number
                         </label>
                         <input
                             wire:model="phone"
                             type="text"
                             id="edit_phone"
                             class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200"
                             placeholder="Enter phone number"
                             required
                         >
                         @error('phone')
                             <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                         @enderror
                     </div>
                            </div>

                            <div>
                     <label for="edit_address" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                         Address
                     </label>
                     <textarea
                         wire:model="address"
                         id="edit_address"
                         rows="3"
                         class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200"
                         placeholder="Enter shop address"
                         required
                     ></textarea>
                     @error('address')
                         <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                     @enderror
                            </div>

                            <div>
                     <div class="flex items-center justify-between mb-4">
                         <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                             Social Links
                         </label>
                                    <button
                                        type="button"
                                        wire:click="addLink"
                             class="inline-flex items-center px-3 py-2 border border-transparent text-sm font-medium rounded-lg text-blue-700 bg-blue-100 hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-blue-900 dark:text-blue-200 dark:hover:bg-blue-800 transition duration-200"
                                    >
                             <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                        </svg>
                                        Add Link
                                    </button>
                                </div>

                                <!-- New Link Form -->
                     <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                             <input
                                            wire:model="newLink.type"
                                            type="text"
                                            placeholder="Link Type (e.g., Facebook, Instagram)"
                                 class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200"
                             >
                             @error('newLink.type')
                                 <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                             @enderror
                                    </div>
                                    <div>
                             <input
                                            wire:model="newLink.url"
                                            type="url"
                                            placeholder="URL"
                                 class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200"
                             >
                             @error('newLink.url')
                                 <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                             @enderror
                                    </div>
                                </div>

                                <!-- Added Links -->
                                @if(count($links) > 0)
                         <div class="space-y-3">
                                        @foreach($links as $type => $url)
                                 <div class="flex items-center space-x-3 p-4 bg-gray-50 dark:bg-gray-700 rounded-xl">
                                     <div class="flex-1 grid grid-cols-1 md:grid-cols-2 gap-4">
                                                    <div>
                                             <input
                                                            type="text"
                                                            value="{{ ucwords(str_replace('_', ' ', $type)) }}"
                                                 class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-100 dark:bg-gray-600 text-gray-900 dark:text-white"
                                                            readonly
                                             >
                                                    </div>
                                                    <div>
                                             <input
                                                            type="url"
                                                            value="{{ $url }}"
                                                 class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-100 dark:bg-gray-600 text-gray-900 dark:text-white"
                                                            readonly
                                             >
                                                    </div>
                                                </div>
                                                <button
                                                    type="button"
                                                    wire:click="removeLink('{{ $type }}')"
                                         class="inline-flex items-center p-2 border border-transparent text-sm font-medium rounded-lg text-red-700 bg-red-100 hover:bg-red-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 dark:bg-red-900 dark:text-red-200 dark:hover:bg-red-800 transition duration-200"
                                                >
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                    </svg>
                                                </button>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            <div>
                     <label for="edit_salesperson_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                         Assign Salesperson
                     </label>
                     <select
                         wire:model="salesperson_id"
                         id="edit_salesperson_id"
                         class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200"
                     >
                                    <option value="">Select a salesperson</option>
                                    @foreach($salespeople as $salesperson)
                                        <option value="{{ $salesperson->id }}">{{ $salesperson->name }}</option>
                                    @endforeach
                     </select>
                     @error('salesperson_id')
                         <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                     @enderror
                            </div>

                 <div class="flex justify-end space-x-4 pt-6 border-t border-gray-200 dark:border-gray-700">
                     <button
                         type="button"
                         wire:click="$set('showEditModal', false)"
                         class="px-6 py-3 border border-gray-300 dark:border-gray-600 rounded-xl text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-200"
                     >
                                    Cancel
                     </button>
                     <button
                         type="submit"
                         class="px-6 py-3 bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 text-white font-medium rounded-xl shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2"
                     >
                                    Update Shop
                     </button>
                            </div>
                        </form>
                    </div>
                </x-modal>
</div>
