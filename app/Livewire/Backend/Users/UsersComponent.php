<?php

namespace App\Livewire\Backend\Users;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

class UsersComponent extends Component
{
    #[Layout('layouts.backend')]
    #[Title('Users Management')]
    public function render()
    {
        return view('livewire.backend.users.users-component');
    }
}
