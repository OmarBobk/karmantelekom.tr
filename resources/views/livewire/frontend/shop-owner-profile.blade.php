<div class="min-h-screen bg-gray-50">
    <!-- Header Section -->
    <div class="bg-gradient-to-r from-blue-600 to-indigo-600 shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                <div class="flex-1">
                    <h1 class="text-3xl font-bold text-white mb-2">{{ $shop->name }}</h1>
                    <div class="flex items-center space-x-6 text-blue-100">
                        <span class="flex items-center space-x-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                            <span>{{ $shop->address }}</span>
                        </span>
                        @if($shop->phone)
                            <span class="flex items-center space-x-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                </svg>
                                <span>{{ $shop->phone }}</span>
                            </span>
                        @endif
                    </div>
                </div>
                <div class="mt-4 md:mt-0">
                    <div class="bg-white/20 backdrop-blur-sm rounded-xl px-6 py-4">
                        <div class="text-center">
                            <div class="text-2xl font-bold text-white">{{ number_format($metrics['total_orders']) }}</div>
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
                                'business' => [
                                    'label' => 'Business Details',
                                    'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>'
                                ],
                                'orders' => [
                                    'label' => 'Order History',
                                    'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>'
                                ],
                                'products' => [
                                    'label' => 'Products & Preferences',
                                    'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>'
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
                                <div class="flex-shrink-0 {!! $activeTab === $tabKey ? 'text-blue-600' : 'text-gray-400' !!}">
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
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        <div class="flex bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center">
                                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                                        </svg>
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-600">Total Revenue</p>
                                    <p class="text-lg font-bold text-gray-900">{{ number_format($metrics['total_revenue'], 2) }} ₺</p>
                                </div>
                            </div>
                        </div>

                        <div class="flex bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center">
                                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                        </svg>
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-600">Pending Orders</p>
                                    <p class="text-2xl font-bold text-gray-900">{{ $metrics['pending_orders'] }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="flex bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center">
                                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                        </svg>
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-600">Total Products</p>
                                    <p class="text-2xl font-bold text-gray-900">{{ $metrics['total_products'] }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="flex bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div class="w-12 h-12 bg-orange-100 rounded-xl flex items-center justify-center">
                                        <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                                        </svg>
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-600">Avg Order</p>
                                    <p class="text-lg font-bold text-gray-900">{{ number_format($metrics['avg_order_value'], 2) }} ₺</p>
                                </div>
                            </div>
                        </div>
                    </div>

                                         <!-- Basic Information Card -->
                     <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8">
                         <div class="flex items-center justify-between mb-6">
                             <h2 class="text-2xl font-bold text-gray-900">Basic Information</h2>
                             <div class="flex space-x-3">
                                 <button
                                     wire:click="openPasswordModal"
                                     class="inline-flex items-center px-4 py-2 bg-gray-600 text-white text-sm font-medium rounded-xl hover:bg-gray-700 transition-colors duration-200"
                                 >
                                     <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                         <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
                                     </svg>
                                     Change Password
                                 </button>
                                 <button
                                     wire:click="openEditModal"
                                     class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-xl hover:bg-blue-700 transition-colors duration-200"
                                 >
                                     <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                         <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                     </svg>
                                     Edit Information
                                 </button>
                             </div>
                         </div>

                         <div class="grid grid-cols-1 gap-8">
                             <!-- Shop Details -->
                             <div class="space-y-6">
                                 <div>
                                     <label class="block text-sm font-medium text-gray-700 mb-2">Owner Email</label>
                                     <div class="bg-gray-50 rounded-xl px-4 py-3 text-gray-900 flex items-center">
                                         <svg class="w-4 h-4 text-gray-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                             <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                         </svg>
                                         <span class="text-gray-600">{{ Auth::user()->email }}</span>
                                     </div>
                                 </div>

                                 <div>
                                     <label class="block text-sm font-medium text-gray-700 mb-2">Owner Name</label>
                                     <div class="bg-gray-50 rounded-xl px-4 py-3 text-gray-900">{{ Auth::user()->name }}</div>
                                 </div>

                                 <div>
                                     <label class="block text-sm font-medium text-gray-700 mb-2">Shop Name</label>
                                     <div class="bg-gray-50 rounded-xl px-4 py-3 text-gray-900 font-medium">{{ $shop->name }}</div>
                                 </div>

                                 @if($shop->phone)
                                     <div>
                                         <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                                         <div class="bg-gray-50 rounded-xl px-4 py-3 text-gray-900 flex items-center">
                                             <svg class="w-4 h-4 text-gray-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                             </svg>
                                             <a href="tel:{{ $shop->phone }}" class="text-blue-600 hover:text-blue-800">{{ $shop->phone }}</a>
                                         </div>
                                     </div>
                                 @endif

                                                                                                                                       <div>
                                       <label class="block text-sm font-medium text-gray-700 mb-2">Tax Number</label>
                                       <div class="bg-gray-50 rounded-xl px-4 py-3 text-gray-900 flex items-center">
                                           <svg class="w-4 h-4 text-gray-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                               <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                           </svg>
                                           @if($shop->tax_number)
                                               {{ $shop->tax_number }}
                                           @else
                                               <span class="text-gray-400 italic">Not provided</span>
                                           @endif
                                       </div>
                                   </div>
                             </div>


                         </div>


                     </div>
                @endif

                @if($activeTab === 'business')
                    <!-- Business Details Card -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8">
                        <h2 class="text-2xl font-bold text-gray-900 mb-6">Business Details</h2>
                        <div class="space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Business Type</label>
                                    <div class="bg-gray-50 rounded-xl px-4 py-3 text-gray-900">Retail Store</div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Registration Date</label>
                                    <div class="bg-gray-50 rounded-xl px-4 py-3 text-gray-900">{{ $shop->created_at->format('M d, Y') }}</div>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Business Description</label>
                                <div class="bg-gray-50 rounded-xl px-4 py-3 text-gray-900 min-h-[100px]">
                                    {{ $shop->description ?? 'No description available' }}
                                </div>
                            </div>

                            @if($shop->links)
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Social Media Links</label>
                                    <div class="space-y-2">
                                        @foreach($shop->links as $platform => $url)
                                            <div class="flex items-center space-x-3 bg-gray-50 rounded-xl px-4 py-3">
                                                <span class="text-sm font-medium text-gray-600 capitalize">{{ $platform }}:</span>
                                                <a href="{{ $url }}" target="_blank" class="text-blue-600 hover:text-blue-800 text-sm">{{ $url }}</a>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                @if($activeTab === 'orders')
                    <!-- Order History Card -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8">
                        <h2 class="text-2xl font-bold text-gray-900 mb-6">Recent Orders</h2>
                        <div class="space-y-4">
                            @forelse($recentOrders as $order)
                                <div class="border border-gray-200 rounded-xl p-4 hover:shadow-md transition-shadow duration-200">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center space-x-4">
                                            <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center">
                                                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                                </svg>
                                            </div>
                                            <div>
                                                <div class="font-medium text-gray-900">Order #{{ $order['id'] }}</div>
                                                <div class="text-sm text-gray-500">{{ $order['customer']['name'] ?? 'Guest' }}</div>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="font-bold text-gray-900">{{ number_format($order['total_price'], 2) }} ₺</div>
                                            <div class="text-sm text-gray-500">{{ \Carbon\Carbon::parse($order['created_at'])->format('M d, Y') }}</div>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="text-center py-8">
                                    <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                    </svg>
                                    <p class="text-gray-500">No orders found</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                @endif

                @if($activeTab === 'products')
                    <!-- Products & Preferences Card -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8">
                        <h2 class="text-2xl font-bold text-gray-900 mb-6">Top Products</h2>
                        <div class="space-y-4">
                            @forelse($topProducts as $product)
                                <div class="border border-gray-200 rounded-xl p-4 hover:shadow-md transition-shadow duration-200">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center space-x-4">
                                            <div class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center">
                                                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                                </svg>
                                            </div>
                                            <div>
                                                <div class="font-medium text-gray-900">{{ $product['product']['name'] ?? 'Unknown Product' }}</div>
                                                <div class="text-sm text-gray-500">{{ $product['total_quantity'] ?? 0 }} units sold</div>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="font-bold text-gray-900">{{ number_format($product['product']['price'] ?? 0, 2) }} ₺</div>
                                            <div class="text-sm text-gray-500">Product ID: {{ $product['product_id'] }}</div>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="text-center py-8">
                                    <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                    </svg>
                                    <p class="text-gray-500">No products found</p>
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
                                    <div class="text-sm text-gray-500">Receive notifications when new orders are placed</div>
                                </div>
                                <div class="flex items-center">
                                    <button class="relative inline-flex h-6 w-11 items-center rounded-full bg-blue-600">
                                        <span class="inline-block h-4 w-4 transform rounded-full bg-white transition"></span>
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
                                        <span class="inline-block h-4 w-4 transform rounded-full bg-white transition"></span>
                                    </button>
                                </div>
                            </div>

                            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                                <div>
                                    <div class="font-medium text-gray-900">Weekly Reports</div>
                                    <div class="text-sm text-gray-500">Receive weekly sales and performance reports</div>
                                </div>
                                <div class="flex items-center">
                                    <button class="relative inline-flex h-6 w-11 items-center rounded-full bg-gray-200">
                                        <span class="inline-block h-4 w-4 transform rounded-full bg-white transition translate-x-5"></span>
                                    </button>
                                </div>
                            </div>

                            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                                <div>
                                    <div class="font-medium text-gray-900">Product Alerts</div>
                                    <div class="text-sm text-gray-500">Notifications about low stock and product updates</div>
                                </div>
                                <div class="flex items-center">
                                    <button class="relative inline-flex h-6 w-11 items-center rounded-full bg-blue-600">
                                        <span class="inline-block h-4 w-4 transform rounded-full bg-white transition"></span>
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
    @if($showEditModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <!-- Background overlay -->
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>

                <!-- Modal panel -->
                <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
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
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>

                                                 <form wire:submit.prevent="saveShopInfo" class="space-y-6">
                             <!-- Owner Name -->
                             <div>
                                 <label for="ownerName" class="block text-sm font-medium text-gray-700 mb-2">Owner Name *</label>
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
                                 <label for="shopName" class="block text-sm font-medium text-gray-700 mb-2">Shop Name *</label>
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
                                <label for="shopAddress" class="block text-sm font-medium text-gray-700 mb-2">Address *</label>
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
                                <label for="shopTaxNumber" class="block text-sm font-medium text-gray-700 mb-2">Tax Number</label>
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
    @endif

    <!-- Change Password Modal -->
    @if($showPasswordModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="password-modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <!-- Background overlay -->
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>

                <!-- Modal panel -->
                <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
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
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>

                        <form wire:submit.prevent="changePassword" class="space-y-6">
                            <!-- Current Password -->
                            <div>
                                <label for="currentPassword" class="block text-sm font-medium text-gray-700 mb-2">Current Password *</label>
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
                                <label for="newPassword" class="block text-sm font-medium text-gray-700 mb-2">New Password *</label>
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
                                <label for="confirmPassword" class="block text-sm font-medium text-gray-700 mb-2">Confirm New Password *</label>
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
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                        </svg>
                                        Minimum 8 characters
                                    </li>
                                    <li class="flex items-center">
                                        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
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
    @endif
</div>

