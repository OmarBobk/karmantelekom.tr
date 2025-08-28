<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * ShopAssigned Event
 *
 * Fired when a shop is assigned to a salesperson by an admin
 */
class ShopAssigned implements ShouldBroadcast
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

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('shops'),
            new PrivateChannel('salesperson.' . $this->salesperson->id),
            new PrivateChannel('admin.dashboard'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'shop.assigned';
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
            ],
            'salesperson' => [
                'id' => $this->salesperson->id,
                'name' => $this->salesperson->name,
                'email' => $this->salesperson->email,
            ],
            'assigned_by' => [
                'id' => $this->assignedBy->id,
                'name' => $this->assignedBy->name,
            ],
            'previous_salesperson' => $this->previousSalesperson ? [
                'id' => $this->previousSalesperson->id,
                'name' => $this->previousSalesperson->name,
            ] : null,
            'assignment_type' => $this->previousSalesperson ? 'reassignment' : 'new_assignment',
            'timestamp' => now()->toISOString(),
        ];
    }
}
