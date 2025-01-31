<?php

namespace App\Livewire\Errors;

use Livewire\Attributes\Layout;
use Livewire\Component;

class NotFound extends Component
{

    #[Layout('layouts.frontend')]
    public function render()
    {
        return view('livewire.errors.not-found');
    }
}
