<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light" style="border: none;">
    <head>
        <meta charset="utf-8">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>{{ $title ?? config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>
            [x-cloak] { display: none !important; }
        </style>

        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    </head>
    <body class="min-h-screen bg-gray-100">
        <!-- Notification Component -->
        <div
            x-data="{
                notifications: [],
                add(message) {
                    if (!message[0] || !message[0].type || !message[0].message) {
                        console.error('Invalid notification format:', message);
                        console.error('Invalid notification format:', message[0].type);
                        console.error('Invalid notification format:', message[0].message);
                        return;
                    }

                    const notification = {
                        id: Date.now(),
                        type: message[0].type,
                        message: message[0].message
                    };

                    this.notifications.push(notification);

                    // Auto-remove notification after 3 seconds
                    setTimeout(() => {
                        this.remove(notification.id);
                    }, 3000);
                },
                remove(id) {
                    this.notifications = this.notifications.filter(notification => notification.id !== id);
                }
            }"
            @notify.window="add($event.detail)"
            class="fixed top-4 right-4 z-50 space-y-2 w-full max-w-sm"
        >
            <template x-for="notification in notifications" :key="notification.id">
                <div
                    x-show="true"
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="transform translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
                    x-transition:enter-end="transform translate-y-0 opacity-100 sm:translate-x-0"
                    x-transition:leave="transition ease-in duration-100"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    :class="{
                        'alert-success': notification.type === 'success',
                        'alert-danger': notification.type === 'error',
                        'alert-info': notification.type === 'info',
                        'alert-warning': notification.type === 'warning'
                    }"
                    class="w-full shadow-lg rounded-lg pointer-events-auto alert"
                >
                    <div class="p-4">
                        <div class="flex items-center justify-between">
                            <div class="flex-1 mr-3">
                                <p class="text-sm font-medium text-white" x-text="notification.message"></p>
                            </div>
                            <div class="flex flex-shrink-0">
                                <button
                                    @click="remove(notification.id)"
                                    class="inline-flex text-white hover:text-gray-200 focus:outline-none focus:ring-2 focus:ring-white rounded-md"
                                >
                                    <span class="sr-only">Close</span>
                                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <div x-data="{
            sidebarOpen: false,
            init() {
                this.$watch('sidebarOpen', value => {
                    if (value) {
                        document.body.classList.add('overflow-hidden', 'lg:overflow-auto');
                    } else {
                        document.body.classList.remove('overflow-hidden', 'lg:overflow-auto');
                    }
                });
            }
        }" class="min-h-screen">
            <!-- Backdrop for mobile -->
            <div
                x-cloak
                x-show="sidebarOpen"
                x-transition:enter="transition-opacity ease-linear duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition-opacity ease-linear duration-300"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                @click="sidebarOpen = false"
                class="fixed inset-0 bg-gray-900/50 lg:hidden z-30"
            ></div>

            <!-- Sidebar -->
            <aside
                x-cloak
                class="fixed top-0 left-0 z-40 w-64 h-screen transition-transform duration-300 ease-in-out lg:translate-x-0 lg:block"
                :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
            >
                <div class="h-full px-3 py-4 overflow-y-auto bg-white border-r border-gray-200">
                    <div class="flex items-center justify-between mb-6 px-2">
                        <a href="#" class="text-2xl font-bold text-gray-900">{{ config('app.name') }}</a>
                        <button @click="sidebarOpen = !sidebarOpen" class="lg:hidden p-2 rounded-full hover:bg-gray-100">
                            <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <ul class="space-y-2 font-medium">
                        <li>
                            <a href="{{route('main')}}" class="flex items-center p-2 rounded-lg hover:bg-gray-100 text-gray-900 group">
                                <svg class="w-5 h-5 transition duration-75" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 22 21">
                                    <path d="M16.975 11H10V4.025a1 1 0 0 0-1.066-.998 8.5 8.5 0 1 0 9.039 9.039.999.999 0 0 0-1-1.066h.002Z"/>
                                    <path d="M12.5 0c-.157 0-.311.01-.565.027A1 1 0 0 0 11 1.02V10h8.975a1 1 0 0 0 1-.935c.013-.188.028-.374.028-.565A8.51 8.51 0 0 0 12.5 0Z"/>
                                </svg>
                                <span class="ml-3">Dashboard</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{route('products')}}" class="flex items-center p-2 rounded-lg hover:bg-gray-100 text-gray-900 group">
                                <svg class="w-5 h-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 18 20">
                                    <path d="M17 5.923A1 1 0 0 0 16 5h-3V4a4 4 0 1 0-8 0v1H2a1 1 0 0 0-1 .923L.086 17.846A2 2 0 0 0 2.08 20h13.84a2 2 0 0 0 1.994-2.153L17 5.923ZM7 4a2 2 0 1 1 4 0v1H7V4Z"/>
                                </svg>
                                <span class="ml-3">Products</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{route('sections')}}" class="flex items-center p-2 rounded-lg hover:bg-gray-100 text-gray-900 group">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5">
                                    <path d="M5.566 4.657A4.505 4.505 0 0 1 6.75 4.5h10.5c.41 0 .806.055 1.183.157A3 3 0 0 0 15.75 3h-7.5a3 3 0 0 0-2.684 1.657ZM2.25 12a3 3 0 0 1 3-3h13.5a3 3 0 0 1 3 3v6a3 3 0 0 1-3 3H5.25a3 3 0 0 1-3-3v-6ZM5.25 7.5c-.41 0-.806.055-1.184.157A3 3 0 0 1 6.75 6h10.5a3 3 0 0 1 2.683 1.657A4.505 4.505 0 0 0 18.75 7.5H5.25Z" />
                                </svg>


                                <span class="ml-3">Products Sections</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{route('orders')}}" class="flex items-center p-2 rounded-lg hover:bg-gray-100 text-gray-900 group">

                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.242 5.992h12m-12 6.003H20.24m-12 5.999h12M4.117 7.495v-3.75H2.99m1.125 3.75H2.99m1.125 0H5.24m-1.92 2.577a1.125 1.125 0 1 1 1.591 1.59l-1.83 1.83h2.16M2.99 15.745h1.125a1.125 1.125 0 0 1 0 2.25H3.74m0-.002h.375a1.125 1.125 0 0 1 0 2.25H2.99" />
                                </svg>

                                <span class="ml-3">Orders</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{route('users')}}" class="flex items-center p-2 rounded-lg hover:bg-gray-100 text-gray-900 group">

                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5">
                                    <path d="M4.5 6.375a4.125 4.125 0 1 1 8.25 0 4.125 4.125 0 0 1-8.25 0ZM14.25 8.625a3.375 3.375 0 1 1 6.75 0 3.375 3.375 0 0 1-6.75 0ZM1.5 19.125a7.125 7.125 0 0 1 14.25 0v.003l-.001.119a.75.75 0 0 1-.363.63 13.067 13.067 0 0 1-6.761 1.873c-2.472 0-4.786-.684-6.76-1.873a.75.75 0 0 1-.364-.63l-.001-.122ZM17.25 19.128l-.001.144a2.25 2.25 0 0 1-.233.96 10.088 10.088 0 0 0 5.06-1.01.75.75 0 0 0 .42-.643 4.875 4.875 0 0 0-6.957-4.611 8.586 8.586 0 0 1 1.71 5.157v.003Z" />
                                </svg>


                                <span class="ml-3">Users</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </aside>

            <!-- Main Content -->
            <div class="lg:ml-64">
                <!-- Top Navbar -->
                <nav class="bg-white border-b border-gray-200">
                    <div class="px-3 py-3 lg:px-5 lg:pl-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center justify-start">
                                <button @click="sidebarOpen = !sidebarOpen" class="lg:hidden p-2 rounded-full hover:bg-gray-100">
                                    <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                                    </svg>
                                </button>
                            </div>
                            <div class="flex items-center">
                                <div class="relative">
                                    <button class="relative flex rounded-full bg-gray-100 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <img class="h-10 w-10 rounded-full" src="https://ui-avatars.com/api/?name=Admin&background=random" alt="Avatar" />
                                    </button>
                                    <div class="absolute right-0 z-10 mt-2 w-48 origin-top-right rounded-md bg-white py-1 shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none hidden group-hover:block">
                                        <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Profile</a>
                                        <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Settings</a>
                                        <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Logout</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </nav>

                <!-- Page Content -->
                <main class="p-4 lg:p-6">
                   {{ $slot }}
                </main>
            </div>
        </div>

        @stack('scripts')
    </body>
</html>


