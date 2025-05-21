<?php

declare(strict_types=1);

namespace App\Livewire\Frontend;

use Livewire\Attributes\Layout;
use Livewire\Component;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Lang;

class ContactusComponent extends Component
{
    public string $name = '';
    public string $email = '';
    public string $subject = '';
    public string $message = '';
    public bool $success = false;

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:2000'],
        ];
    }

    public function submit(): void
    {
        $this->validate();
        // TODO: Send email or store message
        $this->reset(['name', 'email', 'subject', 'message']);
        $this->success = true;
        // Optionally: dispatch browser event for notification
        $this->dispatch('notify', [
            [
                'type' => 'success',
                'message' => __('contact.success'),
                'sec' => 4000,
            ]
        ]);
    }

    #[Layout('layouts.frontend')]
    public function render()
    {
        return view('livewire.frontend.contactus-component');
    }
}
