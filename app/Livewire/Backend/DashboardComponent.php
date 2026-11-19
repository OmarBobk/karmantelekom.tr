<?php

declare(strict_types=1);

namespace App\Livewire\Backend;

use App\Models\Product;
use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Analytics\Facades\Analytics;
use Spatie\Analytics\Period;
use Throwable;

/**
 * Legacy marketing/analytics home for staff who cannot open the owner operations dashboard.
 * Owners are redirected to subdomain.operations.dashboard instead. Analytics failures here
 * must degrade to empty figures and must not run when credentials are missing.
 */
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

    public array $mostViewedProducts = [];

    public function mount(): void
    {
        $user = auth()->user();

        // Owner home is the operations dashboard; keep this GA page for other staff only.
        if ($user !== null && $user->can('viewOperationsDashboard', Store::class)) {
            $this->redirect(route('subdomain.operations.dashboard'), navigate: false);

            return;
        }

        $this->loadDashboardData();
    }

    public function loadDashboardData(): void
    {
        $this->totalProducts = Product::count();
        $this->loadRecentProducts();

        if (! $this->analyticsConfigured()) {
            $this->salesData = ['labels' => [], 'data' => []];

            return;
        }

        $this->loadVisitorsData();
        $this->loadActiveUsersData();
        $this->loadNewUsersData();
        $this->loadSalesData();
        $this->loadMostViewedProducts();
    }

    /**
     * Avoid resolving the Analytics client (and blocking on Google) when env/files are incomplete.
     */
    private function analyticsConfigured(): bool
    {
        if (empty(config('analytics.property_id'))) {
            return false;
        }

        $credentials = config('analytics.service_account_credentials_json');

        if (is_array($credentials)) {
            return $credentials !== [];
        }

        return is_string($credentials) && $credentials !== '' && is_file($credentials);
    }

    public function loadNewUsersData(): void
    {
        try {
            $analyticsData = Analytics::fetchUserTypes(Period::days(30));

            $this->totalNewUsers = (int) data_get(
                $analyticsData->firstWhere('newVsReturning', 'new'),
                'activeUsers',
                0
            );
            $this->totalReturningUsers = (int) data_get(
                $analyticsData->firstWhere('newVsReturning', 'returning'),
                'activeUsers',
                0
            );
        } catch (Throwable $e) {
            logger()->error('Error loading analytics data: '.$e->getMessage());
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
            $analyticsData = Analytics::fetchTotalVisitorsAndPageViews(Period::days(30));
            $this->totalVisitors = (int) $analyticsData->sum('screenPageViews');
        } catch (Throwable $e) {
            logger()->error('Error loading analytics data: '.$e->getMessage());
            $this->totalVisitors = 0;
        }
    }

    /**
     * Load active users data from Google Analytics
     */
    public function loadActiveUsersData(): void
    {
        try {
            $analyticsData = Analytics::fetchVisitorsAndPageViews(Period::days(30));
            $this->totalActiveUsers = (int) $analyticsData->sum('activeUsers');
        } catch (Throwable $e) {
            logger()->error('Error loading active users data: '.$e->getMessage());
            $this->totalActiveUsers = 0;
        }
    }

    /**
     * Load recent products from the database
     */
    public function loadRecentProducts(): void
    {
        try {
            $products = Product::with(['category', 'prices.currency', 'images'])
                ->orderBy('created_at', 'desc')
                ->take(5)
                ->get();

            $this->recentProducts = $products->map(function ($product) {
                $image = Storage::url($product->images->where('is_primary', true)->first()?->image_url
                    ?? $product->images->first()?->image_url
                    ?? '');

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'image' => $image,
                    'category' => $product->category?->name ?? 'Uncategorized',
                    'status' => $product->is_active ? 'active' : 'inactive',
                ];
            })->toArray();
        } catch (Throwable $e) {
            logger()->error('Error loading recent products: '.$e->getMessage());
            $this->recentProducts = [];
        }
    }

    public function updatedPeriod(): void
    {
        $this->loadSalesData();
    }

    public function loadSalesData(): void
    {
        if (! $this->analyticsConfigured()) {
            $this->salesData = ['labels' => [], 'data' => []];

            return;
        }

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

            if ($dates->isEmpty() || $visits->isEmpty()) {
                throw new \RuntimeException('No data available for the selected period');
            }

            $freshData = [
                'labels' => $dates->values()->toArray(),
                'data' => $visits->values()->toArray(),
            ];

            $this->salesData = $freshData;
            $this->dispatch('salesDataUpdated', data: $freshData);
        } catch (Throwable $e) {
            logger()->error('Error loading visits data: '.$e->getMessage());
            $this->salesData = [
                'labels' => [],
                'data' => [],
            ];
        }
    }

    public function updatePeriod(string $newPeriod): void
    {
        if (! in_array($newPeriod, ['week', 'month', 'year'], true)) {
            return;
        }

        $this->period = $newPeriod;
        $this->loadSalesData();
        $this->dispatch('periodChanged', period: $newPeriod);
    }

    public function addNewProduct(): void
    {
        session()->flash('openAddProductModal', true);

        $this->redirect(route('subdomain.products'));
    }

    public function loadMostViewedProducts(): void
    {
        if (! $this->analyticsConfigured()) {
            $this->mostViewedProducts = [];

            return;
        }

        try {
            // Keep the window bounded so a slow GA response cannot hang the home request.
            $analyticsData = Analytics::fetchMostVisitedPages(Period::days(30), 50);

            $productPages = collect($analyticsData)
                ->filter(function ($page) {
                    $url = $page['fullPageUrl'] ?? '';
                    $parts = parse_url($url);
                    if (isset($parts['query']) && ! str_contains($url, 'dev')) {
                        parse_str($parts['query'], $query);

                        return isset($query['productSlugUrl'], $query['productIdUrl']);
                    }

                    return false;
                })
                ->take(50);

            $this->mostViewedProducts = $productPages->map(function ($page) {
                $url = $page['fullPageUrl'];
                $parts = parse_url($url);

                if (! isset($parts['query'])) {
                    return null;
                }

                parse_str($parts['query'], $query);
                $productId = $query['productIdUrl'] ?? null;
                $product = $productId ? Product::with(['category', 'images'])->find($productId) : null;

                if ($product === null) {
                    return null;
                }

                $image = Storage::url($product->images->where('is_primary', true)->first()?->image_url
                    ?? $product->images->first()?->image_url
                    ?? '');

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'category' => $product->category?->name ?? 'Uncategorized',
                    'image' => $image,
                    'views' => $page['screenPageViews'],
                ];
            })->filter()->values()->toArray();
        } catch (Throwable $e) {
            logger()->error('Error loading most viewed products from analytics: '.$e->getMessage());
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Error loading most viewed products from analytics: '.$e->getMessage(),
                'sec' => 10000,
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
