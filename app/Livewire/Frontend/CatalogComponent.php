<?php

namespace App\Livewire\Frontend;

use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

class CatalogComponent extends Component
{

    #[Layout('layouts.frontend')]
    public function render(): Factory|View
    {
        return view('livewire.frontend.catalog-component');
    }
}
