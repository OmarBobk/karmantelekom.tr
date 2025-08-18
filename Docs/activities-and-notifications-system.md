# Activities and Notifications System Documentation

## Overview

This document provides a comprehensive overview of all activities, events, and notifications implemented in the Laravel application. The system uses Spatie Activity Log for activity tracking and Laravel's notification system for real-time alerts.

## Table of Contents

1. [Activity Logging System](#activity-logging-system)
2. [Events and Listeners](#events-and-listeners)
3. [Notifications](#notifications)
4. [Activity Types](#activity-types)
5. [Notification Recipients](#notification-recipients)
6. [Real-time Features](#real-time-features)
7. [Configuration](#configuration)
8. [Monitoring and Analytics](#monitoring-and-analytics)

## Activity Logging System

### Technology Stack
- **Spatie Activity Log**: Primary activity logging package
- **Database**: `activity_log` table for storing activities
- **Queue System**: Asynchronous processing for better performance

### Database Schema

The activity log table (`activity_log`) contains:
- `id`: Primary key
- `log_name`: Activity type identifier
- `description`: Human-readable activity description
- `subject_type`: Model class that was affected
- `subject_id`: ID of the affected model
- `causer_type`: User model class that performed the action
- `causer_id`: ID of the user who performed the action
- `properties`: JSON field containing additional data
- `event`: Event type (optional)
- `batch_uuid`: For grouping related activities
- `created_at`: Timestamp
- `updated_at`: Timestamp

### Configuration

**File**: `config/activitylog.php`

```php
'enabled' => env('ACTIVITY_LOGGER_ENABLED', true),
'delete_records_older_than_days' => 365,
'default_log_name' => 'default',
'activity_model' => \Spatie\Activitylog\Models\Activity::class,
'table_name' => env('ACTIVITY_LOGGER_TABLE_NAME', 'activity_log'),
```

## Events and Listeners

### 1. Order Events

#### OrderCreated Event
**File**: `app/Events/OrderCreated.php`

**Purpose**: Dispatched when a new order is created

**Properties**:
- `Order $order`: The created order
- `?int $userId`: Optional user ID who created the order

**Listener**: `app/Listeners/HandleOrderCreated.php`

**Activities Logged**:
- Log Name: `order_created`
- Description: "Order #X has been created with status 'Y' for Z TL"
- Properties: order_id, shop_id, total_price, status, notes

**Notifications Sent**:
- Recipients: Admin users, shop-related users, order salesperson
- Type: `NewActivityLogged`
- Channels: Database, Broadcast

#### OrderUpdated Event
**File**: `app/Events/OrderUpdated.php`

**Purpose**: Dispatched when an order is updated

**Properties**:
- `Order $order`: The updated order
- `array $originalData`: Original order data before changes
- `?int $userId`: Optional user ID who updated the order

**Listener**: `app/Listeners/HandleOrderUpdated.php`

**Activities Logged**:
- Log Name: `order_updated`
- Description: Change-specific descriptions (status, price, notes changes)
- Properties: order_id, status, shop_id, total_price, notes, changes

**Notifications Sent**:
- Recipients: Admin users, shop-related users, order salesperson
- Type: `NewActivityLogged`
- Channels: Database, Broadcast

### 2. Shop Events

#### ShopCreated Event
**File**: `app/Events/ShopCreated.php`

**Purpose**: Dispatched when a new shop is created

**Properties**:
- `Shop $shop`: The created shop
- `?int $userId`: Optional user ID who created the shop

**Listener**: `app/Listeners/HandleShopCreated.php`

**Activities Logged**:
- Log Name: `shop_created`
- Description: Detailed shop creation description with creator info
- Properties: shop_id, shop_name, shop_phone, shop_address, created_by_id, created_by_name

**Notifications Sent**:
- Recipients: Admin users only
- Type: `ShopCreatedNotification`
- Channels: Database, Broadcast, Mail

#### ShopAssigned Event
**File**: `app/Events/ShopAssigned.php`

**Purpose**: Dispatched when a shop is assigned to a salesperson

**Properties**:
- `Shop $shop`: The shop being assigned
- `User $salesperson`: The salesperson being assigned
- `?User $assignedBy`: User who made the assignment
- `?User $previousSalesperson`: Previous salesperson (for reassignments)

**Listener**: `app/Listeners/HandleShopAssigned.php`

**Activities Logged**:
- Log Name: `shop_assignment`
- Description: Detailed assignment description with assignment type
- Properties: shop_id, shop_name, salesperson_id, assigned_by_id, assignment_type

**Notifications Sent**:
- Recipients: Assigned salesperson
- Type: `ShopAssignmentNotification`
- Channels: Database, Broadcast, Mail

### 3. Authentication Events

#### Login Event
**File**: `app/Listeners/SyncCartAfterLogin.php`

**Purpose**: Handles cart synchronization after user login

**Listener**: `app/Listeners/SyncCartAfterLogin.php`

**Activities Logged**:
- Log Name: `user_login`
- Description: User login activity
- Properties: user_id, session_id, ip_address

**Note**: Currently commented out in the implementation

## Notifications

### 1. NewActivityLogged Notification
**File**: `app/Notifications/NewActivityLogged.php`

**Purpose**: Generic notification for any logged activity

**Channels**: Database, Broadcast

**Content**:
- Activity description
- Model type and ID
- Performer information

### 2. ShopCreatedNotification
**File**: `app/Notifications/ShopCreatedNotification.php`

**Purpose**: Notifies admin users when a new shop is created

**Channels**: Database, Broadcast, Mail

**Content**:
- Shop details (name, phone, address)
- Creator information
- Creation timestamp
- Action link to view shop details

**Mail Template**:
- Subject: "New Shop Created: {shop_name}"
- Greeting with admin name
- Shop details list
- Action button to view shop details

### 3. ShopAssignmentNotification
**File**: `app/Notifications/ShopAssignmentNotification.php`

**Purpose**: Notifies salesperson when assigned to a shop

**Channels**: Database, Broadcast, Mail

**Content**:
- Shop details
- Assignment type (new assignment or reassignment)
- Assigner information
- Responsibilities list
- Action link to view shop details

**Mail Template**:
- Subject: "Shop {assignment_type} - {shop_name}"
- Personalized greeting
- Assignment details and responsibilities
- Shop information
- Action button to view shop details

## Activity Types

### Supported Activity Types

The system supports the following activity types (defined in `ActivitiesComponent`):

1. **User Activities**:
   - `user_login`: User Login
   - `user_logout`: User Logout
   - `user_created`: User Created
   - `user_updated`: User Updated
   - `user_deleted`: User Deleted
   - `email_verified`: Email Verified
   - `password_changed`: Password Changed
   - `profile_updated`: Profile Updated

2. **Shop Activities**:
   - `shop_created`: Shop Created
   - `shop_updated`: Shop Updated
   - `shop_deleted`: Shop Deleted
   - `shop_assignment`: Shop Assignment

3. **Order Activities**:
   - `order_created`: Order Created
   - `order_updated`: Order Updated
   - `order_deleted`: Order Deleted

4. **Role Activities**:
   - `role_assigned`: Role Assigned
   - `role_removed`: Role Removed

### Activity Filtering and Search

The `ActivitiesComponent` provides:
- Text search across description, subject type, log name, and user details
- Activity type filtering
- User filtering
- Date range filtering (today, yesterday, last 7 days, last 30 days, this month, last month, this year)
- Sorting by various fields
- Pagination

## Notification Recipients

### NotificationRecipientsService
**File**: `app/Services/NotificationRecipientsService.php`

**Purpose**: Centralized service for resolving notification recipients

### Recipient Resolution Logic

#### For Orders:
1. **Admin Users**: All users with 'admin' role
2. **Shop-Related Users**: Users linked to the order's shop
3. **Order Salesperson**: User assigned to the order
4. **Exclusions**: Optionally exclude the causer (user who triggered the action)

#### For Shop Creation:
1. **Admin Users**: All users with 'admin' role
2. **Exclusions**: Optionally exclude the causer

#### For Shop Assignment:
1. **Assigned Salesperson**: The user being assigned to the shop

### Advanced Options

The service supports advanced filtering:
```php
$recipients = $recipientsService->resolveNotificationRecipientsFor($order,
    excludeCauser: true,
    options: [
        'exclude_roles' => ['salesperson'],
        'only_roles' => ['admin'],
    ]
);
```

## Real-time Features

### Notification Dropdown
**File**: `app/Livewire/Backend/Partials/NotificationDropdown.php`

**Features**:
- Real-time notification display
- Unread notification count
- Notification formatting and categorization
- Mark as read functionality
- Time display (relative and exact)

### Broadcasting
All notifications support broadcasting for real-time updates:
- Database notifications for persistence
- Broadcast notifications for real-time UI updates
- Mail notifications for email delivery

## Configuration

### Event Service Provider
**Note**: Event listeners are currently commented out in `AppServiceProvider.php`

To enable event listeners, uncomment the following in `AppServiceProvider.php`:

```php
private function registerEventListeners(): void
{
    \Illuminate\Support\Facades\Event::listen(
        \App\Events\OrderCreated::class,
        \App\Listeners\HandleOrderCreated::class
    );

    \Illuminate\Support\Facades\Event::listen(
        \App\Events\OrderUpdated::class,
        \App\Listeners\HandleOrderUpdated::class
    );
}
```

### Queue Configuration
All listeners implement `ShouldQueue` for asynchronous processing:
- Better performance for heavy operations
- Reduced response times
- Better error handling

### Broadcasting Configuration
Notifications use multiple channels:
- **Database**: Persistent storage
- **Broadcast**: Real-time updates
- **Mail**: Email delivery (for specific notifications)

## Monitoring and Analytics

### Activity Statistics
The `ActivitiesComponent` provides:
- Total activity count
- Activity type distribution
- Recent activity types
- Most active users
- Date-based filtering

### Logging
All event handlers include comprehensive logging:
- Success logs with relevant data
- Error logs with stack traces
- Warning logs for edge cases

### Testing
**File**: `app/Console/Commands/TestShopCreationSystem.php`

Command: `php artisan test:shop-creation`

Tests the complete shop creation event system:
- Event dispatch
- Activity logging
- Notification sending
- Error handling

## Integration Points

### Frontend Integration
- **Cart Sync**: Automatic cart synchronization on login
- **Real-time Updates**: Livewire components for real-time notification display
- **User Experience**: Seamless notification management

### Backend Integration
- **Role-based Access**: Notifications respect user roles and permissions
- **Shop Management**: Integrated with shop creation and assignment workflows
- **Order Processing**: Integrated with order creation and update workflows

## Best Practices

### Error Handling
- All event handlers include try-catch blocks
- Graceful degradation on notification failures
- Comprehensive error logging

### Performance
- Queue-based processing for heavy operations
- Efficient database queries with proper indexing
- Pagination for large datasets

### Security
- Role-based notification access
- Proper user authentication checks
- Secure data handling in notifications

### Maintainability
- Centralized recipient resolution
- Reusable notification templates
- Consistent event naming conventions

## Future Enhancements

### Planned Features
1. **Webhook Support**: External system notifications
2. **SMS Notifications**: Mobile alerts
3. **Slack Integration**: Team communication
4. **Analytics Dashboard**: Advanced metrics and reporting
5. **Approval Workflows**: Admin approval for critical actions

### Technical Improvements
1. **Event Sourcing**: Complete audit trail
2. **Performance Optimization**: Caching and query optimization
3. **Scalability**: Horizontal scaling support
4. **Monitoring**: Advanced monitoring and alerting

## Troubleshooting

### Common Issues

1. **Events Not Dispatched**
   - Check if EventServiceProvider is registered
   - Verify event listener registration
   - Check for syntax errors in event classes

2. **Activities Not Logged**
   - Verify activitylog package configuration
   - Check database permissions
   - Review activity log table structure

3. **Notifications Not Sent**
   - Check recipient resolution logic
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

The activities and notifications system provides a robust, scalable solution for tracking user actions and keeping stakeholders informed. It follows Laravel best practices and integrates seamlessly with the existing application architecture, providing comprehensive audit trails and real-time communication capabilities.
