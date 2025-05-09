<?php

declare(strict_types=1);

namespace App\Livewire\Frontend;

use App\Livewire\ProductModalComponent;
use App\Models\Section;
use App\Models\Currency;
use App\Models\ProductPrice;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use function Laravel\Prompts\alert;
use App\Models\Product;
use App\Services\CartService;

class MainComponent extends Component
{
    public $activeCategory = 0;
    public $sections;
    public $contentSections;
    public bool $canSwitchCurrency;
    public string $priceType;

    public function mount(): void
    {
        $this->canSwitchCurrency = true;
        $this->loadAllSections();
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
        $cacheKey = "{$propertyName}_{$currency->code}";

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
                                $query->where('currency_id', $currency->id);
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
    #[Title('Home')]
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
}
