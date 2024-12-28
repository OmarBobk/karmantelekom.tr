<?php

namespace App\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Component;

class MainComponent extends Component
{
    #[Layout('layouts.frontend')]
    public function render()
    {
        return view('livewire.main-component');
    }
}
