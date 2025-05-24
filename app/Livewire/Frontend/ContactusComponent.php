<?php

declare(strict_types=1);

namespace App\Livewire\Frontend;

use App\Facades\Settings;
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
    public string $contact_email = '';
    public string $phone_number_1 = '';
    public string $phone_number_2 = '';
    public string $phone_number_3 = '';
    public bool $success = false;

    public function mount(): void
    {
        $this->contact_email = Settings::get('contact_email', '');
        $this->phone_number_1 = Settings::get('phone_number_1', '');
        $this->phone_number_2 = Settings::get('phone_number_2', '');
        $this->phone_number_3 = Settings::get('phone_number_3', '');
    }

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
