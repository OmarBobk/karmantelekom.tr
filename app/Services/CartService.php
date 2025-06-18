<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Cart;
use App\Models\Product;
use App\Models\CartItem;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class CartService
{
    public function getOrCreateCart(?int $userId, ?string $sessionId): Cart
    {
        $query = Cart::with(['items.product']);

        if ($userId) {
            return $query->firstOrCreate(['user_id' => $userId]);
        } elseif ($sessionId) {
            return $query->firstOrCreate(['session_id' => $sessionId]);
        }

        throw new InvalidArgumentException('Either user_id or session_id must be provided.');
    }

    public function addToCart(?int $userId, ?string $sessionId, Product $product, int $quantity = 1): CartItem
    {
        $cart = $this->getOrCreateCart($userId, $sessionId);

        $item = $cart->items()->where('product_id', $product->id)->first();

        if ($item) {
            $item->quantity += $quantity;
            $item->subtotal = $item->price * $item->quantity;
            $item->save();
        } else {
            $item = $cart->items()->create([
                'product_id' => $product->id,
                'price' => $product->prices->first()->base_price,
                'quantity' => $quantity,
                'subtotal' => $product->prices->first()->base_price * $quantity
            ]);
        }

        return $item;
    }

    public function removeFromCart(?int $userId, ?string $sessionId, int $productId): bool
    {
        $cart = $this->getOrCreateCart($userId, $sessionId);
        return $cart->items()->where('product_id', $productId)->delete() > 0;
    }

    public function updateQuantity(?int $userId, ?string $sessionId, int $productId, int $quantity): ?CartItem
    {
        $cart = $this->getOrCreateCart($userId, $sessionId);
        $item = $cart->items()->where('product_id', $productId)->first();

        if (!$item) {
            return null;
        }

        $item->quantity = $quantity;
        $item->subtotal = $item->price * $quantity;
        $item->save();

        return $item;
    }

    public function clearCart(?int $userId, ?string $sessionId): bool
    {
        $cart = $this->getOrCreateCart($userId, $sessionId);
        return $cart->items()->delete() > 0;
    }

    public function getCartTotal(?int $userId, ?string $sessionId): float
    {
        $cart = $this->getOrCreateCart($userId, $sessionId);
        return (float) $cart->items()->sum('subtotal');
    }

    public function getCartItemCount(?int $userId, ?string $sessionId): int
    {
        $cart = $this->getOrCreateCart($userId, $sessionId);
        return (int) $cart->items()->sum('quantity');
    }

    public function syncGuestCartToUser(int $userId, string $sessionId): void
    {
        Log::info("Starting cart sync process", [
            'user_id' => $userId,
            'session_id' => $sessionId
        ]);

        $guestCart = Cart::with('items')->where('session_id', $sessionId)->first();
        $userCart = Cart::with('items')->where('user_id', $userId)->first();

        if (!$guestCart) {
            Log::info("No guest cart found to sync", ['session_id' => $sessionId]);
            return;
        }

        if (!$userCart) {
            Log::info("No existing user cart found, converting guest cart to user cart", [
                'cart_id' => $guestCart->id,
                'user_id' => $userId
            ]);
            
            $guestCart->user_id = $userId;
            $guestCart->session_id = null;
            $guestCart->save();
            return;
        }

        Log::info("Merging guest cart with user cart", [
            'guest_cart_id' => $guestCart->id,
            'user_cart_id' => $userCart->id
        ]);

        foreach ($guestCart->items as $guestItem) {
            $userItem = $userCart->items()->where('product_id', $guestItem->product_id)->first();
            
            if ($userItem) {
                $newQuantity = $userItem->quantity + $guestItem->quantity;
                Log::info("Merging existing item", [
                    'product_id' => $guestItem->product_id,
                    'old_quantity' => $userItem->quantity,
                    'added_quantity' => $guestItem->quantity,
                    'new_quantity' => $newQuantity
                ]);
                
                $userItem->quantity = $newQuantity;
                $userItem->subtotal = $userItem->price * $newQuantity;
                $userItem->save();
            } else {
                Log::info("Adding new item to user cart", [
                    'product_id' => $guestItem->product_id,
                    'quantity' => $guestItem->quantity
                ]);
                
                $userCart->items()->create([
                    'product_id' => $guestItem->product_id,
                    'price' => $guestItem->price,
                    'quantity' => $guestItem->quantity,
                    'subtotal' => $guestItem->subtotal,
                ]);
            }
        }

        Log::info("Deleting guest cart after successful sync", ['cart_id' => $guestCart->id]);
        $guestCart->delete();
    }
}
