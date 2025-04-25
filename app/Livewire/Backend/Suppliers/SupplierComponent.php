<?php

namespace App\Livewire\Backend\Suppliers;

use Livewire\Attributes\Layout;
use Livewire\Component;

class SupplierComponent extends Component
{
    #[Layout('layouts.backend')]
    public function render()
    {
        return view('livewire.backend.suppliers.supplier-component');
    }
}
