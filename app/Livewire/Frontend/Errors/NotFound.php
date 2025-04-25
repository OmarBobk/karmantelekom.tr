<?php

namespace App\Livewire\Frontend\Errors;

use Livewire\Attributes\Layout;
use Livewire\Component;

class NotFound extends Component
{

    #[Layout('layouts.frontend')]
    public function render()
    {
        return view('livewire.frontend.errors.not-found');
    }
}
