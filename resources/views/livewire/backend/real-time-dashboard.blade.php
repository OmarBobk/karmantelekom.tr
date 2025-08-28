<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Real-Time Dashboard</h2>
            <p class="text-sm text-gray-500">Live updates for shops, orders, and notifications</p>
        </div>
        <div class="flex items-center space-x-3">
            <button 
                wire:click="toggleAutoRefresh"
                class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
            >
                <i class="fas fa-sync-alt mr-2 {{ $autoRefresh ? 'text-green-500' : 'text-gray-400' }}"></i>
                {{ $autoRefresh ? 'Auto Refresh On' : 'Auto Refresh Off' }}
            </button>
            <button 
                wire:click="refreshDashboard"
                class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
            >
                <i class="fas fa-sync mr-2"></i>
                Refresh
            </button>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Total Shops -->
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-indigo-500 rounded-md flex items-center justify-center">
                            <i class="fas fa-store text-white"></i>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Total Shops</dt>
                            <dd class="text-lg font-medium text-gray-900">{{ $stats['total_shops'] ?? 0 }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-5 py-3">
                <div class="text-sm">
                    <span class="text-green-600 font-medium">{{ $stats['assigned_shops'] ?? 0 }}</span>
                    <span class="text-gray-500"> assigned</span>
                    <span class="text-red-600 font-medium ml-2">{{ $stats['unassigned_shops'] ?? 0 }}</span>
                    <span class="text-gray-500"> unassigned</span>
                </div>
            </div>
        </div>

        <!-- Total Orders -->
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-500 rounded-md flex items-center justify-center">
                            <i class="fas fa-shopping-cart text-white"></i>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Total Orders</dt>
                            <dd class="text-lg font-medium text-gray-900">{{ $stats['total_orders'] ?? 0 }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-5 py-3">
                <div class="text-sm">
                    <span class="text-yellow-600 font-medium">{{ $stats['pending_orders'] ?? 0 }}</span>
                    <span class="text-gray-500"> pending</span>
                    <span class="text-blue-600 font-medium ml-2">{{ $stats['processing_orders'] ?? 0 }}</span>
                    <span class="text-gray-500"> processing</span>
                    <span class="text-green-600 font-medium ml-2">{{ $stats['completed_orders'] ?? 0 }}</span>
                    <span class="text-gray-500"> completed</span>
                </div>
            </div>
        </div>

        <!-- Total Users -->
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                            <i class="fas fa-users text-white"></i>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Total Users</dt>
                            <dd class="text-lg font-medium text-gray-900">{{ $stats['total_users'] ?? 0 }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Status -->
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-500 rounded-md flex items-center justify-center">
                            <i class="fas fa-check-circle text-white"></i>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">System Status</dt>
                            <dd class="text-lg font-medium text-green-600">Online</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Recent Shops -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Recent Shops</h3>
            </div>
            <div class="divide-y divide-gray-200">
                @forelse($recentShops as $shop)
                    <div class="px-6 py-4 hover:bg-gray-50">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-10 w-10">
                                    <div class="h-10 w-10 rounded-full bg-indigo-100 flex items-center justify-center">
                                        <i class="fas fa-store text-indigo-600"></i>
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900">{{ $shop['name'] }}</div>
                                    <div class="text-sm text-gray-500">{{ $shop['phone'] }}</div>
                                    <div class="text-xs text-gray-400">{{ $shop['created_at'] }}</div>
                                </div>
                            </div>
                            <div class="flex items-center">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $this->getShopStatusColor($shop['status']) }}">
                                    <i class="{{ $this->getShopStatusIcon($shop['status']) }} mr-1"></i>
                                    {{ $shop['status'] === 'assigned' ? 'Assigned' : 'Unassigned' }}
                                </span>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="px-6 py-4 text-center text-sm text-gray-500">
                        No shops found
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Recent Orders -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Recent Orders</h3>
            </div>
            <div class="divide-y divide-gray-200">
                @forelse($recentOrders as $order)
                    <div class="px-6 py-4 hover:bg-gray-50">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-10 w-10">
                                    <div class="h-10 w-10 rounded-full bg-green-100 flex items-center justify-center">
                                        <i class="fas fa-shopping-cart text-green-600"></i>
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900">#{{ $order['id'] }}</div>
                                                                         <div class="text-sm text-gray-500">{{ $order['shop_name'] }} - {{ $order['customer_name'] }}</div>
                                    <div class="text-xs text-gray-400">{{ $order['created_at'] }}</div>
                                </div>
                            </div>
                            <div class="flex items-center space-x-3">
                                <div class="text-sm font-medium text-gray-900">
                                    {{ $this->formatPrice($order['total_price']) }}
                                </div>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $this->getOrderStatusColor($order['status']) }}">
                                    <i class="{{ $this->getOrderStatusIcon($order['status']) }} mr-1"></i>
                                    {{ $order['status_label'] }}
                                </span>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="px-6 py-4 text-center text-sm text-gray-500">
                        No orders found
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Recent Notifications -->
    <div class="bg-white shadow rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Recent Notifications</h3>
        </div>
        <div class="divide-y divide-gray-200">
            @forelse($recentNotifications as $notification)
                <div class="px-6 py-4 hover:bg-gray-50">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center">
                                <i class="fas fa-bell text-blue-600"></i>
                            </div>
                        </div>
                        <div class="ml-4 flex-1">
                            <div class="text-sm text-gray-900">
                                {{ $notification['data']['description'] ?? 'New notification' }}
                            </div>
                            <div class="text-xs text-gray-400 mt-1">
                                {{ $notification['created_at'] }}
                                @if(!$notification['read_at'])
                                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">
                                        New
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="px-6 py-4 text-center text-sm text-gray-500">
                    No notifications found
                </div>
            @endforelse
        </div>
    </div>
</div>

<!-- Auto-refresh script -->
@if($autoRefresh)
    <script>
        setInterval(() => {
            @this.refreshDashboard();
        }, {{ $refreshInterval }});
    </script>
@endif
