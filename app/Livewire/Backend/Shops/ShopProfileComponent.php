<?php

declare(strict_types=1);

namespace App\Livewire\Backend\Shops;

use App\Enums\OrderStatus;
use App\Models\Shop;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
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
        $shopId = $this->shop->id;

        $top_products = \App\Models\OrderItem::query()
            ->select('product_id', DB::raw('SUM(quantity) as total_quantity'))
            ->whereIn('order_id', function ($query) use ($shopId) {
                $query->select('id')
                    ->from('orders')
                    ->where('shop_id', $shopId);
            })
            ->groupBy('product_id')
            ->orderByDesc('total_quantity')
            ->with('product:id,name') // eager load product details
            ->limit(3)
            ->get();


        $orders = $this->shop->orders();
        $orders_table = $orders
            ->when($this->search, fn($q) => $q->where('customer_name', 'like', "%{$this->search}%"))
            ->when($this->statusFilter !== 'all', fn($q) => $q->where('status', $this->statusFilter))
            ->latest()
            ->paginate(10);

        $sum_total_price = $orders->sum('total_price');
        $orders_count = $orders->count();
        $active_orders = $orders;
        $metrics = [
            'revenue' => number_format((float) $sum_total_price, 2, '.', ','),
            'orders' => $orders_count,
            'active_orders' => $active_orders->whereNot("status", OrderStatus::DELIVERED)->count(),
            'rating' => 15, // $this->shop->rating,
            'delivery_success' => 32, // $this->shop->delivery_success_rate,
            'avg' => number_format(($sum_total_price / $orders_count), 2, '.', ','), // $this->shop->delivery_success_rate,
        ];

        $statusCounts = $orders
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
            'orders_table', 'top_products', 'metrics', 'statusCounts', 'topProducts', 'statusColors'
        ));
    }
}
