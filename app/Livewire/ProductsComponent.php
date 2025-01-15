<?php

namespace App\Livewire;

use App\Models\Product;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

class ProductsComponent extends Component
{
    use WithPagination;

    #[Layout('layouts.frontend')]
    public function render()
    {
        $products = Product::with(['category', 'images', 'prices'])
            ->where('status', 'active')
            ->latest()
            ->paginate(12);

        return view('livewire.products-component', [
            'products' => $products
        ]);
    }

    public function addToCart(Product $product)
    {
        // Cart logic implementation
        $this->dispatch('cart-updated', [
            'message' => 'Product added to cart successfully!',
            'type' => 'success'
        ]);
    }
}