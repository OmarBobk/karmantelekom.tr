<?php

namespace App\Livewire\Backend\Products;

use Livewire\Attributes\Layout;
use Livewire\Component;

class SectionComponent extends Component
{
    #[Layout('layouts.backend')]
    public function render()
    {
        return view('livewire.backend.products.section-component');
    }
}
