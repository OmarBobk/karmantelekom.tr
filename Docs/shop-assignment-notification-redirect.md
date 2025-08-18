# Shop Assignment Notification Redirect

This document describes how the shop assignment notification redirect functionality works in the application.

## Overview

When a salesperson is assigned to a shop by an admin, they receive a notification. Clicking on this notification should redirect them to the specific shop page where they can view and manage the shop details.

## Implementation Details

### 1. Notification Structure

The `ShopAssignmentNotification` includes the following key data:

```php
[
    'description' => 'You have been assigned to manage shop "Shop Name"...',
    'model_type' => 'App\Models\Shop',
    'model_id' => $shop->id,
    'shop_id' => $shop->id,
    'shop_name' => $shop->name,
    'action_url' => route('subdomain.shop', ['shop' => $shop->id]),
    'assignment_type' => 'New Assignment' | 'Reassignment',
    'assigned_by_id' => $assignedBy->id,
    // ... other fields
]
```

### 2. Notification Detection

The `NotificationDropdown` component detects shop assignment notifications using multiple methods:

```php
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
```

### 3. Link Generation

The notification link is generated in the `getOrderLink` method:

```php
private function getOrderLink($notification): ?string
{
    // ... other checks ...

    // Check if this is a shop assignment notification
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

    // ... other checks ...
}
```

### 4. Click Handling

When a user clicks on a notification, the `markAsReadAndNavigate` method is called:

```php
public function markAsReadAndNavigate(string $notificationId, ?string $orderLink = null): void
{
    $this->markAsRead($notificationId);

    if ($orderLink) {
        $this->isOpen = false;
        $this->redirect($orderLink);
    }
}
```

### 5. Visual Indicators

Shop assignment notifications have special visual indicators:

- **Icon**: Shop icon (indigo color)
- **Shop Name**: Displayed in the notification summary with indigo color
- **Clickable**: The entire notification is clickable and redirects to the shop page

## Route Structure

The shop page route is defined as:

```php
Route::get('shops/{shop}', \App\Livewire\Backend\Shops\ShopProfileComponent::class)->name('shop');
```

## Testing

You can test the shop assignment notification using the provided test command:

```bash
php artisan test:shop-assignment-notification {shop_id} {salesperson_id} {assigned_by_id}
```

Example:
```bash
php artisan test:shop-assignment-notification 1 2 3
```

This will:
1. Send a shop assignment notification to the specified salesperson
2. The notification will appear in their notification dropdown
3. Clicking on the notification will redirect to the shop page

## Troubleshooting

### Common Issues

1. **Notification not redirecting**: Check that the notification data includes `model_id` or `shop_id`
2. **Route not found**: Verify that the `subdomain.shop` route exists and is accessible
3. **Permission issues**: Ensure the salesperson has access to view the shop

### Debug Information

The system includes logging to help debug issues:

- Check the Laravel logs for any notification-related errors
- Verify that the notification data structure is correct
- Ensure the route parameters are properly formatted

## Related Files

- `app/Notifications/ShopAssignmentNotification.php` - Notification class
- `app/Livewire/Backend/Partials/NotificationDropdown.php` - Notification dropdown component
- `resources/views/livewire/backend/partials/notification-dropdown.blade.php` - Notification dropdown view
- `routes/subdomain.php` - Route definitions
- `app/Console/Commands/TestShopAssignmentNotification.php` - Test command
