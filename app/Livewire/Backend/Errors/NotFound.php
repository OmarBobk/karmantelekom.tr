<?php

namespace App\Livewire\Backend\Errors;

use Livewire\Attributes\Layout;
use Livewire\Component;

class NotFound extends Component
{

    #[Layout('layouts.backend')]
    public function render()
    {
        return view('livewire.backend.errors.not-found');
    }
}
