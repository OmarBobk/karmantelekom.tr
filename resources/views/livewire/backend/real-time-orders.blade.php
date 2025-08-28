<div class="bg-white shadow rounded-lg">
    <!-- Header -->
    <div class="px-6 py-4 border-b border-gray-200">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-medium text-gray-900">Orders</h3>
                <p class="text-sm text-gray-500">Real-time order management</p>
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
                    wire:click="refreshOrders"
                    class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                >
                    <i class="fas fa-sync mr-2"></i>
                    Refresh
                </button>
            </div>
        </div>
    </div>

    <!-- Orders Table -->
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200" data-orders-list>
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Order
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Shop
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Customer
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Total
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Status
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Created
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Updated
                    </th>
                    <th scope="col" class="relative px-6 py-3">
                        <span class="sr-only">Actions</span>
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($orders as $order)
                    <tr
                        class="hover:bg-gray-50 transition-colors duration-200"
                        data-order-id="{{ $order['id'] }}"
                    >
                        <!-- Order Info -->
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-10 w-10">
                                    <div class="h-10 w-10 rounded-full bg-green-100 flex items-center justify-center">
                                        <i class="fas fa-shopping-cart text-green-600"></i>
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900">
                                        #{{ $order['id'] }}
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        {{ $order['notes'] ? Str::limit($order['notes'], 30) : 'No notes' }}
                                    </div>
                                </div>
                            </div>
                        </td>

                        <!-- Shop -->
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">{{ $order['shop_name'] }}</div>
                        </td>

                                                 <!-- Customer -->
                         <td class="px-6 py-4 whitespace-nowrap">
                             <div class="text-sm text-gray-900">{{ $order['customer_name'] }}</div>
                         </td>

                        <!-- Total -->
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900">
                                {{ $this->formatPrice($order['total_price']) }}
                            </div>
                        </td>

                        <!-- Status -->
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span
                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $this->getOrderStatusColor($order) }}"
                                data-order-status
                            >
                                <i class="{{ $this->getOrderStatusIcon($order) }} mr-1"></i>
                                {{ $order['status_label'] }}
                            </span>
                        </td>

                        <!-- Created -->
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $order['created_at'] }}
                        </td>

                        <!-- Updated -->
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $order['updated_at'] }}
                        </td>

                        <!-- Actions -->
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <a
                                href="{{ route('subdomain.order', $order['id']) }}"
                                class="text-indigo-600 hover:text-indigo-900 mr-3"
                            >
                                View
                            </a>
                            <a
                                href="{{ route('subdomain.order.edit', $order['id']) }}"
                                class="text-gray-600 hover:text-gray-900"
                            >
                                Edit
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-6 py-4 text-center text-sm text-gray-500">
                            No orders found
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Footer -->
    <div class="px-6 py-3 border-t border-gray-200">
        <div class="flex items-center justify-between text-sm text-gray-500">
            <span>{{ count($orders) }} orders total</span>
            <span>Last updated: {{ now()->format('M j, Y g:i A') }}</span>
        </div>
    </div>
</div>

<!-- Auto-refresh script -->
@if($autoRefresh)
    <script>
        setInterval(() => {
            @this.refreshOrders();
        }, {{ $refreshInterval }});
    </script>
@endif
