<?php

declare(strict_types=1);

namespace App\Livewire\Frontend\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Facades\Cart as CartFacade;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Attributes\Computed;

/**
 * @property-read Cart $cart
 * @property-read Collection $items
 * @property-read float $total
 */
class CartComponent extends Component
{
    public Cart $cart;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->loadCart();
    }

    /**
     * Load the user's cart.
     */
    protected function loadCart(): void
    {
        $userId = auth()->id();
        $sessionId = session()->getId();
        $this->cart = CartFacade::getOrCreateCart($userId, $sessionId);
    }

    /**
     * Increase the quantity of a cart item.
     */
    public function increase(int $itemId): void
    {
        $userId = auth()->id();
        $sessionId = session()->getId();
        $item = $this->cart->items()->findOrFail($itemId);
        CartFacade::updateQuantity(
            $userId,
            $sessionId,
            $item->product_id,
            $item->quantity + 1
        );
        $this->dispatch('cart-updated');
    }

    /**
     * Decrease the quantity of a cart item.
     */
    public function decrease(int $itemId): void
    {
        $userId = auth()->id();
        $sessionId = session()->getId();
        $item = $this->cart->items()->findOrFail($itemId);
        if ($item->quantity > 1) {
            CartFacade::updateQuantity(
                $userId,
                $sessionId,
                $item->product_id,
                $item->quantity - 1
            );
            $this->dispatch('cart-updated');
        }
    }

    /**
     * Remove an item from the cart.
     */
    public function removeItem(int $itemId): void
    {
        $userId = auth()->id();
        $sessionId = session()->getId();
        $item = $this->cart->items()->findOrFail($itemId);
        CartFacade::removeFromCart($userId, $sessionId, $item->product_id);
        $this->dispatch('cart-updated');
    }

    /**
     * Clear all items from the cart.
     */
    public function clearCart(): void
    {
        $userId = auth()->id();
        $sessionId = session()->getId();
        CartFacade::clearCart($userId, $sessionId);
        $this->dispatch('cart-updated');
    }

    /**
     * Get the cart items.
     */
    #[Computed]
    public function getItemsProperty(): Collection
    {
        return $this->cart->items;
    }

    /**
     * Get the cart subtotal.
     */
    #[Computed]
    public function getSubtotalProperty(): float
    {
        $userId = auth()->id();
        $sessionId = session()->getId();
        return CartFacade::getCartTotal($userId, $sessionId);
    }

    /**
     * Get the number of items in the cart.
     */
    #[Computed]
    public function getItemsCountProperty(): int
    {
        $userId = auth()->id();
        $sessionId = session()->getId();
        return CartFacade::getCartItemCount($userId, $sessionId);
    }

    /**
     * Listen for cart update events.
     */
    #[On('cart-updated')]
    public function refreshCart(): void
    {
        $this->loadCart();
    }

    /**
     * Render the component.
     */
    #[Layout('layouts.frontend')]
    public function render(): View
    {
        return view('livewire.frontend.cart.cart-component', [
            'items' => $this->items,
            'subtotal' => $this->subtotal,
            'itemsCount' => $this->items_count,
        ]);
    }
}
