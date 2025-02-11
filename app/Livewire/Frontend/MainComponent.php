<?php

declare(strict_types=1);

namespace App\Livewire\Frontend;

use App\Models\Section;
use App\Models\Currency;
use App\Models\ProductPrice;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
use function Laravel\Prompts\alert;

class MainComponent extends Component
{
    public $activeCategory = 0;
    public $sections;
    public $contentSections;
    public bool $canSwitchCurrency;
    public string $priceType;

    public function mount()
    {
        $this->setupUserPricing();
        $this->loadAllSections();
    }

    private function setupUserPricing(): void
    {
        // Determine if user can switch currency and which price type to show
        $this->canSwitchCurrency = auth()->check() && auth()->user()->hasAnyRole(['admin', 'salesperson', 'shop_owner']);
        $this->priceType = $this->canSwitchCurrency ? ProductPrice::TYPE_WHOLESALE : ProductPrice::TYPE_RETAIL;
    }

    private function getCurrency(): Currency
    {
        if (!$this->canSwitchCurrency) {
            return Cache::remember('default_currency', now()->addDay(), function () {
                return Currency::where('is_default', true)->firstOrFail();
            });
        }

        $currencyCode = session('currency', 'TRY');
        return Cache::remember("currency_{$currencyCode}", now()->addHour(), function () use ($currencyCode) {
            return Currency::where('code', $currencyCode)->firstOrFail();
        });
    }

    #[On('currencyChanged')]
    public function loadAllSections()
    {
        try {
            $currency = $this->getCurrency();

            // Load both types of sections
            $this->loadSectionsByPosition('main.slider', 'sections', true);

            $this->loadSectionsByPosition('main.content', 'contentSections', false);
            // Clear currency cache once
            Cache::forget("currency_{$currency->code}");

            // Dispatch update event once
            $this->dispatch('content-sections-updated');

        } catch (\Exception $e) {
            logger()->error('Error loading sections: ' . $e->getMessage());
        }
    }

    private function loadSectionsByPosition(string $position, string $propertyName, bool $scrollable = false): void
    {
        $currency = $this->getCurrency();
        $cacheKey = "{$propertyName}_{$currency->code}_{$this->priceType}";

        Cache::forget($cacheKey);

        $this->{$propertyName} = Cache::remember($cacheKey, now()->addMinutes(30), function () use ($currency, $position, $scrollable) {
            $query = Section::with([
                'products' => function($query) use ($currency, $position) {
                    $query->visibleToUser()
                        ->with([
                            'images' => function($query) use ($position) {
                                if ($position === 'main.slider') {
                                    $query->where('is_primary', true);
                                } else {
                                    $query->orderBy('is_primary', 'desc');
                                }
                            },
                            'prices' => function($query) use ($currency) {
                                $query->where('currency_id', $currency->id)
                                     ->where('price_type', $this->priceType);
                            }
                        ])
                        ->orderBy('section_products.ordering');
                }
            ])
            ->where('position', $position)
            ->where('is_active', true)
            ->orderBy('order');

            if ($scrollable) {
                $query->where('scrollable', true);
            }

            return $query->get();
        });
    }

    // Slider Component
    public $scrollPosition = 0;
    public $isScrolledLeft = true;
    public $isScrolledRight = false;

    #[Layout('layouts.frontend')]
    public function render()
    {
        return view('livewire.frontend.main-component', [
            'canSwitchCurrency' => $this->canSwitchCurrency
        ]);
    }

    public function setActiveCategory($index)
    {
        $this->activeCategory = $index;
    }

    public function addToCart($productId, $quantity)
    {
        // Simulate network delay
        usleep(800000);

        $this->dispatch('cart-updated', [
            'message' => 'Product added to cart successfully!',
            'type' => 'success'
        ]);
    }
}
