<?php

namespace App\Livewire\Frontend\Partials;

use Livewire\Component;
use Illuminate\Support\Facades\Cache;

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

        // Clear ALL relevant caches before dispatching currency change
        Cache::tags(['currency_prices'])->flush();

        // Clear specific cache keys
        $cacheKeys = [
            "main_sections_{$currency}_wholesale",
            "main_sections_{$currency}_retail",
            "content_sections_{$currency}_wholesale",
            "content_sections_{$currency}_retail",
            "currency_{$currency}",
            "default_currency",
        ];

        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }

        $this->dispatch('currencyChanged');
    }

    public function render()
    {
        return view('livewire.frontend.partials.footer-component');
    }
}
