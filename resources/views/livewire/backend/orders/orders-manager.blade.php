<div>
    <div class="bg-white rounded-3xl shadow-lg border border-gray-100">
        <div class="px-6 py-4 border-b border-gray-100 bg-gradient-to-r from-white to-gray-50">
            <div class="flex flex-col space-y-4">
                <h1 class="text-2xl font-extrabold text-gray-900 tracking-tight">Orders Manager</h1>
                <div class="flex flex-col lg:flex-row lg:items-center gap-4">
                    <!-- Search -->
                    <div class="relative flex-1 min-w-0">
                        <input
                            wire:model.live.debounce.300ms="search"
                            type="text"
                            placeholder="Search by order ID, shop, or salesperson..."
                            class="w-full pl-10 pr-8 py-2 rounded-xl border border-gray-200 bg-gray-50 focus:bg-white focus:border-indigo-400 focus:ring-2 focus:ring-indigo-200 text-gray-700 placeholder-gray-400 shadow-sm transition"
                        />
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        @if($search)
                            <button
                                wire:click="$set('search', '')"
                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        @endif
                    </div>

                    <!-- Filters Row -->
                    <div class="flex flex-col sm:flex-row gap-3 flex-wrap">
                        <!-- Date Range Filter -->
                        <div class="flex items-center justify-around gap-2 min-w-fit">
                            <input
                                type="date"
                                wire:model.live="fromDate"
                                class="rounded-xl border-gray-200 bg-gray-50 focus:border-indigo-400 focus:ring-2 focus:ring-indigo-200 px-3 py-2 text-gray-700 shadow-sm w-32 sm:w-36"
                                placeholder="From"
                            />
                            <span class="text-gray-400 text-sm">-</span>
                            <input
                                type="date"
                                wire:model.live="toDate"
                                class="rounded-xl border-gray-200 bg-gray-50 focus:border-indigo-400 focus:ring-2 focus:ring-indigo-200 px-3 py-2 text-gray-700 shadow-sm w-32 sm:w-36"
                                placeholder="To"
                            />
                        </div>
                        <!-- Status Filter -->
                        <div class="relative min-w-32">
                            <select
                                wire:model.live="statusFilter"
                                class="rounded-xl border-gray-200 bg-gray-50 focus:border-indigo-400 focus:ring-2 focus:ring-indigo-200 pl-3 pr-10 py-2 text-gray-700 shadow-sm appearance-none w-full"
                            >
                                <option value="">All Statuses</option>
                                @foreach($statuses as $status)
                                    <option value="{{ $status->value }}">{{ ucfirst($status->value) }}</option>
                                @endforeach
                            </select>
                            <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                        </div>

                        <!-- Salesperson Filter (Admin Only) -->
                        @if(auth()->user()->hasRole('admin'))
                            <div class="relative min-w-36">
                                <select
                                    wire:model.live="salespersonFilter"
                                    class="rounded-xl border-gray-200 bg-gray-50 focus:border-indigo-400 focus:ring-2 focus:ring-indigo-200 pl-3 pr-10 py-2 text-gray-700 shadow-sm appearance-none w-full"
                                >
                                    <option value="">All Salespeople</option>
                                    @foreach($salespeople as $salesperson)
                                        <option value="{{ $salesperson->id }}">{{ $salesperson->name }}</option>
                                    @endforeach
                                </select>
                                <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </div>
                            </div>
                        @endif

                        <!-- Bulk Actions -->
                        <div class="relative min-w-32">
                            <select
                                wire:model="bulkAction"
                                class="rounded-xl border-gray-200 bg-gray-50 focus:border-indigo-400 focus:ring-2 focus:ring-indigo-200 pl-3 pr-10 py-2 text-gray-700 shadow-sm appearance-none w-full"
                            >
                                <option value="">Bulk Actions</option>
                                <option value="delete">Delete</option>
                                <option value="export">Export</option>
                            </select>
                            <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex flex-col sm:flex-row gap-3">
                        <button
                            wire:click="confirmBulkAction"
                            wire:loading.attr="disabled"
                            class="px-4 py-2 bg-indigo-600 text-white rounded-xl font-semibold shadow hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-400 transition whitespace-nowrap"
                        >
                            Apply
                        </button>
                        <!-- Clear All Filters Button -->
                        <button
                            wire:click="clearAllFilters"
                            wire:loading.attr="disabled"
                            class="px-4 py-2 bg-gray-500 text-white rounded-xl font-semibold shadow hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-400 transition whitespace-nowrap"
                            title="Clear all filters"
                        >
                            <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                            Clear Filters
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 sm:px-6 py-3 text-left text-xs font-bold text-gray-400 uppercase tracking-wider w-12">
                            <input
                                type="checkbox"
                                wire:model="selectAll"
                                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-400 focus:ring-indigo-200"
                            />
                        </th>
                        <th class="px-3 sm:px-6 py-3 text-left text-xs font-bold text-gray-400 uppercase tracking-wider min-w-20">Order</th>
                        <th class="px-3 sm:px-6 py-3 text-left text-xs font-bold text-gray-400 uppercase tracking-wider min-w-32 hidden md:table-cell">Salesperson</th>
                        <th class="px-3 sm:px-6 py-3 text-left text-xs font-bold text-gray-400 uppercase tracking-wider min-w-32">Shop</th>
                        <th class="px-3 sm:px-6 py-3 text-left text-xs font-bold text-gray-400 uppercase tracking-wider min-w-24">Total</th>
                        <th class="px-3 sm:px-6 py-3 text-left text-xs font-bold text-gray-400 uppercase tracking-wider min-w-40">Status</th>
                        <th class="px-3 sm:px-6 py-3 text-left text-xs font-bold text-gray-400 uppercase tracking-wider min-w-24 hidden sm:table-cell">Date</th>
                        <th class="px-3 sm:px-6 py-3 text-left text-xs font-bold text-gray-400 uppercase tracking-wider min-w-20">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                    @forelse ($orders as $order)
                        @can('view', $order->shop)
                            <tr class="hover:bg-indigo-50/30 transition">
                                <td class="px-3 sm:px-6 py-4 whitespace-nowrap">
                                    <input
                                        type="checkbox"
                                        wire:model="selectedOrders"
                                        value="{{ $order->id }}"
                                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-400 focus:ring-indigo-200"
                                    />
                                </td>
                                <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">
                                    <span class="inline-flex items-center gap-2">
                                        #{{ $order->id }}
                                    </span>
                                </td>
                                <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-900 hidden md:table-cell">
                                    <div class="flex items-center gap-3">
                                        <img src="https://ui-avatars.com/api/?name={{ urlencode($order->salesperson->name) }}&background=4f46e5&color=fff&size=32" alt="{{ $order->salesperson->name }}" class="w-8 h-8 rounded-full shadow border border-white" />
                                        <div>
                                            <div class="font-semibold">{{ $order->salesperson->name }}</div>
                                            <div class="text-xs text-gray-400">{{ $order->salesperson->email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 sm:px-6 py-4 text-sm text-gray-900">
                                    <div class="flex items-center gap-3">
                                        <div>
                                            <a href="{{ route('subdomain.shop', ['shop' => $order->shop->id]) }}" target="_blank" class="text-indigo-600 hover:underline font-semibold">{{ $order->shop->name }}</a>
                                            <!-- Show salesperson info on mobile when hidden column is not visible -->
                                            <div class="text-xs text-gray-400 md:hidden">by {{ $order->salesperson->name }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm font-bold text-green-600">
                                    {{ number_format($order->total_price, 2) }} TL
                                </td>
                                <td class="px-3 sm:px-6 py-4 text-sm">
                                    <div class="relative" x-data="{ open: false }" @keydown.escape.window="open = false" @click.away="open = false">
                                        <button type="button"
                                            @click="open = !open"
                                            class="w-full max-w-48 flex items-center justify-between px-3 py-2 rounded-xl border-2 focus:outline-none focus:ring-2 focus:ring-indigo-400 transition {{ $order->status->colorClasses() }} shadow-sm"
                                            :aria-expanded="open"
                                        >
                                            <div class="flex items-center gap-2">
                                                {!! $order->status->icon() !!}
                                                <div class="flex flex-col text-left">
                                                    <span class="font-bold text-sm">{{ $order->status->label() }}</span>
                                                </div>
                                            </div>
                                            <svg class="ml-1 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </button>
                                        <div x-show="open" x-transition.origin.top.left class="absolute z-30 mt-2 w-72 rounded-2xl shadow-2xl bg-white ring-1 ring-black ring-opacity-5 focus:outline-none right-0 sm:left-0" style="display: none;">
                                            <div class="py-2 max-h-96 overflow-y-auto">
                                                @foreach($availableStatuses as $status)
                                                    <button type="button"
                                                        wire:click="updateOrderStatus({{ $order->id }}, '{{ $status->value }}')"
                                                        @click="open = false"
                                                        class="w-full flex items-center gap-3 px-4 py-3 mb-1 transition font-semibold focus:outline-none {{ $status->colorClasses() }} {{ $order->status->value === $status->value ? 'ring-2 ring-indigo-400 border border-indigo-200' : 'hover:bg-gray-50' }}"
                                                    >
                                                        {!! $status->icon() !!}
                                                        <div class="flex flex-col text-left flex-1">
                                                            <span class="font-bold text-base">{{ $status->label() }}</span>
                                                            <span class="text-xs text-gray-500">{{ $status->description() }}</span>
                                                        </div>
                                                        @if($order->status->value === $status->value)
                                                            <svg class="w-5 h-5 text-green-500 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                        @endif
                                                    </button>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Show date on mobile when date column is hidden -->
                                    <div class="text-xs text-gray-400 mt-1 sm:hidden">{{ $order->created_at->format('d-m-Y') }}</div>
                                </td>
                                <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-500 hidden sm:table-cell">
                                    <span class="inline-flex items-center gap-1">
                                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        {{ $order->created_at->format('d-m-Y') }}
                                    </span>
                                </td>
                                <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <div class="flex gap-1">
                                        <button
                                            wire:click="showOrderDetails({{ $order->id }})"
                                            class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-indigo-50 hover:bg-indigo-100 text-indigo-600 border border-transparent hover:border-indigo-200 transition"
                                            title="View Order"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </button>
                                        <!-- Add more action buttons here if needed -->
                                    </div>
                                </td>
                            </tr>
                        @endcan
                    @empty
                        <tr>
                            <td colspan="8" class="px-3 sm:px-6 py-8 text-center text-sm text-gray-400">
                                <div class="flex flex-col items-center">
                                    <svg class="w-12 h-12 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <p class="text-gray-500 font-medium">No orders found</p>
                                    <p class="text-gray-400 text-xs mt-1">Try adjusting your filters or search terms</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 rounded-b-3xl">
            {{ $orders->links() }}
        </div>
    </div>

    <!-- Bulk Action Confirmation Modal -->
    <x-confirmation-modal wire:model="showBulkActionModal">
        <x-slot name="title">
            Confirm Bulk Action
        </x-slot>
        <x-slot name="content">
            Are you sure you want to {{ $bulkAction }} the selected orders?
        </x-slot>
        <x-slot name="footer">
            <button
                wire:click="performBulkAction"
                wire:loading.attr="disabled"
                class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            >
                Confirm
            </button>
            <button
                wire:click="cancelBulkAction"
                class="ml-2 px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500"
            >
                Cancel
            </button>
        </x-slot>
    </x-confirmation-modal>

    <!-- Order Details Modal -->
    <div class="fixed inset-0 z-50 overflow-y-auto" style="display: {{ $showOrderDetailsModal ? 'block' : 'none' }};">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity" aria-hidden="true">
                <div class="absolute inset-0 bg-gray-500 opacity-75" wire:click="closeOrderDetailsModal"></div>
            </div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
                <!-- Close Button -->
                <div class="absolute top-0 right-0 pt-4 pr-4 z-10">
                    <button
                        wire:click="closeOrderDetailsModal"
                        type="button"
                        class="bg-white rounded-md text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                        <span class="sr-only">Close</span>
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-indigo-100 sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="h-6 w-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                Order Details #{{ $selectedOrder?->id }}
                            </h3>

                            @if($selectedOrder)
                            <div class="space-y-6">
                                <!-- Order Information -->
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <h4 class="text-sm font-medium text-gray-900 mb-3 flex items-center">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        Order Information
                                    </h4>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                        <div>
                                            <span class="font-medium text-gray-700">Shop:</span>
                                            <span class="ml-2 text-gray-900">{{ $selectedOrder->shop->name }}</span>
                                        </div>
                                        <div>
                                            <span class="font-medium text-gray-700">Salesperson:</span>
                                            <span class="ml-2 text-gray-900">{{ $selectedOrder->salesperson->name }}</span>
                                        </div>
                                        <div>
                                            <span class="font-medium text-gray-700">Status:</span>
                                            <div class="ml-2 relative" x-data="{ open: false }" @keydown.escape.window="open = false" @click.away="open = false">
                                                <button type="button"
                                                    @click="open = !open"
                                                    class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-500 transition {{ $selectedOrder->status->getBackgroundColor() }}"
                                                    :aria-expanded="open"
                                                >
                                                    {{ ucfirst($selectedOrder->status->value) }}
                                                    <svg class="ml-2 h-4 w-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                                    </svg>
                                                </button>
                                                <div x-show="open" class="absolute z-20 mt-2 w-40 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 focus:outline-none" style="display: none;">
                                                    <div class="py-1">
                                                        @foreach($availableStatuses as $status)
                                                            <button type="button"
                                                                wire:click="updateOrderStatus({{ $selectedOrder->id }}, '{{ $status->value }}')"
                                                                @click="open = false"
                                                                class="w-full text-left px-4 py-2 text-xs rounded-full mb-1 font-semibold focus:outline-none transition {{ $status->getBackgroundColor() }} {{ $selectedOrder->status->value === $status->value ? 'ring-2 ring-indigo-400' : '' }}"
                                                                style="margin-bottom: 0.25rem;"
                                                            >
                                                                {{ ucfirst($status->value) }}
                                                            </button>
                                                        @endforeach
                                                    </div>
                                                </div>
                                                <div wire:loading wire:target="updateOrderStatus" class="inline-block ml-2">
                                                    <svg class="animate-spin h-4 w-4 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                    </svg>
                                                </div>
                                            </div>
                                        </div>
                                        <div>
                                            <span class="font-medium text-gray-700">Date:</span>
                                            <span class="ml-2 text-gray-900">{{ $selectedOrder->created_at->format('d M Y, H:i') }}</span>
                                        </div>
                                        <div class="md:col-span-2">
                                            <span class="font-medium text-gray-700">Total:</span>
                                            <span class="ml-2 text-lg font-bold text-green-600">{{ number_format($selectedOrder->total_price, 2) }} TL</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Order Items -->
                                <div class="bg-white border border-gray-200 rounded-lg">
                                    <div class="px-4 py-3 border-b border-gray-200">
                                        <h4 class="text-sm font-medium text-gray-900 flex items-center">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                            </svg>
                                            Order Items ({{ $selectedOrder->items->count() }})
                                        </h4>
                                    </div>
                                    <div class="overflow-hidden">
                                        <table class="min-w-full divide-y divide-gray-200">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subtotal</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white divide-y divide-gray-200">
                                                @foreach($selectedOrder->items as $item)
                                                <tr class="hover:bg-gray-50">
                                                    <td class="px-4 py-3 whitespace-nowrap">
                                                        <div class="flex items-center">
                                                            <div class="flex-shrink-0 h-10 w-10">
                                                                <img class="h-10 w-10 rounded-lg object-cover" src="{{ asset('storage/' . $item->product->primary_image_url) }}" alt="{{ $item->product->name }}">
                                                            </div>
                                                            <div class="ml-3">
                                                                <div class="text-sm font-medium text-gray-900">{{ $item->product->name }}</div>
                                                                <div class="text-sm text-gray-500">{{ $item->product->code }}</div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                            {{ $item->quantity }}
                                                        </span>
                                                    </td>
                                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">${{ number_format($item->price, 2) }}</td>
                                                    <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">${{ number_format($item->subtotal, 2) }}</td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- Order Notes -->
                                @if($selectedOrder->notes)
                                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                                    <h4 class="text-sm font-medium text-yellow-900 mb-2 flex items-center">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        Order Notes
                                    </h4>
                                    <p class="text-sm text-yellow-800">{{ $selectedOrder->notes }}</p>
                                </div>
                                @endif
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button
                        wire:click="exportOrderToPdf({{ $selectedOrder->id ?? 0 }})"
                        wire:loading.attr="disabled"
                        @if(!$selectedOrder) disabled @endif
                        type="button"
                        class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 sm:ml-3 sm:w-auto sm:text-sm"
                    >
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Export PDF
                    </button>
                    <button
                        wire:click="closeOrderDetailsModal"
                        type="button"
                        class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto sm:text-sm"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Listen for PDF download event
    document.addEventListener('download-pdf', function(event) {
        // Create a temporary link element to trigger download
        const link = document.createElement('a');
        // Ensure HTTPS is used
        let url = event.detail.url;
        // Only force HTTPS if not in local development
        const isLocalDev = window.location.hostname === 'localhost' ||
                           window.location.hostname.includes('.local') ||
                           window.location.hostname.includes('dev.bobk');

        if (!isLocalDev && window.location.protocol === 'https:' && url.startsWith('http:')) {
            url = url.replace('http:', 'https:');
        }
        link.href = url;
        link.download = ''; // Let the server set the filename
        link.style.display = 'none';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    });
</script>
