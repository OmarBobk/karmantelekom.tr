<?php

namespace App\Livewire\Frontend;

use Livewire\Attributes\Layout;
use Livewire\Component;

class ProfileComponent extends Component
{

    #[Layout('layouts.frontend')]
    public function render()
    {
        return view('livewire.frontend.profile-component');
    }
}
