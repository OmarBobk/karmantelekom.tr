<?php

declare(strict_types=1);

namespace App\Livewire\Cart;

use App\Models\CartItem;
use App\Models\Product;
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

            $this->dispatch('cart-updated');

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
            $this->dispatch('cart-updated');
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
            $this->dispatch('cart-updated');
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
            $this->dispatch('cart-updated');
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
