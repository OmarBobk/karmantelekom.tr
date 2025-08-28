<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

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
    // Allow access if user is admin, shop owner, or assigned salesperson
    return $user->hasRole('admin') || 
           $user->shops()->where('id', $id)->exists() || 
           $user->assignedShops()->where('id', $id)->exists();
});

// Public channels (no authorization required)
Broadcast::channel('shops', function ($user) {
    return true; // Anyone can listen to shop events
});

Broadcast::channel('orders', function ($user) {
    return true; // Anyone can listen to order events
});
