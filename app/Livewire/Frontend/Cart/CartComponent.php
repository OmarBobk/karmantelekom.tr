<?php

declare(strict_types=1);

namespace App\Livewire\Frontend\Cart;

use App\Facades\Cart;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

class CartComponent extends Component
{
    public bool $showCart = false;
    public Collection $cartItems;

    #[On('sync-cart')]
    public function handleCartSync(array $items): void
    {
        $user = auth()->check() ? auth()->user() : null;

        // the combined cart.
        $items = Cart::syncCartToDatabase(
            user: $user,
            items: $items,
        );

        $this->cartItems  = $items;

        $this->dispatch('cart-synced', $this->cartItems->toJson());

    }

    #[Layout('layouts.frontend')]
    public function render()
    {

        return view('livewire.frontend.cart.cart-component');
    }
}
