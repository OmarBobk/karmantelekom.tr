<?php

declare(strict_types=1);

namespace App\Livewire\Frontend\Partials;

use App\Facades\Cart as CartFacade;
use App\Models\Cart;
use App\Models\Product;
use App\Services\CartService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\On;
use Livewire\Component;
use App\Services\LanguageService;
use App\Exceptions\LanguageNotSupportedException;

class HeaderComponent extends Component
{
    public string $cartCount = '1';
    public string $currentLanguage = 'en';
    public string $searchComponentKey;
    public string $currentDirection = 'ltr';
    private LanguageService $languageService;
    public $categories;

    public Cart $cart;

    #[On('sync-cart')]
    public function handleSyncCart(array $items): void
    {
        Log::info('Cart sync requested', ['items_count' => count($items)]);

        $user_id = auth()->id();

        // If the user is not authenticated, we cannot sync the cart.

        try {
            if (!$user_id) {
                Log::info('User is not authenticated, skipping cart sync');
                return;
            } else {
                $cart = CartFacade::syncCart($user_id, $items);
                if (is_array($cart) && !empty($cart)) {
                    $this->dispatch('cart-items-from-server', $cart);
                }

                Log::info('Cart synced successfully', ['user_id' => $user_id]);
            }
        } catch (\Exception $e) {
            Log::error('Error syncing cart', [
                'user_id' => $user_id,
                'error' => $e->getMessage()
            ]);
        }

    }

    #[On('clear-cart')]
    public function handleClearCart(): void
    {
        $user_id = auth()->id();
        try {
            if (!$user_id) {
                Log::info('User is not authenticated, skipping cart clear User ID: ' . $user_id);
                return;
            } else {
                CartFacade::clearCart($user_id, null);
            }
        } catch (\Exception $e) {
            Log::error('Error Clearing cart', [
                'user_id' => $user_id,
                'error' => $e->getMessage()
            ]);
        }

    }

    #[On('remove-item')]
    public function handleRemoveItem($id): void
    {
        $user_id = auth()->id();
        try {
            if (!$user_id) {
                Log::info('User is not authenticated, skipping Removing : ' . $user_id);
                return;
            } else {
                CartFacade::removeFromCart($user_id, null, $id);
            }
        } catch (\Exception $e) {
            Log::error('Error Clearing cart', [
                'user_id' => $user_id,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function boot(LanguageService $languageService): void
    {
        $this->languageService = $languageService;
    }

    public function mount(): void
    {
        $this->currentLanguage = $this->languageService->getCurrentLanguage();
        $this->currentDirection = $this->languageService->getCurrentDirection();
        $this->searchComponentKey = 'search-' . uniqid();
        $this->categories = \App\Models\Category::with('children')
            ->whereNull('parent_id')
            ->where('status', true)
            ->orderBy('name')
            ->get();

        $this->searchResults = [];
    }

    public function render()
    {
        return view('livewire.frontend.partials.header-component');
    }

    public function changeLanguage(string $code): void
    {
        try {
            $this->languageService->switchLanguage($code);

            $this->currentLanguage = $this->languageService->getCurrentLanguage();
            $this->currentDirection = $this->languageService->getCurrentDirection();

            // Force a re-render of the search component
            $this->searchComponentKey = 'search-' . uniqid();

            // Dispatch event with language metadata
            $this->dispatch('languageChanged', [
                'code' => $this->currentLanguage,
                'direction' => $this->currentDirection,
                'locale' => strtolower($this->currentLanguage)
            ]);

        } catch (LanguageNotSupportedException $e) {
            logger()->error('Language switch failed: ' . $e->getMessage());
            $this->dispatch('languageError', ['message' => $e->getMessage()]);
        }
    }

    public function getLanguageName(string $code): string
    {
        return $this->languageService->getLanguageName($code);
    }

    public function getLanguageFlag(string $code): string
    {
        return Storage::url($this->languageService->getLanguageFlag($code));
    }

    public string $searchQuery = '';
    public bool $isLoading = false;
    public array $searchResults = [];


    public function updatedSearchQuery(): void
    {
        $this->search();
    }

    public function resetSearch(): void
    {
        $this->searchQuery = '';
        $this->searchResults = [];
        $this->isLoading = false;
    }

    public function search(): void
    {
        if (strlen($this->searchQuery) < 2) {
            $this->searchResults = [];
            return;
        }

        $this->isLoading = true;

        // Search in products
        $products = Product::where('is_active', true)
            ->with('images')
            ->search(
                $this->searchQuery,
                'id',
                'asc'
            )
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'image' => Storage::url($product->images->where('is_primary', true)->first()->image_url),
                    'title' => $product->translated_name,
                    'description' => $product->translated_description,
                    'category' => 'Product',
                    'url' => '#',
                ];
            });

        $this->searchResults = $products->toArray();
        $this->isLoading = false;
    }


}
