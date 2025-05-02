<?php

namespace App\Livewire\Backend\Settings;

use Livewire\Attributes\Layout;
use Livewire\Component;

class SettingsComponent extends Component
{
    #[Layout('layouts.backend')]
    public function render()
    {
        return view('livewire.backend.settings.settings-component');
    }
}
