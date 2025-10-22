<?php

declare(strict_types=1);

namespace App\Livewire\Backend\Settings;

use App\Facades\Settings;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Attributes\Rule;

class SettingsComponent extends Component
{
    #[Layout('layouts.backend')]
    #[Title('Settings')]

    public array $settings = [];
    public string $activeTab = 'general';

    public function mount(): void
    {
        $this->loadSettings();
    }

    public function loadSettings(): void
    {
        $this->settings = [
            'general' => [
                'site_name' => Settings::get('site_name', ''),
                'phone_number_1' => Settings::get('phone_number_1', ''),
                'phone_number_2' => Settings::get('phone_number_2', ''),
                'phone_number_3' => Settings::get('phone_number_3', ''),
                'contact_email' => Settings::get('contact_email', ''),
                'product_prices' => Settings::get('product_prices', ''),
            ],
            'social' => [
                'facebook_url' => Settings::get('facebook_url', ''),
                'tiktok_url' => Settings::get('tiktok_url', ''),
                'instagram_url' => Settings::get('instagram_url', ''),
                'whatsapp_number' => Settings::get('whatsapp_number', ''),
            ]
        ];
    }

    public function saveSettings(): void
    {

        foreach ($this->settings[$this->activeTab] as $key => $value) {

            if($key === 'product_prices') {
                $value = (is_bool($value))
                    ? ($value ? 'enabled' : 'disabled')
                    : $value;
            }

            Settings::set(
                key: $key,
                value: $value,
                type: 'string',
                group: $this->activeTab,
                description: null,
                isPublic: false
            );
        }

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Settings updated successfully!'
        ]);
    }

    public function render()
    {
        return view('livewire.backend.settings.settings-component');
    }
}
