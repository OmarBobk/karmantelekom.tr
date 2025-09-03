<div class="relative" wire:ignore x-data="{
    open: false,
    toggleDropdown() {
        this.open = !this.open;
        console.log(this.unreadNotifications);
    },

    // Seed from PHP once; keep updates fully in Alpine for instant UI
    unreadNotifications: @js($this->unreadNotifications),
    unreadCount: @js($this->unreadCount),

    init() {
        // Store reference to Alpine component
        const alpineComponent = this;

        window.userId = document.querySelector('meta[name=\'user-id\']')?.content;
        window.Echo.private(`App.Models.User.${window.userId}`)
            .notification((notification) => {
                // Increment the unread count immediately
                alpineComponent.unreadCount++;
                const newItem = { id: notification.id ?? Date.now().toString(), data: notification };
                // Replace array reference to guarantee Alpine reactivity
                alpineComponent.unreadNotifications = [newItem, ...alpineComponent.unreadNotifications];

                console.log(this.unreadNotifications)
                console.log(notification)
                // Notify toast listener
                window.Livewire?.dispatch('notify', [{
                    type: notification.action ?? 'alert-info',
                    message: notification.description ?? 'You have a new notification',
                    sec: 1500
                }]);
            })
            .error((error) => {
                console.error('Notification channel error:', error);
            });
    },
    }">
    <!-- Notification Bell Button -->
    <button
        @click="toggleDropdown()"
        @click.away="open = false"
        class="relative flex items-center justify-center w-10 h-10 rounded-full hover:bg-blue-100 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500"
        :class="open ? 'bg-blue-100' : ''"
    >
        <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
        </svg>

        <!-- Unread Count Badge -->
        <span
            x-show="unreadCount > 0"
            class="absolute -top-1 -right-1 inline-flex items-center justify-center px-1.5 py-0.5 text-xs font-bold leading-none text-white bg-red-500 rounded-full min-w-[1.25rem] h-5">
            <span x-text="unreadCount > 99 ? '99+' : unreadCount"></span>
        </span>
    </button>
    <!-- Dropdown Panel -->
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="transform opacity-0 scale-95"
        x-transition:enter-end="transform opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="transform opacity-100 scale-100"
        x-transition:leave-end="transform opacity-0 scale-95"
        class="absolute right-[-240%] z-50 mt-2 w-screen sm:w-96 max-w-[calc(100vw-2rem)] origin-top-right rounded-lg bg-white
        shadow-xl ring-1 ring-black ring-opacity-5 focus:outline-none
               sm:right-0
               "
        style="display: none;"
    >
        <!-- Header -->
        <div class="flex items-center justify-between px-3 sm:px-4 py-3 border-b border-gray-100">
            <div class="flex items-center space-x-2 min-w-0">
                <h3 class="text-base sm:text-lg font-semibold text-gray-800 truncate">Notifications</h3>
                <span
                    x-show="unreadCount > 0"
                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 flex-shrink-0">
                    <span x-text="unreadCount"></span> new
                </span>
            </div>

            <template x-if="unreadCount > 0">
                <button
                    @click="unreadNotifications = []; unreadCount = 0; $wire.markAllAsRead()"
                    class="text-xs sm:text-sm text-blue-600 hover:text-blue-800 font-medium transition-all duration-200 hover:bg-blue-50 px-2 py-1 rounded disabled:opacity-50 flex-shrink-0"
                >
                    <span>Mark all read</span>
                </button>
            </template>
        </div>

        <!-- Notifications List -->
        <div class="max-h-96 overflow-y-auto" wire:loading.class="opacity-50">
            <template x-for="notification in unreadNotifications" :key="notification.id">
                <div
                    class="group flex items-start space-x-2 sm:space-x-3 px-3 sm:px-4 py-3 hover:bg-gray-50 transition-all duration-200 border-b border-gray-50 last:border-b-0 cursor-pointer"
                    x-data="{ isRemoving: false }"
                    x-show="!isRemoving"
                    x-transition:leave="transition ease-in duration-200"
                    x-transition:leave-start="opacity-100 transform scale-100"
                    x-transition:leave-end="opacity-0 transform scale-95"
                    @click="unreadNotifications = unreadNotifications.filter(n => n.id !== notification.id); if (unreadCount > 0) unreadCount--; $wire.markAsReadAndNavigate(notification.id, notification.data.order_link)"
                    @click="if($event.target.tagName !== 'A' && $event.target.tagName !== 'BUTTON') { isRemoving = true; setTimeout(() => isRemoving = false, 200); }">

                    <!-- Notification Icon -->
                    <div class="flex-shrink-0 mt-0.5 sm:mt-1">
                        <template x-if="notification.data.is_icon_exist">
                            <div
                                class="w-7 h-7 sm:w-8 sm:h-8 rounded-full flex items-center justify-center group-hover:bg-indigo-200 transition-colors"
                                x-html="notification.data.icon"
                            >
                            </div>
                        </template>
                    </div>

                    <!-- Notification Content -->
                    <div class="flex-1 min-w-0">
                        <!-- Summary Line -->
                        <div class="flex items-center space-x-1 text-sm">
                            <span
                                class="font-medium text-gray-900 group-hover:text-gray-800" x-text="notification.data.performed_by"></span>
                            <span
                                class="text-gray-600 group-hover:text-gray-700" x-text="notification.data.summary.what"></span>
                            <template x-if="notification.data.order_id">
                                <template x-if="notification.data.order_link">
                                    <a :href="notification.data.order_link"
                                       class="font-medium text-blue-600 hover:text-blue-800 transition-colors hover:underline"
                                       @click.stop="open = false"
                                       onclick="event.stopPropagation();">
                                        #<span x-text="notification.data.order_id"></span>
                                    </a>
                                </template>
                                <template x-if="!notification.data.order_link">
                                    <span class="font-medium text-gray-700 group-hover:text-gray-800">
                                        #<span x-text="notification.data.order_id"></span>
                                    </span>
                                </template>
                            </template>
                        </div>

                        <!-- Full Message -->
                        <p class="text-sm text-gray-600 mt-1 line-clamp-2 group-hover:text-gray-700" x-html="notification.data.description"></p>

                        <!-- Timestamp and Actions -->
                        <div class="flex items-center justify-between mt-2">
                            <span class="text-xs text-gray-400 group-hover:text-gray-500"
                                  :title="notification.data.time_exact" x-text="notification.data.time"></span>

                            <div class="flex items-center space-x-2">
                                <button
                                    @click.stop="unreadNotifications = unreadNotifications.filter(n => n.id !== notification.id); if (unreadCount > 0) unreadCount--; $wire.markAsRead(notification.id)"
                                    class="text-xs text-blue-600 hover:text-blue-800 font-medium transition-all duration-200 opacity-70 sm:opacity-0 group-hover:opacity-100 hover:bg-blue-50 px-2 py-1 rounded"
                                    onclick="event.stopPropagation();"
                                >
                                    <span>Mark read</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Unread Indicator -->
                    <div class="flex-shrink-0">
                        <div class="w-2 h-2 bg-blue-500 rounded-full group-hover:bg-blue-600 transition-colors"></div>
                    </div>
                </div>
            </template>

            <!-- Empty State -->
            <template x-if="unreadNotifications.length === 0">
                <div class="flex flex-col items-center justify-center py-8 px-4">
                    <svg class="w-12 h-12 text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1"
                              d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                    <p class="text-gray-500 text-sm font-medium">No new notifications</p>
                    <p class="text-gray-400 text-xs mt-1">You're all caught up!</p>
                </div>
            </template>
        </div>

        <!-- Footer -->
        @if($this->unreadCount > 0)
            <div class="px-3 sm:px-4 py-3 border-t border-gray-100 bg-gray-50 rounded-b-lg">
                <div class="text-center">
                    <a href="#"
                       class="text-xs sm:text-sm text-blue-600 hover:text-blue-800 font-medium transition-colors">
                        View all notifications
                    </a>
                </div>
            </div>
        @endif
    </div>
</div>
