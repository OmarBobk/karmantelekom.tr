<div>
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between">
            <div>
                <h3 class="text-3xl font-bold text-gray-900">Dashboard</h3>
                <p class="mt-1 text-gray-600">Monitor your business metrics at a glance.</p>
            </div>
            <div class="mt-4 md:mt-0">
                <div class="inline-flex rounded-md shadow-sm">
                    <button
                        wire:click="updatePeriod('week')"
                        class="relative inline-flex items-center px-4 py-2 text-sm font-medium rounded-l-md border {{ $period === 'week' ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' }}"
                    >Week</button>
                    <button
                        wire:click="updatePeriod('month')"
                        class="relative inline-flex items-center px-4 py-2 text-sm font-medium border-t border-b {{ $period === 'month' ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' }}"
                    >Month</button>
                    <button
                        wire:click="updatePeriod('year')"
                        class="relative inline-flex items-center px-4 py-2 text-sm font-medium rounded-r-md border {{ $period === 'year' ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' }}"
                    >Year</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid gap-6 mb-8 md:grid-cols-2 xl:grid-cols-4">
        <!-- Total Products Card -->
        <div class="bg-white rounded-lg shadow-lg hover:shadow-xl transition-shadow duration-300">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="inline-flex flex-shrink-0 items-center justify-center h-16 w-16 text-blue-600 bg-blue-100 rounded-lg">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h2 class="mb-2 text-sm font-medium text-gray-600">Total Products</h2>
                        <p class="text-2xl font-semibold text-gray-900">{{ number_format($totalProducts) }}</p>
                        <p class="mt-1 text-sm">
                            <span class="font-medium text-green-600">↑ 12%</span>
                            <span class="text-gray-600">vs last month</span>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Website Visits Card -->
        <div class="bg-white rounded-lg shadow-lg hover:shadow-xl transition-shadow duration-300">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="inline-flex flex-shrink-0 items-center justify-center h-16 w-16 text-purple-600 bg-purple-100 rounded-lg">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h2 class="mb-2 text-sm font-medium text-gray-600">Total Website Visits</h2>
                        <p class="text-2xl font-semibold text-gray-900">{{ number_format($totalVisitors) }}</p>
                        <p class="mt-1 text-sm">
                            <span class="font-medium text-green-600">↑ 18%</span>
                            <span class="text-gray-600">vs last month</span>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Active Users Card -->
        <div class="bg-white rounded-lg shadow-lg hover:shadow-xl transition-shadow duration-300">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="inline-flex flex-shrink-0 items-center justify-center h-16 w-16 text-indigo-600 bg-indigo-100 rounded-lg">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h2 class="mb-2 text-sm font-medium text-gray-600">Total Active Users</h2>
                        <p class="text-2xl font-semibold text-gray-900">{{ number_format($totalActiveUsers) }}</p>
                        <p class="mt-1 text-sm">
                            <span class="font-medium text-green-600">↑ 7%</span>
                            <span class="text-gray-600">vs last month</span>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Revenue Card -->
        <div class="bg-white rounded-lg shadow-lg hover:shadow-xl transition-shadow duration-300">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="inline-flex flex-shrink-0 items-center justify-center h-16 w-16 text-green-600 bg-green-100 rounded-lg">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h2 class="mb-2 text-sm font-medium text-gray-600">New Vs Returning Users</h2>
                        <p class="text-2xl font-semibold text-gray-900">{{ number_format($totalNewUsers) }} / {{ number_format($totalReturningUsers) }}</p>
                        <p class="mt-1 text-sm">
                            <span class="font-medium text-green-600">↑ 8%</span>
                            <span class="text-gray-600">vs last month</span>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="grid gap-4 sm:gap-6 mb-6 sm:mb-8 grid-cols-1 lg:grid-cols-3">
        <!-- Sales Chart -->
        <div class="bg-white rounded-lg shadow-lg lg:col-span-2">
            <div class="p-0 sm:p-6">
                <div class="flex flex-wrap items-center justify-between p-4 sm:p-0">
                    <h2 class="text-lg font-medium text-gray-900">Website Visits Overview</h2>
                    <div class="flex items-center">
                        <div class="relative">
                            <button class="p-2 hover:bg-gray-100 rounded-full">
                                <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path>
                                </svg>
                            </button>
                            <div class="hidden absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-10">
                                <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Download SVG</a>
                                <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Download PNG</a>
                                <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Print Chart</a>
                            </div>
                        </div>
                    </div>
                </div>
                <div
                    wire:ignore.self
                    x-data="{
                        chart: null,
                        isInitialized: false,

                        init() {
                            // Initial setup
                            this.initChart(@js($salesData));

                            // Listen for changes in data from Livewire
                            this.$wire.$on('salesDataUpdated', (newData) => {
                                console.log('Received new chart data:', newData);
                                this.refreshChart(newData);
                            });
                        },

                        initChart(data) {
                            // Make sure we have valid data
                            if (!data || !data.labels || !data.data) {
                                console.error('Invalid chart data provided', data);
                                return;
                            }

                            console.log('Initializing chart with data:', data);

                            // Clean up existing chart
                            if (this.chart) {
                                this.chart.destroy();
                                this.chart = null;
                            }

                            const canvas = this.$refs.canvas;
                            if (!canvas) {
                                console.error('Canvas element not found');
                                return;
                            }

                            const ctx = canvas.getContext('2d');
                            if (!ctx) {
                                console.error('Canvas context not available');
                                return;
                            }

                            // Create the chart
                            this.chart = new Chart(ctx, {
                                type: 'line',
                                data: {
                                    labels: data.labels,
                                    datasets: [{
                                        label: 'Page Views',
                                        data: data.data,
                                        borderColor: 'rgb(59, 130, 246)',
                                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                                        tension: 0.4,
                                        fill: true,
                                        pointRadius: 4,
                                        pointHoverRadius: 6,
                                        pointBackgroundColor: 'rgb(59, 130, 246)',
                                        pointBorderColor: '#fff',
                                        pointBorderWidth: 2,
                                        pointHoverBackgroundColor: 'rgb(59, 130, 246)',
                                        pointHoverBorderColor: '#fff',
                                        pointHoverBorderWidth: 2
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    animation: {
                                        duration: 0
                                    },
                                    interaction: {
                                        mode: 'index',
                                        intersect: false
                                    },
                                    plugins: {
                                        legend: {
                                            display: false
                                        },
                                        tooltip: {
                                            mode: 'index',
                                            intersect: false,
                                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                            titleColor: '#fff',
                                            bodyColor: '#fff',
                                            borderColor: 'rgba(0, 0, 0, 0.8)',
                                            borderWidth: 1,
                                            padding: 10,
                                            displayColors: false
                                        }
                                    },
                                    scales: {
                                        y: {
                                            beginAtZero: true,
                                            grid: {
                                                display: true,
                                                drawBorder: false,
                                                color: 'rgba(0, 0, 0, 0.05)'
                                            },
                                            ticks: {
                                                color: '#6b7280',
                                                font: {
                                                    size: 12
                                                }
                                            }
                                        },
                                        x: {
                                            grid: {
                                                display: false
                                            },
                                            ticks: {
                                                color: '#6b7280',
                                                font: {
                                                    size: 12
                                                },
                                                maxRotation: 45,
                                                minRotation: 45
                                            }
                                        }
                                    }
                                }
                            });

                            this.isInitialized = true;
                        },

                        refreshChart(newData) {
                            console.log('Refreshing chart with new data:', newData);

                            // Handle nested data structure
                            const chartData = newData.data || newData;

                            // Make sure we have valid data
                            if (!chartData || !chartData.labels || !chartData.data) {
                                console.error('Invalid chart data provided for refresh', chartData);
                                return;
                            }

                            // Completely destroy and recreate the chart
                            this.initChart(chartData);
                        }
                    }"
                    x-init="init()"
                    class="relative w-full aspect-[16/9] sm:aspect-[21/9] p-4 sm:p-0"
                >
                    <canvas x-ref="canvas" class="w-full h-full"></canvas>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="bg-white rounded-lg shadow-lg">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 mb-4">Quick Actions</h2>
                <div class="space-y-4">
                    <button wire:click="addNewProduct" class="inline-flex items-center justify-center w-full px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        Add New Product
                    </button>
                    <a href="{{route('subdomain.sections')}}" class="inline-flex items-center justify-center w-full px-4 py-2 text-sm font-medium text-white bg-purple-600 rounded-md hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5 mr-2">
                            <path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" />
                            <path fill-rule="evenodd" d="M1.323 11.447C2.811 6.976 7.028 3.75 12.001 3.75c4.97 0 9.185 3.223 10.675 7.69.12.362.12.752 0 1.113-1.487 4.471-5.705 7.697-10.677 7.697-4.97 0-9.186-3.223-10.675-7.69a1.762 1.762 0 0 1 0-1.113ZM17.25 12a5.25 5.25 0 1 1-10.5 0 5.25 5.25 0 0 1 10.5 0Z" clip-rule="evenodd" />
                        </svg>

                        View Sections
                    </a>
                    <a href="{{route('subdomain.settings')}}" class="inline-flex items-center justify-center w-full px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5 mr-2">
                            <path fill-rule="evenodd" d="M12 6.75a5.25 5.25 0 0 1 6.775-5.025.75.75 0 0 1 .313 1.248l-3.32 3.319c.063.475.276.934.641 1.299.365.365.824.578 1.3.64l3.318-3.319a.75.75 0 0 1 1.248.313 5.25 5.25 0 0 1-5.472 6.756c-1.018-.086-1.87.1-2.309.634L7.344 21.3A3.298 3.298 0 1 1 2.7 16.657l8.684-7.151c.533-.44.72-1.291.634-2.309A5.342 5.342 0 0 1 12 6.75ZM4.117 19.125a.75.75 0 0 1 .75-.75h.008a.75.75 0 0 1 .75.75v.008a.75.75 0 0 1-.75.75h-.008a.75.75 0 0 1-.75-.75v-.008Z" clip-rule="evenodd" />
                        </svg>

                        Go To Settings
                    </a>
                    <a href="{{config('app.url')}}" class="inline-flex items-center justify-center w-full px-4 py-2 text-sm font-medium text-white bg-teal-600 rounded-md hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-teal-500">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        Vist the Store
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="grid gap-4 sm:gap-6 mb-6 sm:mb-8 grid-cols-1 lg:grid-cols-1">

        {{-- Most Viewed Products --}}
        <div class="bg-white rounded-lg shadow-lg">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 mb-4">Most Viewed Products</h2>
                <div class="overflow-x-auto">
                    <div class="inline-block min-w-full align-middle">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Image</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Views</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                            </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($mostViewedProducts as $product)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        #{{ $product['id'] }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex-shrink-0 h-16 w-16">
                                            <img class="h-16 w-16 rounded-lg object-cover border border-gray-200 shadow-sm hover:shadow-md transition-shadow duration-200"
                                                 src="{{ $product['image'] ?? asset('images/placeholder.png') }}"
                                                 alt="{{ $product['name'] }}">
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">{{ $product['name'] }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ number_format($product['views']) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-500">{{ $product['category'] }}</div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                                        No view data available.
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Products -->
        <div class="bg-white rounded-lg shadow-lg">
            <div class="p-6">
                <div class="flex flex-wrap items-center justify-between border-b border-gray-200 pb-4">
                    <h2 class="text-lg font-medium text-gray-900">Recent Products</h2>
                </div>
                <div class="overflow-x-auto">
                    <div class="inline-block min-w-full align-middle">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Image</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($recentProducts as $product)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            #{{ $product['id'] }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex-shrink-0 h-16 w-16">
                                                <img class="h-16 w-16 rounded-lg object-cover border border-gray-200 shadow-sm hover:shadow-md transition-shadow duration-200"
                                                     src="{{ $product['image']  }}"
                                                     alt="{{ $product['name'] }}">
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900">{{ $product['name'] }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-500">{{ $product['category'] }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $product['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                                {{ ucfirst($product['status']) }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach

                                @if(count($recentProducts) === 0)
                                    <tr>
                                        <td colspan="6" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                                            No products found.
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('salesChart', (initialData) => ({
            chart: null,
            data: initialData,

            initChart() {
                try {
                    // Get canvas element
                    const canvas = this.$refs.canvas;
                    if (!canvas) {
                        console.error('Canvas element not found');
                        return;
                    }

                    // Clean up existing chart
                    if (this.chart) {
                        this.chart.destroy();
                        this.chart = null;
                    }

                    // Get context with null check
                    const ctx = canvas.getContext('2d');
                    if (!ctx) {
                        console.error('Could not get canvas context');
                        return;
                    }

                    // Set canvas dimensions
                    canvas.style.width = '100%';
                    canvas.style.height = '100%';
                    canvas.width = canvas.offsetWidth;
                    canvas.height = canvas.offsetHeight;

                    // Create new chart
                    this.chart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: this.data?.labels || [],
                            datasets: [{
                                label: 'Sales',
                                data: this.data?.data || [],
                                borderColor: 'rgb(59, 130, 246)',
                                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                                tension: 0.4,
                                fill: true
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: false
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    grid: {
                                        display: true,
                                        drawBorder: false
                                    }
                                },
                                x: {
                                    grid: {
                                        display: false
                                    }
                                }
                            }
                        }
                    });
                } catch (error) {
                    console.error('Error initializing chart:', error);
                }
            },

            updateChart(newData) {
                if (!this.chart) {
                    this.initChart();
                    return;
                }

                try {
                    this.data = newData;
                    this.chart.data.labels = newData?.labels || [];
                    this.chart.data.datasets[0].data = newData?.data || [];
                    this.chart.update('none');
                } catch (error) {
                    console.error('Error updating chart:', error);
                }
            }
        }));
    });
    </script>
    @endpush

    <style>
        @layer utilities {
            .scrollbar-thin {
                scrollbar-width: thin;
            }
            .scrollbar-thumb-gray-300::-webkit-scrollbar-thumb {
                background-color: #D1D5DB;
                border-radius: 0.25rem;
            }
            .scrollbar-track-gray-100::-webkit-scrollbar-track {
                background-color: #F3F4F6;
            }
        }
    </style>

</div>
