<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Cart;
use App\Models\Product;
use App\Models\CartItem;
use Illuminate\Support\Collection;
use Illuminate\Session\SessionManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CartService
{
    protected function getCartIdentifier(): array
    {
        if (Auth::check()) {
            return ['user_id' => Auth::id()];
        }
        
        return ['session_id' => session()->getId()];
    }

    public function getOrCreateCart(int $userId = null): Cart
    {
        $identifier = $this->getCartIdentifier();
        
        return Cart::with(['items.product'])
            ->firstOrCreate($identifier);
    }

    public function addToCart(int $userId = null, Product $product, int $quantity = 1): CartItem
    {
        $cart = $this->getOrCreateCart($userId);

        $item = $cart->items()->firstOrNew([
            'product_id' => $product->id
        ]);

        $item->price = $product->prices->first()->base_price;
        $item->quantity += $quantity;
        $item->subtotal = $item->price * $item->quantity;
        $item->save();

        return $item;
    }

    public function removeFromCart(int $userId = null, int $productId): bool
    {
        $cart = $this->getOrCreateCart($userId);
        return $cart->items()->where('product_id', $productId)->delete() > 0;
    }

    public function updateQuantity(int $userId = null, int $productId, int $quantity): ?CartItem
    {
        $cart = $this->getOrCreateCart($userId);
        $item = $cart->items()->where('product_id', $productId)->first();

        if (!$item) {
            return null;
        }

        $item->quantity = $quantity;
        $item->subtotal = $item->price * $quantity;
        $item->save();

        return $item;
    }

    public function clearCart(int $userId = null): bool
    {
        $cart = $this->getOrCreateCart($userId);
        return $cart->items()->delete() > 0;
    }

    public function getCartTotal(int $userId = null): float
    {
        $cart = $this->getOrCreateCart($userId);
        return (float) $cart->items()->sum('subtotal');
    }

    public function getCartItemCount(int $userId = null): int
    {
        $cart = $this->getOrCreateCart($userId);
        return (int) $cart->items()->sum('quantity');
    }

    /**
     * Transfer guest cart to user cart after login
     */
    public function transferGuestCartToUser(int $userId): void
    {
        $sessionId = session()->getId();
        
        // Get guest cart
        $guestCart = Cart::where('session_id', $sessionId)->first();
        
        if (!$guestCart) {
            return;
        }

        // Get or create user cart
        $userCart = Cart::firstOrCreate(['user_id' => $userId]);

        // Transfer items from guest cart to user cart
        foreach ($guestCart->items as $item) {
            $existingItem = $userCart->items()
                ->where('product_id', $item->product_id)
                ->first();

            if ($existingItem) {
                // If item exists in user cart, add quantities
                $existingItem->quantity += $item->quantity;
                $existingItem->subtotal = $existingItem->price * $existingItem->quantity;
                $existingItem->save();
            } else {
                // If item doesn't exist, create new item
                $userCart->items()->create([
                    'product_id' => $item->product_id,
                    'price' => $item->price,
                    'quantity' => $item->quantity,
                    'subtotal' => $item->subtotal
                ]);
            }
        }

        // Delete guest cart
        $guestCart->delete();
    }
}
