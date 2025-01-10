<?php

namespace App\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Component;
use App\Models\Product;

class ProductsComponent extends Component
{
    #[Layout('layouts.frontend')]
    public function render()
    {
        $products = Product::with(['category', 'images', 'prices'])
            ->latest()
            ->paginate(12);

        return view('livewire.products-component', [
            'products' => $products
        ]);
    }
} 