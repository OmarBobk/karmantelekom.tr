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
                            placeholder="Search by order ID, shop, or user..."
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
                                    <option value="">All users</option>
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

        <div class="">
            <!-- Sorting Loading Indicator -->
            <div wire:loading.delay wire:target="sortByColumn" class="flex items-center justify-center py-2 bg-indigo-50 border-b border-indigo-200">
                <div class="flex items-center space-x-2 text-indigo-600">
                    <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span class="text-sm font-medium">Sorting...</span>
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

                            <!-- Sortable Order Column -->
                            <th class="px-3 sm:px-6 py-3 text-left text-xs font-bold text-gray-400 uppercase tracking-wider min-w-20 {{ $sortBy === 'id' ? 'bg-indigo-50' : '' }}">
                                <button
                                    wire:click="sortByColumn('id')"
                                    class="flex items-center space-x-1 group hover:text-indigo-600 transition-colors duration-150"
                                    x-data="{ hover: false }"
                                    @mouseenter="hover = true"
                                    @mouseleave="hover = false"
                                >
                                    <span>Order</span>
                                    <div class="flex flex-col">
                                        <svg class="w-2 h-2 {{ $sortBy === 'id' && $sortDirection === 'asc' ? 'text-indigo-600' : 'text-gray-300' }}"
                                             :class="hover ? 'text-indigo-400' : ''"
                                             fill="currentColor" viewBox="0 0 8 8">
                                            <path d="M4 0l4 4H0z"/>
                                        </svg>
                                        <svg class="w-2 h-2 {{ $sortBy === 'id' && $sortDirection === 'desc' ? 'text-indigo-600' : 'text-gray-300' }}"
                                             :class="hover ? 'text-indigo-400' : ''"
                                             fill="currentColor" viewBox="0 0 8 8">
                                            <path d="M4 8L0 4h8z"/>
                                        </svg>
                                    </div>
                                </button>
                            </th>

                            <!-- Sortable Salesperson Column -->
                            <th class="px-3 sm:px-6 py-3 text-left text-xs font-bold text-gray-400 uppercase tracking-wider min-w-32 hidden md:table-cell {{ $sortBy === 'salesperson' ? 'bg-indigo-50' : '' }}">
                                <button
                                    wire:click="sortByColumn('salesperson')"
                                    class="flex items-center space-x-1 group hover:text-indigo-600 transition-colors duration-150"
                                    x-data="{ hover: false }"
                                    @mouseenter="hover = true"
                                    @mouseleave="hover = false"
                                >
                                    <span>Ordered By</span>
                                    <div class="flex flex-col">
                                        <svg class="w-2 h-2 {{ $sortBy === 'salesperson' && $sortDirection === 'asc' ? 'text-indigo-600' : 'text-gray-300' }}"
                                             :class="hover ? 'text-indigo-400' : ''"
                                             fill="currentColor" viewBox="0 0 8 8">
                                            <path d="M4 0l4 4H0z"/>
                                        </svg>
                                        <svg class="w-2 h-2 {{ $sortBy === 'salesperson' && $sortDirection === 'desc' ? 'text-indigo-600' : 'text-gray-300' }}"
                                             :class="hover ? 'text-indigo-400' : ''"
                                             fill="currentColor" viewBox="0 0 8 8">
                                            <path d="M4 8L0 4h8z"/>
                                        </svg>
                                    </div>
                                </button>
                            </th>

                            <!-- Sortable Shop Column -->
                            <th class="px-3 sm:px-6 py-3 text-left text-xs font-bold text-gray-400 uppercase tracking-wider min-w-32 {{ $sortBy === 'shop' ? 'bg-indigo-50' : '' }}">
                                <button
                                    wire:click="sortByColumn('shop')"
                                    class="flex items-center space-x-1 group hover:text-indigo-600 transition-colors duration-150"
                                    x-data="{ hover: false }"
                                    @mouseenter="hover = true"
                                    @mouseleave="hover = false"
                                >
                                    <span>Shop</span>
                                    <div class="flex flex-col">
                                        <svg class="w-2 h-2 {{ $sortBy === 'shop' && $sortDirection === 'asc' ? 'text-indigo-600' : 'text-gray-300' }}"
                                             :class="hover ? 'text-indigo-400' : ''"
                                             fill="currentColor" viewBox="0 0 8 8">
                                            <path d="M4 0l4 4H0z"/>
                                        </svg>
                                        <svg class="w-2 h-2 {{ $sortBy === 'shop' && $sortDirection === 'desc' ? 'text-indigo-600' : 'text-gray-300' }}"
                                             :class="hover ? 'text-indigo-400' : ''"
                                             fill="currentColor" viewBox="0 0 8 8">
                                            <path d="M4 8L0 4h8z"/>
                                        </svg>
                                    </div>
                                </button>
                            </th>

                            <!-- Sortable Total Column -->
                            <th class="px-3 sm:px-6 py-3 text-left text-xs font-bold text-gray-400 uppercase tracking-wider min-w-24 {{ $sortBy === 'total' ? 'bg-indigo-50' : '' }}">
                                <button
                                    wire:click="sortByColumn('total')"
                                    class="flex items-center space-x-1 group hover:text-indigo-600 transition-colors duration-150"
                                    x-data="{ hover: false }"
                                    @mouseenter="hover = true"
                                    @mouseleave="hover = false"
                                >
                                    <span>Total</span>
                                    <div class="flex flex-col">
                                        <svg class="w-2 h-2 {{ $sortBy === 'total' && $sortDirection === 'asc' ? 'text-indigo-600' : 'text-gray-300' }}"
                                             :class="hover ? 'text-indigo-400' : ''"
                                             fill="currentColor" viewBox="0 0 8 8">
                                            <path d="M4 0l4 4H0z"/>
                                        </svg>
                                        <svg class="w-2 h-2 {{ $sortBy === 'total' && $sortDirection === 'desc' ? 'text-indigo-600' : 'text-gray-300' }}"
                                             :class="hover ? 'text-indigo-400' : ''"
                                             fill="currentColor" viewBox="0 0 8 8">
                                            <path d="M4 8L0 4h8z"/>
                                        </svg>
                                    </div>
                                </button>
                            </th>

                            <!-- Sortable Status Column -->
                            <th class="px-3 sm:px-6 py-3 text-left text-xs font-bold text-gray-400 uppercase tracking-wider min-w-40 {{ $sortBy === 'status' ? 'bg-indigo-50' : '' }}">
                                <button
                                    wire:click="sortByColumn('status')"
                                    class="flex items-center space-x-1 group hover:text-indigo-600 transition-colors duration-150"
                                    x-data="{ hover: false }"
                                    @mouseenter="hover = true"
                                    @mouseleave="hover = false"
                                >
                                    <span>Status</span>
                                    <div class="flex flex-col">
                                        <svg class="w-2 h-2 {{ $sortBy === 'status' && $sortDirection === 'asc' ? 'text-indigo-600' : 'text-gray-300' }}"
                                             :class="hover ? 'text-indigo-400' : ''"
                                             fill="currentColor" viewBox="0 0 8 8">
                                            <path d="M4 0l4 4H0z"/>
                                        </svg>
                                        <svg class="w-2 h-2 {{ $sortBy === 'status' && $sortDirection === 'desc' ? 'text-indigo-600' : 'text-gray-300' }}"
                                             :class="hover ? 'text-indigo-400' : ''"
                                             fill="currentColor" viewBox="0 0 8 8">
                                            <path d="M4 8L0 4h8z"/>
                                        </svg>
                                    </div>
                                </button>
                            </th>

                            <!-- Sortable Date Column -->
                            <th class="px-3 sm:px-6 py-3 text-left text-xs font-bold text-gray-400 uppercase tracking-wider min-w-24 hidden sm:table-cell {{ $sortBy === 'created_at' ? 'bg-indigo-50' : '' }}">
                                <button
                                    wire:click="sortByColumn('created_at')"
                                    class="flex items-center space-x-1 group hover:text-indigo-600 transition-colors duration-150"
                                    x-data="{ hover: false }"
                                    @mouseenter="hover = true"
                                    @mouseleave="hover = false"
                                >
                                    <span>Date</span>
                                    <div class="flex flex-col">
                                        <svg class="w-2 h-2 {{ $sortBy === 'created_at' && $sortDirection === 'asc' ? 'text-indigo-600' : 'text-gray-300' }}"
                                             :class="hover ? 'text-indigo-400' : ''"
                                             fill="currentColor" viewBox="0 0 8 8">
                                            <path d="M4 0l4 4H0z"/>
                                        </svg>
                                        <svg class="w-2 h-2 {{ $sortBy === 'created_at' && $sortDirection === 'desc' ? 'text-indigo-600' : 'text-gray-300' }}"
                                             :class="hover ? 'text-indigo-400' : ''"
                                             fill="currentColor" viewBox="0 0 8 8">
                                            <path d="M4 8L0 4h8z"/>
                                        </svg>
                                    </div>
                                </button>
                            </th>

                            <!-- Non-sortable Actions Column -->
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
                                        <img src="https://ui-avatars.com/api/?name={{ urlencode($order->customer->name) }}&background=4f46e5&color=fff&size=32" alt="{{ $order->customer->name }}" class="w-8 h-8 rounded-full shadow border border-white" />
                                        <div>
                                            <div class="font-semibold">{{ $order->customer->name }}</div>
                                            <div class="text-xs text-gray-400">{{ $order->customer->email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 sm:px-6 py-4 text-sm text-gray-900">
                                    <div class="flex items-center gap-3">
                                        <div>
                                            <a href="{{ route('subdomain.shop', ['shop' => $order->shop->id]) }}" target="_blank" class="text-indigo-600 hover:underline font-semibold">{{ $order->shop->name }}</a>
                                            <!-- Show salesperson info on mobile when hidden column is not visible -->
                                            <div class="text-xs text-gray-400 md:hidden">by {{ $order->customer->name }}</div>
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
    <div class="fixed inset-0 z-50 overflow-y-auto" style="display: {{ $showOrderDetailsModal ? 'block' : 'none' }};" x-data="{ show: @entangle('showOrderDetailsModal') }">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
                 wire:click="closeOrderDetailsModal"
                 x-show="show"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0">
            </div>

            <!-- Modal positioning helper -->
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <!-- Modal panel -->
            <div class="relative inline-block align-bottom bg-white rounded-t-2xl sm:rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle w-full sm:max-w-6xl sm:w-full max-h-[90vh] sm:max-h-[85vh]"
                 x-show="show"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">

                <!-- Modal Header -->
                <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-4 py-6 sm:px-6">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <div class="flex-shrink-0 w-10 h-10 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg sm:text-xl font-bold text-white">
                                    Order #{{ $selectedOrder?->id }}
                                </h3>
                                <p class="text-indigo-100 text-sm">
                                    {{ $selectedOrder?->created_at->format('d M Y, H:i') }}
                                </p>
                            </div>
                        </div>
                        <button wire:click="closeOrderDetailsModal"
                                class="rounded-full bg-white bg-opacity-20 p-2 text-white hover:bg-opacity-30 focus:outline-none focus:ring-2 focus:ring-white focus:ring-opacity-50 transition-all">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Modal Content -->
                <div class="bg-white flex flex-col max-h-[calc(90vh-120px)] sm:max-h-[calc(85vh-120px)]">
                    @if($selectedOrder)
                        <div class="flex-1 overflow-y-auto">
                            <div class="p-4 sm:p-6 space-y-6">

                                <!-- Order Summary Cards -->
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                                    <!-- Shop Card -->
                                    <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl p-4 border border-blue-200">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-10 h-10 bg-blue-500 rounded-lg flex items-center justify-center">
                                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-6m-8 0H3m2 0h6M9 7h6m-6 4h6m-6 4h6" />
                                                </svg>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-xs font-medium text-blue-600 uppercase tracking-wide">Shop</p>
                                                <p class="text-sm font-bold text-blue-900 truncate">{{ $selectedOrder->shop->name }}</p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Salesperson Card -->
                                    <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-xl p-4 border border-green-200">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-10 h-10 bg-green-500 rounded-lg flex items-center justify-center">
                                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                                </svg>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-xs font-medium text-green-600 uppercase tracking-wide">Ordered By</p>
                                                <p class="text-sm font-bold text-green-900 truncate">{{ $selectedOrder->customer->name }}</p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Status Card -->
                                    <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-xl p-4 border border-purple-200">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-10 h-10 bg-purple-500 rounded-lg flex items-center justify-center">
                                                {!! $selectedOrder->status->icon() !!}
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-xs font-medium text-purple-600 uppercase tracking-wide">Status</p>
                                                <div class="relative" x-data="{ open: false }" @keydown.escape.window="open = false" @click.away="open = false">
                                                    <button type="button"
                                                            @click="open = !open"
                                                            class="text-sm font-bold text-purple-900 hover:text-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 rounded-md transition-colors"
                                                            :aria-expanded="open">
                                                        {{ $selectedOrder->status->label() }}
                                                        <svg class="inline ml-1 w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                                        </svg>
                                                    </button>
                                                    <div x-show="open"
                                                         x-transition:enter="transition ease-out duration-100"
                                                         x-transition:enter-start="transform opacity-0 scale-95"
                                                         x-transition:enter-end="transform opacity-100 scale-100"
                                                         x-transition:leave="transition ease-in duration-75"
                                                         x-transition:leave-start="transform opacity-100 scale-100"
                                                         x-transition:leave-end="transform opacity-0 scale-95"
                                                         class="absolute z-30 mt-2 w-64 rounded-xl shadow-2xl bg-white ring-1 ring-black ring-opacity-5 focus:outline-none right-0 sm:left-0"
                                                         style="display: none;">
                                                        <div class="py-2 max-h-80 overflow-y-auto">
                                                            @foreach($availableStatuses as $status)
                                                                <button type="button"
                                                                        wire:click="updateOrderStatus({{ $selectedOrder->id }}, '{{ $status->value }}')"
                                                                        @click="open = false"
                                                                        class="w-full flex items-center gap-3 px-4 py-3 text-left hover:bg-gray-50 transition-colors focus:outline-none focus:bg-gray-50 {{ $selectedOrder->status->value === $status->value ? 'bg-indigo-50 border-l-4 border-indigo-400' : '' }}">
                                                                    <div class="flex-shrink-0">
                                                                        {!! $status->icon() !!}
                                                                    </div>
                                                                    <div class="flex-1 min-w-0">
                                                                        <p class="text-sm font-semibold text-gray-900">{{ $status->label() }}</p>
                                                                        <p class="text-xs text-gray-500">{{ $status->description() }}</p>
                                                                    </div>
                                                                    @if($selectedOrder->status->value === $status->value)
                                                                        <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                                        </svg>
                                                                    @endif
                                                                </button>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Total Card -->
                                    <div class="bg-gradient-to-br from-emerald-50 to-emerald-100 rounded-xl p-4 border border-emerald-200">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-10 h-10 bg-emerald-500 rounded-lg flex items-center justify-center">
                                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1" />
                                                </svg>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-xs font-medium text-emerald-600 uppercase tracking-wide">Total</p>
                                                <p class="text-sm font-bold text-emerald-900">{{ number_format($selectedOrder->total_price, 2) }} TL</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Order Items -->
                                <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden">
                                    <div class="bg-gradient-to-r from-gray-50 to-gray-100 px-4 sm:px-6 py-4 border-b border-gray-200">
                                        <h4 class="text-lg font-bold text-gray-900 flex items-center">
                                            <svg class="w-5 h-5 mr-3 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                            </svg>
                                            Order Items
                                            <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
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
                                                            <img class="w-16 h-16 rounded-lg object-cover border border-gray-200"
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
                                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                                        Qty: {{ $item->quantity }}
                                                                    </span>
                                                                </div>
                                                            </div>
                                                            <div class="mt-2 flex items-center justify-between">
                                                                <div class="text-sm text-gray-600">
                                                                    {{ number_format($item->price, 2) }} TL each
                                                                </div>
                                                                <div class="text-sm font-bold text-gray-900">
                                                                    {{ number_format($item->subtotal, 2) }} TL
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
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subtotal</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white divide-y divide-gray-200">
                                                @foreach($selectedOrder->items as $item)
                                                    <tr class="hover:bg-gray-50 transition-colors">
                                                        <td class="px-6 py-4 whitespace-nowrap">
                                                            <div class="flex items-center">
                                                                <div class="flex-shrink-0 w-12 h-12">
                                                                    <img class="w-12 h-12 rounded-lg object-cover border border-gray-200"
                                                                         src="{{ asset('storage/' . $item->product->primary_image_url) }}"
                                                                         alt="{{ $item->product->name }}">
                                                                </div>
                                                                <div class="ml-4">
                                                                    <div class="text-sm font-semibold text-gray-900">{{ $item->product->name }}</div>
                                                                    <div class="text-sm text-gray-500">{{ $item->product->code }}</div>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap">
                                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                                {{ $item->quantity }}
                                                            </span>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                            {{ number_format($item->price, 2) }} TL
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">
                                                            {{ number_format($item->subtotal, 2) }} TL
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- Order Notes -->
                                @if($selectedOrder->notes)
                                    <div class="bg-gradient-to-br from-amber-50 to-orange-50 border border-amber-200 rounded-2xl p-4 sm:p-6">
                                        <h4 class="text-lg font-bold text-amber-900 mb-3 flex items-center">
                                            <svg class="w-5 h-5 mr-3 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
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
                    @endif
                        <!-- Modal Footer -->
                        <div class="bg-gray-50 px-4 py-4 sm:px-6 sm:py-6 border-t border-gray-200 flex flex-col sm:flex-row gap-3 sm:gap-0 sm:justify-between">
                            <div class="flex flex-col sm:flex-row gap-3">
                                <form target="_blank" action="{{route('subdomain.invoice_pdf', $selectedOrder->id ?? 0)}}" method="POST">
                                    @csrf
                                    <button type="submit"
                                            wire:loading.attr="disabled"
                                            @if(!$selectedOrder) disabled @endif
                                            class="w-full sm:w-auto inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-xl text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
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

