<?php

declare(strict_types=1);

namespace App\Livewire\Backend;

use Livewire\Component;
use Livewire\Attributes\On;

/**
 * RealTimeNotifications Component
 *
 * Handles real-time notifications and displays them in the UI
 */
class RealTimeNotifications extends Component
{
    public array $notifications = [];
    public int $unreadCount = 0;
    public bool $showDropdown = false;

    protected $listeners = [
        'echo-private:App.Models.User.{userId},.Illuminate\\Notifications\\Events\\BroadcastNotificationCreated' => 'handleNotification',
        'shop-created' => 'handleShopCreated',
        'shop-assigned' => 'handleShopAssigned',
        'order-created' => 'handleOrderCreated',
        'order-updated' => 'handleOrderUpdated',
    ];

    public function mount()
    {
        $this->loadNotifications();
    }

    public function render()
    {
        return view('livewire.backend.real-time-notifications');
    }

    /**
     * Load existing notifications from database
     */
    public function loadNotifications()
    {
        $user = auth()->user();
        if (!$user) return;

        $this->notifications = $user->notifications()
            ->latest()
            ->take(10)
            ->get()
            ->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'type' => $notification->type,
                    'data' => $notification->data,
                    'read_at' => $notification->read_at,
                    'created_at' => $notification->created_at->diffForHumans(),
                ];
            })
            ->toArray();

        $this->unreadCount = $user->unreadNotifications()->count();
    }

    /**
     * Handle incoming notification from Echo
     */
    #[On('echo-private:App.Models.User.{userId},.Illuminate\\Notifications\\Events\\BroadcastNotificationCreated')]
    public function handleNotification($event)
    {
        $this->addNotification([
            'id' => uniqid(),
            'type' => 'notification',
            'data' => $event,
            'read_at' => null,
            'created_at' => now()->diffForHumans(),
        ]);

        $this->unreadCount++;
        $this->showDropdown = true;
    }

    /**
     * Handle shop created event
     */
    #[On('shop-created')]
    public function handleShopCreated($data)
    {
        $this->addNotification([
            'id' => uniqid(),
            'type' => 'shop-created',
            'data' => $data,
            'read_at' => null,
            'created_at' => now()->diffForHumans(),
        ]);

        $this->unreadCount++;
    }

    /**
     * Handle shop assigned event
     */
    #[On('shop-assigned')]
    public function handleShopAssigned($data)
    {
        $this->addNotification([
            'id' => uniqid(),
            'type' => 'shop-assigned',
            'data' => $data,
            'read_at' => null,
            'created_at' => now()->diffForHumans(),
        ]);

        $this->unreadCount++;
    }

    /**
     * Handle order created event
     */
    #[On('order-created')]
    public function handleOrderCreated($data)
    {
        $this->addNotification([
            'id' => uniqid(),
            'type' => 'order-created',
            'data' => $data,
            'read_at' => null,
            'created_at' => now()->diffForHumans(),
        ]);

        $this->unreadCount++;
    }

    /**
     * Handle order updated event
     */
    #[On('order-updated')]
    public function handleOrderUpdated($data)
    {
        $this->addNotification([
            'id' => uniqid(),
            'type' => 'order-updated',
            'data' => $data,
            'read_at' => null,
            'created_at' => now()->diffForHumans(),
        ]);

        $this->unreadCount++;
    }

    /**
     * Add notification to the list
     */
    private function addNotification(array $notification)
    {
        array_unshift($this->notifications, $notification);
        
        // Keep only the latest 10 notifications
        if (count($this->notifications) > 10) {
            $this->notifications = array_slice($this->notifications, 0, 10);
        }
    }

    /**
     * Mark notification as read
     */
    public function markAsRead($notificationId)
    {
        $user = auth()->user();
        if (!$user) return;

        // Mark database notification as read
        $user->notifications()->where('id', $notificationId)->update(['read_at' => now()]);

        // Mark local notification as read
        foreach ($this->notifications as &$notification) {
            if ($notification['id'] === $notificationId) {
                $notification['read_at'] = now();
                break;
            }
        }

        $this->unreadCount = max(0, $this->unreadCount - 1);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead()
    {
        $user = auth()->user();
        if (!$user) return;

        // Mark all database notifications as read
        $user->unreadNotifications()->update(['read_at' => now()]);

        // Mark all local notifications as read
        foreach ($this->notifications as &$notification) {
            $notification['read_at'] = now();
        }

        $this->unreadCount = 0;
    }

    /**
     * Toggle notification dropdown
     */
    public function toggleDropdown()
    {
        $this->showDropdown = !$this->showDropdown;
    }

    /**
     * Get notification icon based on type
     */
    public function getNotificationIcon($type): string
    {
        return match ($type) {
            'shop-created' => 'fas fa-store',
            'shop-assigned' => 'fas fa-user-check',
            'order-created' => 'fas fa-shopping-cart',
            'order-updated' => 'fas fa-edit',
            default => 'fas fa-bell',
        };
    }

    /**
     * Get notification color based on type
     */
    public function getNotificationColor($type): string
    {
        return match ($type) {
            'shop-created', 'order-created' => 'text-green-500',
            'shop-assigned', 'order-updated' => 'text-blue-500',
            default => 'text-gray-500',
        };
    }

    /**
     * Get notification title based on type
     */
    public function getNotificationTitle($type, $data): string
    {
        return match ($type) {
            'shop-created' => 'New Shop Created',
            'shop-assigned' => 'Shop Assignment',
            'order-created' => 'New Order',
            'order-updated' => 'Order Updated',
            default => 'New Notification',
        };
    }

    /**
     * Get notification message based on type
     */
    public function getNotificationMessage($type, $data): string
    {
        return match ($type) {
            'shop-created' => "Shop \"{$data['shop']['name']}\" has been created",
            'shop-assigned' => "Shop \"{$data['shop']['name']}\" has been assigned to {$data['salesperson']['name']}",
            'order-created' => "Order #{$data['order']['id']} created with status: {$data['order']['status_label']}",
            'order-updated' => "Order #{$data['order']['id']} has been updated",
            default => $data['description'] ?? 'New notification received',
        };
    }
}
