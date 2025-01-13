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
    public array $recentOrders = [];
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

        // Placeholder data - replace with actual models when available
        $this->totalCustomers = 150;
        $this->totalOrders = 75;
        $this->totalRevenue = 15000;

        // Sample recent orders - replace with actual order data
        $this->recentOrders = [
            [
                'id' => '1234',
                'customer' => 'John Doe',
                'status' => 'completed',
                'total' => 299.00,
                'date' => now()->subHours(2),
            ],
            [
                'id' => '1235',
                'customer' => 'Jane Smith',
                'status' => 'pending',
                'total' => 199.00,
                'date' => now()->subHours(3),
            ],
            [
                'id' => '1236',
                'customer' => 'Bob Johnson',
                'status' => 'processing',
                'total' => 499.00,
                'date' => now()->subHours(4),
            ],
        ];

        $this->loadSalesData();
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
