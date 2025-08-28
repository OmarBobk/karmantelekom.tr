<?php

declare(strict_types=1);

namespace App\Livewire\Backend;

use App\Models\Shop;
use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\WithPagination;

/**
 * RealTimeShops Component
 *
 * Displays shops with real-time updates
 */
class RealTimeShops extends Component
{
    use WithPagination;

    public array $shops = [];
    public bool $autoRefresh = true;
    public int $refreshInterval = 30000; // 30 seconds

    protected $listeners = [
        'shop-created' => 'handleShopCreated',
        'shop-assigned' => 'handleShopAssigned',
    ];

    public function mount()
    {
        $this->loadShops();
    }

    public function render()
    {
        return view('livewire.backend.real-time-shops');
    }

    /**
     * Load shops from database
     */
    public function loadShops()
    {
        $this->shops = Shop::with(['owner', 'salesperson'])
            ->latest()
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
                    'updated_at' => $shop->updated_at->diffForHumans(),
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
        $newShop = [
            'id' => $data['shop']['id'],
            'name' => $data['shop']['name'],
            'phone' => $data['shop']['phone'],
            'address' => $data['shop']['address'],
            'owner_name' => 'N/A',
            'salesperson_name' => 'Unassigned',
            'created_at' => now()->diffForHumans(),
            'updated_at' => now()->diffForHumans(),
        ];

        array_unshift($this->shops, $newShop);

        // Keep only the latest 50 shops
        if (count($this->shops) > 50) {
            $this->shops = array_slice($this->shops, 0, 50);
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
        // Update the shop in the list
        foreach ($this->shops as &$shop) {
            if ($shop['id'] === $data['shop']['id']) {
                $shop['salesperson_name'] = $data['salesperson']['name'];
                $shop['updated_at'] = now()->diffForHumans();
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
     * Refresh shops list
     */
    public function refreshShops()
    {
        $this->loadShops();
    }

    /**
     * Toggle auto refresh
     */
    public function toggleAutoRefresh()
    {
        $this->autoRefresh = !$this->autoRefresh;
    }

    /**
     * Get shop status color
     */
    public function getShopStatusColor($shop): string
    {
        return $shop['salesperson_name'] === 'Unassigned' ? 'text-red-500' : 'text-green-500';
    }

    /**
     * Get shop status icon
     */
    public function getShopStatusIcon($shop): string
    {
        return $shop['salesperson_name'] === 'Unassigned' ? 'fas fa-exclamation-triangle' : 'fas fa-check-circle';
    }
}
