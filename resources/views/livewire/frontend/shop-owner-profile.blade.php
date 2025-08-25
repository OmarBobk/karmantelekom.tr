<div>

    <div class="min-h-screen bg-gray-50">
        <!-- Header Section -->
        <div class="bg-gradient-to-r from-blue-600 to-indigo-600 shadow-lg">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                    <div class="flex-1">
                        <h1 class="text-3xl font-bold text-white mb-2">{{ $shop->name }}</h1>
                        <div class="flex sm:items-center items-baseline sm:flex-row flex-col sm:space-x-6 space-x-0 sm:space-y-0 space-y-2 text-blue-100">
                            <div class="flex items-center space-x-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                                <button
                                    onclick="toggleAddress()"
                                    class="text-left hover:underline focus:outline-none focus:underline"
                                    title="Click to view full address"
                                >
                                     <span id="address-display" class="block truncate max-w-xs sm:max-w-md lg:max-w-lg">
                                         {{ $shop->primaryAddress ? $shop->primaryAddress->full_address : 'No address available' }}
                                     </span>
                                    <span id="address-full" class="hidden block max-w-xs sm:max-w-md lg:max-w-lg">
                                         {{ $shop->primaryAddress ? $shop->primaryAddress->full_address : 'No address available' }}
                                     </span>
                                </button>
                            </div>
                            @if($shop->phone)
                                <span class="flex items-center space-x-2">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                    </svg>
                                    <span>{{ $shop->phone }}</span>
                                </span>
                            @endif
                        </div>
                    </div>
                    <div class="mt-4 md:mt-0">
                        <div class="bg-white/20 backdrop-blur-sm rounded-xl px-6 py-4">
                            <div class="text-center">
                                <div
                                    class="text-2xl font-bold text-white">{{ number_format($metrics['total_orders']) }}</div>
                                <div class="text-blue-100 text-sm">Total Orders</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="flex flex-col lg:flex-row gap-8">
                <!-- Sidebar Navigation -->
                <div class="lg:w-64 flex-shrink-0">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Profile Sections</h3>
                        <nav class="space-y-2">
                            @php
                                $tabs = [
                                    'basic' => [
                                        'label' => 'Basic Information',
                                        'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>'
                                    ],

                                    'orders' => [
                                        'label' => 'Order History',
                                        'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>'
                                    ],
                                    'products' => [
                                        'label' => 'Products & Preferences',
                                        'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>'
                                    ],
                                        'addresses' => [
                                            'label' => 'Addresses',
                                            'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>'
                                    ],
                                    'notifications' => [
                                        'label' => 'Notifications',
                                        'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-5 5v-5zM4.19 4.19A2 2 0 004 6v10a2 2 0 002 2h10a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-1.81 1.19zM4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>'
                                    ]
                                ];
                            @endphp

                            @foreach($tabs as $tabKey => $tab)
                                <button
                                    wire:click="setActiveTab('{{ $tabKey }}')"
                                    class="w-full flex items-center space-x-3 px-4 py-3 rounded-xl text-left transition-all duration-200 {{ $activeTab === $tabKey ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}"
                                >
                                    <div
                                        class="flex-shrink-0 {!! $activeTab === $tabKey ? 'text-blue-600' : 'text-gray-400' !!}">
                                        {!! $tab['icon'] !!}
                                    </div>
                                    <span class="font-medium">{{ $tab['label'] }}</span>
                                </button>
                            @endforeach
                        </nav>
                    </div>
                </div>

                <!-- Main Content Area -->
                <div class="flex-1">
                    <!-- Metrics Cards -->
                    @if($activeTab === 'basic')
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-6 sm:mb-8">
                            <div class="flex bg-white rounded-2xl shadow-sm border border-gray-200 p-4 sm:p-6">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <div
                                            class="w-10 h-10 sm:w-12 sm:h-12 bg-green-100 rounded-xl flex items-center justify-center">
                                            <svg class="w-5 h-5 sm:w-6 sm:h-6 text-green-600" fill="none"
                                                 stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                                            </svg>
                                        </div>
                                    </div>
                                    <div class="ml-3 sm:ml-4">
                                        <p class="text-xs sm:text-sm font-medium text-gray-600">Total Revenue</p>
                                        <p class="text-sm sm:text-lg font-bold text-gray-900">{{ number_format($metrics['total_revenue'], 2) }}
                                            ₺</p>
                                    </div>
                                </div>
                            </div>

                            <div class="flex bg-white rounded-2xl shadow-sm border border-gray-200 p-4 sm:p-6">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <div
                                            class="w-10 h-10 sm:w-12 sm:h-12 bg-blue-100 rounded-xl flex items-center justify-center">
                                            <svg class="w-5 h-5 sm:w-6 sm:h-6 text-blue-600" fill="none"
                                                 stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                            </svg>
                                        </div>
                                    </div>
                                    <div class="ml-3 sm:ml-4">
                                        <p class="text-xs sm:text-sm font-medium text-gray-600">Pending Orders</p>
                                        <p class="text-lg sm:text-2xl font-bold text-gray-900">{{ $metrics['pending_orders'] }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="flex bg-white rounded-2xl shadow-sm border border-gray-200 p-4 sm:p-6">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <div
                                            class="w-10 h-10 sm:w-12 sm:h-12 bg-purple-100 rounded-xl flex items-center justify-center">
                                            <svg class="w-5 h-5 sm:w-6 sm:h-6 text-purple-600" fill="none"
                                                 stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                            </svg>
                                        </div>
                                    </div>
                                    <div class="ml-3 sm:ml-4">
                                        <p class="text-xs sm:text-sm font-medium text-gray-600">Total Products</p>
                                        <p class="text-lg sm:text-2xl font-bold text-gray-900">{{ $metrics['total_products'] }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="flex bg-white rounded-2xl shadow-sm border border-gray-200 p-4 sm:p-6">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <div
                                            class="w-10 h-10 sm:w-12 sm:h-12 bg-orange-100 rounded-xl flex items-center justify-center">
                                            <svg class="w-5 h-5 sm:w-6 sm:h-6 text-orange-600" fill="none"
                                                 stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                                            </svg>
                                        </div>
                                    </div>
                                    <div class="ml-3 sm:ml-4">
                                        <p class="text-xs sm:text-sm font-medium text-gray-600">Avg Order</p>
                                        <p class="text-sm sm:text-lg font-bold text-gray-900">{{ number_format($metrics['avg_order_value'], 2) }}
                                            ₺</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Basic Information Card -->
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-4 sm:p-6 lg:p-8">
                            <div
                                class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-4 sm:mb-6">
                                <h2 class="text-xl sm:text-2xl font-bold text-gray-900">Basic Information</h2>
                                <div class="flex flex-col sm:flex-row gap-2 sm:gap-3">
                                    <button
                                        wire:click="openPasswordModal"
                                        class="inline-flex items-center justify-center px-3 sm:px-4 py-2 bg-gray-600 text-white text-xs sm:text-sm font-medium rounded-xl hover:bg-gray-700 transition-colors duration-200 w-full sm:w-auto"
                                    >
                                        <svg class="w-3 h-3 sm:w-4 sm:h-4 mr-1 sm:mr-2" fill="none"
                                             stroke="currentColor"
                                             viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
                                        </svg>
                                        Change Password
                                    </button>
                                    <button
                                        wire:click="openEditModal"
                                        class="inline-flex items-center justify-center px-3 sm:px-4 py-2 bg-blue-600 text-white text-xs sm:text-sm font-medium rounded-xl hover:bg-blue-700 transition-colors duration-200 w-full sm:w-auto"
                                    >
                                        <svg class="w-3 h-3 sm:w-4 sm:h-4 mr-1 sm:mr-2" fill="none"
                                             stroke="currentColor"
                                             viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                        Edit Information
                                    </button>

                                </div>
                            </div>

                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6">
                                <!-- Left Column -->
                                <div class="space-y-4 sm:space-y-6">
                                    <div>
                                        <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-2">Owner
                                            Email</label>
                                        <div
                                            class="bg-gray-50 rounded-xl px-3 sm:px-4 py-2 sm:py-3 text-gray-900 flex items-center">
                                            <svg class="w-3 h-3 sm:w-4 sm:h-4 text-gray-400 mr-2 flex-shrink-0"
                                                 fill="none"
                                                 stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                            </svg>
                                            <span
                                                class="text-xs sm:text-sm text-gray-600 break-all">{{ Auth::user()->email }}</span>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-2">Owner
                                            Name</label>
                                        <div
                                            class="bg-gray-50 rounded-xl px-3 sm:px-4 py-2 sm:py-3 text-gray-900 text-xs sm:text-sm">{{ Auth::user()->name }}</div>
                                    </div>

                                    <div>
                                        <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-2">Shop
                                            Name</label>
                                        <div
                                            class="bg-gray-50 rounded-xl px-3 sm:px-4 py-2 sm:py-3 text-gray-900 font-medium text-xs sm:text-sm">{{ $shop->name }}</div>
                                    </div>
                                </div>

                                <!-- Right Column -->
                                <div class="space-y-4 sm:space-y-6">
                                    @if($shop->phone)
                                        <div>
                                            <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-2">Phone
                                                Number</label>
                                            <div
                                                class="bg-gray-50 rounded-xl px-3 sm:px-4 py-2 sm:py-3 text-gray-900 flex items-center">
                                                <svg class="w-3 h-3 sm:w-4 sm:h-4 text-gray-400 mr-2 flex-shrink-0"
                                                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                          stroke-width="2"
                                                          d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                                </svg>
                                                <a href="tel:{{ $shop->phone }}"
                                                   class="text-blue-600 hover:text-blue-800 text-xs sm:text-sm">{{ $shop->phone }}</a>
                                            </div>
                                        </div>
                                    @endif

                                    <div>
                                        <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-2">Tax
                                            Number</label>
                                        <div
                                            class="bg-gray-50 rounded-xl px-3 sm:px-4 py-2 sm:py-3 text-gray-900 flex items-center">
                                            <svg class="w-3 h-3 sm:w-4 sm:h-4 text-gray-400 mr-2 flex-shrink-0"
                                                 fill="none"
                                                 stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                            </svg>
                                            <span class="text-xs sm:text-sm">
                                                @if($shop->tax_number)
                                                    {{ $shop->tax_number }}
                                                @else
                                                    <span class="text-gray-400 italic">Not provided</span>
                                                @endif
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>


                        </div>


                </div>
                @endif



                @if($activeTab === 'orders')
                    <!-- Order History Card -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-4 sm:p-6 lg:p-8">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                            <h2 class="text-xl sm:text-2xl font-bold text-gray-900">Recent Orders</h2>
                            
                            <!-- Search Bar -->
                            <div class="flex items-center space-x-3">
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                        </svg>
                                    </div>
                                    <input
                                        type="text"
                                        wire:model.live="orderSearch"
                                        placeholder="Search by order ID or customer name..."
                                        class="block w-full pl-10 pr-10 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200 text-sm"
                                        
                                    >
                                    <div wire:loading wire:target="orderSearch" class="absolute inset-y-0 right-0 top-[23%] pr-3 flex items-center justify-center">
                                        <svg class="animate-spin h-5 w-5 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                    </div>
                                    @if($orderSearch)
                                        <button
                                            wire:click="clearOrderSearch"
                                            class="absolute inset-y-0 right-0 pr-3 flex items-center justify-center text-gray-400 hover:text-gray-600"
                                            wire:loading wire:target="orderSearch" class="hidden"
                                        >
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                            </svg>
                                        </button>
                                    @endif
                                </div>
                                
                                @if($orderSearch || $orderStatusFilter || $orderDateFilter)
                                    <div class="text-sm text-gray-500">
                                        {{ $recentOrders->count() }} result{{ $recentOrders->count() !== 1 ? 's' : '' }}
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Filters Section -->
                        <div class="mb-6">
                            <div class="flex flex-col sm:flex-row gap-4">
                                <!-- Status Filter -->
                                <div class="flex-1">
                                    <label for="statusFilter" class="block text-sm font-medium text-gray-700 mb-2">Filter by Status</label>
                                    <select
                                        id="statusFilter"
                                        wire:model.live="orderStatusFilter"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200 text-sm"
                                    >
                                        <option value="">All Statuses</option>
                                        <option value="pending">Pending</option>
                                        <option value="confirmed">Confirmed</option>
                                        <option value="processing">Processing</option>
                                        <option value="ready">Ready</option>
                                        <option value="delivering">Delivering</option>
                                        <option value="delivered">Delivered</option>
                                        <option value="canceled">Canceled</option>
                                    </select>
                                </div>

                                <!-- Date Filter -->
                                <div class="flex-1">
                                    <label for="dateFilter" class="block text-sm font-medium text-gray-700 mb-2">Filter by Date</label>
                                    <select
                                        id="dateFilter"
                                        wire:model.live="orderDateFilter"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200 text-sm"
                                    >
                                        <option value="">All Dates</option>
                                        <option value="today">Today</option>
                                        <option value="yesterday">Yesterday</option>
                                        <option value="this_week">This Week</option>
                                        <option value="this_month">This Month</option>
                                        <option value="last_month">Last Month</option>
                                        <option value="last_30_days">Last 30 Days</option>
                                        <option value="last_90_days">Last 90 Days</option>
                                    </select>
                                </div>

                                <!-- Clear Filters Button -->
                                @if($orderSearch || $orderStatusFilter || $orderDateFilter)
                                    <div class="flex items-end">
                                        <button
                                            wire:click="clearAllFilters"
                                            class="px-4 py-2 bg-gray-100 text-gray-700 rounded-xl hover:bg-gray-200 transition-colors duration-200 text-sm font-medium flex items-center"
                                        >
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                            </svg>
                                            Clear Filters
                                        </button>
                                    </div>
                                @endif
                            </div>

                            <!-- Active Filters Display -->
                            @if($orderSearch || $orderStatusFilter || $orderDateFilter)
                                <div class="mt-4 flex flex-wrap gap-2">
                                    @if($orderSearch)
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            Search: "{{ $orderSearch }}"
                                            <button wire:click="$set('orderSearch', '')" class="ml-2 text-blue-600 hover:text-blue-800">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                </svg>
                                            </button>
                                        </span>
                                    @endif
                                    @if($orderStatusFilter)
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            Status: {{ ucfirst($orderStatusFilter) }}
                                            <button wire:click="$set('orderStatusFilter', '')" class="ml-2 text-green-600 hover:text-green-800">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                </svg>
                                            </button>
                                        </span>
                                    @endif
                                    @if($orderDateFilter)
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                            Date: {{ ucwords(str_replace('_', ' ', $orderDateFilter)) }}
                                            <button wire:click="$set('orderDateFilter', '')" class="ml-2 text-purple-600 hover:text-purple-800">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                </svg>
                                            </button>
                                        </span>
                                    @endif
                                </div>
                            @endif
                        </div>


                        <div class="space-y-6" wire:loading.class="opacity-50">
                            @forelse($recentOrders as $order)
                                <div
                                    class="bg-white border border-gray-200 rounded-2xl p-4 sm:p-6 lg:p-8 hover:shadow-lg transition-all duration-300 hover:border-gray-300">

                                    <!-- Order Header -->
                                    <div class="flex justify-between lg:flex-row lg:items-start lg:justify-between gap-4 mb-6">
                                        <!-- Left Section: Order Info -->
                                        <div class="flex items-center space-x-4">
                                            <!-- Order Icon -->
                                            <div
                                                class="w-12 h-12 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center shadow-sm flex-shrink-0">
                                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor"
                                                     viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                          stroke-width="2"
                                                          d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                                </svg>
                                            </div>

                                            <!-- Order Details -->
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center space-x-3 mb-2">
                                                    <h3 class="text-lg font-semibold text-gray-900">
                                                        Order #{{ $order->id }}
                                                    </h3>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Right Section: Price and Actions -->
                                        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4">

                                            <!-- Action Buttons -->
                                            <div class="flex flex-col gap-1 sm:gap-0 items-end sm:flex-row sm:items-center space-x-2">
                                                <button
                                                    wire:click="showOrderDetails({{ $order->id }})"
                                                    class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-xl hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all duration-200 shadow-sm"
                                                    title="View Order Details"
                                                >
                                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor"
                                                         viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                              stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                              stroke-width="2"
                                                              d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                    </svg>
                                                    <span>Details</span>
                                                </button>

                                                <form target="_blank"
                                                      action="{{ route('shop.invoice_pdf', [$order->id, $shop->id]) }}"
                                                      method="POST" class="inline w-full sm:w-auto"

                                                      >
                                                    @csrf
                                                    <button
                                                        type="submit"
                                                        class="justify-center sm:justify-start w-full sm:w-auto inline-flex items-center px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-xl hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-all duration-200 shadow-sm"
                                                        title="Export as PDF"
                                                    >
                                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor"
                                                             viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                  stroke-width="2"
                                                                  d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                        </svg>
                                                        <span>PDF</span>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Order Progress Bar -->
                                    @if($order->status !== \App\Enums\OrderStatus::CANCELED)
                                        <div class="mb-6">
                                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 sm:gap-0 mb-4">
                                                <h4 class="text-sm font-semibold text-gray-700">Order Progress</h4>
                                                <span class="text-sm font-medium text-gray-500">{{ $order->status->getProgressPercentage() }}% Complete</span>
                                            </div>

                                            <!-- Progress Steps -->
                                            <div class="relative">
                                                <!-- Progress Line -->
                                                <div class="absolute top-4 left-0 right-0 h-1 bg-gray-200 rounded-full"></div>

                                                <!-- Progress Fill -->
                                                <div class="absolute top-4 left-0 h-1 bg-gradient-to-r from-blue-500 to-green-500 rounded-full transition-all duration-1000 ease-out"
                                                     style="width: {{ $order->status->getProgressPercentage() }}%"></div>

                                                <!-- Progress Steps -->
                                                <div class="relative flex justify-between sm:gap-0 gap-6">
                                                    @php
                                                        $steps = [
                                                            ['status' => \App\Enums\OrderStatus::PENDING, 'label' => 'Pending', 'icon' => 'M12 6v6l4 2'],
                                                            ['status' => \App\Enums\OrderStatus::CONFIRMED, 'label' => 'Confirmed', 'icon' => 'M5 13l4 4L19 7'],
                                                            ['status' => \App\Enums\OrderStatus::PROCESSING, 'label' => 'Processing', 'icon' => 'M12 6v6l4 2'],
                                                            ['status' => \App\Enums\OrderStatus::READY, 'label' => 'Ready', 'icon' => 'M5 13l4 4L19 7'],
                                                            ['status' => \App\Enums\OrderStatus::DELIVERING, 'label' => 'Delivering', 'icon' => 'M3 10h1l2 7h13l2-7h1'],
                                                            ['status' => \App\Enums\OrderStatus::DELIVERED, 'label' => 'Delivered', 'icon' => 'M5 13l4 4L19 7']
                                                        ];
                                                    @endphp

                                                    @foreach($steps as $index => $step)
                                                        @php
                                                            $isCompleted = $order->status->getProgressStep() >= $step['status']->getProgressStep();
                                                            $isCurrent = $order->status === $step['status'];
                                                            $isActive = $isCompleted || $isCurrent;
                                                        @endphp

                                                        <div class="flex flex-col items-center min-w-0 flex-1">
                                                            <!-- Step Circle -->
                                                            <div class="relative z-10 flex items-center justify-center w-8 h-8 rounded-full border-2 transition-all duration-300 {{ $isActive ? 'bg-gradient-to-r from-blue-500 to-green-500 border-transparent shadow-lg' : 'bg-white border-gray-300' }}">
                                                                @if($isCompleted)
                                                                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                                    </svg>
                                                                @elseif($isCurrent)
                                                                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $step['icon'] }}"></path>
                                                                    </svg>
                                                                @else
                                                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $step['icon'] }}"></path>
                                                                    </svg>
                                                                @endif
                                                            </div>

                                                            <!-- Step Label -->
                                                            <div class="mt-2 text-center px-1">
                                                                <span class="text-[.5rem] sm:text-sm font-medium {{ $isActive ? 'text-gray-900' : 'text-gray-500' }} break-words leading-tight">{{ $step['label'] }}</span>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>
                                    @else
                                        <!-- Canceled Order Notice -->
                                        <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl">
                                            <div class="flex items-center">
                                                <svg class="w-5 h-5 text-red-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                                </svg>
                                                <div>
                                                    <h4 class="text-sm font-medium text-red-800">Order Canceled</h4>
                                                    <p class="text-sm text-red-600">This order has been canceled and will not proceed through the normal fulfillment process.</p>
                                                </div>
                                            </div>
                                        </div>
                                    @endif

                                    <!-- Order Summary -->
                                    <div class="bg-gray-50 rounded-xl p-4">
                                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-center">
                                            <div>
                                                <div class="text-sm font-medium text-gray-500">Order Date</div>
                                                <div class="text-sm font-semibold text-gray-900">
                                                    {{ $order->customer->name ?? 'Guest' }}
                                                    • {{ $order->created_at->format('M d, Y') }}
                                                </div>
                                            </div>
                                            <div>
                                                <div class="text-sm font-medium text-gray-500">Items</div>
                                                <div class="text-sm font-semibold text-gray-900">{{ $order->items->count() }}</div>
                                            </div>
                                            <div>
                                                <div class="text-sm font-medium text-gray-500">Total</div>
                                                <div class="text-sm font-semibold text-gray-900">{{ number_format($order->total_price, 2) }} ₺</div>
                                            </div>
                                            <div>
                                                <div class="text-sm font-medium text-gray-500">Status</div>
                                                <div class="text-sm font-semibold {{ $order->status->getTextColor() }}">{{ $order->status->label() }}</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="text-center py-12">
                                    <div class="w-20 h-20 bg-gradient-to-br from-blue-100 to-indigo-100 rounded-full flex items-center justify-center mx-auto mb-6">
                                        @if($orderSearch)
                                            <svg class="w-10 h-10 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                            </svg>
                                        @else
                                        <svg class="w-10 h-10 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                        </svg>
                                        @endif
                                    </div>
                                    @if($orderSearch || $orderStatusFilter || $orderDateFilter)
                                        <h3 class="text-lg font-semibold text-gray-900 mb-2">No orders found</h3>
                                        <p class="text-gray-500 mb-6">
                                            @if($orderSearch)
                                                No orders match your search for "{{ $orderSearch }}".
                                            @else
                                                No orders match your current filters.
                                            @endif
                                        </p>
                                        <button
                                            wire:click="clearAllFilters"
                                            class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-xl hover:bg-blue-700 transition-colors duration-200"
                                        >
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                            </svg>
                                            Clear All Filters
                                        </button>
                                    @else
                                    <h3 class="text-lg font-semibold text-gray-900 mb-2">No orders found</h3>
                                    <p class="text-gray-500 mb-6">Your shop hasn't received any orders yet.</p>
                                    <div class="inline-flex items-center px-4 py-2 bg-blue-100 text-blue-700 rounded-xl text-sm font-medium">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        Orders will appear here once customers start shopping
                                    </div>
                                    @endif
                                </div>
                            @endforelse
                        </div>
                    </div>
                @endif

                @if($activeTab === 'products')
                    <!-- Enhanced Purchasing Analytics Dashboard -->
                    <div class="space-y-4 sm:space-y-6">
                        <!-- Header Section with Purchasing Insights -->
                        <div class="bg-gradient-to-r from-blue-600 to-indigo-600 rounded-2xl p-4 sm:p-6 text-white">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-4">
                                <div>
                                    <h2 class="text-xl sm:text-2xl font-bold mb-1 sm:mb-2">Purchasing Analytics</h2>
                                    <p class="text-sm sm:text-base text-blue-100">Track your buying patterns and
                                        optimize your inventory strategy</p>
                                </div>
                                <div class="flex items-center justify-center sm:justify-end space-x-3 sm:space-x-4">
                                    <div class="text-center">
                                        <div class="text-lg sm:text-2xl font-bold">{{ count($topProducts) }}</div>
                                        <div class="text-xs sm:text-sm text-blue-100">Products Purchased</div>
                                    </div>
                                    <div class="text-center">
                                        <div
                                            class="text-lg sm:text-2xl font-bold">{{ array_sum(array_column($topProducts, 'total_quantity')) }}</div>
                                        <div class="text-xs sm:text-sm text-blue-100">Total Units Bought</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Purchasing Insights Grid -->
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6">
                            <!-- Most Purchased Products -->
                            <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-200 p-4 sm:p-6">
                                <div
                                    class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-0 mb-4 sm:mb-6">
                                    <div>
                                        <h3 class="text-lg sm:text-xl font-bold text-gray-900">Most Purchased
                                            Products</h3>
                                        <p class="text-xs sm:text-sm text-gray-500 mt-1">Your top buying choices by
                                            quantity</p>
                                    </div>
                                    <div class="flex items-center justify-center sm:justify-end space-x-2">
                                        <span
                                            class="inline-flex items-center px-2 sm:px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd"
                                                      d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                      clip-rule="evenodd"></path>
                                            </svg>
                                            Live Data
                                        </span>
                                    </div>
                                </div>

                                <div class="space-y-3 sm:space-y-4">
                                    @forelse($topProducts as $index => $product)
                                        <div
                                            class="group relative bg-gradient-to-r from-gray-50 to-white border border-gray-200 rounded-xl p-3 sm:p-4 hover:shadow-lg hover:border-blue-300 transition-all duration-300">
                                            <!-- Rank Badge -->
                                            <div
                                                class="absolute -top-2 -left-2 w-6 h-6 sm:w-8 sm:h-8 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-full flex items-center justify-center text-white font-bold text-xs sm:text-sm shadow-lg">
                                                #{{ $index + 1 }}
                                            </div>

                                            <div
                                                class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-0">
                                                <!-- Product Info -->
                                                <div class="flex items-center space-x-3 sm:space-x-4 flex-1">
                                                    <!-- Product Image -->
                                                    <div class="relative">
                                                        <div
                                                            class="w-10 h-10 sm:w-14 sm:h-14 bg-gray-100 rounded-xl overflow-hidden flex items-center justify-center group-hover:scale-110 transition-transform duration-200">
                                                            @php
                                                                $productModel = \App\Models\Product::find($product['product_id']);
                                                                $primaryImage = $productModel ? $productModel->images->where('is_primary', true)->first() : null;
                                                            @endphp
                                                            @if($primaryImage)
                                                                <img src="{{ Storage::url($primaryImage->image_url) }}"
                                                                     alt="{{ $product['product']['name'] ?? 'Product' }}"
                                                                     class="w-full h-full object-contain">
                                                            @else
                                                                <svg class="w-5 h-5 sm:w-7 sm:h-7 text-gray-400"
                                                                     fill="none" stroke="currentColor"
                                                                     viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                                          stroke-width="2"
                                                                          d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                                                </svg>
                                                            @endif
                                                        </div>
                                                        <!-- Trend Indicator -->
                                                        <div
                                                            class="absolute -bottom-1 -right-1 w-3 h-3 sm:w-4 sm:h-4 bg-blue-500 rounded-full border-2 border-white flex items-center justify-center">
                                                            <svg class="w-1.5 h-1.5 sm:w-2 sm:h-2 text-white"
                                                                 fill="currentColor" viewBox="0 0 20 20">
                                                                <path fill-rule="evenodd"
                                                                      d="M3 3a1 1 0 000 2v8a2 2 0 002 2h2.586l-1.293 1.293a1 1 0 101.414 1.414L10 15.414l2.293 2.293a1 1 0 001.414-1.414L12.414 15H15a2 2 0 002-2V5a1 1 0 100-2H3zm11.707 4.707a1 1 0 00-1.414-1.414L10 9.586 8.707 8.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                                      clip-rule="evenodd"></path>
                                                            </svg>
                                                        </div>
                                                    </div>

                                                    <!-- Product Details -->
                                                    <div class="flex-1 min-w-0">
                                                        <div
                                                            class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-2 mb-1">
                                                            <h4 class="font-semibold text-sm sm:text-base text-gray-900 truncate group-hover:text-blue-700 transition-colors">
                                                                {{ $product['product']['name'] ?? 'Unknown Product' }}
                                                            </h4>
                                                            <span
                                                                class="inline-flex items-center px-1.5 sm:px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                                ID: {{ $product['product_id'] }}
                                                            </span>
                                                        </div>

                                                        <!-- Purchase Metrics -->
                                                        <div
                                                            class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4 text-xs sm:text-sm">
                                                            <div class="flex items-center space-x-1 text-gray-600">
                                                                <svg class="w-3 h-3 sm:w-4 sm:h-4 text-blue-500"
                                                                     fill="none" stroke="currentColor"
                                                                     viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                                          stroke-width="2"
                                                                          d="M3 3h2l.4 2M7 13h10l4-8H5.4m0 0L7 13m0 0l-2.5 5M7 13l2.5 5m6-5v6a2 2 0 01-2 2H9a2 2 0 01-2-2v-6m6 0V9a2 2 0 00-2-2H9a2 2 0 00-2 2v4.01"></path>
                                                                </svg>
                                                                <span
                                                                    class="font-medium text-gray-900">{{ number_format($product['total_quantity']) }}</span>
                                                                <span class="text-gray-500">units bought</span>
                                                            </div>

                                                            <!-- Performance Bar -->
                                                            <div class="flex-1 max-w-24 sm:max-w-32">
                                                                <div
                                                                    class="w-full bg-gray-200 rounded-full h-1.5 sm:h-2">
                                                                    @php
                                                                        $maxQuantity = max(array_column($topProducts, 'total_quantity'));
                                                                        $percentage = $maxQuantity > 0 ? ($product['total_quantity'] / $maxQuantity) * 100 : 0;
                                                                    @endphp
                                                                    <div
                                                                        class="bg-gradient-to-r from-blue-500 to-indigo-600 h-1.5 sm:h-2 rounded-full transition-all duration-300"
                                                                        style="width: {{ $percentage }}%"></div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Action Buttons -->
                                                <div class="flex items-center justify-end sm:justify-start space-x-2">
                                                    <button
                                                        wire:click="$dispatch('openProductModal', { productId: {{ $product['product_id'] }} })"
                                                        class="p-2 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors duration-200"
                                                        title="View Product Details">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                             viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                  stroke-width="2"
                                                                  d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                  stroke-width="2"
                                                                  d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                        </svg>
                                                    </button>

                                                </div>
                                            </div>
                                        </div>
                                    @empty
                                        <!-- Empty State -->
                                        <div class="text-center py-12">
                                            <div
                                                class="w-20 h-20 bg-gradient-to-br from-blue-100 to-indigo-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                                <svg class="w-10 h-10 text-blue-600" fill="none" stroke="currentColor"
                                                     viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                          stroke-width="2"
                                                          d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                                                </svg>
                                            </div>
                                            <h3 class="text-lg font-semibold text-gray-900 mb-2">No Purchase History
                                                Yet</h3>
                                            <p class="text-gray-500 mb-6 max-w-md mx-auto">Start purchasing products to
                                                see your buying analytics and track your most popular items.</p>
                                            <button
                                                class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-xl hover:bg-blue-700 transition-colors duration-200">
                                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor"
                                                     viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                          stroke-width="2"
                                                          d="M3 3h2l.4 2M7 13h10l4-8H5.4m0 0L7 13m0 0l-2.5 5M7 13l2.5 5m6-5v6a2 2 0 01-2 2H9a2 2 0 01-2-2v-6m6 0V9a2 2 0 00-2-2H9a2 2 0 00-2 2v4.01"></path>
                                                </svg>
                                                Browse Products
                                            </button>
                                        </div>
                                    @endforelse
                                </div>
                            </div>

                            <!-- Purchasing Insights Sidebar -->
                            <div class="space-y-4 sm:space-y-6">
                                <!-- Quick Stats Card -->
                                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-4 sm:p-6">
                                    <h4 class="text-base sm:text-lg font-semibold text-gray-900 mb-3 sm:mb-4">Purchase
                                        Summary</h4>
                                    <div class="space-y-3 sm:space-y-4">
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs sm:text-sm text-gray-600">Total Spent</span>
                                            <span class="text-sm sm:text-base font-semibold text-gray-900">{{ number_format($metrics['total_revenue'], 2) }} ₺</span>
                                        </div>
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs sm:text-sm text-gray-600">Orders Placed</span>
                                            <span
                                                class="text-sm sm:text-base font-semibold text-gray-900">{{ $metrics['total_orders'] }}</span>
                                        </div>
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs sm:text-sm text-gray-600">Avg Order Value</span>
                                            <span class="text-sm sm:text-base font-semibold text-gray-900">{{ number_format($metrics['avg_order_value'], 2) }} ₺</span>
                                        </div>
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs sm:text-sm text-gray-600">Unique Products</span>
                                            <span
                                                class="text-sm sm:text-base font-semibold text-gray-900">{{ $metrics['total_products'] }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Smart Recommendations -->
                                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-4 sm:p-6">
                                    <h4 class="text-base sm:text-lg font-semibold text-gray-900 mb-3 sm:mb-4">Smart
                                        Insights</h4>
                                    <div class="space-y-2 sm:space-y-3">
                                        <div class="flex items-start space-x-2 sm:space-x-3">
                                            <div
                                                class="w-6 h-6 sm:w-8 sm:h-8 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                                <svg class="w-3 h-3 sm:w-4 sm:h-4 text-blue-600" fill="none"
                                                     stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                          stroke-width="2"
                                                          d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                            </div>
                                            <div>
                                                <p class="text-xs sm:text-sm font-medium text-gray-900">Bulk Purchase
                                                    Opportunity</p>
                                                <p class="text-xs text-gray-500">Consider buying larger quantities of
                                                    your top products for better wholesale pricing.</p>
                                            </div>
                                        </div>
                                        <div class="flex items-start space-x-2 sm:space-x-3">
                                            <div
                                                class="w-6 h-6 sm:w-8 sm:h-8 bg-green-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                                <svg class="w-3 h-3 sm:w-4 sm:h-4 text-green-600" fill="none"
                                                     stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                          stroke-width="2"
                                                          d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                            </div>
                                            <div>
                                                <p class="text-xs sm:text-sm font-medium text-gray-900">Seasonal
                                                    Trends</p>
                                                <p class="text-xs text-gray-500">Your purchasing pattern shows
                                                    consistent demand for these products.</p>
                                            </div>
                                        </div>
                                        <div class="flex items-start space-x-2 sm:space-x-3">
                                            <div
                                                class="w-6 h-6 sm:w-8 sm:h-8 bg-purple-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                                <svg class="w-3 h-3 sm:w-4 sm:h-4 text-purple-600" fill="none"
                                                     stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                          stroke-width="2"
                                                          d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                                                </svg>
                                            </div>
                                            <div>
                                                <p class="text-xs sm:text-sm font-medium text-gray-900">Inventory
                                                    Optimization</p>
                                                <p class="text-xs text-gray-500">Based on your buying history, you might
                                                    want to stock up on these popular items.</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>


                            </div>
                        </div>
                    </div>
                @endif

                @if($activeTab === 'addresses')
                    <!-- Addresses Card -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-4 sm:p-6 lg:p-8">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                            <h2 class="text-xl sm:text-2xl font-bold text-gray-900">Shop Addresses</h2>
                            <button
                                wire:click="openAddressModal"
                                class="inline-flex items-center justify-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-xl hover:bg-blue-700 transition-colors duration-200 w-full sm:w-auto"
                            >
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                Add Address
                            </button>
                        </div>

                        <div class="space-y-4">
                            @forelse($shop->addresses as $address)
                                <div
                                    class="border border-gray-200 rounded-xl p-4 sm:p-6 hover:shadow-md transition-shadow duration-200">
                                    <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
                                        <div class="flex-1">
                                            <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-3 mb-3">
                                                <div class="flex items-center gap-2">
                                                     <span
                                                         class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $address->is_primary ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                                         {{ $address->is_primary ? 'Primary' : 'Secondary' }}
                                                     </span>
                                                    <h3 class="text-base sm:text-lg font-semibold text-gray-900">{{ $address->label }}</h3>
                                                </div>
                                            </div>

                                            <div class="space-y-2">
                                                <div class="flex items-start gap-2 text-gray-600">
                                                    <svg class="w-4 h-4 text-gray-400 mt-0.5 flex-shrink-0" fill="none"
                                                         stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                              stroke-width="2"
                                                              d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                              stroke-width="2"
                                                              d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                    </svg>
                                                    <span
                                                        class="text-sm sm:text-base break-words">{{ $address->full_address }}</span>
                                                </div>

                                                @if($address->coordinates)
                                                    <div class="flex items-start gap-2 text-gray-600">
                                                        <svg class="w-4 h-4 text-gray-400 mt-0.5 flex-shrink-0"
                                                             fill="none"
                                                             stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                  stroke-width="2"
                                                                  d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-1.447-.894L15 4m0 13V4m-6 3l6-3"></path>
                                                        </svg>
                                                        <span class="text-sm sm:text-base">{{ number_format($address->latitude, 6) }}, {{ number_format($address->longitude, 6) }}</span>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="flex flex-wrap items-center gap-2 lg:ml-4">
                                            @if(!$address->is_primary)
                                                <button
                                                    wire:click="setPrimaryAddress({{ $address->id }})"
                                                    class="inline-flex items-center px-2 sm:px-3 py-1.5 bg-green-100 text-green-700 text-xs font-medium rounded-lg hover:bg-green-200 transition-colors duration-200"
                                                    title="Set as Primary"
                                                >
                                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor"
                                                         viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                              stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                    </svg>
                                                    <span class="hidden sm:inline">Set Primary</span>
                                                    <span class="sm:hidden">Primary</span>
                                                </button>
                                            @endif

                                            <button
                                                wire:click="editAddress({{ $address->id }})"
                                                class="inline-flex items-center px-2 sm:px-3 py-1.5 bg-blue-100 text-blue-700 text-xs font-medium rounded-lg hover:bg-blue-200 transition-colors duration-200"
                                                title="Edit Address"
                                            >
                                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor"
                                                     viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                          stroke-width="2"
                                                          d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                </svg>
                                                Edit
                                            </button>

                                            @if($shop->addresses->count() > 1)
                                                <button
                                                    wire:click="deleteAddress({{ $address->id }})"
                                                    wire:confirm="Are you sure you want to delete this address?"
                                                    class="inline-flex items-center px-2 sm:px-3 py-1.5 bg-red-100 text-red-700 text-xs font-medium rounded-lg hover:bg-red-200 transition-colors duration-200"
                                                    title="Delete Address"
                                                >
                                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor"
                                                         viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                              stroke-width="2"
                                                              d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                    </svg>
                                                    Delete
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="text-center py-8 sm:py-12">
                                    <svg class="w-12 h-12 sm:w-16 sm:h-16 text-gray-300 mx-auto mb-4" fill="none"
                                         stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    </svg>
                                    <h3 class="text-base sm:text-lg font-medium text-gray-900 mb-2">No addresses
                                        found</h3>
                                    <p class="text-sm sm:text-base text-gray-500 mb-4 px-4">Add your first shop address
                                        to
                                        get started.</p>
                                    <button
                                        wire:click="openAddressModal"
                                        class="inline-flex items-center justify-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-xl hover:bg-blue-700 transition-colors duration-200 w-full sm:w-auto"
                                    >
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                        </svg>
                                        Add First Address
                                    </button>
                                </div>
                            @endforelse
                        </div>
                    </div>
                @endif

                @if($activeTab === 'notifications')
                    <!-- Notifications Card -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8">
                        <h2 class="text-2xl font-bold text-gray-900 mb-6">Notification Settings</h2>
                        <div class="space-y-6">
                            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                                <div>
                                    <div class="font-medium text-gray-900">New Order Notifications</div>
                                    <div class="text-sm text-gray-500">Receive notifications when new orders are placed
                                    </div>
                                </div>
                                <div class="flex items-center">
                                    <button class="relative inline-flex h-6 w-11 items-center rounded-full bg-blue-600">
                                    <span
                                        class="inline-block h-4 w-4 transform rounded-full bg-white transition"></span>
                                    </button>
                                </div>
                            </div>

                            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                                <div>
                                    <div class="font-medium text-gray-900">Order Status Updates</div>
                                    <div class="text-sm text-gray-500">Get notified when order status changes</div>
                                </div>
                                <div class="flex items-center">
                                    <button class="relative inline-flex h-6 w-11 items-center rounded-full bg-blue-600">
                                    <span
                                        class="inline-block h-4 w-4 transform rounded-full bg-white transition"></span>
                                    </button>
                                </div>
                            </div>

                            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                                <div>
                                    <div class="font-medium text-gray-900">Weekly Reports</div>
                                    <div class="text-sm text-gray-500">Receive weekly sales and performance reports
                                    </div>
                                </div>
                                <div class="flex items-center">
                                    <button class="relative inline-flex h-6 w-11 items-center rounded-full bg-gray-200">
                                    <span
                                        class="inline-block h-4 w-4 transform rounded-full bg-white transition translate-x-5"></span>
                                    </button>
                                </div>
                            </div>

                            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                                <div>
                                    <div class="font-medium text-gray-900">Product Alerts</div>
                                    <div class="text-sm text-gray-500">Notifications about low stock and product updates
                                    </div>
                                </div>
                                <div class="flex items-center">
                                    <button class="relative inline-flex h-6 w-11 items-center rounded-full bg-blue-600">
                                    <span
                                        class="inline-block h-4 w-4 transform rounded-full bg-white transition"></span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Edit Shop Information Modal -->
    <div class="fixed inset-0 z-50 overflow-y-auto {{ $showEditModal ? '' : 'hidden' }}" aria-labelledby="modal-title"
         role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <!-- Background overlay -->
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"
                 wire:click="closeEditModal"></div>

            <!-- Modal panel -->
            <div
                class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                <div class="bg-white px-6 py-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-2xl font-bold text-gray-900" id="modal-title">
                            Edit Shop Information
                        </h3>
                        <button
                            wire:click="closeEditModal"
                            class="text-gray-400 hover:text-gray-600 transition-colors duration-200"
                        >
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>

                    <form wire:submit.prevent="saveShopInfo" class="space-y-6">
                        <!-- Owner Name -->
                        <div>
                            <label for="ownerName" class="block text-sm font-medium text-gray-700 mb-2">Owner Name
                                *</label>
                            <input
                                type="text"
                                id="ownerName"
                                wire:model="ownerName"
                                class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200"
                                placeholder="Enter owner name"
                            >
                            @error('ownerName') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <!-- Shop Name -->
                        <div>
                            <label for="shopName" class="block text-sm font-medium text-gray-700 mb-2">Shop Name
                                *</label>
                            <input
                                type="text"
                                id="shopName"
                                wire:model="shopName"
                                class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200"
                                placeholder="Enter shop name"
                            >
                            @error('shopName') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <!-- Shop Address -->
                        <div>
                            <label for="shopAddress" class="block text-sm font-medium text-gray-700 mb-2">Address
                                *</label>
                            <textarea
                                id="shopAddress"
                                wire:model="shopAddress"
                                rows="3"
                                class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200"
                                placeholder="Enter shop address"
                            ></textarea>
                            @error('shopAddress') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <!-- Phone -->
                        <div>
                            <label for="shopPhone" class="block text-sm font-medium text-gray-700 mb-2">Phone</label>
                            <input
                                type="tel"
                                id="shopPhone"
                                wire:model="shopPhone"
                                class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200"
                                placeholder="Enter phone number"
                            >
                            @error('shopPhone') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <!-- Tax Number -->
                        <div>
                            <label for="shopTaxNumber" class="block text-sm font-medium text-gray-700 mb-2">Tax
                                Number</label>
                            <input
                                type="text"
                                id="shopTaxNumber"
                                wire:model="shopTaxNumber"
                                class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200"
                                placeholder="Enter tax number"
                            >
                            @error('shopTaxNumber') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>


                        <!-- Modal Actions -->
                        <div class="flex items-center justify-end space-x-3 pt-6 border-t border-gray-200">
                            <button
                                type="button"
                                wire:click="closeEditModal"
                                class="px-6 py-2 text-gray-700 bg-gray-100 rounded-xl hover:bg-gray-200 transition-colors duration-200"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                class="px-6 py-2 bg-blue-600 text-white rounded-xl hover:bg-blue-700 transition-colors duration-200"
                                wire:loading.attr="disabled"
                                wire:loading.class="opacity-50 cursor-not-allowed"
                            >
                                <span wire:loading.remove>Save Changes</span>
                                <span wire:loading>Saving...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Change Password Modal -->
    <div class="fixed inset-0 z-50 overflow-y-auto {{ $showPasswordModal ? '' : 'hidden' }}"
         aria-labelledby="password-modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <!-- Background overlay -->
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"
                 wire:click="closePasswordModal"></div>

            <!-- Modal panel -->
            <div
                class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white px-6 py-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-2xl font-bold text-gray-900" id="password-modal-title">
                            Change Password
                        </h3>
                        <button
                            wire:click="closePasswordModal"
                            class="text-gray-400 hover:text-gray-600 transition-colors duration-200"
                        >
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>

                    <form wire:submit.prevent="changePassword" class="space-y-6">
                        <!-- Current Password -->
                        <div>
                            <label for="currentPassword" class="block text-sm font-medium text-gray-700 mb-2">Current
                                Password *</label>
                            <input
                                type="password"
                                id="currentPassword"
                                wire:model="currentPassword"
                                class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200"
                                placeholder="Enter your current password"
                                required
                            >
                            @error('currentPassword') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <!-- New Password -->
                        <div>
                            <label for="newPassword" class="block text-sm font-medium text-gray-700 mb-2">New Password
                                *</label>
                            <input
                                type="password"
                                id="newPassword"
                                wire:model="newPassword"
                                class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200"
                                placeholder="Enter new password (min 8 characters)"
                                required
                            >
                            @error('newPassword') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <!-- Confirm New Password -->
                        <div>
                            <label for="confirmPassword" class="block text-sm font-medium text-gray-700 mb-2">Confirm
                                New
                                Password *</label>
                            <input
                                type="password"
                                id="confirmPassword"
                                wire:model="confirmPassword"
                                class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200"
                                placeholder="Confirm new password"
                                required
                            >
                            @error('confirmPassword') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <!-- Password Requirements -->
                        <div class="bg-blue-50 rounded-xl p-4">
                            <h4 class="text-sm font-medium text-blue-900 mb-2">Password Requirements:</h4>
                            <ul class="text-sm text-blue-800 space-y-1">
                                <li class="flex items-center">
                                    <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                              d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                              clip-rule="evenodd"></path>
                                    </svg>
                                    Minimum 8 characters
                                </li>
                                <li class="flex items-center">
                                    <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                              d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                              clip-rule="evenodd"></path>
                                    </svg>
                                    Passwords must match
                                </li>
                            </ul>
                        </div>

                        <!-- Modal Actions -->
                        <div class="flex items-center justify-end space-x-3 pt-6 border-t border-gray-200">
                            <button
                                type="button"
                                wire:click="closePasswordModal"
                                class="px-6 py-2 text-gray-700 bg-gray-100 rounded-xl hover:bg-gray-200 transition-colors duration-200"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                class="px-6 py-2 bg-blue-600 text-white rounded-xl hover:bg-blue-700 transition-colors duration-200"
                                wire:loading.attr="disabled"
                                wire:loading.class="opacity-50 cursor-not-allowed"
                            >
                                <span wire:loading.remove>Change Password</span>
                                <span wire:loading>Changing...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Address Modal -->
    <div class="fixed inset-0 z-50 overflow-y-auto {{ $showAddressModal ? '' : 'hidden' }}"
         aria-labelledby="address-modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-2 pb-20 text-center sm:block sm:p-0">
            <!-- Background overlay -->
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"
                 wire:click="closeAddressModal"></div>

            <!-- Modal panel -->
            <div
                class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full w-full">
                <div class="bg-white px-4 sm:px-6 py-4 sm:py-6">
                    <div class="flex items-center justify-between mb-4 sm:mb-6">
                        <h3 class="text-xl sm:text-2xl font-bold text-gray-900" id="address-modal-title">
                            {{ $isEditingAddress ? 'Edit Address' : 'Add New Address' }}
                        </h3>
                        <button
                            wire:click="closeAddressModal"
                            class="text-gray-400 hover:text-gray-600 transition-colors duration-200 p-1"
                        >
                            <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>

                    <form wire:submit.prevent="saveAddress" class="space-y-4 sm:space-y-6">
                        <!-- Address Label -->
                        <div>
                            <label for="addressLabel" class="block text-sm font-medium text-gray-700 mb-2">Address Label
                                *</label>
                            <input
                                type="text"
                                id="addressLabel"
                                wire:model="addressLabel"
                                class="w-full px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200 text-sm sm:text-base"
                                placeholder="e.g., Head Office, Branch, Warehouse"
                                required
                            >
                            @error('addressLabel') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <!-- Address Line -->
                        <div>
                            <label for="addressLine" class="block text-sm font-medium text-gray-700 mb-2">Street Address
                                *</label>
                            <textarea
                                id="addressLine"
                                wire:model="addressLine"
                                rows="3"
                                class="w-full px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200 text-sm sm:text-base"
                                placeholder="Enter street address"
                                required
                            ></textarea>
                            @error('addressLine') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <!-- City and State -->
                        <div class="grid grid-cols-1 gap-4">
                            <div>
                                <label for="addressCity" class="block text-sm font-medium text-gray-700 mb-2">City
                                    *</label>
                                <select
                                    id="addressCity"
                                    wire:model.live="addressCity"
                                    class="w-full px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200 text-sm sm:text-base"
                                    required
                                >
                                    <option value="">Select a city</option>
                                    @foreach($turkishCities as $cityKey => $cityName)
                                        <option
                                            value="{{ $cityKey }}" {{$cityName == $addressCity ? 'selected' :''}}>{{ $cityName }}</option>
                                    @endforeach
                                </select>
                                @error('addressCity') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label for="addressState" class="block text-sm font-medium text-gray-700 mb-2">District
                                    *</label>
                                <select
                                    id="addressState"
                                    wire:model.live="addressState"
                                    class="w-full px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200 text-sm sm:text-base {{ empty($addressCity) ? 'bg-gray-100 cursor-not-allowed' : '' }}"
                                    {{ empty($addressCity) ? 'disabled' : '' }}
                                    required
                                >
                                    <option value="">Select a district</option>
                                    @foreach($cityDistricts as $district)
                                        <option
                                            value="{{ $district }}" {{$district == $addressState ? 'selected' :''}}>{{ $district }}</option>
                                    @endforeach
                                </select>
                                @error('addressState') <span
                                    class="text-red-500 text-sm">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <!-- Postal Code -->
                        <div>
                            <label for="addressPostalCode" class="block text-sm font-medium text-gray-700 mb-2">Postal
                                Code</label>
                            <input
                                type="text"
                                id="addressPostalCode"
                                wire:model="addressPostalCode"
                                class="w-full px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200 text-sm sm:text-base"
                                placeholder="Enter postal code"
                            >
                            @error('addressPostalCode') <span
                                class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <!-- Coordinates -->
                        <div class="grid grid-cols-1 gap-4">
                            <div>
                                <label for="addressLatitude" class="block text-sm font-medium text-gray-700 mb-2">Latitude
                                    (Optional)</label>
                                <input
                                    type="number"
                                    id="addressLatitude"
                                    wire:model="addressLatitude"
                                    step="any"
                                    class="w-full px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200 text-sm sm:text-base"
                                    placeholder="e.g., 40.7128"
                                >
                                @error('addressLatitude') <span
                                    class="text-red-500 text-sm">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label for="addressLongitude" class="block text-sm font-medium text-gray-700 mb-2">Longitude
                                    (Optional)</label>
                                <input
                                    type="number"
                                    id="addressLongitude"
                                    wire:model="addressLongitude"
                                    step="any"
                                    class="w-full px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200 text-sm sm:text-base"
                                    placeholder="e.g., -74.0060"
                                >
                                @error('addressLongitude') <span
                                    class="text-red-500 text-sm">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <!-- Primary Address Toggle -->
                        <div class="flex items-center space-x-3">
                            <input
                                type="checkbox"
                                id="addressIsPrimary"
                                wire:model="addressIsPrimary"
                                class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 focus:ring-2"
                            >
                            <label for="addressIsPrimary" class="text-sm font-medium text-gray-700">
                                Set as primary address
                            </label>
                        </div>

                        <!-- Modal Actions -->
                        <div
                            class="flex flex-col sm:flex-row items-center justify-end gap-3 pt-4 sm:pt-6 border-t border-gray-200">
                            <button
                                type="button"
                                wire:click="closeAddressModal"
                                class="w-full sm:w-auto px-4 sm:px-6 py-2 text-gray-700 bg-gray-100 rounded-xl hover:bg-gray-200 transition-colors duration-200 text-sm sm:text-base"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                class="w-full sm:w-auto px-4 sm:px-6 py-2 bg-blue-600 text-white rounded-xl hover:bg-blue-700 transition-colors duration-200 text-sm sm:text-base"
                                wire:loading.attr="disabled"
                                wire:loading.class="opacity-50 cursor-not-allowed"
                            >
                                <span
                                    wire:loading.remove>{{ $isEditingAddress ? 'Update Address' : 'Add Address' }}</span>
                                <span wire:loading>Saving...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Order Details Modal -->
    <div class="fixed inset-0 z-50 overflow-y-auto {{ $showOrderDetailsModal ? '' : 'hidden' }}"
         aria-labelledby="order-details-modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <!-- Background overlay -->
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"
                 wire:click="closeOrderDetailsModal"></div>

            <!-- Modal panel -->
            <div
                class="relative inline-block align-bottom bg-white rounded-t-2xl sm:rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle w-full sm:max-w-4xl sm:w-full max-h-[90vh] sm:max-h-[85vh]">
                @if($selectedOrder)
                    <!-- Modal Header -->
                    <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-4 py-6 sm:px-6">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <div
                                    class="flex-shrink-0 w-10 h-10 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor"
                                         viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-lg sm:text-xl font-bold text-white" id="order-details-modal-title">
                                        Order #{{ $selectedOrder->id }}
                                    </h3>
                                    <p class="text-indigo-100 text-sm">
                                        {{ $selectedOrder->created_at->format('d M Y, H:i') }}
                                    </p>
                                </div>
                            </div>
                            <button wire:click="closeOrderDetailsModal"
                                    class="rounded-full bg-white bg-opacity-20 p-2 text-white hover:bg-opacity-30 focus:outline-none focus:ring-2 focus:ring-white focus:ring-opacity-50 transition-all">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Modal Content -->
                    <div class="bg-white flex flex-col max-h-[calc(90vh-120px)] sm:max-h-[calc(85vh-120px)]">
                        <div class="flex-1 overflow-y-auto">
                            <div class="p-4 sm:p-6 space-y-6">

                                <!-- Order Summary Cards -->
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                                    <!-- Customer Card -->
                                    <div
                                        class="bg-gradient-to-br from-green-50 to-green-100 rounded-xl p-4 border border-green-200">
                                        <div class="flex items-center space-x-3">
                                            <div
                                                class="w-10 h-10 bg-green-500 rounded-lg flex items-center justify-center">
                                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor"
                                                     viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                          stroke-width="2"
                                                          d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                                </svg>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-xs font-medium text-green-600 uppercase tracking-wide">
                                                    Customer</p>
                                                <p class="text-sm font-bold text-green-900 truncate">{{ $selectedOrder->customer->name }}</p>
                                                <p class="text-xs text-green-700">{{ $selectedOrder->customer->email }}</p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Status Card -->
                                    <div
                                        class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-xl p-4 border border-purple-200">
                                        <div class="flex items-center space-x-3">
                                            <div
                                                class="w-10 h-10 bg-purple-500 rounded-lg flex items-center justify-center">
                                                {!! $selectedOrder->status->icon() !!}
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-xs font-medium text-purple-600 uppercase tracking-wide">
                                                    Status</p>
                                                <p class="text-sm font-bold text-purple-900">{{ $selectedOrder->status->label() }}</p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Total Card -->
                                    <div
                                        class="bg-gradient-to-br from-emerald-50 to-emerald-100 rounded-xl p-4 border border-emerald-200">
                                        <div class="flex items-center space-x-3">
                                            <div
                                                class="w-10 h-10 bg-emerald-500 rounded-lg flex items-center justify-center">
                                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor"
                                                     viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                          stroke-width="2"
                                                          d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/>
                                                </svg>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-xs font-medium text-emerald-600 uppercase tracking-wide">
                                                    Total</p>
                                                <p class="text-sm font-bold text-emerald-900">{{ number_format($selectedOrder->total_price, 2) }}
                                                    ₺</p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Items Count Card -->
                                    <div
                                        class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl p-4 border border-blue-200">
                                        <div class="flex items-center space-x-3">
                                            <div
                                                class="w-10 h-10 bg-blue-500 rounded-lg flex items-center justify-center">
                                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor"
                                                     viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                          stroke-width="2"
                                                          d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                                </svg>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-xs font-medium text-blue-600 uppercase tracking-wide">
                                                    Items</p>
                                                <p class="text-sm font-bold text-blue-900">{{ $selectedOrder->items->count() }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Order Items -->
                                <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden">
                                    <div
                                        class="bg-gradient-to-r from-gray-50 to-gray-100 px-4 sm:px-6 py-4 border-b border-gray-200">
                                        <h4 class="text-lg font-bold text-gray-900 flex items-center">
                                            <svg class="w-5 h-5 mr-3 text-gray-600" fill="none" stroke="currentColor"
                                                 viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                            </svg>
                                            Order Items
                                            <span
                                                class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                                {{ $selectedOrder->items->count() }}
                                            </span>
                                        </h4>
                                    </div>

                                    <!-- Mobile View -->
                                    <div class="block sm:hidden">
                                        <div class="divide-y divide-gray-200">
                                            @foreach($selectedOrder->items as $item)
                                                <div class="p-4 hover:bg-gray-50 transition-colors">
                                                    <div class="flex items-start space-x-3">
                                                        <div class="flex-shrink-0">
                                                            <img
                                                                class="w-16 h-16 rounded-lg object-cover border border-gray-200"
                                                                src="{{ asset('storage/' . $item->product->primary_image_url) }}"
                                                                alt="{{ $item->product->name }}">
                                                        </div>
                                                        <div class="flex-1 min-w-0">
                                                            <div class="flex items-start justify-between">
                                                                <div class="flex-1 min-w-0">
                                                                    <p class="text-sm font-semibold text-gray-900 truncate">{{ $item->product->name }}</p>
                                                                    <p class="text-xs text-gray-500">{{ $item->product->code }}</p>
                                                                </div>
                                                                <div class="flex-shrink-0 ml-2">
                                                                    <span
                                                                        class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                                        Qty: {{ $item->quantity }}
                                                                    </span>
                                                                </div>
                                                            </div>
                                                            <div class="mt-2 flex items-center justify-between">
                                                                <div class="text-sm text-gray-600">
                                                                    {{ number_format($item->price, 2) }} ₺ each
                                                                </div>
                                                                <div class="text-sm font-bold text-gray-900">
                                                                    {{ number_format($item->subtotal, 2) }} ₺
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>

                                    <!-- Desktop View -->
                                    <div class="hidden sm:block overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200">
                                            <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Product
                                                </th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Quantity
                                                </th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Price
                                                </th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Subtotal
                                                </th>
                                            </tr>
                                            </thead>
                                            <tbody class="bg-white divide-y divide-gray-200">
                                            @foreach($selectedOrder->items as $item)
                                                <tr class="hover:bg-gray-50 transition-colors">
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="flex items-center">
                                                            <div class="flex-shrink-0 w-12 h-12">
                                                                <img
                                                                    class="w-12 h-12 rounded-lg object-cover border border-gray-200"
                                                                    src="{{ asset('storage/' . $item->product->primary_image_url) }}"
                                                                    alt="{{ $item->product->name }}">
                                                            </div>
                                                            <div class="ml-4">
                                                                <div
                                                                    class="text-sm font-semibold text-gray-900">{{ $item->product->name }}</div>
                                                                <div
                                                                    class="text-sm text-gray-500">{{ $item->product->code }}</div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                            <span
                                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                                {{ $item->quantity }}
                                                            </span>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        {{ number_format($item->price, 2) }} ₺
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">
                                                        {{ number_format($item->subtotal, 2) }} ₺
                                                    </td>
                                                </tr>
                                            @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- Order Notes -->
                                @if($selectedOrder->notes)
                                    <div
                                        class="bg-gradient-to-br from-amber-50 to-orange-50 border border-amber-200 rounded-2xl p-4 sm:p-6">
                                        <h4 class="text-lg font-bold text-amber-900 mb-3 flex items-center">
                                            <svg class="w-5 h-5 mr-3 text-amber-600" fill="none" stroke="currentColor"
                                                 viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                            Order Notes
                                        </h4>
                                        <div class="bg-white rounded-xl p-4 border border-amber-200">
                                            <p class="text-sm text-gray-700 leading-relaxed">{{ $selectedOrder->notes }}</p>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Modal Footer -->
                        <div
                            class="bg-gray-50 px-4 py-4 sm:px-6 sm:py-6 border-t border-gray-200 flex flex-col sm:flex-row gap-3 sm:gap-0 sm:justify-between">
                            <div class="flex flex-col sm:flex-row gap-3">
                                <form target="_blank"
                                      action="{{ route('shop.invoice_pdf', [$selectedOrder->id, $shop->id]) }}"
                                      method="POST">
                                    @csrf
                                    <button type="submit"
                                            wire:loading.attr="disabled"
                                            class="w-full sm:w-auto inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-xl text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                        <span wire:loading.remove>Export PDF</span>
                                        <span wire:loading>Generating...</span>
                                    </button>
                                </form>
                            </div>
                            <div class="flex flex-col sm:flex-row gap-3">
                                <button wire:click="closeOrderDetailsModal"
                                        type="button"
                                        class="w-full sm:w-auto inline-flex items-center justify-center px-6 py-2 border border-gray-300 text-sm font-medium rounded-xl text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors">
                                    Close
                                </button>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

</div>

<script>
    // Handle modal scroll prevention
    document.addEventListener('livewire:init', () => {
        Livewire.on('modal-opened', () => {
            // Prevent background scrolling
            document.body.style.overflow = 'hidden';
        });

        Livewire.on('modal-closed', () => {
            // Re-enable background scrolling
            document.body.style.overflow = '';
        });
    });

    // Also handle when user clicks outside modal to close
    document.addEventListener('click', (e) => {
        if (e.target.classList.contains('modal-backdrop')) {
            // Re-enable scrolling when modal is closed by clicking outside
            document.body.style.overflow = '';
        }
    });

    // Address toggle functionality
    function toggleAddress() {
        const displayElement = document.getElementById('address-display');
        const fullElement = document.getElementById('address-full');

        if (displayElement && fullElement) {
            if (displayElement.classList.contains('hidden')) {
                // Show truncated version
                displayElement.classList.remove('hidden');
                fullElement.classList.add('hidden');
            } else {
                // Show full version
                displayElement.classList.add('hidden');
                fullElement.classList.remove('hidden');
            }
        }
    }
</script>
