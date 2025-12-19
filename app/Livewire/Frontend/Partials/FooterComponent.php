<?php

namespace App\Livewire\Frontend\Partials;

use App\Models\Setting;
use App\Services\LanguageService;
use Livewire\Component;

class FooterComponent extends Component
{
    public $socialLinks = [];

    public $companyLinks = [];

    public $legalLinks = [];

    private LanguageService $languageService;

    public $supportLinks = [
        ['name' => 'Help Center', 'url' => '#'],
        ['name' => 'FAQs', 'url' => '#'],
        ['name' => 'Community', 'url' => '#'],
    ];

    public $currentCurrency;
    public bool $canSwitchCurrency;

    public array $settings = [];

    public function boot(LanguageService $languageService): void
    {
        $this->languageService = $languageService;
    }

    public function mount()
    {
        $this->settings = Setting::whereGroup('social')
            ->get()
            ->pluck('value', 'key')
            ->toArray();

        $this->currentCurrency = session('currency', config('app.currency', 'TRY'));
        $this->canSwitchCurrency = auth()->check() && auth()->user()->hasAnyRole(['admin', 'salesperson', 'shop_owner']);

        $currentLang = $this->languageService->getCurrentLanguage();

        $this->companyLinks = [
            ['name' => 'About Us', 'url' => '#'],
            ['name' => 'Contact', 'url' => route('contactus')],
            ['name' => 'Blog', 'url' => '#'],
        ];

        // Use translation keys here; the Blade view will call __('main.' . $link['name'])
        $this->legalLinks = [
            ['name' => 'privacy_policy', 'url' => route('privacy-policy', ['locale' => $currentLang])],
            ['name' => 'distance_sales_contract', 'url' => route('distance-sales-contract', ['locale' => $currentLang])],
            ['name' => 'delivery_and_return', 'url' => route('delivery-and-return', ['locale' => $currentLang])],
            ['name' => 'cookie_policy', 'url' => '#'],
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
