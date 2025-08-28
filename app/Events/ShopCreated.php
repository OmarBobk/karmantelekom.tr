<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Shop;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * ShopCreated Event
 *
 * Dispatched when a new shop is created in the system.
 * This event triggers activity logging and notification processes.
 */
class ShopCreated implements ShouldBroadcast
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

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('shops'),
            new PrivateChannel('admin.dashboard'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'shop.created';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'shop' => [
                'id' => $this->shop->id,
                'name' => $this->shop->name,
                'phone' => $this->shop->phone,
                'address' => $this->shop->address,
                'created_at' => $this->shop->created_at->toISOString(),
            ],
            'created_by' => $this->userId,
            'timestamp' => now()->toISOString(),
        ];
    }
}
