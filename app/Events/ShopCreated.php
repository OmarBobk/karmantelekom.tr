<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Shop;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * ShopCreated Event
 *
 * Dispatched when a new shop is created in the system.
 * This event triggers activity logging and notification processes.
 */
class ShopCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public readonly Shop $shop,
        public readonly ?int $userId = null
    ) {
    }
}
