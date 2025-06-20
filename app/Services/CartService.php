<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Cart;
use App\Models\Product;
use App\Models\CartItem;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class CartService
{
    public function getOrCreateCart(?int $userId = null, ?string $sessionId = null): Cart
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

    public function removeFromCart(?int $userId, ?string $sessionId, int $productId): bool
    {
        $cart = $this->getOrCreateCart($userId, null);
        return $cart->items()->where('product_id', $productId)->delete() > 0;
    }

    public function clearCart(?int $userId, ?string $sessionId): bool
    {
        $cart = $this->getOrCreateCart($userId, null);
        return $cart->items()->delete() > 0;
    }

    public function getCartTotal(?int $userId, ?string $sessionId): float
    {
        $cart = $this->getOrCreateCart($userId, $sessionId);
        return (float)$cart->items()->sum('subtotal');
    }

    public function getCartItemCount(?int $userId, ?string $sessionId): int
    {
        $cart = $this->getOrCreateCart($userId, $sessionId);
        return (int)$cart->items()->sum('quantity');
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

    /**
     * @throws Throwable
     */
    public function syncCart(int $user_id, array $items)
    {
        // Validate that user_id is provided and not null
        if (!$user_id) {
            Log::warning('Attempted to sync cart without user_id');
            throw new InvalidArgumentException('User ID is required for cart syncing');
        }

        try {
            Log::info('Start Syncing', ['user_id' => $user_id, 'items_count' => count($items)]);
            $cart = $this->getOrCreateCart($user_id, null);

            // if the $items is empty,
            // and the cart has items,
            // we need to sync in the opposite direction from db to localStorage.
            if (empty($items) && $cart->items()->exists()) {
                Log::info('No items provided, clearing existing cart items', ['cart_id' => $cart->id]);
                return $cart->items->map(function ($item) {
                    return [
                        'product_id' => $item->product_id,
                        'quantity' => $item->quantity,
                        'price' => $item->price,
                        'subtotal' => $item->subtotal,
                        'name' => $item->product->name ?? '',
                        'description' => $item->product->description ?? '',
                        'image' => $item->product->primaryImageUrl ?? '',
                    ];
                })->toArray();
            }

            DB::transaction(function () use ($cart, $items) {

                $cart->items()->delete(); // Clear existing items before syncing

                Log::info('Start Transaction');

                foreach ($items as $item) {
                    $productId = $item['product_id'];
                    $quantity = $item['quantity'];

                    // Use a database query instead of a collection to avoid race conditions
                    $existingItem = $cart->items()->where('product_id', $productId)->first();

                    if ($existingItem) {
                        Log::info('Updating existing cart item', [
                            'product_id' => $productId,
                            'old_quantity' => $existingItem->quantity,
                            'new_quantity' => $quantity
                        ]);

                        $newQuantity = $quantity;
                        $existingItem->update([
                            'quantity' => $newQuantity,
                            'subtotal' => $existingItem->price * $newQuantity,
                        ]);
                    } else {
                        Log::info('Creating new cart item', [
                            'product_id' => $productId,
                            'quantity' => $quantity
                        ]);

                        // Get product price safely
                        $product = Product::with('prices')->find($productId);
                        if (!$product || $product->prices->isEmpty()) {
                            Log::warning('Product not found or has no prices', ['product_id' => $productId]);
                            continue;
                        }

                        $price = $product->prices->first()->base_price ?? 0;
                        $subtotal = $price * $quantity;

                        try {
                            $cart->items()->create([
                                'product_id' => $productId,
                                'price' => $price,
                                'quantity' => $quantity,
                                'subtotal' => $subtotal,
                            ]);
                        } catch (QueryException $e) {
                            // Handle another process might have created duplicate entry - item
                            if ($e->getCode() == 23000 && str_contains($e->getMessage(), 'Duplicate entry')) {
                                Log::info('Duplicate entry detected, updating existing item', ['product_id' => $productId]);

                                // Try to update the existing item
                                $existingItem = $cart->items()->where('product_id', $productId)->first();
                                if ($existingItem) {
                                    $newQuantity = $existingItem->quantity + $quantity;
                                    $existingItem->update([
                                        'quantity' => $newQuantity,
                                        'subtotal' => $existingItem->price * $newQuantity,
                                    ]);
                                }
                            } else {
                                throw $e; // Re-throw if it's not a duplicate entry error
                            }
                        }
                    }
                }
                Log::info('End Transaction');
            });

        } catch (Throwable $th) {
            Log::error('Error syncing cart', [
                'user_id' => $user_id,
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString()
            ]);

            // Re-throw the exception so the calling code can handle it
            throw $th;
        }
    }

    /**
     * Alternative sync method using updateOrCreate for better race condition handling
     * @throws Throwable
     */
    public function syncCartOptimized(int $user_id, array $items): void
    {
        // Validate that user_id is provided and not null
        if (!$user_id) {
            Log::warning('Attempted to sync cart without user_id');
            throw new InvalidArgumentException('User ID is required for cart syncing');
        }

        try {
            Log::info('Start Optimized Syncing', ['user_id' => $user_id, 'items_count' => count($items)]);
            $cart = $this->getOrCreateCart($user_id, null);

            DB::transaction(function () use ($cart, $items) {
                Log::info('Start Optimized Transaction');

                foreach ($items as $item) {
                    $productId = $item['product_id'];
                    $quantity = $item['quantity'];

                    // Get product price safely
                    $product = Product::with('prices')->find($productId);
                    if (!$product || $product->prices->isEmpty()) {
                        Log::warning('Product not found or has no prices', ['product_id' => $productId]);
                        continue;
                    }

                    $price = $product->prices->first()->base_price ?? 0;

                    // Use updateOrCreate to handle race conditions elegantly
                    $cart->items()->updateOrCreate(
                        ['product_id' => $productId], // Search criteria
                        [
                            'price' => $price,
                            'quantity' => DB::raw("quantity + $quantity"), // Add to existing quantity
                            'subtotal' => DB::raw("price * (quantity + $quantity)"), // Recalculate subtotal
                        ]
                    );

                    Log::info('Cart item synced', [
                        'product_id' => $productId,
                        'quantity' => $quantity
                    ]);
                }
                Log::info('End Optimized Transaction');
            });

        } catch (Throwable $th) {
            Log::error('Error in optimized cart sync', [
                'user_id' => $user_id,
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString()
            ]);

            throw $th;
        }
    }
}
