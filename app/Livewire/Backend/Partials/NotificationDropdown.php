<?php

declare(strict_types=1);

namespace App\Livewire\Backend\Partials;

use Livewire\Component;
use Illuminate\Notifications\DatabaseNotification;
use Carbon\Carbon;

/**
 * NotificationDropdown Component
 *
 * Real-time notification dropdown for displaying unread notifications
 */
class NotificationDropdown extends Component
{
    public bool $isOpen = false;
    public $listeners = ['notificationReceived' => 'refreshNotifications'];

    public function mount(): void
    {
        // Listen for real-time notifications if you have broadcasting enabled
    }

    public function getUnreadNotificationsProperty()
    {
        return auth()->user()->unreadNotifications()
            ->latest()
            ->limit(10)
            ->get()
            ->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'type' => $this->getNotificationType($notification),
                    'icon' => $this->getNotificationIcon($notification),
                    'message' => $this->formatNotificationMessage($notification),
                    'summary' => $this->getNotificationSummary($notification),
                    'time' => $notification->created_at->diffForHumans(),
                    'time_exact' => $notification->created_at->format('M j, Y \a\t g:i A'),
                    'order_id' => $this->getOrderId($notification),
                    'order_link' => $this->getOrderLink($notification),
                    'read_at' => $notification->read_at,
                ];
            });
    }

    public function getUnreadCountProperty(): int
    {
        return auth()->user()->unreadNotifications()->count();
    }

    public function markAsRead(string $notificationId): void
    {
        $notification = auth()->user()->unreadNotifications()->find($notificationId);

        if ($notification) {
            $notification->markAsRead();

            // Trigger immediate UI update by refreshing computed properties
            $this->getUnreadNotificationsProperty();
            $this->getUnreadCountProperty();

            // Dispatch events to update other notification components
            $this->dispatch('notificationUpdated');
            $this->dispatch('notification-updated');

            $this->dispatch('notify', [
                'type' => 'success',
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

            // Force refresh of computed properties for immediate UI update
            $this->getUnreadNotificationsProperty();
            $this->getUnreadCountProperty();

            // Dispatch events to update other notification components
            $this->dispatch('notificationUpdated');
            $this->dispatch('notification-updated');

            $this->dispatch('notify', [
                'type' => 'success',
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

    public function toggleDropdown(): void
    {
        $this->isOpen = !$this->isOpen;
    }

    public function refreshNotifications(): void
    {
        // This method will be called when new notifications arrive
        $this->render();
    }

    private function getNotificationType($notification): string
    {
        // Map notification types to UI types
        $data = $notification->data;

        if (isset($data['description'])) {
            $description = strtolower($data['description']);

            if (str_contains($description, 'created')) {
                return 'info';
            } elseif (str_contains($description, 'status changed') || str_contains($description, 'updated')) {
                return 'success';
            } elseif (str_contains($description, 'cancelled') || str_contains($description, 'failed')) {
                return 'error';
            } else {
                return 'info';
            }
        }

        return 'info';
    }

    private function getNotificationIcon($notification): string
    {
        $type = $this->getNotificationType($notification);

        return match($type) {
            'success' => 'check-circle',
            'error' => 'x-circle',
            'warning' => 'exclamation-triangle',
            default => 'information-circle'
        };
    }

    private function formatNotificationMessage($notification): string
    {
        return $notification->data['description'] ?? 'New notification';
    }

    private function getNotificationSummary($notification): array
    {
        $data = $notification->data;
        $performedBy = $data['performed_by'] ?? 'System';

        // Extract order ID if present
        $description = $data['description'] ?? '';
        preg_match('/Order #(\d+)/', $description, $matches);
        $orderId = $matches[1] ?? null;

        return [
            'who' => $performedBy,
            'what' => $this->extractAction($description),
            'order_id' => $orderId,
        ];
    }

    private function extractAction(string $description): string
    {
        if (str_contains($description, 'has been created')) {
            return 'created an order';
        } elseif (str_contains($description, 'status changed')) {
            return 'updated order status';
        } elseif (str_contains($description, 'total changed')) {
            return 'updated order total';
        } elseif (str_contains($description, 'has been updated')) {
            return 'updated an order';
        }

        return 'performed an action';
    }

    private function getOrderId($notification): ?string
    {
        $description = $notification->data['description'] ?? '';
        preg_match('/Order #(\d+)/', $description, $matches);
        return $matches[1] ?? null;
    }

    private function getOrderLink($notification): ?string
    {
        $orderId = $this->getOrderId($notification);

        if ($orderId) {
            return route('subdomain.orders') . '?search=' . $orderId;
        }

        return null;
    }

    public function render()
    {
        return view('livewire.backend.partials.notification-dropdown');
    }
}
