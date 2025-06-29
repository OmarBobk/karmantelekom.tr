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
    public $listeners = ['notificationUpdated' => '$refresh'];

    public function getUnreadCountProperty(): int
    {
        if (!auth()->check()) {
            return 0;
        }
        
        return auth()->user()->unreadNotifications()->count();
    }

    public function render()
    {
        return view('livewire.frontend.partials.notification-bell');
    }
} 