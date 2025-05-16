<?php

declare(strict_types=1);

namespace App\Livewire\Frontend;

use App\Livewire\ProductModalComponent;
use App\Models\Section;
use App\Models\Currency;
use App\Models\ProductPrice;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use function Laravel\Prompts\alert;
use App\Models\Product;
use App\Services\CartService;
use App\Services\LanguageService;

class MainComponent extends Component
{
    public int $activeCategory = 0;
    public $sections;
    public $contentSections;
    public bool $canSwitchCurrency;
    public string $priceType;
    public string $currentDirection = 'ltr';
    private LanguageService $languageService;

    public function boot(LanguageService $languageService): void
    {
        $this->languageService = $languageService;
    }

    public function mount(): void
    {
        $this->canSwitchCurrency = true;
        $this->currentDirection = $this->languageService->getCurrentDirection();
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

    #[On('languageChanged')]
    public function handleLanguageChanged(array $data): void
    {
        try {
            $this->currentDirection = $data['direction'] ?? 'ltr';
            $this->loadAllSections($data['code']);
            
            // Update document direction
            $this->dispatch('updateDirection', ['direction' => $this->currentDirection]);
            
        } catch (\Exception $e) {
            logger()->error('Error handling language change: ' . $e->getMessage());
            $this->dispatch('languageError', ['message' => 'Failed to update content for the selected language']);
        }
    }

    #[On('currencyChanged')]
    public function loadAllSections(string $code = null): void
    {
        try {
            $code = $code ?? $this->languageService->getCurrentLanguage();
            $this->languageService->switchLanguage($code);
            
            $currency = $this->getCurrency();

            // Load both types of sections with proper caching
            $this->loadSectionsByPosition('main.slider', 'sections', true);
            $this->loadSectionsByPosition('main.content', 'contentSections', false);

            // Clear currency cache
            Cache::forget("currency_{$currency->code}");

            // Dispatch update event
            $this->dispatch('content-sections-updated');

        } catch (\Exception $e) {
            logger()->error('Error loading sections: ' . $e->getMessage());
            $this->dispatch('sectionError', ['message' => 'Failed to load content sections']);
        }
    }

    private function loadSectionsByPosition(string $position, string $propertyName, bool $scrollable = false): void
    {
        $currency = $this->getCurrency();
        $locale = App::getLocale();
        $cacheKey = "{$propertyName}_{$currency->code}_{$locale}";

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
            'canSwitchCurrency' => $this->canSwitchCurrency,
            'direction' => $this->currentDirection
        ]);
    }

    public function setActiveCategory(int $index): void
    {
        $this->activeCategory = $index;
    }
}
