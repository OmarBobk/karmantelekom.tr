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
                    'shop_id' => $this->getShopId($notification),
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

        // Check if this is a shop assignment notification
        $data = $notification->data;
        if ($this->isShopAssignmentNotification($notification, $data)) {
            return 'shop';
        }

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

        // Extract shop name if present
        $shopName = $data['shop_name'] ?? null;

        return [
            'who' => $performedBy,
            'what' => $this->extractAction($description),
            'order_id' => $orderId,
            'shop_name' => $shopName,
        ];
    }

    private function extractAction(string $description): string
    {
        if (str_contains($description, 'New shop') && str_contains($description, 'has been created')) {
            return 'created a new shop';
        } elseif (str_contains($description, 'Order #') && str_contains($description, 'has been created')) {
            return 'created an order';
        } elseif (str_contains($description, 'has been created')) {
            return 'created an item';
        } elseif (str_contains($description, 'status changed')) {
            return 'updated order status';
        } elseif (str_contains($description, 'total changed')) {
            return 'updated order total';
        } elseif (str_contains($description, 'has been updated')) {
            return 'updated an order';
        } elseif (str_contains($description, 'assigned to manage shop')) {
            return 'assigned you to a shop';
        } elseif (str_contains($description, 'reassigned')) {
            return 'reassigned you to a shop';
        }

        return 'performed an action';
    }

    private function getOrderId($notification): ?string
    {
        $description = $notification->data['description'] ?? '';
        preg_match('/Order #(\d+)/', $description, $matches);
        return $matches[1] ?? null;
    }

    private function getShopId($notification): ?string
    {
        $modelId = $notification->data['model_id'] ?? null;
        return $modelId ? (string) $modelId : null;
    }

    private function getOrderLink($notification): ?string
    {
        $orderId = $this->getOrderId($notification);

        if ($orderId) {
            return route('subdomain.orders') . '?search=' . $orderId;
        }

        $data = $notification->data;

        // Check if this is a shop assignment notification (multiple ways to detect)
        if ($this->isShopAssignmentNotification($notification, $data)) {
            // First try to use the action_url if available
            if (isset($data['action_url'])) {
                return $data['action_url'];
            }
            
            // Fallback to generating the route using model_id or shop_id
            $shopId = $data['model_id'] ?? $data['shop_id'] ?? null;
            if ($shopId) {
                return route('subdomain.shop', ['shop' => $shopId]);
            }
        }

        // Check if this is a shop creation notification
        if (isset($data['model_type']) && $data['model_type'] === 'App\\Models\\Shop') {
            return route('subdomain.shops');
        }

        return null;
    }

    private function isShopAssignmentNotification($notification, $data): bool
    {
        // Check by notification type
        if ($notification->type === 'App\\Notifications\\ShopAssignmentNotification') {
            return true;
        }

        // Check by description content
        $description = $data['description'] ?? '';
        if (str_contains($description, 'assigned to manage shop') || 
            str_contains($description, 'reassigned') ||
            str_contains($description, 'Shop Assignment') ||
            str_contains($description, 'New Assignment')) {
            return true;
        }

        // Check by data structure
        if (isset($data['assignment_type']) || isset($data['assigned_by_id'])) {
            return true;
        }

        return false;
    }

    public function render()
    {
        return view('livewire.backend.partials.notification-dropdown');
    }
}
