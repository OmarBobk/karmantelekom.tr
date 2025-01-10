<?php

namespace App\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Component;

class ProfileComponent extends Component
{

    #[Layout('layouts.frontend')]
    public function render()
    {
        return view('livewire.profile-component');
    }
}
