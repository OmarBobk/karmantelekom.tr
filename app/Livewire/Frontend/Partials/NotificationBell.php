<?php

declare(strict_types=1);

namespace App\Livewire\Frontend\Partials;

use Livewire\Component;

/**
 * NotificationBell Component
 *
 * Simple notification bell for frontend header - shows unread count only
 */
class NotificationBell extends Component
{
    public bool $isOpen = false;
    public int $unreadCount = 0;
    public array $unreadNotifications = [];
    public $listeners = ['notificationReceived' => 'refreshNotifications'];

    public function mount(): void
    {
        // Listen for real-time notifications if you have broadcasting enabled
        $this->refreshNotificationData();
    }

    public function markAsRead(string $notificationId): void
    {
        $notification = auth()->user()->unreadNotifications()->find($notificationId);

        if ($notification) {
            $notification->markAsRead();

            // Refresh notification data
            $this->refreshNotificationData();

            // Dispatch events to update other notification components
            $this->dispatch('notificationUpdated');
            $this->dispatch('notification-updated');

            $this->dispatch('notify', [
                'type' => 'alert-success',
                'message' => 'Notification marked as read',
                'sec' => 1500
            ]);
        }
    }

    public function markAllAsRead(): void
    {
        $count = auth()->user()->unreadNotifications()->count();

        if ($count > 0) {
            auth()->user()->unreadNotifications()->update(['read_at' => now()]);

            // Refresh notification data
            $this->refreshNotificationData();

            // Dispatch events to update other notification components
            $this->dispatch('notificationUpdated');
            $this->dispatch('notification-updated');

            $this->dispatch('notify', [
                'type' => 'alert-success',
                'message' => "All {$count} notifications marked as read",
                'sec' => 2000
            ]);
        }
    }

    public function markAsReadAndNavigate(string $notificationId, ?string $orderLink = null): void
    {
        $this->markAsRead($notificationId);

        if ($orderLink) {
            $this->isOpen = false;
            $this->redirect($orderLink);
        }
    }
    public function refreshNotificationData(): void
    {
        $notifications = auth()->user()->unreadNotifications();
        $this->unreadCount = $notifications->count();
        $this->unreadNotifications = $notifications
            ->latest()
            ->limit(10)
            ->get()
            ->toArray();
    }

    public function refreshNotifications(): void
    {
        // This method will be called when new notifications arrive
        $this->refreshNotificationData();
    }
    public function render()
    {
        return view('livewire.frontend.partials.notification-bell');
    }
}
