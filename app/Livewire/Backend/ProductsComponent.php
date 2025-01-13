<?php

namespace App\Livewire\Backend;

use Livewire\Attributes\Layout;
use Livewire\Component;

class ProductsComponent extends Component
{

    #[Layout('layouts.backend')]
    public function render()
    {
        return view('livewire.backend.products-component');
    }
}
