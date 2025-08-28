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
        <script>
            window.Laravel = { userId: {{ auth()->id() ?? 'null' }} }
        </script>
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
                        message: message[0].message,
                        sec: message[0].sec || 3000
                    };

                    this.notifications.push(notification);

                    // Auto-remove notification after 3 seconds
                    setTimeout(() => {
                        this.remove(notification.id);
                    }, notification.sec);
                },
                remove(id) {
                    this.notifications = this.notifications.filter(notification => notification.id !== id);
                }
            }"
            @notify.window="add($event.detail)"
            class="fixed top-4 right-4 z-[99999] space-y-2 w-full max-w-sm"
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
                class="fixed top-0 left-0 z-40 w-64 h-screen transition-transform duration-300 ease-in-out lg:translate-x-0 lg:block bg-gradient-to-b from-gray-50 to-white border-r border-gray-100"
                :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
            >
                <div class="h-full px-3 py-4 overflow-y-auto">
                    <div class="flex items-center justify-center px-2">
                        <a href="#" class="text-2xl font-bold bg-gradient-to-r from-blue-600 to-indigo-600 bg-clip-text text-transparent">
                            <img src="{{ Storage::url('title-logo.svg') }}" class="w-32" alt="">
                        </a>
                        <button @click="sidebarOpen = !sidebarOpen" class="lg:hidden p-2 rounded-lg hover:bg-gray-100 transition-colors duration-200">
                            <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="space-y-8">
                        <!-- Management Section -->
                        <div>
                            <div class="flex items-center px-2 py-2 text-sm font-semibold text-gray-500 uppercase tracking-wider">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 text-blue-500" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd" />
                                </svg>
                                Management
                            </div>
                            <ul class="mt-2 space-y-1">
                                <li>
                                    <a href="{{route('subdomain.main')}}" class="flex items-center p-2 rounded-lg text-gray-700 hover:bg-blue-50 hover:text-blue-600 group transition-colors duration-200">
                                        <svg class="w-5 h-5 transition duration-75 group-hover:text-blue-600" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 22 21">
                                            <path d="M16.975 11H10V4.025a1 1 0 0 0-1.066-.998 8.5 8.5 0 1 0 9.039 9.039.999.999 0 0 0-1-1.066h.002Z"/>
                                            <path d="M12.5 0c-.157 0-.311.01-.565.027A1 1 0 0 0 11 1.02V10h8.975a1 1 0 0 0 1-.935c.013-.188.028-.374.028-.565A8.51 8.51 0 0 0 12.5 0Z"/>
                                        </svg>
                                        <span class="ml-3">Dashboard</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="{{route('subdomain.orders')}}" class="flex items-center p-2 rounded-lg text-gray-700 hover:bg-blue-50 hover:text-blue-600 group transition-colors duration-200">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-clipboard-list-icon lucide-clipboard-list w-5 h-5 transition duration-75 group-hover:text-blue-600"><rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M12 11h4"/><path d="M12 16h4"/><path d="M8 11h.01"/><path d="M8 16h.01"/></svg>
                                        <span class="ml-3">Orders Manager</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="{{route('subdomain.shops')}}" class="flex items-center p-2 rounded-lg text-gray-700 hover:bg-blue-50 hover:text-blue-600 group transition-colors duration-200">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5 transition duration-75 group-hover:text-blue-600">
                                            <path d="M5.223 2.25c-.497 0-.974.198-1.325.55l-1.3 1.298A3.75 3.75 0 0 0 7.5 9.75c.627.47 1.406.75 2.25.75.844 0 1.624-.28 2.25-.75.626.47 1.406.75 2.25.75.844 0 1.623-.28 2.25-.75a3.75 3.75 0 0 0 4.902-5.652l-1.3-1.299a1.875 1.875 0 0 0-1.325-.549H5.223Z" />
                                            <path fill-rule="evenodd" d="M3 20.25v-8.755c1.42.674 3.08.673 4.5 0A5.234 5.234 0 0 0 9.75 12c.804 0 1.568-.182 2.25-.506a5.234 5.234 0 0 0 2.25.506c.804 0 1.567-.182 2.25-.506 1.42.674 3.08.675 4.5.001v8.755h.75a.75.75 0 0 1 0 1.5H2.25a.75.75 0 0 1 0-1.5H3Zm3-6a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75v3a.75.75 0 0 1-.75.75h-3a.75.75 0 0 1-.75-.75v-3Zm8.25-.75a.75.75 0 0 0-.75.75v5.25c0 .414.336.75.75.75h3a.75.75 0 0 0 .75-.75v-5.25a.75.75 0 0 0-.75-.75h-3Z" clip-rule="evenodd" />
                                        </svg>
                                        <span class="ml-3">Shops Manager</span>
                                    </a>
                                </li>
                                @role('admin')
                                <li>
                                    <a href="{{route('subdomain.users')}}" class="flex items-center p-2 rounded-lg text-gray-700 hover:bg-blue-50 hover:text-blue-600 group transition-colors duration-200">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5 transition duration-75 group-hover:text-blue-600">
                                            <path d="M4.5 6.375a4.125 4.125 0 1 1 8.25 0 4.125 4.125 0 0 1-8.25 0ZM14.25 8.625a3.375 3.375 0 1 1 6.75 0 3.375 3.375 0 0 1-6.75 0ZM1.5 19.125a7.125 7.125 0 0 1 14.25 0v.003l-.001.119a.75.75 0 0 1-.363.63 13.067 13.067 0 0 1-6.761 1.873c-2.472 0-4.786-.684-6.76-1.873a.75.75 0 0 1-.364-.63l-.001-.122ZM17.25 19.128l-.001.144a2.25 2.25 0 0 1-.233.96 10.088 10.088 0 0 0 5.06-1.01.75.75 0 0 0 .42-.643 4.875 4.875 0 0 0-6.957-4.611 8.586 8.586 0 0 1 1.71 5.157v.003Z" />
                                        </svg>

                                        <span class="ml-3">Users</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="{{route('subdomain.settings')}}" class="flex items-center p-2 rounded-lg text-gray-700 hover:bg-blue-50 hover:text-blue-600 group transition-colors duration-200">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5 transition duration-75 group-hover:text-blue-600">
                                            <path fill-rule="evenodd" d="M11.078 2.25c-.917 0-1.699.663-1.85 1.567L9.05 4.889c-.02.12-.115.26-.297.348a7.493 7.493 0 0 0-.986.57c-.166.115-.334.126-.45.083L6.3 5.508a1.875 1.875 0 0 0-2.282.819l-.922 1.597a1.875 1.875 0 0 0 .432 2.385l.84.692c.095.078.17.229.154.43a7.598 7.598 0 0 0 0 1.139c.015.2-.059.352-.153.43l-.841.692a1.875 1.875 0 0 0-.432 2.385l.922 1.597a1.875 1.875 0 0 0 2.282.818l1.019-.382c.115-.043.283-.031.45.082.312.214.641.405.985.57.182.088.277.228.297.35l.178 1.071c.151.904.933 1.567 1.85 1.567h1.844c.916 0 1.699-.663 1.85-1.567l.178-1.072c.02-.12.114-.26.297-.349.344-.165.673-.356.985-.57.167-.114.335-.125.45-.082l1.02.382a1.875 1.875 0 0 0 2.28-.819l.923-1.597a1.875 1.875 0 0 0-.432-2.385l-.84-.692c-.095-.078-.17-.229-.154-.43a7.614 7.614 0 0 0 0-1.139c-.016-.2.059-.352.153-.43l.84-.692c.708-.582.891-1.59.433-2.385l-.922-1.597a1.875 1.875 0 0 0-2.282-.818l-1.02.382c-.114.043-.282.031-.449-.083a7.49 7.49 0 0 0-.985-.57c-.183-.087-.277-.227-.297-.348l-.179-1.072a1.875 1.875 0 0 0-1.85-1.567h-1.843ZM12 15.75a3.75 3.75 0 1 0 0-7.5 3.75 3.75 0 0 0 0 7.5Z" clip-rule="evenodd" />
                                        </svg>

                                        <span class="ml-3">Settings</span>
                                    </a>
                                </li>
                                @endrole
                                <li>
                                    <a href="{{route('subdomain.activities')}}" class="flex items-center p-2 rounded-lg text-gray-700 hover:bg-blue-50 hover:text-blue-600 group transition-colors duration-200">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5 transition duration-75 group-hover:text-blue-600">
                                            <path fill-rule="evenodd" d="M5.25 9a6.75 6.75 0 0 1 13.5 0v.75c0 2.123.8 4.057 2.118 5.52a.75.75 0 0 1-.297 1.206c-1.544.57-3.16.99-4.831 1.243a3.75 3.75 0 1 1-7.48 0 24.585 24.585 0 0 1-4.831-1.244.75.75 0 0 1-.298-1.205A8.217 8.217 0 0 0 5.25 9.75V9Zm4.502 8.9a2.25 2.25 0 1 0 4.496 0 25.057 25.057 0 0 1-4.496 0Z" clip-rule="evenodd" />
                                        </svg>


                                        <span class="ml-3">Activities</span>
                                    </a>
                                </li>
                            </ul>
                        </div>

                        <!-- Inventory Section -->
                        <div>
                            <div class="flex items-center px-2 py-2 text-sm font-semibold text-gray-500 uppercase tracking-wider">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 text-emerald-500" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M7 3a1 1 0 000 2h6a1 1 0 100-2H7zM4 7a1 1 0 011-1h10a1 1 0 110 2H5a1 1 0 01-1-1zM2 11a2 2 0 012-2h12a2 2 0 012 2v4a2 2 0 01-2 2H4a2 2 0 01-2-2v-4z" />
                                </svg>
                                Inventory
                            </div>
                            <ul class="mt-2 space-y-1">
                                <li>
                                    <a href="{{route('subdomain.categories')}}" class="flex items-center p-2 rounded-lg text-gray-700 hover:bg-emerald-50 hover:text-emerald-600 group transition-colors duration-200">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5 transition duration-75 group-hover:text-emerald-600">
                                            <path fill-rule="evenodd" d="M7.502 6h7.128A3.375 3.375 0 0 1 18 9.375v9.375a3 3 0 0 0 3-3V6.108c0-1.505-1.125-2.811-2.664-2.94a48.972 48.972 0 0 0-.673-.05A3 3 0 0 0 15 1.5h-1.5a3 3 0 0 0-2.663 1.618c-.225.015-.45.032-.673.05C8.662 3.295 7.554 4.542 7.502 6ZM13.5 3A1.5 1.5 0 0 0 12 4.5h4.5A1.5 1.5 0 0 0 15 3h-1.5Z" clip-rule="evenodd" />
                                            <path fill-rule="evenodd" d="M3 9.375C3 8.339 3.84 7.5 4.875 7.5h9.75c1.036 0 1.875.84 1.875 1.875v11.25c0 1.035-.84 1.875-1.875 1.875h-9.75A1.875 1.875 0 0 1 3 20.625V9.375ZM6 12a.75.75 0 0 1 .75-.75h.008a.75.75 0 0 1 .75.75v.008a.75.75 0 0 1-.75.75H6.75a.75.75 0 0 1-.75-.75V12Zm2.25 0a.75.75 0 0 1 .75-.75h3.75a.75.75 0 0 1 0 1.5H9a.75.75 0 0 1-.75-.75ZM6 15a.75.75 0 0 1 .75-.75h.008a.75.75 0 0 1 .75.75v.008a.75.75 0 0 1-.75.75H6.75a.75.75 0 0 1-.75-.75V15Zm2.25 0a.75.75 0 0 1 .75-.75h3.75a.75.75 0 0 1 0 1.5H9a.75.75 0 0 1-.75-.75ZM6 18a.75.75 0 0 1 .75-.75h.008a.75.75 0 0 1 .75.75v.008a.75.75 0 0 1-.75.75H6.75a.75.75 0 0 1-.75-.75V18Zm2.25 0a.75.75 0 0 1 .75-.75h3.75a.75.75 0 0 1 0 1.5H9a.75.75 0 0 1-.75-.75Z" clip-rule="evenodd" />
                                        </svg>
                                        <span class="ml-3">Categories</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="{{route('subdomain.products')}}" class="flex items-center p-2 rounded-lg text-gray-700 hover:bg-emerald-50 hover:text-emerald-600 group transition-colors duration-200">
                                        <svg class="w-5 h-5 transition duration-75 group-hover:text-emerald-600" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 18 20">
                                            <path d="M17 5.923A1 1 0 0 0 16 5h-3V4a4 4 0 1 0-8 0v1H2a1 1 0 0 0-1 .923L.086 17.846A2 2 0 0 0 2.08 20h13.84a2 2 0 0 0 1.994-2.153L17 5.923ZM7 4a2 2 0 1 1 4 0v1H7V4Z"/>
                                        </svg>
                                        <span class="ml-3">Products</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="{{route('subdomain.tags')}}" class="flex items-center p-2 rounded-lg text-gray-700 hover:bg-emerald-50 hover:text-emerald-600 group transition-colors duration-200">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5 transition duration-75 group-hover:text-emerald-600">
                                            <path fill-rule="evenodd" d="M5.25 2.25a3 3 0 0 0-3 3v4.318a3 3 0 0 0 .879 2.121l9.58 9.581c.92.92 2.39 1.186 3.548.428a18.849 18.849 0 0 0 5.441-5.44c.758-1.16.492-2.629-.428-3.548l-9.58-9.581a3 3 0 0 0-2.122-.879H5.25ZM6.375 7.5a1.125 1.125 0 1 0 0-2.25 1.125 1.125 0 0 0 0 2.25Z" clip-rule="evenodd" />
                                        </svg>

                                        <span class="ml-3">Tags</span>
                                    </a>
                                </li>
                            </ul>
                        </div>

                        <!-- Content Management Section -->
                        <div>
                            <div class="flex items-center px-2 py-2 text-sm font-semibold text-gray-500 uppercase tracking-wider">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 text-purple-500" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M5 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2H5zM5 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2H5zM11 5a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V5zM11 13a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                                </svg>
                                Content
                            </div>
                            <ul class="mt-2 space-y-1">
                                <li>
                                    <a href="{{route('subdomain.sections')}}" class="flex items-center p-2 rounded-lg text-gray-700 hover:bg-purple-50 hover:text-purple-600 group transition-colors duration-200">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5 transition duration-75 group-hover:text-purple-600">
                                            <path d="M5.566 4.657A4.505 4.505 0 0 1 6.75 4.5h10.5c.41 0 .806.055 1.183.157A3 3 0 0 0 15.75 3h-7.5a3 3 0 0 0-2.684 1.657ZM2.25 12a3 3 0 0 1 3-3h13.5a3 3 0 0 1 3 3v6a3 3 0 0 1-3 3H5.25a3 3 0 0 1-3-3v-6ZM5.25 7.5c-.41 0-.806.055-1.184.157A3 3 0 0 1 6.75 6h10.5a3 3 0 0 1 2.683 1.657A4.505 4.505 0 0 0 18.75 7.5H5.25Z" />
                                        </svg>
                                        <span class="ml-3">Sections</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="{{route('subdomain.ads')}}" class="flex items-center p-2 rounded-lg text-gray-700 hover:bg-purple-50 hover:text-purple-600 group transition-colors duration-200">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5 transition duration-75 group-hover:text-purple-600">
                                            <path fill-rule="evenodd" d="M9 4.5a.75.75 0 0 1 .721.544l.813 2.846a3.75 3.75 0 0 0 2.576 2.576l2.846.813a.75.75 0 0 1 0 1.442l-2.846.813a3.75 3.75 0 0 0-2.576 2.576l-.813 2.846a.75.75 0 0 1-1.442 0l-.813-2.846a3.75 3.75 0 0 0-2.576-2.576l-2.846-.813a.75.75 0 0 1 0-1.442l2.846-.813A3.75 3.75 0 0 0 7.466 7.89l.813-2.846A.75.75 0 0 1 9 4.5ZM18 1.5a.75.75 0 0 1 .728.568l.258 1.036c.236.94.97 1.674 1.91 1.91l1.036.258a.75.75 0 0 1 0 1.456l-1.036.258c-.94.236-1.674.97-1.91 1.91l-.258 1.036a.75.75 0 0 1-1.456 0l-.258-1.036a2.625 2.625 0 0 0-1.91-1.91l-1.036-.258a.75.75 0 0 1 0-1.456l1.036-.258a2.625 2.625 0 0 0 1.91-1.91l.258-1.036A.75.75 0 0 1 18 1.5ZM16.5 15a.75.75 0 0 1 .712.513l.394 1.183c.15.447.5.799.948.948l1.183.395a.75.75 0 0 1 0 1.422l-1.183.395c-.447.15-.799.5-.948.948l-.395 1.183a.75.75 0 0 1-1.422 0l-.395-1.183a1.5 1.5 0 0 0-.948-.948l-1.183-.395a.75.75 0 0 1 0-1.422l1.183-.395c.447-.15.799-.5.948-.948l.395-1.183A.75.75 0 0 1 16.5 15Z" clip-rule="evenodd" />
                                        </svg>

                                        <span class="ml-3">Ads</span>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </aside>

            <!-- Main Content -->
            <div class="lg:ml-64">
                <!-- Top Navbar -->
                <nav class="bg-white border-b border-gray-200">
                    <div class="px-3 py-3 lg:px-5 lg:pl-3">
                        <div class="flex items-center justify-between">

                            <!-- Mobile Menu -->
                            <div class="flex items-center justify-start">
                                <button @click="sidebarOpen = !sidebarOpen" class="lg:hidden p-2 rounded-full hover:bg-gray-100">
                                    <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                                    </svg>
                                </button>
                            </div>

                            <!-- Profile Dropdown -->
                            <div class="flex items-center gap-4">
                                <!-- Notification Bell Dropdown -->
                                <livewire:backend.partials.notification-dropdown />
                                <div x-data="{ open: false }" class="relative">
                                    <button
                                        @click="open = !open"
                                        @click.away="open = false"
                                        class="relative flex rounded-full bg-gray-100 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    >
                                        <!-- User Avatar -->
                                        <img
                                            class="h-10 w-10 rounded-full"
                                            src="https://ui-avatars.com/api/?name={{auth()->user()->name}}&background=random"
                                            alt="Avatar"
                                        />
                                    </button>
                                    <div
                                        x-show="open"
                                        x-transition:enter="transition ease-out duration-100"
                                        x-transition:enter-start="transform opacity-0 scale-95"
                                        x-transition:enter-end="transform opacity-100 scale-100"
                                        x-transition:leave="transition ease-in duration-75"
                                        x-transition:leave-start="transform opacity-100 scale-100"
                                        x-transition:leave-end="transform opacity-0 scale-95"
                                        class="absolute right-0 z-10 mt-2 w-48 origin-top-right rounded-md bg-white py-1 shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none"
                                    >
                                        <a href="{{config('app.url')}}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Website</a>
                                        <a href="{{route('subdomain.settings')}}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Settings</a>
                                        <form method="POST" action="{{ route('logout') }}">
                                            @csrf
                                            <button type="submit" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                Logout
                                            </button>
                                        </form>
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

        <script>
            document.addEventListener('DOMContentLoaded', () => {
                if (window.Echo && window.Laravel?.userId) {
                    window.Echo.private(`App.Models.User.${window.Laravel.userId}`)
                        .notification(() => {
                            // Notify Livewire components to refresh
                            window.Livewire?.dispatch('notificationReceived')
                        })
                }
            })
        </script>
        @stack('scripts')
    </body>
</html>


