<?php

namespace App\Livewire\Backend\Settings;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

class SettingsComponent extends Component
{
    #[Layout('layouts.backend')]
    #[Title('Settings')]
    public function render()
    {
        return view('livewire.backend.settings.settings-component');
    }
}
