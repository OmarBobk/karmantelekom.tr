<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\App;

class HeaderComponent extends Component
{
    public $currentLanguage = 'EN';
    public $currentCurrency = 'USD';

    public function mount()
    {
        $this->currentLanguage = session('locale', 'EN');
        $this->currentCurrency = session('currency', 'USD');
    }

    public function render()
    {
        return view('livewire.header-component');
    }

    public function changeLanguage($code)
    {
        $this->currentLanguage = $code;
        App::setLocale($code);
        session()->put('locale', $code);
        $this->dispatch('language-changed', ['code' => $code]);
    }

    public function changeCurrency($code)
    {
        $this->currentCurrency = $code;
        session()->put('currency', $code);
        $this->dispatch('currency-changed', ['code' => $code]);
    }
}
