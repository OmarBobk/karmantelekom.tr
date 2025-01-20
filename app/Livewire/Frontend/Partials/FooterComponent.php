<?php

namespace App\Livewire\Frontend\Partials;

use Livewire\Component;

class FooterComponent extends Component
{
    public $socialLinks = [
        ['name' => 'Facebook', 'url' => '#', 'icon' => 'facebook'],
        ['name' => 'Twitter', 'url' => '#', 'icon' => 'twitter'],
        ['name' => 'Instagram', 'url' => '#', 'icon' => 'instagram'],
        ['name' => 'LinkedIn', 'url' => '#', 'icon' => 'linkedin'],
        ['name' => 'YouTube', 'url' => '#', 'icon' => 'youtube'],
    ];

    public $companyLinks = [
        ['name' => 'About Us', 'url' => '#'],
        ['name' => 'Contact', 'url' => '#'],
        ['name' => 'Jobs', 'url' => '#'],
        ['name' => 'Press kit', 'url' => '#'],
        ['name' => 'Blog', 'url' => '#'],
    ];

    public $legalLinks = [
        ['name' => 'Terms of use', 'url' => '#'],
        ['name' => 'Privacy policy', 'url' => '#'],
        ['name' => 'Cookie policy', 'url' => '#'],
        ['name' => 'License', 'url' => '#'],
    ];

    public $supportLinks = [
        ['name' => 'Help Center', 'url' => '#'],
        ['name' => 'FAQs', 'url' => '#'],
        ['name' => 'Community', 'url' => '#'],
        ['name' => 'Developer API', 'url' => '#'],
        ['name' => 'Documentation', 'url' => '#'],
    ];

    public $currentCurrency;

    public function mount()
    {
        $this->currentCurrency = session('currency', config('app.currency', '$'));
    }

    public function switchCurrency($currency)
    {
        session(['currency' => $currency]);
        $this->currentCurrency = $currency;
        $this->dispatch('currencyChanged');
    }

    public function render()
    {
        return view('livewire.frontend.partials.footer-component');
    }
}
