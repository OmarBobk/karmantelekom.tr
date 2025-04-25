<?php

namespace App\Livewire\Backend\Categories;

use Livewire\Attributes\Layout;
use Livewire\Component;

class CategoryComponent extends Component
{

    #[Layout('layouts.backend')]
    public function render()
    {
        return view('livewire.backend.categories.category-component');
    }
}
