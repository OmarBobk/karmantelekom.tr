<div class="relative" x-data="{ open: @entangle('isOpen') }">
    <!-- Notification Bell Button -->
    <button
        wire:click="toggleDropdown"
        @click.away="open = false"
        class="relative flex items-center justify-center w-10 h-10 rounded-full hover:bg-blue-100 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500"
        :class="open ? 'bg-blue-100' : ''"
    >
        <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
        </svg>

        <!-- Unread Count Badge -->
        @if($this->unreadCount > 0)
            <span class="absolute -top-1 -right-1 inline-flex items-center justify-center px-1.5 py-0.5 text-xs font-bold leading-none text-white bg-red-500 rounded-full min-w-[1.25rem] h-5">
                {{ $this->unreadCount > 99 ? '99+' : $this->unreadCount }}
            </span>
        @endif
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
                @if($this->unreadCount > 0)
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 flex-shrink-0">
                        {{ $this->unreadCount }} new
                    </span>
                @endif
            </div>

            @if($this->unreadCount > 0)
                <button
                    wire:click="markAllAsRead"
                    wire:loading.attr="disabled"
                    wire:target="markAllAsRead"
                    class="text-xs sm:text-sm text-blue-600 hover:text-blue-800 font-medium transition-all duration-200 hover:bg-blue-50 px-2 py-1 rounded disabled:opacity-50 flex-shrink-0"
                >
                    <span wire:loading.remove wire:target="markAllAsRead" class="hidden sm:inline">Mark all read</span>
                    <span wire:loading.remove wire:target="markAllAsRead" class="sm:hidden">Mark all</span>
                    <span wire:loading wire:target="markAllAsRead" class="flex items-center space-x-1">
                        <svg class="animate-spin h-3 w-3 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 0 14 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span class="hidden sm:inline">Marking...</span>
                        <span class="sm:hidden">...</span>
                    </span>
                </button>
            @endif
        </div>

        <!-- Notifications List -->
        <div class="max-h-96 overflow-y-auto" wire:loading.class="opacity-50">
            @forelse($this->unreadNotifications as $notification)
                <div class="group flex items-start space-x-2 sm:space-x-3 px-3 sm:px-4 py-3 hover:bg-gray-50 transition-all duration-200 border-b border-gray-50 last:border-b-0 cursor-pointer"
                     x-data="{ isRemoving: false }"
                     x-show="!isRemoving"
                     x-transition:leave="transition ease-in duration-200"
                     x-transition:leave-start="opacity-100 transform scale-100"
                     x-transition:leave-end="opacity-0 transform scale-95"
                     wire:click="markAsReadAndNavigate('{{ $notification['id'] }}', '{{ $notification['order_link'] }}')"
                     @click="if($event.target.tagName !== 'A' && $event.target.tagName !== 'BUTTON') { isRemoving = true; setTimeout(() => isRemoving = false, 200); }">

                    <!-- Notification Icon -->
                    <div class="flex-shrink-0 mt-0.5 sm:mt-1">
                        @if($notification['icon'] === 'check-circle')
                            <div class="w-7 h-7 sm:w-8 sm:h-8 bg-green-100 rounded-full flex items-center justify-center group-hover:bg-green-200 transition-colors">
                                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                        @elseif($notification['icon'] === 'x-circle')
                            <div class="w-7 h-7 sm:w-8 sm:h-8 bg-red-100 rounded-full flex items-center justify-center group-hover:bg-red-200 transition-colors">
                                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </div>
                        @elseif($notification['icon'] === 'exclamation-triangle')
                            <div class="w-7 h-7 sm:w-8 sm:h-8 bg-yellow-100 rounded-full flex items-center justify-center group-hover:bg-yellow-200 transition-colors">
                                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                </svg>
                            </div>
                        @elseif($notification['icon'] === 'shop')
                            <div class="w-7 h-7 sm:w-8 sm:h-8 bg-indigo-100 rounded-full flex items-center justify-center group-hover:bg-indigo-200 transition-colors">
                                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                            </div>
                        @else
                            <div class="w-7 h-7 sm:w-8 sm:h-8 bg-blue-100 rounded-full flex items-center justify-center group-hover:bg-blue-200 transition-colors">
                                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                        @endif
                    </div>

                    <!-- Notification Content -->
                    <div class="flex-1 min-w-0">
                        <!-- Summary Line -->
                        <div class="flex items-center space-x-1 text-sm">
                            <span class="font-medium text-gray-900 group-hover:text-gray-800">{{ $notification['summary']['who'] }}</span>
                            <span class="text-gray-600 group-hover:text-gray-700">{{ $notification['summary']['what'] }}</span>
                            @if($notification['summary']['order_id'])
                                @if($notification['order_link'])
                                    <a href="{{ $notification['order_link'] }}"
                                       class="font-medium text-blue-600 hover:text-blue-800 transition-colors hover:underline"
                                       @click.stop="open = false"
                                       onclick="event.stopPropagation();">
                                        #{{ $notification['summary']['order_id'] }}
                                    </a>
                                @else
                                    <span class="font-medium text-gray-700 group-hover:text-gray-800">#{{ $notification['summary']['order_id'] }}</span>
                                @endif
                            @endif
                            @if($notification['summary']['shop_name'])
                                @if($notification['order_link'])
                                    <a href="{{ $notification['order_link'] }}"
                                       class="font-medium text-indigo-600 hover:text-indigo-800 transition-colors hover:underline"
                                       @click.stop="open = false"
                                       onclick="event.stopPropagation();">
                                        "{{ $notification['summary']['shop_name'] }}"
                                    </a>
                                @else
                                    <span class="font-medium text-gray-700 group-hover:text-gray-800">"{{ $notification['summary']['shop_name'] }}"</span>
                                @endif
                            @endif
                        </div>

                        <!-- Full Message -->
                        <p class="text-sm text-gray-600 mt-1 line-clamp-2 group-hover:text-gray-700">{{ $notification['message'] }}</p>

                        <!-- Timestamp and Actions -->
                        <div class="flex items-center justify-between mt-2">
                            <span class="text-xs text-gray-400 group-hover:text-gray-500" title="{{ $notification['time_exact'] }}">
                                {{ $notification['time'] }}
                            </span>

                            <div class="flex items-center space-x-2">
                                <button
                                    wire:click.stop="markAsRead('{{ $notification['id'] }}')"
                                    wire:loading.attr="disabled"
                                    wire:target="markAsRead('{{ $notification['id'] }}')"
                                    class="text-xs text-blue-600 hover:text-blue-800 font-medium transition-all duration-200 opacity-70 sm:opacity-0 group-hover:opacity-100 hover:bg-blue-50 px-2 py-1 rounded"
                                    onclick="event.stopPropagation();"
                                >
                                    <span wire:loading.remove wire:target="markAsRead('{{ $notification['id'] }}')">Mark read</span>
                                    <span wire:loading wire:target="markAsRead('{{ $notification['id'] }}')">
                                        <svg class="animate-spin h-3 w-3 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 0 14 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Unread Indicator -->
                    <div class="flex-shrink-0">
                        <div class="w-2 h-2 bg-blue-500 rounded-full group-hover:bg-blue-600 transition-colors"></div>
                    </div>
                </div>
            @empty
                <div class="flex flex-col items-center justify-center py-8 px-4">
                    <svg class="w-12 h-12 text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                    <p class="text-gray-500 text-sm font-medium">No new notifications</p>
                    <p class="text-gray-400 text-xs mt-1">You're all caught up!</p>
                </div>
            @endforelse
        </div>

        <!-- Footer -->
        @if($this->unreadNotifications->count() > 0)
            <div class="px-3 sm:px-4 py-3 border-t border-gray-100 bg-gray-50 rounded-b-lg">
                <div class="text-center">
                    <a href="#" class="text-xs sm:text-sm text-blue-600 hover:text-blue-800 font-medium transition-colors">
                        View all notifications
                    </a>
                </div>
            </div>
        @endif
    </div>

    <!-- Real-time updates and interactions -->
    <script>
        // Auto-refresh notifications every 30 seconds
        setInterval(() => {
            if (!@this.get('isOpen')) {
            @this.refreshNotifications();
            }
        }, 30000);

        // Update timestamps every minute
        setInterval(() => {
        @this.$refresh();
        }, 60000);

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Mark all as read with Ctrl+Shift+M
            if (e.ctrlKey && e.shiftKey && e.key === 'M') {
                e.preventDefault();
                if (@this.get('unreadCount') > 0) {
                @this.call('markAllAsRead');
                }
            }

            // Close dropdown with Escape
            if (e.key === 'Escape' && @this.get('isOpen')) {
            @this.set('isOpen', false);
            }
        });

        // Listen for notification updates from other components
        window.addEventListener('notification-updated', function(e) {
        @this.refreshNotifications();
        });

        // Listen for real-time broadcasting events
        document.addEventListener('shop-created', function(e) {
            console.log('Frontend: Shop created event received', e.detail);
            @this.call('handleShopCreated', e.detail);
        });

        document.addEventListener('shop-assigned', function(e) {
            console.log('Frontend: Shop assigned event received', e.detail);
            @this.call('handleShopAssigned', e.detail);
        });

        document.addEventListener('order-created', function(e) {
            console.log('Frontend: Order created event received', e.detail);
            @this.call('handleOrderCreated', e.detail);
        });

        document.addEventListener('order-updated', function(e) {
            console.log('Frontend: Order updated event received', e.detail);
            @this.call('handleOrderUpdated', e.detail);
        });

                 document.addEventListener('notification-received', function(e) {
             console.log('Frontend: Notification received', e.detail);
             @this.call('handleBroadcastNotification', e.detail);
         });

         // Also listen for the specific Echo notification event
         document.addEventListener('echo-private:App.Models.User.{{ auth()->id() }},.Illuminate\\Notifications\\Events\\BroadcastNotificationCreated', function(e) {
             console.log('Frontend: Echo notification received', e.detail);
             @this.call('handleBroadcastNotification', e.detail);
         });

        // Connection status monitoring
        if (window.Echo) {
            window.Echo.connector.pusher.connection.bind('connected', () => {
                console.log('Frontend: Connected to Reverb broadcasting server');
            });

            window.Echo.connector.pusher.connection.bind('disconnected', () => {
                console.log('Frontend: Disconnected from Reverb broadcasting server');
            });

            window.Echo.connector.pusher.connection.bind('error', (error) => {
                console.error('Frontend: Reverb connection error:', error);
            });
        }
    </script>
</div>
