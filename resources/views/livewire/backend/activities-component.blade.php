<div class="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50 dark:from-gray-900 dark:to-gray-800">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header Section -->
        <div class="mb-8">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between space-y-4 lg:space-y-0">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                        Activity Log
                    </h1>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                        Track user activities, system events, and important actions across the platform
                    </p>
                </div>
                
                <div class="flex flex-col sm:flex-row gap-3">
                    <button
                        wire:click="clearAllFilters"
                        class="inline-flex items-center justify-center px-4 py-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 font-medium rounded-xl shadow-sm hover:shadow-md transform hover:-translate-y-0.5 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2"
                    >
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        Clear Filters
                    </button>
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
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Today's Activities</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stats['today'] }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-100 dark:bg-green-900 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">This Week</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stats['this_week'] }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-purple-100 dark:bg-purple-900 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">This Month</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stats['this_month'] }}</p>
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
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Activities</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stats['total'] }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search and Filters -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 mb-8">
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-4">
                <!-- Search -->
                <div class="lg:col-span-2">
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </div>
                        <input 
                            type="text"
                            wire:model.live.debounce.300ms="search"
                            placeholder="Search activities, users, or descriptions..."
                            class="block w-full pl-12 pr-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200"
                        >
                    </div>
                </div>

                <!-- Activity Type Filter -->
                <div>
                    <select 
                        wire:model.live="activityType" 
                        class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200"
                    >
                        <option value="">All Activity Types</option>
                        @foreach($activityTypes as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Date Range Filter -->
                <div>
                    <select 
                        wire:model.live="dateRange" 
                        class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200"
                    >
                        <option value="today">Today</option>
                        <option value="yesterday">Yesterday</option>
                        <option value="last_7_days">Last 7 Days</option>
                        <option value="last_30_days">Last 30 Days</option>
                        <option value="this_month">This Month</option>
                        <option value="last_month">Last Month</option>
                        <option value="this_year">This Year</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mt-4">
                <!-- User Filter -->
                <div>
                    <select 
                        wire:model.live="userFilter" 
                        class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200"
                    >
                        <option value="">All Users</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Per Page -->
                <div>
                    <select 
                        wire:model.live="perPage" 
                        class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200"
                    >
                        <option value="15">15 per page</option>
                        <option value="25">25 per page</option>
                        <option value="50">50 per page</option>
                        <option value="100">100 per page</option>
                    </select>
                </div>

                <!-- Active Filters Display -->
                <div class="flex items-center space-x-2">
                    @if($search || $activityType || $userFilter || $dateRange !== 'today')
                        <span class="text-sm text-gray-500 dark:text-gray-400">Active filters:</span>
                        @if($search)
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                Search: "{{ $search }}"
                            </span>
                        @endif
                        @if($activityType)
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                {{ $activityTypes[$activityType] }}
                            </span>
                        @endif
                        @if($dateRange !== 'today')
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200">
                                {{ ucwords(str_replace('_', ' ', $dateRange)) }}
                            </span>
                        @endif
                    @endif
                </div>
            </div>
        </div>

        <!-- Activities Table -->
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
                            <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                                User
                            </th>
                            <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors duration-200" wire:click="sortBy('event')">
                                <div class="flex items-center space-x-2">
                                    <span>Activity Type</span>
                                    @if($sortField === 'event')
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
                                Description
                            </th>
                            <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                                Subject
                            </th>
                            <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors duration-200" wire:click="sortBy('created_at')">
                                <div class="flex items-center space-x-2">
                                    <span>Timestamp</span>
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
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($activities as $activity)
                            <tr class="hover:bg-gradient-to-r hover:from-blue-50 hover:to-indigo-50 dark:hover:from-gray-700 dark:hover:to-gray-600 transition-all duration-200 ease-in-out group">
                                <td class="px-6 py-6 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 bg-gradient-to-r from-blue-500 to-indigo-500 rounded-lg flex items-center justify-center text-white text-sm font-semibold">
                                            {{ $loop->iteration + ($activities->currentPage() - 1) * $activities->perPage() }}
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-6 whitespace-nowrap">
                                    @if($activity->user_name)
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10">
                                                @if($activity->profile_photo_path)
                                                    <img class="h-10 w-10 rounded-full" src="{{ asset('storage/' . $activity->profile_photo_path) }}" alt="{{ $activity->user_name }}">
                                                @else
                                                    <div class="h-10 w-10 bg-gradient-to-r from-blue-100 to-indigo-100 dark:from-blue-900 dark:to-indigo-900 rounded-full flex items-center justify-center">
                                                        <svg class="h-5 w-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                                        </svg>
                                                    </div>
                                                @endif
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-semibold text-gray-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors duration-200">
                                                    {{ $activity->user_name }}
                                                </div>
                                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                                    {{ $activity->user_email }}
                                                </div>
                                            </div>
                                        </div>
                                    @else
                                        <span class="text-sm text-gray-400 dark:text-gray-500 italic">System</span>
                                    @endif
                                </td>
                                <td class="px-6 py-6 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                                             @if(str_contains($activity->log_name, 'created')) bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                                     @elseif(str_contains($activity->log_name, 'updated')) bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200
                                     @elseif(str_contains($activity->log_name, 'deleted')) bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200
                                     @elseif(str_contains($activity->log_name, 'login')) bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200
                                     @elseif(str_contains($activity->log_name, 'logout')) bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200
                                        @else bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200
                                        @endif
                                    ">
                                        @switch($activity->log_name)
                                            @case('user_login')
                                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                                                </svg>
                                                @break
                                            @case('user_logout')
                                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                                </svg>
                                                @break
                                            @default
                                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                </svg>
                                        @endswitch
                                        {{ $activityTypes[$activity->log_name] ?? ucwords(str_replace('_', ' ', $activity->log_name)) }}
                                    </span>
                                </td>
                                <td class="px-6 py-6">
                                    <div class="text-sm text-gray-900 dark:text-white max-w-xs">
                                        {{ $activity->description ?? 'No description available' }}
                                    </div>
                                </td>
                                <td class="px-6 py-6 whitespace-nowrap">
                                    <div class="text-sm text-gray-900 dark:text-white">
                                        @if($activity->subject_type)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">
                                                {{ class_basename($activity->subject_type) }}
                                            </span>
                                        @else
                                            <span class="text-gray-400 dark:text-gray-500 italic">N/A</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-6 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    <div>{{ \Carbon\Carbon::parse($activity->created_at)->format('M j, Y') }}</div>
                                    <div class="text-xs text-gray-400 dark:text-gray-500">{{ \Carbon\Carbon::parse($activity->created_at)->format('H:i:s') }}</div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 whitespace-nowrap text-center">
                                    <div class="flex flex-col items-center justify-center">
                                        <div class="w-24 h-24 bg-gradient-to-br from-blue-100 to-indigo-100 dark:from-blue-900 dark:to-indigo-900 rounded-full flex items-center justify-center mb-6">
                                            <svg class="h-12 w-12 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                            </svg>
                                        </div>
                                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">No activities found</h3>
                                        <p class="text-gray-500 dark:text-gray-400 mb-6 max-w-md">
                                            {{ $search || $activityType || $userFilter || $dateRange !== 'today' ? 'No activities match your current filters. Try adjusting your search criteria.' : 'No activities have been recorded yet.' }}
                                        </p>
                                        @if($search || $activityType || $userFilter || $dateRange !== 'today')
                                            <button
                                                wire:click="clearAllFilters"
                                                class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-medium rounded-xl shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                                            >
                                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                                </svg>
                                                Clear All Filters
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
        @if($activities->hasPages())
            <div class="mt-8">
                {{ $activities->links() }}
            </div>
        @endif

        <!-- Analytics Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mt-8">
            <!-- Recent Activity Types -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Recent Activity Types</h3>
                <div class="space-y-3">
                    @forelse($recentActivityTypes as $activityType)
                        <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <div class="flex items-center">
                                <div class="w-3 h-3 bg-blue-500 rounded-full mr-3"></div>
                                                                 <span class="text-sm font-medium text-gray-900 dark:text-white">
                                     {{ $activityTypes[$activityType->log_name] ?? ucwords(str_replace('_', ' ', $activityType->log_name)) }}
                                 </span>
                            </div>
                            <span class="text-sm text-gray-500 dark:text-gray-400 font-medium">
                                {{ $activityType->count }}
                            </span>
                        </div>
                    @empty
                        <p class="text-gray-500 dark:text-gray-400 text-sm italic">No recent activity types</p>
                    @endforelse
                </div>
            </div>

            <!-- Most Active Users -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Most Active Users (30 days)</h3>
                <div class="space-y-3">
                    @forelse($mostActiveUsers as $user)
                        <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-gradient-to-r from-blue-100 to-indigo-100 dark:from-blue-900 dark:to-indigo-900 rounded-full flex items-center justify-center mr-3">
                                    <svg class="h-4 w-4 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $user->name }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $user->email }}</p>
                                </div>
                            </div>
                            <span class="text-sm text-gray-500 dark:text-gray-400 font-medium">
                                {{ $user->activity_count }} activities
                            </span>
                        </div>
                    @empty
                        <p class="text-gray-500 dark:text-gray-400 text-sm italic">No active users found</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>