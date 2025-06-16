<?php

declare(strict_types=1);

namespace App\Livewire\Backend\Shops;

use App\Models\Shop;
use App\Models\Order;
use App\Models\Product;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

class ShopProfileComponent extends Component
{
    use WithPagination;

    public Shop $shop;
    public string $search = '';
    public string $statusFilter = 'all';

    protected $queryString = ['search', 'statusFilter'];

    public function mount(Shop $shop)
    {
        $this->shop = $shop;
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    #[Layout('layouts.backend')]
    #[Title('Shop Profile')]
    public function render()
    {
        $orders = $this->shop->orders()
            ->when($this->search, fn($q) => $q->where('customer_name', 'like', "%{$this->search}%"))
            ->when($this->statusFilter !== 'all', fn($q) => $q->where('status', $this->statusFilter))
            ->latest()
            ->paginate(10);

        $metrics = [
            'revenue' => 23, // $this->shop->orders()->sum('total'),
            'orders' => 13, // $this->shop->orders()->count(),
            'rating' => 15, // $this->shop->rating,
            'delivery_success' => 32, // $this->shop->delivery_success_rate,
        ];

        $statusCounts = $this->shop->orders()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->all();

        $topProducts = [
            [
                'name' => 'Product A',
                'orders_count' => 150,
            ],
            [
                'name' => 'Product B',
                'orders_count' => 100,
            ],
            [
                'name' => 'Product C',
                'orders_count' => 80,
            ]
        ];

        $statusColors = [
            'pending'    => ['bg' => 'orange-100',   'text' => 'orange-700'],
            'confirmed'  => ['bg' => 'blue-100',     'text' => 'blue-700'],
            'processing' => ['bg' => 'purple-100',   'text' => 'purple-700'],
            'ready'      => ['bg' => 'green-100',    'text' => 'green-700'],
            'delivering' => ['bg' => 'indigo-100',   'text' => 'indigo-700'],
            'delivered'  => ['bg' => 'emerald-100',  'text' => 'emerald-700'],
            'canceled'   => ['bg' => 'red-100',      'text' => 'red-700'],
        ];

        return view('livewire.backend.shops.shop-profile-component', compact(
            'orders', 'metrics', 'statusCounts', 'topProducts', 'statusColors'
        ));
    }
}
