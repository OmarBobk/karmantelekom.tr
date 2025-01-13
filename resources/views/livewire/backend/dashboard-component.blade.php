<div>
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between">
            <div>
                <h3 class="text-3xl font-bold text-base-content">Dashboard</h3>
                <p class="mt-1 text-base-content/70">Monitor your business metrics at a glance.</p>
            </div>
            <div class="mt-4 md:mt-0">
                <div class="join">
                    <button
                        wire:click="updatePeriod('week')"
                        class="join-item btn btn-sm {{ $period === 'week' ? 'btn-primary' : '' }}"
                    >Week</button>
                    <button
                        wire:click="updatePeriod('month')"
                        class="join-item btn btn-sm {{ $period === 'month' ? 'btn-primary' : '' }}"
                    >Month</button>
                    <button
                        wire:click="updatePeriod('year')"
                        class="join-item btn btn-sm {{ $period === 'year' ? 'btn-primary' : '' }}"
                    >Year</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid gap-6 mb-8 md:grid-cols-2 xl:grid-cols-4">
        <!-- Total Products Card -->
        <div class="card bg-base-100 shadow-xl hover:shadow-2xl transition-shadow duration-300">
            <div class="card-body">
                <div class="flex items-center">
                    <div class="inline-flex flex-shrink-0 items-center justify-center h-16 w-16 text-primary bg-primary/10 rounded-lg">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h2 class="mb-2 text-sm font-medium text-base-content/70">Total Products</h2>
                        <p class="text-2xl font-semibold text-base-content">{{ number_format($totalProducts) }}</p>
                        <p class="mt-1 text-sm text-success">
                            <span class="font-medium">↑ 12%</span>
                            <span class="text-base-content/60">vs last month</span>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Customers Card -->
        <div class="card bg-base-100 shadow-xl hover:shadow-2xl transition-shadow duration-300">
            <div class="card-body">
                <div class="flex items-center">
                    <div class="inline-flex flex-shrink-0 items-center justify-center h-16 w-16 text-secondary bg-secondary/10 rounded-lg">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h2 class="mb-2 text-sm font-medium text-base-content/70">Total Customers</h2>
                        <p class="text-2xl font-semibold text-base-content">{{ number_format($totalCustomers) }}</p>
                        <p class="mt-1 text-sm text-success">
                            <span class="font-medium">↑ 18%</span>
                            <span class="text-base-content/60">vs last month</span>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Orders Card -->
        <div class="card bg-base-100 shadow-xl hover:shadow-2xl transition-shadow duration-300">
            <div class="card-body">
                <div class="flex items-center">
                    <div class="inline-flex flex-shrink-0 items-center justify-center h-16 w-16 text-accent bg-accent/10 rounded-lg">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h2 class="mb-2 text-sm font-medium text-base-content/70">Total Orders</h2>
                        <p class="text-2xl font-semibold text-base-content">{{ number_format($totalOrders) }}</p>
                        <p class="mt-1 text-sm text-error">
                            <span class="font-medium">↓ 4%</span>
                            <span class="text-base-content/60">vs last month</span>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Revenue Card -->
        <div class="card bg-base-100 shadow-xl hover:shadow-2xl transition-shadow duration-300">
            <div class="card-body">
                <div class="flex items-center">
                    <div class="inline-flex flex-shrink-0 items-center justify-center h-16 w-16 text-success bg-success/10 rounded-lg">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h2 class="mb-2 text-sm font-medium text-base-content/70">Total Revenue</h2>
                        <p class="text-2xl font-semibold text-base-content">${{ number_format($totalRevenue) }}</p>
                        <p class="mt-1 text-sm text-success">
                            <span class="font-medium">↑ 8%</span>
                            <span class="text-base-content/60">vs last month</span>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="grid gap-6 mb-8 md:grid-cols-2">
        <!-- Sales Chart -->
        <div class="card bg-base-100 shadow-xl">
            <div class="card-body p-0 sm:p-6">
                <div class="flex flex-wrap items-center justify-between p-4 sm:p-0">
                    <h2 class="card-title text-base-content/80 font-medium">Sales Overview</h2>
                    <div class="flex items-center">
                        <div class="dropdown dropdown-end">
                            <label tabindex="0" class="btn btn-ghost btn-sm">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path>
                                </svg>
                            </label>
                            <ul tabindex="0" class="dropdown-content z-[1] menu p-2 shadow bg-base-100 rounded-box w-52">
                                <li><a>Download SVG</a></li>
                                <li><a>Download PNG</a></li>
                                <li><a>Print Chart</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div
                    x-data="{
                        chart: null,
                        chartData: @js($salesData),

                        init() {
                            this.$nextTick(() => {
                                if (typeof Chart === 'undefined') {
                                    console.error('Chart.js not loaded');
                                    return;
                                }
                                this.initChart();
                            });

                            this.$watch('chartData', () => {
                                if (this.chart) {
                                    this.updateChartData();
                                }
                            });
                        },

                        initChart() {
                            const canvas = this.$refs.canvas;
                            if (!canvas) return;

                            const ctx = canvas.getContext('2d');
                            if (!ctx) return;

                            if (this.chart) {
                                this.chart.destroy();
                            }

                            this.chart = new Chart(ctx, {
                                type: 'line',
                                data: {
                                    labels: this.chartData?.labels || [],
                                    datasets: [{
                                        label: 'Sales',
                                        data: this.chartData?.data || [],
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
                        },

                        updateChartData() {
                            if (!this.chart) return;

                            this.chart.data.labels = this.chartData?.labels || [];
                            this.chart.data.datasets[0].data = this.chartData?.data || [];
                            this.chart.update('none');
                        }
                    }"
                    @chart-data-updated.window="chartData = $event.detail"
                    wire:ignore
                    class="relative w-full aspect-[16/9] sm:aspect-[21/9] p-4 sm:p-0"
                >
                    <canvas x-ref="canvas" class="w-full h-full"></canvas>
                </div>
            </div>
        </div>

        <!-- Recent Orders -->
        <div class="card bg-base-100 shadow-xl overflow-x-auto">
            <div class="card-body p-0 sm:p-6">
                <div class="flex flex-wrap items-center justify-between p-4 sm:p-0 border-b border-base-200">
                    <h2 class="card-title text-base-content/80 font-medium">Recent Orders</h2>
                    <a href="#" class="btn btn-ghost btn-sm">
                        View All
                        <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </a>
                </div>
                <div class="overflow-x-auto scrollbar-thin scrollbar-thumb-base-300 scrollbar-track-base-100">
                    <div class="inline-block min-w-full align-middle">
                        <table class="table table-zebra w-full">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Customer</th>
                                    <th>Status</th>
                                    <th>Total</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($recentOrders as $order)
                                <tr>
                                    <td>#{{ $order['id'] }}</td>
                                    <td>{{ $order['customer'] }}</td>
                                    <td>
                                        @php
                                            $statusClass = match($order['status']) {
                                                'completed' => 'badge-success',
                                                'pending' => 'badge-warning',
                                                'processing' => 'badge-info',
                                                default => 'badge-ghost'
                                            };
                                        @endphp
                                        <div class="badge {{ $statusClass }} capitalize">{{ $order['status'] }}</div>
                                    </td>
                                    <td>${{ number_format($order['total'], 2) }}</td>
                                    <td>{{ $order['date']->diffForHumans() }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions Section -->
    <div class="grid gap-6 mb-8 md:grid-cols-3">
        <!-- Quick Actions -->
        <div class="card bg-base-100 shadow-xl md:col-span-1">
            <div class="card-body">
                <h2 class="card-title mb-4">Quick Actions</h2>
                <div class="space-y-4">
                    <button class="btn btn-primary w-full">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        Add New Product
                    </button>
                    <button class="btn btn-secondary w-full">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                        </svg>
                        View Orders
                    </button>
                    <button class="btn btn-accent w-full">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                        View Reports
                    </button>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="card bg-base-100 shadow-xl md:col-span-2">
            <div class="card-body">
                <h2 class="card-title mb-4">Recent Activity</h2>
                <div class="space-y-4">
                    <div class="flex items-start space-x-4">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center">
                                <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="flex-1">
                            <p class="text-sm font-medium text-base-content">New product added</p>
                            <p class="text-sm text-base-content/70">iPhone 15 Pro Max was added to the store</p>
                            <p class="text-xs text-base-content/50 mt-1">2 hours ago</p>
                        </div>
                    </div>
                    <div class="flex items-start space-x-4">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 rounded-full bg-success/10 flex items-center justify-center">
                                <svg class="w-4 h-4 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="flex-1">
                            <p class="text-sm font-medium text-base-content">Order completed</p>
                            <p class="text-sm text-base-content/70">Order #1234 was completed</p>
                            <p class="text-xs text-base-content/50 mt-1">3 hours ago</p>
                        </div>
                    </div>
                    <div class="flex items-start space-x-4">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 rounded-full bg-warning/10 flex items-center justify-center">
                                <svg class="w-4 h-4 text-warning" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="flex-1">
                            <p class="text-sm font-medium text-base-content">Low stock alert</p>
                            <p class="text-sm text-base-content/70">Product "MacBook Pro" is running low on stock</p>
                            <p class="text-xs text-base-content/50 mt-1">5 hours ago</p>
                        </div>
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
            .scrollbar-thumb-base-300::-webkit-scrollbar-thumb {
                background-color: hsl(var(--b3));
                border-radius: 0.25rem;
            }
            .scrollbar-track-base-100::-webkit-scrollbar-track {
                background-color: hsl(var(--b1));
            }
        }
    </style>

</div>
