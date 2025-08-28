<div class="relative" x-data="{ open: @entangle('showDropdown') }">
    <!-- Notification Bell -->
    <button 
        wire:click="toggleDropdown"
        class="relative p-2 text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-indigo-500"
        data-notification-count="{{ $unreadCount }}"
    >
        <span class="sr-only">View notifications</span>
        <i class="fas fa-bell h-6 w-6"></i>
        
        <!-- Notification Badge -->
        @if($unreadCount > 0)
            <span class="absolute -top-1 -right-1 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white transform translate-x-1/2 -translate-y-1/2 bg-red-500 rounded-full">
                {{ $unreadCount > 99 ? '99+' : $unreadCount }}
            </span>
        @endif
    </button>

    <!-- Notification Dropdown -->
    <div 
        x-show="open" 
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="transform opacity-0 scale-95"
        x-transition:enter-end="transform opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="transform opacity-100 scale-100"
        x-transition:leave-end="transform opacity-0 scale-95"
        class="absolute right-0 z-50 mt-2 w-80 origin-top-right rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none"
        role="menu"
        aria-orientation="vertical"
        aria-labelledby="menu-button"
        tabindex="-1"
        data-notifications-list
    >
        <div class="py-1">
            <!-- Header -->
            <div class="flex items-center justify-between px-4 py-2 border-b border-gray-200">
                <h3 class="text-sm font-medium text-gray-900">Notifications</h3>
                @if($unreadCount > 0)
                    <button 
                        wire:click="markAllAsRead"
                        class="text-xs text-indigo-600 hover:text-indigo-500"
                    >
                        Mark all as read
                    </button>
                @endif
            </div>

            <!-- Notifications List -->
            <div class="max-h-96 overflow-y-auto">
                @forelse($notifications as $notification)
                    <div 
                        class="px-4 py-3 hover:bg-gray-50 {{ $notification['read_at'] ? 'opacity-75' : '' }}"
                        data-notification-id="{{ $notification['id'] }}"
                    >
                        <div class="flex items-start">
                            <!-- Icon -->
                            <div class="flex-shrink-0">
                                <i class="{{ $this->getNotificationIcon($notification['type']) }} {{ $this->getNotificationColor($notification['type']) }} h-5 w-5"></i>
                            </div>

                            <!-- Content -->
                            <div class="ml-3 flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900">
                                    {{ $this->getNotificationTitle($notification['type'], $notification['data']) }}
                                </p>
                                <p class="text-sm text-gray-500 mt-1">
                                    {{ $this->getNotificationMessage($notification['type'], $notification['data']) }}
                                </p>
                                <p class="text-xs text-gray-400 mt-1">
                                    {{ $notification['created_at'] }}
                                </p>
                            </div>

                            <!-- Action -->
                            @if(!$notification['read_at'])
                                <div class="ml-3 flex-shrink-0">
                                    <button 
                                        wire:click="markAsRead('{{ $notification['id'] }}')"
                                        class="text-xs text-indigo-600 hover:text-indigo-500"
                                    >
                                        Mark as read
                                    </button>
                                </div>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="px-4 py-3 text-center text-sm text-gray-500">
                        No notifications
                    </div>
                @endforelse
            </div>

            <!-- Footer -->
            @if(count($notifications) > 0)
                <div class="border-t border-gray-200 px-4 py-2">
                    <a 
                        href="{{ route('notifications.index') }}" 
                        class="block text-center text-sm text-indigo-600 hover:text-indigo-500"
                    >
                        View all notifications
                    </a>
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Click outside to close -->
<div 
    x-show="open" 
    x-transition:enter="transition ease-out duration-100"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-75"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    class="fixed inset-0 z-40"
    @click="open = false"
></div>
