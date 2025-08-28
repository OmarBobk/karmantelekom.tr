<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * OrderUpdated Event
 *
 * Dispatched when an order is updated in the system.
 * This event triggers activity logging and notification processes.
 */
class OrderUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public readonly Order $order,
        public readonly array $originalData,
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
            new Channel('orders'),
            new PrivateChannel('shop.' . $this->order->shop_id),
            new PrivateChannel('admin.dashboard'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'order.updated';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $changes = [];
        foreach (['status', 'total_price', 'shop_id', 'notes'] as $field) {
            if (isset($this->originalData[$field]) && $this->originalData[$field] !== $this->order->$field) {
                $changes[$field] = [
                    'old' => $this->originalData[$field],
                    'new' => $this->order->$field,
                ];
            }
        }

        return [
            'order' => [
                'id' => $this->order->id,
                'shop_id' => $this->order->shop_id,
                'total_price' => $this->order->total_price,
                'status' => $this->order->status->value,
                'status_label' => $this->order->status->label(),
                'notes' => $this->order->notes,
                'updated_at' => $this->order->updated_at->toISOString(),
            ],
            'changes' => $changes,
            'updated_by' => $this->userId,
            'timestamp' => now()->toISOString(),
        ];
    }
}
