<?php

declare(strict_types=1);

namespace App\Livewire\Cart;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Services\CartService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class CartComponent extends Component
{
    public bool $showCart = false;
    private CartService $cartService;

    public function boot(CartService $cartService): void
    {
        $this->cartService = $cartService;
    }

    #[Computed]
    public function cartItems(): Collection
    {
        return $this->cartService->getCartItems(auth()->user());
    }

    /**
     * Refresh cart items and ensure frontend is synced with backend
     */
    #[On('refresh-cart')]
    public function refreshCart(): void
    {
        // First explicitly clean up any invalid items
        $this->cartService->cleanupInvalidItems(auth()->user());

        // Then get the updated items
        $items = $this->cartItems;

        // Dispatch event to update client-side cart
        $this->dispatch('cart-updated', [
            'items' => $items
        ]);
    }

    #[On('getCartItems')]
    public function sendCartItems(): void
    {
        $items = $this->cartItems;
        $this->dispatch('cart-items-loaded', $items);
    }

    #[Computed]
    public function cartTotal(): float
    {
        return $this->cartItems->sum(fn (CartItem $item) => $item->total);
    }

    #[Computed]
    public function cartCount(): int
    {
        return $this->cartItems->sum('quantity');
    }

    #[On('sync-cart')]
    public function handleCartSync(array $items): void
    {
        try {
            if (!is_array($items)) {
                throw new \InvalidArgumentException('Invalid cart data format');
            }

            // Clear existing cart
            $this->cartService->clearCart(auth()->user());
            // Add each item from client-side cart
            foreach ($items as $item) {
                if (!isset($item['product_id']) || !isset($item['quantity'])) {
                    continue;
                }

                $product = Product::with(['prices', 'images'])->findOrFail($item['product_id']);
                $this->cartService->addItem($product, $item['quantity'], auth()->user());
            }

            // After syncing, fetch updated items and notify client
            $updatedItems = $this->cartItems;
            $this->dispatch('cart-updated', [
                'items' => $updatedItems
            ]);

            $this->dispatch('cart-synced', [
                'message' => 'Cart synchronized successfully',
                'type' => 'success'
            ]);
        } catch (\Exception $e) {
            logger()->error('Error syncing cart: ' . $e->getMessage());
            $this->dispatch('notify', [
                'message' => 'Error syncing cart: ' . $e->getMessage(),
                'type' => 'error'
            ]);
        }
    }

    #[On('add-to-cart')]
    public function handleAddToCart($data): void
    {
        try {
            $product = Product::with(['prices', 'images'])->findOrFail($data['product']);
            $this->addToCart($product, $data['quantity']);
        } catch (\Exception $e) {
            logger()->error('Error adding product to cart: ' . $e->getMessage());
            $this->dispatch('notify', [
                'message' => 'Error adding product to cart: ' . $e->getMessage(),
                'type' => 'error',
            ]);
        }
    }

    public function addToCart(Product $product, int $quantity = 1): void
    {
        try {
            $this->cartService->addItem($product, $quantity, auth()->user());

            // Dispatch event to update client-side cart
            $this->dispatch('cart-updated', [
                'items' => $this->cartItems
            ]);

            $this->dispatch('notify', [
                'message' => 'Product added to cart successfully!',
                'type' => 'success',
            ]);
        } catch (\Exception $e) {
            logger()->error('Error adding product to cart: ' . $e->getMessage());
            $this->dispatch('notify', [
                'message' => 'Error adding product to cart: ' . $e->getMessage(),
                'type' => 'error',
            ]);
        }
    }

    public function updateQuantity(CartItem $cartItem, int $quantity): void
    {
        try {
            $this->cartService->updateQuantity($cartItem, $quantity);

            // Dispatch event to update client-side cart
            $this->dispatch('cart-updated', [
                'items' => $this->cartItems
            ]);

            $this->dispatch('notify', [
                'message' => 'Cart updated successfully!',
                'type' => 'success',
            ]);
        } catch (\Exception $e) {
            logger()->error('Error updating cart quantity: ' . $e->getMessage());
            $this->dispatch('notify', [
                'message' => 'Error updating cart quantity: ' . $e->getMessage(),
                'type' => 'error',
            ]);
        }
    }

    public function removeFromCart(CartItem $cartItem): void
    {
        try {
            $this->cartService->removeItem($cartItem);

            // Dispatch event to update client-side cart
            $this->dispatch('cart-updated', [
                'items' => $this->cartItems
            ]);

            $this->dispatch('notify', [
                'message' => 'Product removed from cart successfully!',
                'type' => 'success',
            ]);
        } catch (\Exception $e) {
            logger()->error('Error removing product from cart: ' . $e->getMessage());
            $this->dispatch('notify', [
                'message' => 'Error removing product from cart: ' . $e->getMessage(),
                'type' => 'error',
            ]);
        }
    }

    public function clearCart(): void
    {
        try {
            $this->cartService->clearCart(auth()->user());

            // Dispatch event to update client-side cart
            $this->dispatch('cart-updated', [
                'items' => []
            ]);

            $this->dispatch('notify', [
                'message' => 'Cart cleared successfully!',
                'type' => 'success',
            ]);
        } catch (\Exception $e) {
            logger()->error('Error clearing cart: ' . $e->getMessage());
            $this->dispatch('notify', [
                'message' => 'Error clearing cart: ' . $e->getMessage(),
                'type' => 'error',
            ]);
        }
    }

    public function toggleCart(): void
    {
        $this->showCart = !$this->showCart;
        if ($this->showCart) {
            $this->dispatch('lock-scroll');
        } else {
            $this->dispatch('unlock-scroll');
        }
    }

    #[Layout('layouts.frontend')]
    public function render()
    {
        return view('livewire.cart.cart-component');
    }
}
