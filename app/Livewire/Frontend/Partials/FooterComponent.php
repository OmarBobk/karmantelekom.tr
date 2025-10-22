<?php

namespace App\Livewire\Frontend\Partials;

use App\Models\Setting;
use Livewire\Component;

class FooterComponent extends Component
{
    public $socialLinks = [];

    public $companyLinks = [];

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

    public array $settings = [];

    public function mount()
    {
        $this->settings = Setting::whereGroup('social')
            ->get()
            ->pluck('value', 'key')
            ->toArray();

        $this->currentCurrency = session('currency', config('app.currency', 'TRY'));
        $this->canSwitchCurrency = auth()->check() && auth()->user()->hasAnyRole(['admin', 'salesperson', 'shop_owner']);

        $this->companyLinks = [
            ['name' => 'About Us', 'url' => '#'],
            ['name' => 'Contact', 'url' => route('contactus')],
            ['name' => 'Blog', 'url' => '#'],
        ];

        $this->socialLinks = [
            ['name' => 'Instagram', 'url' => $this->settings['instagram_url'], 'icon' => 'instagram'],
            ['name' => 'Facebook', 'url' => $this->settings['facebook_url'] , 'icon' => 'facebook'],
            ['name' => 'Tiktok', 'url' => $this->settings['tiktok_url'] , 'icon' => 'tiktok'],
            ['name' => 'WhatsApp', 'url' => 'https://wa.me/' . $this->settings['whatsapp_number'], 'icon' => 'whatsapp'],
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
