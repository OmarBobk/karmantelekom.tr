# Laravel Reverb Broadcasting Implementation

This document outlines the complete implementation of Laravel Reverb broadcasting in our application, including events, notifications, and real-time UI components.

## Overview

Our broadcasting system uses Laravel Reverb to provide real-time updates for:
- Shop creation and assignment events
- Order creation and updates
- Real-time notifications
- Live dashboard updates

## Architecture

### 1. Events (`app/Events/`)

All events implement `ShouldBroadcast` interface and are configured to broadcast on specific channels:

#### ShopCreated Event
- **Channels**: `shops` (public), `admin.dashboard` (private)
- **Event Name**: `shop.created`
- **Data**: Shop details, creation timestamp

#### ShopAssigned Event
- **Channels**: `shops` (public), `salesperson.{id}` (private), `admin.dashboard` (private)
- **Event Name**: `shop.assigned`
- **Data**: Shop details, salesperson info, assignment type

#### OrderCreated Event
- **Channels**: `orders` (public), `shop.{id}` (private), `admin.dashboard` (private)
- **Event Name**: `order.created`
- **Data**: Order details, status, total price

#### OrderUpdated Event
- **Channels**: `orders` (public), `shop.{id}` (private), `admin.dashboard` (private)
- **Event Name**: `order.updated`
- **Data**: Order details, changes made, update timestamp

### 2. Channel Authorization (`routes/channels.php`)

```php
// User-specific private channels
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Admin dashboard channel
Broadcast::channel('admin.dashboard', function ($user) {
    return $user->hasRole('admin');
});

// Salesperson-specific channels
Broadcast::channel('salesperson.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id || $user->hasRole('admin');
});

// Shop-specific channels
Broadcast::channel('shop.{id}', function ($user, $id) {
    return $user->hasRole('admin') || 
           $user->shops()->where('id', $id)->exists() || 
           $user->assignedShops()->where('id', $id)->exists();
});

// Public channels
Broadcast::channel('shops', function ($user) {
    return true;
});

Broadcast::channel('orders', function ($user) {
    return true;
});
```

### 3. JavaScript Broadcasting (`resources/js/broadcasting.js`)

The `BroadcastingManager` class handles all client-side event listening and UI updates:

```javascript
class BroadcastingManager {
    constructor() {
        this.userId = window.userId || null;
        this.userRole = window.userRole || null;
        this.initializeListeners();
    }

    initializeListeners() {
        this.listenToShopEvents();
        this.listenToOrderEvents();
        this.listenToNotifications();
        this.listenToUserEvents();
    }
}
```

#### Event Listeners
- **Shop Events**: Listens to `shop.created` and `shop.assigned` events
- **Order Events**: Listens to `order.created` and `order.updated` events
- **Notifications**: Listens to user-specific notification events
- **User Events**: Listens to user update events

#### UI Updates
- Real-time notification display
- Live shop list updates
- Order status changes
- Dashboard statistics updates

### 4. Livewire Components

#### RealTimeNotifications (`app/Livewire/Backend/RealTimeNotifications.php`)
- Displays real-time notifications in a dropdown
- Handles notification marking as read
- Supports different notification types

#### RealTimeShops (`app/Livewire/Backend/RealTimeShops.php`)
- Shows live shop list with real-time updates
- Auto-refresh functionality
- Shop assignment status updates

#### RealTimeOrders (`app/Livewire/Backend/RealTimeOrders.php`)
- Displays live order list
- Real-time order status updates
- Order creation notifications

#### RealTimeDashboard (`app/Livewire/Backend/RealTimeDashboard.php`)
- Comprehensive dashboard with statistics
- Recent shops, orders, and notifications
- Real-time data updates

## Configuration

### 1. Environment Variables

Add these to your `.env` file:

```env
BROADCAST_CONNECTION=reverb
REVERB_APP_KEY=your-reverb-app-key
REVERB_APP_SECRET=your-reverb-app-secret
REVERB_APP_ID=your-reverb-app-id
REVERB_HOST=localhost
REVERB_PORT=6001
REVERB_SCHEME=http

# Frontend variables
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

### 2. Broadcasting Configuration (`config/broadcasting.php`)

```php
'default' => env('BROADCAST_CONNECTION', 'reverb'),

'reverb' => [
    'driver' => 'reverb',
    'key' => env('REVERB_APP_KEY'),
    'secret' => env('REVERB_APP_SECRET'),
    'app_id' => env('REVERB_APP_ID'),
    'options' => [
        'host' => env('REVERB_HOST'),
        'port' => env('REVERB_PORT', env('APP_ENV') === 'local' ? 6001 : 443),
        'scheme' => env('REVERB_SCHEME', 'https'),
        'useTLS' => env('REVERB_SCHEME', 'https') === 'https',
    ],
],
```

## Usage

### 1. Starting Reverb Server

```bash
# Start Reverb server
php artisan reverb:start

# Or use the dev script that includes Reverb
composer run dev
```

### 2. Testing Broadcasting

```bash
# Test all events
php artisan test:broadcasting

# Test specific event
php artisan test:broadcasting --event=shop-created
php artisan test:broadcasting --event=order-updated
```

### 3. Using Livewire Components

#### In Blade Views
```php
<!-- Real-time notifications -->
<livewire:backend.real-time-notifications />

<!-- Real-time shops -->
<livewire:backend.real-time-shops />

<!-- Real-time orders -->
<livewire:backend.real-time-orders />

<!-- Comprehensive dashboard -->
<livewire:backend.real-time-dashboard />
```

#### In Layout Files
Add user information to your layout for proper channel authorization:

```php
<meta name="user-id" content="{{ auth()->id() }}">
<meta name="user-role" content="{{ auth()->user()->roles->first()?->name ?? 'user' }}">
```

### 4. Dispatching Events

```php
// Shop created
event(new ShopCreated($shop, auth()->id()));

// Shop assigned
event(new ShopAssigned($shop, $salesperson, auth()->user()));

// Order created
event(new OrderCreated($order, auth()->id()));

// Order updated
event(new OrderUpdated($order, $originalData, auth()->id()));
```

## Features

### 1. Real-Time Notifications
- Toast notifications for events
- Notification dropdown with read/unread status
- Browser notifications (if permitted)
- Notification count badges

### 2. Live Data Updates
- Automatic UI updates without page refresh
- Real-time statistics updates
- Live lists with new items appearing instantly
- Status changes reflected immediately

### 3. Auto-Refresh
- Configurable refresh intervals
- Toggle auto-refresh on/off
- Manual refresh buttons
- Background data synchronization

### 4. Channel Security
- Private channels for user-specific data
- Role-based channel access
- Admin-only channels
- Public channels for general updates

## Troubleshooting

### 1. Connection Issues
- Verify Reverb server is running: `php artisan reverb:start`
- Check environment variables are set correctly
- Ensure WebSocket ports are accessible
- Check browser console for connection errors

### 2. Events Not Broadcasting
- Verify events implement `ShouldBroadcast`
- Check channel authorization callbacks
- Ensure Reverb server is receiving events
- Check browser console for JavaScript errors

### 3. Notifications Not Showing
- Verify notification channels include 'broadcast'
- Check user authentication for private channels
- Ensure JavaScript is loading correctly
- Check browser notification permissions

### 4. Performance Issues
- Adjust refresh intervals for better performance
- Limit the number of items in real-time lists
- Use pagination for large datasets
- Monitor WebSocket connection health

## Best Practices

### 1. Event Design
- Keep broadcast data minimal and relevant
- Use descriptive event names
- Include timestamps for all events
- Structure data consistently

### 2. Channel Organization
- Use private channels for sensitive data
- Group related events on same channels
- Implement proper authorization
- Use descriptive channel names

### 3. UI Updates
- Provide visual feedback for updates
- Use smooth transitions and animations
- Handle connection states gracefully
- Implement fallbacks for offline scenarios

### 4. Performance
- Limit real-time updates to essential data
- Use debouncing for frequent updates
- Implement proper cleanup for listeners
- Monitor memory usage in long-running sessions

## Security Considerations

### 1. Channel Authorization
- Always implement proper authorization callbacks
- Verify user permissions before broadcasting
- Use private channels for sensitive data
- Validate data before broadcasting

### 2. Data Protection
- Don't broadcast sensitive information
- Sanitize data before sending to clients
- Use encryption for sensitive channels
- Implement rate limiting for events

### 3. Authentication
- Ensure proper user authentication
- Verify CSRF tokens for private channels
- Implement session validation
- Use secure WebSocket connections

## Monitoring and Debugging

### 1. Logging
- Monitor Reverb server logs
- Log broadcasting events for debugging
- Track connection statistics
- Monitor error rates

### 2. Testing
- Use the test command for event verification
- Test with multiple browser tabs
- Verify channel authorization
- Test offline/online scenarios

### 3. Performance Monitoring
- Monitor WebSocket connection health
- Track event processing times
- Monitor memory usage
- Check for memory leaks

This broadcasting implementation provides a robust, scalable solution for real-time updates in your Laravel application using Laravel Reverb.
