@php
    $statusColors = [
        'pending'    => ['bg' => 'orange-100',   'text' => 'orange-700'],
        'confirmed'  => ['bg' => 'blue-100',     'text' => 'blue-700'],
        'processing' => ['bg' => 'purple-100',   'text' => 'purple-700'],
        'ready'      => ['bg' => 'green-100',    'text' => 'green-700'],
        'delivering' => ['bg' => 'indigo-100',   'text' => 'indigo-700'],
        'delivered'  => ['bg' => 'emerald-100',  'text' => 'emerald-700'],
        'canceled'   => ['bg' => 'red-100',      'text' => 'red-700'],
    ];
    $links = $shop->links ?? [];
@endphp
<div class="max-w-7xl mx-auto p-6 space-y-8">
    {{-- Shop Header --}}
    <div class="bg-gradient-to-r from-blue-600 to-indigo-600 rounded-3xl py-6 px-8 text-white shadow-lg flex flex-col md:flex-row md:items-center md:justify-between min-h-[96px]">
        {{-- Shop Header left section --}}
        <div class="w-full md:w-[60%] h-full flex flex-col justify-center">
            <h1 class="text-3xl font-bold mb-2">{{ $shop->name }}</h1>
            <div class="flex items-center space-x-6 text-base opacity-90">
                <span class="flex items-center space-x-3">
                    <svg class="w-5 h-5 text-white/80" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7-7.5 11-7.5 11s-7.5-4-7.5-11a7.5 7.5 0 1 1 15 0Z" /></svg>
                    <span>{{ $shop->address }}</span>
                </span>
                <span class="flex items-center space-x-3">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-white/80">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z" />
                    </svg>
                    <span>{{ $shop->phone }}</span>
                </span>
            </div>
        </div>
        {{-- Shop Header right section --}}
        <div class="flex flex-wrap justify-around gap-4 mt-4 md:mt-0 w-full md:w-auto h-full items-center">
            @if(!empty($links['facebook']))
                <a href="{{ $links['facebook'] }}" target="_blank" rel="noopener" x-data="{hover:false}" @mouseenter="hover=true" @mouseleave="hover=false"
                   :class="{'ring-2 ring-white/40 scale-105': hover}" class="flex items-center px-5 py-2 rounded-xl bg-white/10 border border-white/20 backdrop-blur-md transition-all duration-300 space-x-2 focus:outline-none focus:ring-2 focus:ring-white/60">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 2.5h-2.5A4.5 4.5 0 0 0 10 7v2H7v3h3v7h3v-7h2.5l.5-3H13V7a1.5 1.5 0 0 1 1.5-1.5H17V2.5Z" /></svg>
                    <span class="font-semibold">Green Valley Market</span>
                </a>
            @endif
            @if(!empty($links['instagram']))
                <a href="{{ $links['instagram'] }}" target="_blank" rel="noopener" x-data="{hover:false}" @mouseenter="hover=true" @mouseleave="hover=false"
                   :class="{'ring-2 ring-white/40 scale-105': hover}" class="flex items-center px-5 py-2 rounded-xl bg-white/10 border border-white/20 backdrop-blur-md transition-all duration-300 space-x-2 focus:outline-none focus:ring-2 focus:ring-white/60">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><rect width="18" height="18" x="3" y="3" rx="4" /><circle cx="12" cy="12" r="4" /><path d="M16.5 7.5v.001" /></svg>
                    <span class="font-semibold">@greenvalleymarket</span>
                </a>
            @endif
            @if(!empty($links['website']))
                <a href="{{ $links['website'] }}" target="_blank" rel="noopener" x-data="{hover:false}" @mouseenter="hover=true" @mouseleave="hover=false"
                   :class="{'ring-2 ring-white/40 scale-105': hover}" class="flex items-center px-5 py-2 rounded-xl bg-white/10 border border-white/20 backdrop-blur-md transition-all duration-300 space-x-2 focus:outline-none focus:ring-2 focus:ring-white/60">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3a9 9 0 1 0 0 18a9 9 0 0 0 0-18Zm0 0c2.5 0 4.5 4 4.5 9s-2 9-4.5 9s-4.5-4-4.5-9s2-9 4.5-9Zm0 0a8.997 8.997 0 0 1 7.843 4.582M12 3A8.997 8.997 0 0 0 4.157 7.582" /></svg>
                    <span class="font-semibold">{{ parse_url($links['website'], PHP_URL_HOST) ?? $links['website'] }}</span>
                </a>
            @endif
        </div>
    </div>

    {{-- Metrics Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <x-metric-card
            title="Total Revenue"
            :value="'$12,450.75'"
            icon="currency-dollar"
            trend='<span class="ml-2 px-3 py-1 rounded-full text-sm font-semibold bg-green-100 text-green-700 flex items-center gap-1"><svg class="w-4 h-4 inline" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 10l7-7m0 0l7 7m-7-7v18" stroke-linecap="round" stroke-linejoin="round"/></svg>+12.5%</span>'>
            <span class="text-gray-500">This month</span>
        </x-metric-card>
        <x-metric-card
            title="Total Orders"
            :value="'190'"
            icon="clipboard-list"
            trend="<span class='ml-2 px-3 py-1 rounded-full text-sm font-semibold bg-blue-100 text-blue-700'>45 active</span>"
        >
            <span class="text-gray-500">Avg: $145.25</span>
        </x-metric-card>
        <x-metric-card
            title="Customer Rating"
            :value="'4.7/5.0'"
            icon="star"
            trend='<span class="flex items-center gap-1">
                <svg class="w-4 h-4 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><polygon points="9.9,1.1 7.6,6.6 1.6,7.3 6.2,11.2 4.8,17.1 9.9,14.1 15,17.1 13.6,11.2 18.2,7.3 12.2,6.6 "/></svg>
                <svg class="w-4 h-4 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><polygon points="9.9,1.1 7.6,6.6 1.6,7.3 6.2,11.2 4.8,17.1 9.9,14.1 15,17.1 13.6,11.2 18.2,7.3 12.2,6.6 "/></svg>
                <svg class="w-4 h-4 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><polygon points="9.9,1.1 7.6,6.6 1.6,7.3 6.2,11.2 4.8,17.1 9.9,14.1 15,17.1 13.6,11.2 18.2,7.3 12.2,6.6 "/></svg>
                <svg class="w-4 h-4 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><polygon points="9.9,1.1 7.6,6.6 1.6,7.3 6.2,11.2 4.8,17.1 9.9,14.1 15,17.1 13.6,11.2 18.2,7.3 12.2,6.6 "/></svg>
                <svg class="w-4 h-4 text-gray-300" fill="currentColor" viewBox="0 0 20 20"><polygon points="9.9,1.1 7.6,6.6 1.6,7.3 6.2,11.2 4.8,17.1 9.9,14.1 15,17.1 13.6,11.2 18.2,7.3 12.2,6.6 "/></svg>
            </span>'
        >
            <span class="block text-gray-500">68% repeat rate</span>
        </x-metric-card>
        <x-metric-card
            title="Delivery Success"
            :value="'94.2%'"
            icon="truck"
            trend='<span class="ml-2 px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700">Excellent</span>'
        >
            <span class="block text-gray-500">On-time delivery</span>
        </x-metric-card>
    </div>

    {{-- Order Status Overview --}}
    <div class="bg-white rounded-3xl shadow-lg px-8 py-7">
        <div class="flex items-center justify-between mb-6">
            <h2 class="font-bold text-2xl md:text-3xl text-gray-900">Order Status Overview</h2>
            <div class="flex items-center space-x-2">
                <span class="h-2 w-2 rounded-full bg-green-500 inline-block"></span>
                <span class="text-emerald-700 text-base font-medium select-none">{{ array_sum($statusCounts) - ($statusCounts['canceled'] ?? 0) }} active orders</span>
            </div>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-5">
            @php
                $statusIcons = [
                    'pending'    => '<span class="flex items-center justify-center w-16 h-16 rounded-2xl bg-orange-100 mb-3"><svg class="w-7 h-7 text-orange-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2" /><circle cx="12" cy="12" r="9" /></svg></span>',
                    'confirmed'  => '<span class="flex items-center justify-center w-16 h-16 rounded-2xl bg-blue-100 mb-3"><svg class="w-7 h-7 text-blue-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg></span>',
                    'processing' => '<span class="flex items-center justify-center w-16 h-16 rounded-2xl bg-purple-100 mb-3"><svg class="w-7 h-7 text-purple-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><rect x="3" y="7" width="18" height="13" rx="2" /><path d="M16 3v4M8 3v4" /></svg></span>',
                    'ready'      => '<span class="flex items-center justify-center w-16 h-16 rounded-2xl bg-green-100 mb-3"><svg class="w-7 h-7 text-green-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><rect x="6" y="6" width="12" height="12" rx="2" /></svg></span>',
                    'delivering' => '<span class="flex items-center justify-center w-16 h-16 rounded-2xl bg-indigo-100 mb-3"><svg class="w-7 h-7 text-indigo-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M3 13l2-2m0 0l7-7 7 7M5 11V19a2 2 0 002 2h10a2 2 0 002-2v-8" /></svg></span>',
                    'delivered'  => '<span class="flex items-center justify-center w-16 h-16 rounded-2xl bg-emerald-100 mb-3"><svg class="w-7 h-7 text-emerald-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7" /></svg></span>',
                    'canceled'   => '<span class="flex items-center justify-center w-16 h-16 rounded-2xl bg-red-100 mb-3"><svg class="w-7 h-7 text-red-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" /><path d="M15 9l-6 6M9 9l6 6" /></svg></span>',
                ];
                $statusLabels = [
                    'pending'    => 'Pending',
                    'confirmed'  => 'Confirmed',
                    'processing' => 'Processing',
                    'ready'      => 'Ready',
                    'delivering' => 'Delivering',
                    'delivered'  => 'Delivered',
                    'canceled'   => 'Canceled',
                ];
                $statusUnderline = [
                    'pending'    => 'bg-orange-200',
                    'confirmed'  => 'bg-blue-200',
                    'processing' => 'bg-purple-200',
                    'ready'      => 'bg-green-200',
                    'delivering' => 'bg-indigo-200',
                    'delivered'  => 'bg-emerald-200',
                    'canceled'   => 'bg-red-200',
                ];
            @endphp
            @foreach(['pending', 'confirmed', 'processing', 'ready', 'delivering', 'delivered', 'canceled'] as $status)
                <div
                    x-data="{hover:false}"
                    @mouseenter="hover=true" @mouseleave="hover=false"
                    :class="{'scale-105 shadow-xl': hover}"
                    class="transition-all duration-300 bg-white rounded-2xl p-0 pt-6 pb-5 flex flex-col items-center border border-gray-200 cursor-pointer min-h-[210px]"
                    @click="$wire.statusFilter = '{{ $status }}'"
                >
                    {!! $statusIcons[$status] !!}
                    <div class="text-3xl font-extrabold text-gray-900 mt-1 mb-0.5">{{ $statusCounts[$status] ?? 0 }}</div>
                    <div class="text-base font-medium text-gray-700 mb-4">{{ $statusLabels[$status] }}</div>
                    <div class="w-4/5 h-1 rounded-full {{ $statusUnderline[$status] }}"></div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Top Performing Products --}}
    <div class="bg-white rounded-3xl shadow-lg px-8 py-7 mt-8">
        <div class="flex items-center justify-between mb-8">
            <h2 class="font-extrabold text-3xl text-gray-900">Top Performing Products</h2>
            <a href="#" class="flex items-center gap-2 text-gray-700 hover:text-blue-600 font-semibold text-lg">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18" /><path stroke-linecap="round" stroke-linejoin="round" d="M7 15l3-3 4 4 5-5" /></svg>
                View All
            </a>
        </div>
        <div class="space-y-7">
            @php
                $trendIcons = [
                    'trending' => '<svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19V6m0 0l-4 4m4-4l4 4"/></svg>',
                    'declining' => '<svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v13m0 0l-4-4m4 4l4-4"/></svg>',
                ];
                $trendLabels = [
                    'trending' => '<span class="flex items-center gap-1 text-green-600 font-semibold text-base">' . $trendIcons['trending'] . ' Trending</span>',
                    'declining' => '<span class="flex items-center gap-1 text-red-600 font-semibold text-base">' . $trendIcons['declining'] . ' Declining</span>',
                ];
                $products = [
                    [
                        'name' => 'Fresh Vegetables Bundle',
                        'orders_count' => 45,
                        'trend' => 'trending',
                        'progress' => 100,
                    ],
                    [
                        'name' => 'Organic Dairy Pack',
                        'orders_count' => 38,
                        'trend' => 'trending',
                        'progress' => 80,
                    ],
                    [
                        'name' => 'Premium Meat Selection',
                        'orders_count' => 32,
                        'trend' => 'declining',
                        'progress' => 65,
                    ],
                ];
            @endphp
            @foreach($products as $i => $product)
                <div
                    x-data="{hover:false}"
                    @mouseenter="hover=true" @mouseleave="hover=false"
                    :class="hover ? 'shadow-2xl -translate-y-1 bg-white' : 'shadow-lg bg-[#fafbfc]'"
                    class="flex items-center rounded-2xl px-8 py-7 min-h-[96px] transition-all duration-200 cursor-pointer"
                    style="box-shadow: 0 4px 24px 0 rgba(0,0,0,0.07);"
                >
                    <div class="flex-shrink-0">
                        <span class="flex items-center justify-center w-14 h-14 rounded-xl font-bold text-2xl text-white"
                            style="background: #4264fa; box-shadow: 0 4px 16px 0 rgba(66,100,250,0.15);">
                            {{ $i+1 }}
                        </span>
                    </div>
                    <div class="ml-6 flex-1">
                        <div class="font-bold text-xl text-gray-900">{{ $product['name'] }}</div>
                        <div class="text-gray-500 text-base mt-1">{{ $product['orders_count'] }} orders this month</div>
                    </div>
                    <div class="flex items-center gap-6 ml-auto">
                        {!! $trendLabels[$product['trend']] !!}
                        <div class="w-48 h-3 bg-gray-200 rounded-full overflow-hidden">
                            <div class="h-3 rounded-full"
                                style="background: linear-gradient(90deg, #4264fa 0%, #3b82f6 100%); width: {{ $product['progress'] }}%;">
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Recent Orders Table --}}
    <div class="bg-white rounded-3xl shadow-lg p-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-4">
            <h2 class="font-semibold text-lg">Recent Orders</h2>
            <div class="flex space-x-2 mt-2 md:mt-0">
                <input type="text" wire:model.debounce.300ms="search" placeholder="Search orders or customers..."
                       class="border rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-300"/>
                <div x-data="{open:false}" class="relative">
                    <button @click="open=!open" class="border rounded-lg px-3 py-2 flex items-center">
                        All Status
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 ml-1">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                        </svg>

                    </button>
                    <div x-show="open" @click.away="open=false" class="absolute right-0 mt-2 w-40 bg-white rounded-lg shadow-lg z-10">
                        @foreach(['all', 'pending', 'confirmed', 'processing', 'ready', 'delivering', 'delivered', 'canceled'] as $status)
                            <div @click="$wire.statusFilter = '{{ $status }}'; open=false"
                                 class="px-4 py-2 hover:bg-gray-100 cursor-pointer capitalize">{{ $status }}</div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr>
                        <th class="py-2 px-4 text-left">Order</th>
                        <th class="py-2 px-4 text-left">Items</th>
                        <th class="py-2 px-4 text-left">Total</th>
                        <th class="py-2 px-4 text-left">Status</th>
                        <th class="py-2 px-4 text-left">Date</th>
                        <th class="py-2 px-4 text-left">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                        <tr>
                            <td class="py-2 px-4">{{ $order->order_number }}</td>
                            <td class="py-2 px-4">{{ $order->items_count }}</td>
                            <td class="py-2 px-4 font-bold">${{ number_format($order->total, 2) }}</td>
                            <td class="py-2 px-4">
                                <x-status-badge :status="$order->status->value"/>
                            </td>
                            <td class="py-2 px-4">{{ $order->created_at->format('m/d/Y') }}</td>
                            <td class="py-2 px-4">
                                <button @click="/* open modal */" class="text-blue-600 hover:underline">View</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-4 text-center text-gray-400">No orders found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $orders->links() }}
        </div>
    </div>
</div>
