<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Broadcasting Demo - Laravel Reverb</title>
    <meta name="user-id" content="{{ auth()->id() }}">
    <meta name="user-role" content="{{ auth()->user()->roles->first()?->name ?? 'user' }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Toastr for notifications -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen">
        <!-- Header -->
        <header class="bg-white shadow">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex items-center">
                        <h1 class="text-xl font-semibold text-gray-900">Broadcasting Demo</h1>
                    </div>
                    <div class="flex items-center space-x-4">
                        <!-- Real-time notifications component -->
                        <livewire:backend.real-time-notifications />
                        
                        <div class="text-sm text-gray-500">
                            <i class="fas fa-circle text-green-500 mr-2"></i>
                            Connected to Reverb
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
            <!-- Demo Controls -->
            <div class="bg-white shadow rounded-lg mb-6">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-medium text-gray-900">Demo Controls</h2>
                    <p class="text-sm text-gray-500">Trigger events to test broadcasting functionality</p>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <button 
                            onclick="triggerShopCreated()"
                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                        >
                            <i class="fas fa-store mr-2"></i>
                            Create Shop
                        </button>
                        
                        <button 
                            onclick="triggerShopAssigned()"
                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                        >
                            <i class="fas fa-user-check mr-2"></i>
                            Assign Shop
                        </button>
                        
                        <button 
                            onclick="triggerOrderCreated()"
                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500"
                        >
                            <i class="fas fa-shopping-cart mr-2"></i>
                            Create Order
                        </button>
                        
                        <button 
                            onclick="triggerOrderUpdated()"
                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-yellow-600 hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500"
                        >
                            <i class="fas fa-edit mr-2"></i>
                            Update Order
                        </button>
                    </div>
                </div>
            </div>

            <!-- Real-time Dashboard -->
            <livewire:backend.real-time-dashboard />

            <!-- Event Log -->
            <div class="bg-white shadow rounded-lg mt-6">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-medium text-gray-900">Event Log</h2>
                    <p class="text-sm text-gray-500">Real-time event reception log</p>
                </div>
                <div class="p-6">
                    <div id="event-log" class="space-y-2 max-h-64 overflow-y-auto">
                        <div class="text-sm text-gray-500">Waiting for events...</div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Configure Toastr
        toastr.options = {
            "closeButton": true,
            "debug": false,
            "newestOnTop": true,
            "progressBar": true,
            "positionClass": "toast-top-right",
            "preventDuplicates": false,
            "onclick": null,
            "showDuration": "300",
            "hideDuration": "1000",
            "timeOut": "5000",
            "extendedTimeOut": "1000",
            "showEasing": "swing",
            "hideEasing": "linear",
            "showMethod": "fadeIn",
            "hideMethod": "fadeOut"
        };

        // Event log function
        function addEventLog(message, type = 'info') {
            const log = document.getElementById('event-log');
            const timestamp = new Date().toLocaleTimeString();
            const colors = {
                'info': 'text-blue-600',
                'success': 'text-green-600',
                'warning': 'text-yellow-600',
                'error': 'text-red-600'
            };
            
            const logEntry = document.createElement('div');
            logEntry.className = `text-sm ${colors[type] || colors.info}`;
            logEntry.innerHTML = `<span class="font-mono">[${timestamp}]</span> ${message}`;
            
            log.insertBefore(logEntry, log.firstChild);
            
            // Keep only last 50 entries
            while (log.children.length > 50) {
                log.removeChild(log.lastChild);
            }
        }

        // Demo functions
        function triggerShopCreated() {
            fetch('/api/demo/shop-created', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            })
            .then(response => response.json())
            .then(data => {
                addEventLog('Shop created event triggered', 'success');
                toastr.success('Shop created event dispatched!');
            })
            .catch(error => {
                addEventLog('Error triggering shop created event: ' + error.message, 'error');
                toastr.error('Failed to trigger shop created event');
            });
        }

        function triggerShopAssigned() {
            fetch('/api/demo/shop-assigned', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            })
            .then(response => response.json())
            .then(data => {
                addEventLog('Shop assigned event triggered', 'success');
                toastr.success('Shop assigned event dispatched!');
            })
            .catch(error => {
                addEventLog('Error triggering shop assigned event: ' + error.message, 'error');
                toastr.error('Failed to trigger shop assigned event');
            });
        }

        function triggerOrderCreated() {
            fetch('/api/demo/order-created', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            })
            .then(response => response.json())
            .then(data => {
                addEventLog('Order created event triggered', 'success');
                toastr.success('Order created event dispatched!');
            })
            .catch(error => {
                addEventLog('Error triggering order created event: ' + error.message, 'error');
                toastr.error('Failed to trigger order created event');
            });
        }

        function triggerOrderUpdated() {
            fetch('/api/demo/order-updated', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            })
            .then(response => response.json())
            .then(data => {
                addEventLog('Order updated event triggered', 'success');
                toastr.success('Order updated event dispatched!');
            })
            .catch(error => {
                addEventLog('Error triggering order updated event: ' + error.message, 'error');
                toastr.error('Failed to trigger order updated event');
            });
        }

        // Listen for custom events from broadcasting
        document.addEventListener('shop-created', function(e) {
            addEventLog('Shop created event received: ' + e.detail.shop.name, 'info');
        });

        document.addEventListener('shop-assigned', function(e) {
            addEventLog('Shop assigned event received: ' + e.detail.shop.name + ' -> ' + e.detail.salesperson.name, 'info');
        });

        document.addEventListener('order-created', function(e) {
            addEventLog('Order created event received: #' + e.detail.order.id, 'info');
        });

        document.addEventListener('order-updated', function(e) {
            addEventLog('Order updated event received: #' + e.detail.order.id, 'info');
        });

        document.addEventListener('notification-received', function(e) {
            addEventLog('Notification received: ' + e.detail.description, 'warning');
        });

        // Connection status
        if (window.Echo) {
            window.Echo.connector.pusher.connection.bind('connected', () => {
                addEventLog('Connected to Reverb broadcasting server', 'success');
            });

            window.Echo.connector.pusher.connection.bind('disconnected', () => {
                addEventLog('Disconnected from Reverb broadcasting server', 'error');
            });

            window.Echo.connector.pusher.connection.bind('error', (error) => {
                addEventLog('Reverb connection error: ' + error.message, 'error');
            });
        }
    </script>
</body>
</html>
