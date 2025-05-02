<?php

declare(strict_types=1);

namespace App\Livewire\Backend;

use App\Models\Product;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Carbon\Carbon;

class DashboardComponent extends Component
{
    public int $totalProducts = 0;
    public int $totalCustomers = 0;
    public int $totalOrders = 0;
    public float $totalRevenue = 0;
    public array $recentProducts = [];
    public array $salesData = [];
    public string $period = 'week';

    public function mount(): void
    {
        $this->loadDashboardData();
    }

    public function loadDashboardData()
    {
        // Get real statistics
        $this->totalProducts = Product::count();

        // Get recent products
        $this->loadRecentProducts();

        // Placeholder data - replace with actual models when available
        $this->totalCustomers = 150;
        $this->totalOrders = 75;
        $this->totalRevenue = 15000;

        $this->loadSalesData();
    }

    /**
     * Load recent products from the database
     */
    public function loadRecentProducts(): void
    {
        try {
            $products = Product::with(['category', 'prices.currency'])
                ->orderBy('created_at', 'desc')
                ->take(5)
                ->get();

            $this->recentProducts = $products->map(function ($product) {
                $price = $product->prices->first();
                
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'category' => $product->category?->name ?? 'Uncategorized',
                    'status' => $product->is_active ? 'active' : 'inactive',
                    'price' => $price ? $price->getFormattedPrice() : 'N/A',
                    'date' => $product->created_at
                ];
            })->toArray();
        } catch (\Exception $e) {
            logger()->error('Error loading recent products: ' . $e->getMessage());
            $this->recentProducts = [];
        }
    }

    public function loadSalesData(): void
    {
        try {
            $dates = collect();
            $sales = collect();

            switch ($this->period) {
                case 'week':
                    for ($i = 6; $i >= 0; $i--) {
                        $date = Carbon::now()->subDays($i);
                        $dates->push($date->format('D'));
                        $sales->push(rand(100, 1000));
                    }
                    break;
                case 'month':
                    for ($i = 29; $i >= 0; $i--) {
                        $date = Carbon::now()->subDays($i);
                        $dates->push($date->format('d M'));
                        $sales->push(rand(100, 1000));
                    }
                    break;
                case 'year':
                    for ($i = 11; $i >= 0; $i--) {
                        $date = Carbon::now()->subMonths($i);
                        $dates->push($date->format('M'));
                        $sales->push(rand(1000, 10000));
                    }
                    break;
            }

            $this->salesData = [
                'labels' => $dates->toArray(),
                'data' => $sales->toArray(),
            ];

            // Use dispatch instead of dispatchBrowserEvent
            $this->dispatch('chart-data-updated', $this->salesData);
        } catch (\Exception $e) {
            logger()->error('Error loading sales data: ' . $e->getMessage());
            $this->salesData = [
                'labels' => [],
                'data' => [],
            ];
        }
    }

    public function updatePeriod(string $newPeriod): void
    {
        if (!in_array($newPeriod, ['week', 'month', 'year'])) {
            return;
        }
        
        $this->period = $newPeriod;
        $this->loadSalesData();
    }

    #[Layout('layouts.backend')]
    public function render()
    {
        return view('livewire.backend.dashboard-component');
    }
}
