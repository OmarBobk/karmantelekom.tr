<?php

declare(strict_types=1);

namespace App\Livewire\Backend;

use App\Models\Order;
use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\WithPagination;

/**
 * RealTimeOrders Component
 *
 * Displays orders with real-time updates
 */
class RealTimeOrders extends Component
{
    use WithPagination;

    public array $orders = [];
    public bool $autoRefresh = true;
    public int $refreshInterval = 30000; // 30 seconds

    protected $listeners = [
        'order-created' => 'handleOrderCreated',
        'order-updated' => 'handleOrderUpdated',
    ];

    public function mount()
    {
        $this->loadOrders();
    }

    public function render()
    {
        return view('livewire.backend.real-time-orders');
    }

    /**
     * Load orders from database
     */
    public function loadOrders()
    {
        $this->orders = Order::with(['shop', 'customer'])
            ->latest()
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'shop_name' => $order->shop?->name ?? 'N/A',
                    'customer_name' => $order->customer?->name ?? 'N/A',
                    'total_price' => $order->total_price,
                    'status' => $order->status->value,
                    'status_label' => $order->status->label(),
                    'notes' => $order->notes,
                    'created_at' => $order->created_at->diffForHumans(),
                    'updated_at' => $order->updated_at->diffForHumans(),
                ];
            })
            ->toArray();
    }

    /**
     * Handle order created event
     */
    #[On('order-created')]
    public function handleOrderCreated($data)
    {
        $newOrder = [
            'id' => $data['order']['id'],
            'shop_name' => 'N/A', // Will be updated when we refresh
            'customer_name' => 'N/A', // Will be updated when we refresh
            'total_price' => $data['order']['total_price'],
            'status' => $data['order']['status'],
            'status_label' => $data['order']['status_label'],
            'notes' => $data['order']['notes'],
            'created_at' => now()->diffForHumans(),
            'updated_at' => now()->diffForHumans(),
        ];

        array_unshift($this->orders, $newOrder);

        // Keep only the latest 50 orders
        if (count($this->orders) > 50) {
            $this->orders = array_slice($this->orders, 0, 50);
        }

        // Show success message
        $this->dispatch('show-message', [
            'type' => 'success',
            'message' => "Order #{$data['order']['id']} has been created"
        ]);
    }

    /**
     * Handle order updated event
     */
    #[On('order-updated')]
    public function handleOrderUpdated($data)
    {
        // Update the order in the list
        foreach ($this->orders as &$order) {
            if ($order['id'] === $data['order']['id']) {
                $order['status'] = $data['order']['status'];
                $order['status_label'] = $data['order']['status_label'];
                $order['total_price'] = $data['order']['total_price'];
                $order['notes'] = $data['order']['notes'];
                $order['updated_at'] = now()->diffForHumans();
                break;
            }
        }

        // Show info message
        $this->dispatch('show-message', [
            'type' => 'info',
            'message' => "Order #{$data['order']['id']} has been updated"
        ]);
    }

    /**
     * Refresh orders list
     */
    public function refreshOrders()
    {
        $this->loadOrders();
    }

    /**
     * Toggle auto refresh
     */
    public function toggleAutoRefresh()
    {
        $this->autoRefresh = !$this->autoRefresh;
    }

    /**
     * Get order status color
     */
    public function getOrderStatusColor($order): string
    {
        return match ($order['status']) {
            'pending' => 'text-yellow-500',
            'processing' => 'text-blue-500',
            'completed' => 'text-green-500',
            'cancelled' => 'text-red-500',
            default => 'text-gray-500',
        };
    }

    /**
     * Get order status icon
     */
    public function getOrderStatusIcon($order): string
    {
        return match ($order['status']) {
            'pending' => 'fas fa-clock',
            'processing' => 'fas fa-cog',
            'completed' => 'fas fa-check-circle',
            'cancelled' => 'fas fa-times-circle',
            default => 'fas fa-question-circle',
        };
    }

    /**
     * Format price
     */
    public function formatPrice($price): string
    {
        return number_format((float) $price, 2) . ' TL';
    }
}
