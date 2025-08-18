<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * ShopAssigned Event
 *
 * Fired when a shop is assigned to a salesperson by an admin
 */
class ShopAssigned
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Shop $shop,
        public User $salesperson,
        public ?User $assignedBy = null,
        public ?User $previousSalesperson = null
    ) {
        $this->assignedBy = $assignedBy ?? auth()->user();
    }
}
