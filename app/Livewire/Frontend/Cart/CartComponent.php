<?php

declare(strict_types=1);

namespace App\Livewire\Frontend\Cart;

use App\Models\Cart;
use App\Models\CartItem;
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
        $this->cart = Cart::with(['items.product'])
            ->firstOrCreate([
                'user_id' => auth()->id(),
            ]);
    }

    /**
     * Increase the quantity of a cart item.
     */
    public function increase(int $itemId): void
    {
        $item = $this->cart->items()->findOrFail($itemId);


        $item->incrementQuantity();
        $this->dispatch('cart-updated');
    }

    /**
     * Decrease the quantity of a cart item.
     */
    public function decrease(int $itemId): void
    {
        $item = $this->cart->items()->findOrFail($itemId);
        if ( $item->quantity > 1) {
            $item->decrementQuantity();
            $this->dispatch('cart-updated');
        }
    }

    /**
     * Remove an item from the cart.
     */
    public function removeItem(int $itemId): void
    {
        $this->cart->items()->findOrFail($itemId)->delete();
        $this->dispatch('cart-updated');
    }

    /**
     * Clear all items from the cart.
     */
    public function clearCart(): void
    {
        $this->cart->clear();
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
        return $this->cart->subtotal;
    }

    /**
     * Get the number of items in the cart.
     */
    #[Computed]
    public function getItemsCountProperty(): int
    {
        return $this->cart->items_count;
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
