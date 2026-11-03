# Shop Creation Event System

This document describes the implementation of the shop creation event system that logs activities and sends notifications to admin users when new shops are created.

## Overview

The shop creation event system follows the same patterns and conventions as the existing order and shop assignment event systems in the application. It consists of:

1. **ShopCreated Event** - Dispatched when a new shop is created
2. **HandleShopCreated Listener** - Handles the event and logs activity
3. **ShopCreatedNotification** - Sends notifications to admin users
4. **EventServiceProvider** - Registers all event listeners

## Components

### 1. ShopCreated Event

**File:** `app/Events/ShopCreated.php`

```php
<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Shop;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ShopCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Shop $shop,
        public readonly ?int $userId = null
    ) {
    }
}
```

**Purpose:** Dispatched when a new shop is created in the system.

### 2. HandleShopCreated Listener

**File:** `app/Listeners/HandleShopCreated.php`

**Responsibilities:**
- Logs shop creation activity with detailed properties
- Sends notifications to all admin users
- Handles errors gracefully with proper logging

**Key Features:**
- Detailed activity logging with shop information
- Admin notification system
- Error handling and logging
- Queue support for better performance

### 3. ShopCreatedNotification

**File:** `app/Notifications/ShopCreatedNotification.php`

**Channels:** Database, Broadcast, Mail

**Content:**
- Shop details (name, phone, address)
- Creator information
- Creation timestamp
- Action link to view shop details

### 4. EventServiceProvider

**File:** `app/Providers/EventServiceProvider.php`

Registers all event listeners in a centralized location:

```php
protected $listen = [
    OrderCreated::class => [HandleOrderCreated::class],
    OrderUpdated::class => [HandleOrderUpdated::class],
    ShopAssigned::class => [HandleShopAssigned::class],
    ShopCreated::class => [HandleShopCreated::class],
];
```

## Integration Points

### 1. Backend Shop Creation

**File:** `app/Livewire/Backend/Shops/ShopComponent.php`

Updated the `save()` method to dispatch the event:

```php
// Create shop
$shop = Shop::create([
    'name' => $this->name,
    'phone' => $this->phone,
    'address' => $this->address,
    'links' => $this->links,
    'salesperson_id' => $this->salesperson_id,
]);

// Dispatch ShopCreated event
ShopCreated::dispatch($shop, auth()->id());
```

### 2. Frontend Shop Creation

**File:** `app/Livewire/Frontend/ShopCreationComponent.php`

Updated the `createShop()` method to dispatch the event:

```php
$shop = Shop::create([
    'name' => $this->name,
    'phone' => $this->phone,
    'address' => $this->address,
    'links' => $this->links,
    'owner_id' => Auth::id(),
]);

// Dispatch ShopCreated event
ShopCreated::dispatch($shop, Auth::id());
```

### 3. Activities Component

**File:** `app/Livewire/Backend/ActivitiesComponent.php`

Enhanced to display detailed shop creation activities:

```php
// Enhance descriptions for shop activities
$activities->getCollection()->transform(function ($activity) {
    if ($activity->log_name === 'shop_assignment') {
        $activity->description = $this->buildDetailedShopAssignmentDescription($activity);
    } elseif ($activity->log_name === 'shop_created') {
        $activity->description = $this->buildDetailedShopCreationDescription($activity);
    }
    return $activity;
});
```

## Activity Logging Details

### Log Name
- `shop_created`

### Properties Logged
- Shop details (id, name, phone, address, links)
- Creator information (id, name, email, role)
- Creation timestamp
- Owner and salesperson details (if applicable)

### Description Format
```
🏪 New Shop Created at [timestamp]: Shop '[name]' has been created by [creator]. 
This new shop is now available in the system and can be assigned to salespersons for management. 
Shop Details: 📞 [phone] | 📍 [address]
```

## Notification System

### Recipients
- All users with 'admin' role

### Notification Content
- Shop name, phone, and address
- Creator information
- Creation timestamp
- Link to view shop details

### Delivery Channels
- Database (for in-app notifications)
- Broadcast (for real-time notifications)
- Mail (for email notifications)

### Notification Display
The notification dropdown correctly identifies and displays:
- **Shop Creation:** "User created a new shop"
- **Order Creation:** "User created an order"
- **Other Activities:** Generic action descriptions

The system provides appropriate links to navigate to the relevant sections (shops, orders, etc.).

## Testing

### Unit Tests
**File:** `tests/Unit/ShopCreatedEventTest.php`

Tests the event creation and dispatching without database dependencies.

### Manual Testing
**Command:** `php artisan test:shop-creation`

Creates a test shop and verifies the entire event system works correctly.

## Error Handling

The system includes comprehensive error handling:

1. **Listener Errors:** Logged but don't break the application
2. **Notification Errors:** Logged per recipient, continues with others
3. **Database Errors:** Logged with full stack traces
4. **Missing Users:** Graceful fallbacks to system defaults

## Performance Considerations

1. **Queue Support:** All listeners implement `ShouldQueue`
2. **Efficient Queries:** Optimized database queries for admin users
3. **Batch Processing:** Notifications sent individually to avoid timeouts
4. **Memory Management:** Proper cleanup of large objects

## Security

1. **Input Validation:** All shop data validated before processing
2. **User Authentication:** Creator information verified
3. **Role-Based Access:** Only admin users receive notifications
4. **Data Sanitization:** Sensitive information properly handled

## Monitoring

### Log Entries
- Success: `Shop created event handled successfully`
- Errors: `Failed to handle ShopCreated event`
- Warnings: `Failed to send shop creation notification to admin`

### Metrics
- Activity log entries with `shop_created` log name
- Notification delivery status
- Error rates and types

## Future Enhancements

1. **Webhook Support:** External system notifications
2. **SMS Notifications:** Mobile alerts for admins
3. **Slack Integration:** Team communication notifications
4. **Analytics Dashboard:** Shop creation metrics
5. **Approval Workflow:** Admin approval for new shops

## Troubleshooting

### Common Issues

1. **Event Not Dispatched**
   - Check if EventServiceProvider is registered
   - Verify event listener registration
   - Check for syntax errors in event classes

2. **Activity Not Logged**
   - Verify activitylog package configuration
   - Check database permissions
   - Review activity log table structure

3. **Notifications Not Sent**
   - Check admin user existence and roles
   - Verify notification channels configuration
   - Review queue worker status

4. **Performance Issues**
   - Monitor queue processing
   - Check database query performance
   - Review notification delivery times

### Debug Commands

```bash
# Test the complete system
php artisan test:shop-creation

# Check event listeners
php artisan event:list

# Clear caches
php artisan config:clear
php artisan cache:clear

# Check queue status
php artisan queue:work --once
```

## Conclusion

The shop creation event system provides a robust, scalable solution for tracking shop creation activities and notifying administrators. It follows Laravel best practices and integrates seamlessly with the existing application architecture.
