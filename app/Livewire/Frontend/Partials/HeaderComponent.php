<?php

namespace App\Livewire\Frontend\Partials;

use Illuminate\Support\Facades\App;
use Livewire\Component;

class HeaderComponent extends Component
{
    public $cartCount = 1;
    public $currentLanguage = 'EN';
    public $currentCurrency;

    public function mount()
    {
        $this->currentLanguage = session('locale', 'EN');
        $this->currentCurrency = session('currency', config('app.currency', '$'));
    }

    public function render()
    {
        return view('livewire.frontend.partials.header-component');
    }

    public function addToCart()
    {
        $this->cartCount++;
        $this->dispatch('notify', [
            'message' => 'Item added to cart',
            'type' => 'success'
        ]);
    }

    public function removeFromCart()
    {
        if ($this->cartCount > 0) {
            $this->cartCount--;
        }
    }

    public function changeLanguage($code)
    {
        $this->currentLanguage = $code;
        App::setLocale($code);
        session()->put('locale', $code);
        $this->dispatch('language-changed', ['code' => $code]);
    }

    public function switchCurrency($currency)
    {
        // Dispatch event before starting the update
        $this->dispatch('currency-switching');
        
        session(['currency' => $currency]);
        $this->currentCurrency = $currency;
        $this->dispatch('currencyChanged');
    }
}
