<?php

namespace App\Livewire\Backend\Users;

use Livewire\Attributes\Layout;
use Livewire\Component;

class UsersComponent extends Component
{
    #[Layout('layouts.backend')]
    public function render()
    {
        return view('livewire.backend.users.users-component');
    }
}
