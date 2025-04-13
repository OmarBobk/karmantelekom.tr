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


    #[On('sync-cart')]
    public function handleCartSync(array $items): void
    {
        dd($items);
    }

    #[Layout('layouts.frontend')]
    public function render()
    {
        return view('livewire.cart.cart-component');
    }
}
