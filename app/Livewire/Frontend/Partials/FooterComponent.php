<?php

namespace App\Livewire\Frontend\Partials;

use App\Facades\Settings;
use Livewire\Component;

class FooterComponent extends Component
{
    public $socialLinks = [];

    public $companyLinks = [
        ['name' => 'About Us', 'url' => '#'],
        ['name' => 'Contact', 'url' => '#'],
        ['name' => 'Blog', 'url' => '#'],
    ];

    public $legalLinks = [
        ['name' => 'Terms of use', 'url' => '#'],
        ['name' => 'Privacy policy', 'url' => '#'],
        ['name' => 'Cookie policy', 'url' => '#'],
    ];

    public $supportLinks = [
        ['name' => 'Help Center', 'url' => '#'],
        ['name' => 'FAQs', 'url' => '#'],
        ['name' => 'Community', 'url' => '#'],
    ];

    public $currentCurrency;
    public bool $canSwitchCurrency;

    public function mount()
    {
        $this->currentCurrency = session('currency', config('app.currency', 'TRY'));
        $this->canSwitchCurrency = auth()->check() && auth()->user()->hasAnyRole(['admin', 'salesperson', 'shop_owner']);

        $this->socialLinks = [
            ['name' => 'Facebook', 'url' => Settings::get('facebook_url') , 'icon' => 'facebook'],
            ['name' => 'Instagram', 'url' => Settings::get('instagram_url'), 'icon' => 'instagram'],
            ['name' => 'WhatsApp', 'url' => 'https://wa.me/' . Settings::get('whatsapp_number'), 'icon' => 'whatsapp'],
        ];

    }

    public function switchCurrency($currency)
    {
        if (!$this->canSwitchCurrency) {
            return;
        }

        // Dispatch event before starting the update
        $this->dispatch('currency-switching');

        session(['currency' => $currency]);
        $this->currentCurrency = $currency;

        $this->dispatch('currencyChanged');
    }

    public function render()
    {
        return view('livewire.frontend.partials.footer-component');
    }
}
