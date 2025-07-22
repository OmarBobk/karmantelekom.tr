<?php

namespace App\Livewire\Backend;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

class ActivitiesComponent extends Component
{


    #[Layout('layouts.backend')]
    #[Title('Activities')]
    public function render()
    {
        return view('livewire.backend.activities-component');
    }
}
