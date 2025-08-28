<?php

declare(strict_types=1);

namespace App\Livewire\Backend;

use App\Models\Order;
use App\Models\Shop;
use App\Models\User;
use Livewire\Component;
use Livewire\Attributes\On;

/**
 * RealTimeDashboard Component
 *
 * Comprehensive dashboard with real-time updates for shops, orders, and notifications
 */
class RealTimeDashboard extends Component
{
    public array $stats = [];
    public array $recentShops = [];
    public array $recentOrders = [];
    public array $recentNotifications = [];
    public bool $autoRefresh = true;
    public int $refreshInterval = 30000; // 30 seconds

    protected $listeners = [
        'shop-created' => 'handleShopCreated',
        'shop-assigned' => 'handleShopAssigned',
        'order-created' => 'handleOrderCreated',
        'order-updated' => 'handleOrderUpdated',
    ];

    public function mount()
    {
        $this->loadDashboardData();
    }

    public function render()
    {
        return view('livewire.backend.real-time-dashboard');
    }

    /**
     * Load all dashboard data
     */
    public function loadDashboardData()
    {
        $this->loadStats();
        $this->loadRecentShops();
        $this->loadRecentOrders();
        $this->loadRecentNotifications();
    }

    /**
     * Load dashboard statistics
     */
    public function loadStats()
    {
        $this->stats = [
            'total_shops' => Shop::count(),
            'total_orders' => Order::count(),
            'total_users' => User::count(),
            'pending_orders' => Order::where('status', 'pending')->count(),
            'processing_orders' => Order::where('status', 'processing')->count(),
            'completed_orders' => Order::where('status', 'completed')->count(),
            'unassigned_shops' => Shop::whereNull('salesperson_id')->count(),
            'assigned_shops' => Shop::whereNotNull('salesperson_id')->count(),
        ];
    }

    /**
     * Load recent shops
     */
    public function loadRecentShops()
    {
        $this->recentShops = Shop::with(['owner', 'salesperson'])
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($shop) {
                return [
                    'id' => $shop->id,
                    'name' => $shop->name,
                    'phone' => $shop->phone,
                    'address' => $shop->address,
                    'owner_name' => $shop->owner?->name ?? 'N/A',
                    'salesperson_name' => $shop->salesperson?->name ?? 'Unassigned',
                    'created_at' => $shop->created_at->diffForHumans(),
                    'status' => $shop->salesperson_id ? 'assigned' : 'unassigned',
                ];
            })
            ->toArray();
    }

    /**
     * Load recent orders
     */
    public function loadRecentOrders()
    {
        $this->recentOrders = Order::with(['shop', 'customer'])
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'shop_name' => $order->shop?->name ?? 'N/A',
                    'customer_name' => $order->customer?->name ?? 'N/A',
                    'total_price' => $order->total_price,
                    'status' => $order->status->value,
                    'status_label' => $order->status->label(),
                    'created_at' => $order->created_at->diffForHumans(),
                ];
            })
            ->toArray();
    }

    /**
     * Load recent notifications
     */
    public function loadRecentNotifications()
    {
        $user = auth()->user();
        if (!$user) return;

        $this->recentNotifications = $user->notifications()
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'type' => $notification->type,
                    'data' => $notification->data,
                    'read_at' => $notification->read_at,
                    'created_at' => $notification->created_at->diffForHumans(),
                ];
            })
            ->toArray();
    }

    /**
     * Handle shop created event
     */
    #[On('shop-created')]
    public function handleShopCreated($data)
    {
        // Update stats
        $this->stats['total_shops']++;
        $this->stats['unassigned_shops']++;

        // Add to recent shops
        $newShop = [
            'id' => $data['shop']['id'],
            'name' => $data['shop']['name'],
            'phone' => $data['shop']['phone'],
            'address' => $data['shop']['address'],
            'owner_name' => 'N/A',
            'salesperson_name' => 'Unassigned',
            'created_at' => now()->diffForHumans(),
            'status' => 'unassigned',
        ];

        array_unshift($this->recentShops, $newShop);

        // Keep only the latest 5 shops
        if (count($this->recentShops) > 5) {
            $this->recentShops = array_slice($this->recentShops, 0, 5);
        }

        // Show success message
        $this->dispatch('show-message', [
            'type' => 'success',
            'message' => "Shop \"{$data['shop']['name']}\" has been created"
        ]);
    }

    /**
     * Handle shop assigned event
     */
    #[On('shop-assigned')]
    public function handleShopAssigned($data)
    {
        // Update stats
        $this->stats['unassigned_shops']--;
        $this->stats['assigned_shops']++;

        // Update in recent shops
        foreach ($this->recentShops as &$shop) {
            if ($shop['id'] === $data['shop']['id']) {
                $shop['salesperson_name'] = $data['salesperson']['name'];
                $shop['status'] = 'assigned';
                break;
            }
        }

        // Show info message
        $assignmentType = $data['assignment_type'] === 'reassignment' ? 'reassigned' : 'assigned';
        $this->dispatch('show-message', [
            'type' => 'info',
            'message' => "Shop \"{$data['shop']['name']}\" has been {$assignmentType} to {$data['salesperson']['name']}"
        ]);
    }

    /**
     * Handle order created event
     */
    #[On('order-created')]
    public function handleOrderCreated($data)
    {
        // Update stats
        $this->stats['total_orders']++;
        $this->stats['pending_orders']++;

        // Add to recent orders
        $newOrder = [
            'id' => $data['order']['id'],
            'shop_name' => 'N/A', // Will be updated when we refresh
            'customer_name' => 'N/A', // Will be updated when we refresh
            'total_price' => $data['order']['total_price'],
            'status' => $data['order']['status'],
            'status_label' => $data['order']['status_label'],
            'created_at' => now()->diffForHumans(),
        ];

        array_unshift($this->recentOrders, $newOrder);

        // Keep only the latest 5 orders
        if (count($this->recentOrders) > 5) {
            $this->recentOrders = array_slice($this->recentOrders, 0, 5);
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
        // Update stats based on status changes
        if (isset($data['changes']['status'])) {
            $oldStatus = $data['changes']['status']['old'];
            $newStatus = $data['changes']['status']['new'];

            // Decrease old status count
            if ($oldStatus === 'pending') $this->stats['pending_orders']--;
            elseif ($oldStatus === 'processing') $this->stats['processing_orders']--;
            elseif ($oldStatus === 'completed') $this->stats['completed_orders']--;

            // Increase new status count
            if ($newStatus === 'pending') $this->stats['pending_orders']++;
            elseif ($newStatus === 'processing') $this->stats['processing_orders']++;
            elseif ($newStatus === 'completed') $this->stats['completed_orders']++;
        }

        // Update in recent orders
        foreach ($this->recentOrders as &$order) {
            if ($order['id'] === $data['order']['id']) {
                $order['status'] = $data['order']['status'];
                $order['status_label'] = $data['order']['status_label'];
                $order['total_price'] = $data['order']['total_price'];
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
     * Refresh dashboard data
     */
    public function refreshDashboard()
    {
        $this->loadDashboardData();
    }

    /**
     * Toggle auto refresh
     */
    public function toggleAutoRefresh()
    {
        $this->autoRefresh = !$this->autoRefresh;
    }

    /**
     * Get status color for orders
     */
    public function getOrderStatusColor($status): string
    {
        return match ($status) {
            'pending' => 'text-yellow-500',
            'processing' => 'text-blue-500',
            'completed' => 'text-green-500',
            'cancelled' => 'text-red-500',
            default => 'text-gray-500',
        };
    }

    /**
     * Get status icon for orders
     */
    public function getOrderStatusIcon($status): string
    {
        return match ($status) {
            'pending' => 'fas fa-clock',
            'processing' => 'fas fa-cog',
            'completed' => 'fas fa-check-circle',
            'cancelled' => 'fas fa-times-circle',
            default => 'fas fa-question-circle',
        };
    }

    /**
     * Get shop status color
     */
    public function getShopStatusColor($status): string
    {
        return $status === 'assigned' ? 'text-green-500' : 'text-red-500';
    }

    /**
     * Get shop status icon
     */
    public function getShopStatusIcon($status): string
    {
        return $status === 'assigned' ? 'fas fa-check-circle' : 'fas fa-exclamation-triangle';
    }

    /**
     * Format price
     */
    public function formatPrice($price): string
    {
        return number_format((float) $price, 2) . ' TL';
    }
}
