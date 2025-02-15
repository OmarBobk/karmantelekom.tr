<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use App\Models\Currency;
use App\Models\ProductPrice;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Cache;

class CartService
{
    private string $sessionKey = 'cart_session_id';

    public function __construct()
    {
        if (!Session::has($this->sessionKey)) {
            Session::put($this->sessionKey, uniqid('cart_', true));
        }
    }

    public function getSessionId(): string
    {
        return Session::get($this->sessionKey);
    }

    private function getDefaultCurrency(): Currency
    {
        return Cache::remember('default_currency', now()->addDay(), function () {
            return Currency::where('is_default', true)->firstOrFail();
        });
    }

    private function getUserCurrency(User $user): Currency
    {
        $currencyCode = $user->preferred_currency ?? 'TRY';
        return Cache::remember("currency_{$currencyCode}", now()->addHour(), function () use ($currencyCode) {
            return Currency::where('code', $currencyCode)->firstOrFail();
        });
    }

    private function getProductPrice(Product $product, Currency $currency, string $priceType): ?ProductPrice
    {
        // First try to get price in requested currency
        $price = $product->prices()
            ->where('currency_id', $currency->id)
            ->where('price_type', $priceType)
            ->first();

        if (!$price) {
            // If not found, try to get price in default currency
            $defaultCurrency = $this->getDefaultCurrency();
            $price = $product->prices()
                ->where('currency_id', $defaultCurrency->id)
                ->where('price_type', $priceType)
                ->first();
        }

        return $price;
    }

    public function addItem(Product $product, int $quantity = 1, ?User $user = null): CartItem
    {
        $cartItem = $this->findCartItem($product, $user);
        $currency = $user ? $this->getUserCurrency($user) : $this->getDefaultCurrency();
        $priceType = $user && $user->is_shop_owner ? ProductPrice::TYPE_WHOLESALE : ProductPrice::TYPE_RETAIL;

        // Get the appropriate price for the product
        $price = $this->getProductPrice($product, $currency, $priceType);

        if (!$price) {
            throw new \Exception('No price available for this product.');
        }

        if ($cartItem) {
            $cartItem->update([
                'quantity' => $cartItem->quantity + $quantity,
                'price' => $price->base_price,
                'currency' => $currency->code
            ]);
            return $cartItem;
        }

        return CartItem::create([
            'user_id' => $user?->id,
            'session_id' => $user ? null : $this->getSessionId(),
            'product_id' => $product->id,
            'quantity' => $quantity,
            'price' => $price->base_price,
            'currency' => $currency->code
        ]);
    }

    public function updateQuantity(CartItem $cartItem, int $quantity): void
    {
        if ($quantity < 1) {
            $cartItem->delete();
            return;
        }

        $cartItem->update(['quantity' => $quantity]);
    }

    public function removeItem(CartItem $cartItem): void
    {
        $cartItem->delete();
    }

    public function clearCart(?User $user = null): void
    {
        if ($user) {
            CartItem::where('user_id', $user->id)->delete();
        } else {
            CartItem::where('session_id', $this->getSessionId())->delete();
        }
    }

    public function getCartItems(?User $user = null): Collection
    {
        $query = CartItem::with(['product.prices', 'product.images']);

        if ($user) {
            return $query->where('user_id', $user->id)->get();
        }

        return $query->where('session_id', $this->getSessionId())->get();
    }

    public function syncSessionCartToUser(User $user): void
    {
        $sessionItems = CartItem::where('session_id', $this->getSessionId())->get();
        $currency = $this->getUserCurrency($user);
        $priceType = $user->is_shop_owner ? ProductPrice::TYPE_WHOLESALE : ProductPrice::TYPE_RETAIL;

        foreach ($sessionItems as $sessionItem) {
            $existingItem = CartItem::where('user_id', $user->id)
                ->where('product_id', $sessionItem->product_id)
                ->first();

            $price = $sessionItem->product->prices()
                ->where('currency_id', $currency->id)
                ->where('price_type', $priceType)
                ->first();

            if (!$price) {
                // Try to get default currency price
                $price = $sessionItem->product->prices()
                    ->where('price_type', $priceType)
                    ->whereHas('currency', function ($query) {
                        $query->where('is_default', true);
                    })
                    ->first();

                if (!$price) {
                    $sessionItem->delete();
                    continue;
                }
            }

            if ($existingItem) {
                $existingItem->update([
                    'quantity' => $existingItem->quantity + $sessionItem->quantity,
                    'price' => $price->base_price,
                    'currency' => $currency->code
                ]);
                $sessionItem->delete();
            } else {
                $sessionItem->update([
                    'user_id' => $user->id,
                    'session_id' => null,
                    'price' => $price->base_price,
                    'currency' => $currency->code
                ]);
            }
        }
    }

    private function findCartItem(Product $product, ?User $user): ?CartItem
    {
        if ($user) {
            return CartItem::where('user_id', $user->id)
                ->where('product_id', $product->id)
                ->first();
        }

        return CartItem::where('session_id', $this->getSessionId())
            ->where('product_id', $product->id)
            ->first();
    }
}