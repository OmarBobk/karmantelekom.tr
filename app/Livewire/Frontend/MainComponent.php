<?php

declare(strict_types=1);

namespace App\Livewire\Frontend;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Currency;
use App\Models\Product;
use App\Models\Section;
use App\Services\LanguageService;
use Illuminate\Support\Facades\App;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

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
            return Currency::where('is_default', true)->firstOrFail();
        }

        $currencyCode = session('currency', 'TRY');
        return Currency::where('code', $currencyCode)->firstOrFail();
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

            // Dispatch update event
            $this->dispatch('content-sections-updated');

        } catch (\Exception $e) {
            logger()->error('Error loading sections: ' . $e->getMessage());
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Failed to load content sections: ' . $e->getMessage(),
                'sec' => 10000
            ]);
        }
    }

    private function loadSectionsByPosition(string $position, string $propertyName, bool $scrollable = false): void
    {
        $currency = $this->getCurrency();
        $locale = App::getLocale();

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

        $this->{$propertyName} = $query->get();
    }

    // Slider Component
    public $scrollPosition = 0;
    public $isScrolledLeft = true;
    public $isScrolledRight = false;

    /**
     * Add a product to the cart.
     *
     * @param int $productId
     * @return void
     */
    public function addToCart(int $productId): void
    {
        try {
            // Get the product with its prices
            $product = Product::with(['prices'])->findOrFail($productId);


            // Check if product has price
            if ($product->prices->isEmpty()) {
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => __('Product price is not available'),
                    'sec' => 3000
                ]);
                return;
            }

            // Get or create user's cart
            $cart = Cart::firstOrCreate([
                'user_id' => auth()->id(),
            ]);

            // Check if product already exists in cart
            $cartItem = $cart->items()->where('product_id', $productId)->first();

            if ($cartItem) {
                // Increment quantity if product exists
                $cartItem->incrementQuantity();
            } else {
                $price = $product->prices->first()->base_price; // Get the first price for the product

                $quantity = 1; // Default quantity
                // Create new cart item
                $cart->items()->create([
                    'product_id' => $productId,
                    'price' => $price,
                    'quantity' => $quantity,
                    'subtotal' => $price * $quantity,
                ]);
            }

            // Dispatch success notification
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => __('Product added to cart successfully'),
                'sec' => 3000
            ]);

            // Dispatch cart update event
            $this->dispatch('cart-updated');

        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => __('Failed to add product to cart') . $e->getMessage(),
                'sec' => 3000
            ]);
        }
    }

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
