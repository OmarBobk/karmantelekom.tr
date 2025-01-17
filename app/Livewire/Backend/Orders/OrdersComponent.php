<?php

namespace App\Livewire\Backend\Orders;

use Livewire\Attributes\Layout;
use Livewire\Component;

class OrdersComponent extends Component
{

    #[Layout('layouts.backend')]
    public function render()
    {
        return view('livewire.backend.orders.orders-component');
    }
}
