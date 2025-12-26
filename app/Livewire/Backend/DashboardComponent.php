<?php

declare(strict_types=1);

namespace App\Livewire\Backend;

use App\Livewire\Frontend\MainComponent;
use App\Models\Product;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Carbon\Carbon;
use Spatie\Analytics\Facades\Analytics;
use Spatie\Analytics\Period;

class DashboardComponent extends Component
{
    public int $totalProducts = 0;
    public int $totalVisitors = 0;
    public int $totalActiveUsers = 0;
    public int $totalNewUsers = 0;
    public int $totalReturningUsers = 0;
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

        // Get total website visitors (last 30 days)
        $this->loadVisitorsData();

        // Get total active users (last 30 days)
        $this->loadActiveUsersData();

        // Placeholder data - replace with actual models when available
        $this->loadNewUsersData();

        $this->loadSalesData();

        $this->loadMostViewedProducts();
    }

    public function loadNewUsersData(): void
    {
        try {
            // Fetch total visitors for the last 30 days
            $analyticsData = Analytics::fetchUserTypes(Period::days(30));

            // Sum all visitors across the period
            $this->totalNewUsers = $analyticsData->firstWhere('newVsReturning', 'new')['activeUsers'];
            $this->totalReturningUsers = $analyticsData->firstWhere('newVsReturning', 'returning')['activeUsers'];
        } catch (\Exception $e) {
            logger()->error('Error loading analytics data: ' . $e->getMessage());
            $this->totalNewUsers = 0;
            $this->totalReturningUsers = 0;
        }
    }
    /**
     * Load website visitors data from Google Analytics
     */
    public function loadVisitorsData(): void
    {
        try {
            // Fetch total visitors for the last 30 days
            $analyticsData = Analytics::fetchTotalVisitorsAndPageViews(Period::days(30));

            // Sum all visitors across the period
            $this->totalVisitors = $analyticsData->sum('screenPageViews');
        } catch (\Exception $e) {
            logger()->error('Error loading analytics data: ' . $e->getMessage());
            $this->totalVisitors = 0;
        }
    }

    /**
     * Load active users data from Google Analytics
     */
    public function loadActiveUsersData(): void
    {
        try {
            // Fetch visitors and pageviews for the last 30 days
            $analyticsData = Analytics::fetchVisitorsAndPageViews(Period::days(30));

            // Sum all active users
            $this->totalActiveUsers = $analyticsData->sum('activeUsers');
        } catch (\Exception $e) {
            logger()->error('Error loading active users data: ' . $e->getMessage());
            $this->totalActiveUsers = 0;
        }
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
                $image = Storage::url($product?->images->where('is_primary', true)->first()?->image_url
                    ?? $product?->images->first()?->image_url ?? '');

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'image' => $image,
                    'category' => $product->category?->name ?? 'Uncategorized',
                    'status' => $product->is_active ? 'active' : 'inactive',
                ];
            })->toArray();
        } catch (\Exception $e) {
            logger()->error('Error loading recent products: ' . $e->getMessage());
            $this->recentProducts = [];
        }
    }

    public function updatedPeriod(): void
    {
        $this->loadSalesData();
    }

    public function loadSalesData(): void
    {
        try {
            $dates = collect();
            $visits = collect();

            switch ($this->period) {
                case 'week':
                    $analyticsData = Analytics::fetchVisitorsAndPageViewsByDate(Period::days(7));
                    foreach ($analyticsData as $data) {
                        $dates->prepend(Carbon::parse($data['date'])->format('D'));
                        $visits->prepend($data['screenPageViews']);
                    }
                    break;
                case 'month':
                    $analyticsData = Analytics::fetchVisitorsAndPageViewsByDate(Period::days(30));
                    foreach ($analyticsData as $data) {
                        $dates->prepend(Carbon::parse($data['date'])->format('d M'));
                        $visits->prepend($data['screenPageViews']);
                    }
                    break;
                case 'year':
                    $analyticsData = Analytics::fetchVisitorsAndPageViewsByDate(Period::months(12));
                    foreach ($analyticsData as $data) {
                        $dates->prepend(Carbon::parse($data['date'])->format('M Y'));
                        $visits->prepend($data['screenPageViews']);
                    }
                    break;
            }

            // Ensure we have valid data
            if ($dates->isEmpty() || $visits->isEmpty()) {
                throw new \Exception('No data available for the selected period');
            }

            // Create fresh data array to avoid reference issues
            $freshData = [
                'labels' => $dates->values()->toArray(),
                'data' => $visits->values()->toArray(),
            ];

            $this->salesData = $freshData;

            // Dispatch the event with the freshly created data
            $this->dispatch('salesDataUpdated', data: $freshData);
        } catch (\Exception $e) {
            logger()->error('Error loading visits data: ' . $e->getMessage());
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

        // Force the frontend to update by dispatching a browser event
        $this->dispatch('periodChanged', period: $newPeriod);
    }

    public function addNewProduct(): void
    {
        session()->flash('openAddProductModal', true);

        $this->redirect(route('subdomain.products'));
    }

    public array $mostViewedProducts = [];

    public function loadMostViewedProducts(): void
    {
        try {
            // Fetch top 5 most visited product pages in the last 30 days
            $analyticsData = Analytics::fetchMostVisitedPages(Period::days(300), 100);

            // Filter for product URLs (adjust the pattern to match your routes)
            $productPages = collect($analyticsData)
                ->filter(function ($page) {
                    $url = $page['fullPageUrl'];
//                    $url = 'http://developing.store/?productSlugUrl=ut-quidem-tempore&productIdUrl=15';
                    $parts = parse_url($url);
                    if (isset($parts['query']) && !str_contains($url, 'dev')) {
                        parse_str($parts['query'], $query);
                        return isset($query['productSlugUrl']) && isset($query['productIdUrl']);
                    }

                })
                ->take(50);

            // Map URLs to product slugs or IDs
            $this->mostViewedProducts = $productPages->map(function ($page) {
                // Extract slug or ID from URL, e.g., /products/{slug}
                $url = $page['fullPageUrl'];
//                $url = 'http://developing.store/?productSlugUrl=ut-quidem-tempore&productIdUrl=15';
                $parts = parse_url($url);

                if ( isset($parts['query']) ) {
                    parse_str($parts['query'], $query);
                    $productId = $query['productIdUrl'];
                    $product = Product::where('id', $productId)->first();

                    $fullPageUrl = (!str_starts_with($page['fullPageUrl'], 'http://') && !str_starts_with($page['fullPageUrl'], 'https://'))
                        ? 'https://' . $page['fullPageUrl']
                        : $page['fullPageUrl'];

                    $image = Storage::url($product?->images->where('is_primary', true)->first()?->image_url
                        ?? $product?->images->first()?->image_url ?? '');

                    return [
                        'id' => $product?->id,
                        'name' => $product?->name,
                        'category' => $product?->category->name,
                        'image' => $image,
                        'views' => $page['screenPageViews'],
                    ];
                }
            })->toArray();

        } catch (\Exception $e) {
            logger()->error('Error loading most viewed products from analytics: ' . $e->getMessage());
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Error loading most viewed products from analytics: ' . $e->getMessage(),
                'sec' => 10000
            ]);

            $this->mostViewedProducts = [];
        }
    }

    #[Layout('layouts.backend')]
    #[Title('Dashboard')]
    public function render()
    {
        return view('livewire.backend.dashboard-component');
    }
}
