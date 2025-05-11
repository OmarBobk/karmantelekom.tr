<?php

namespace App\Livewire\Frontend\Partials;

use Illuminate\Support\Facades\App;
use Livewire\Component;

class HeaderComponent extends Component
{
    public $cartCount = 1;
    public $currentLanguage = 'EN';

    public function mount()
    {
        $this->currentLanguage = session('locale', 'EN');
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

    public function removeFromCart(): void
    {
        if ($this->cartCount > 0) {
            $this->cartCount--;
        }
    }

    public function changeLanguage($code): void
    {
        $this->currentLanguage = $code;
        App::setLocale(strtolower($code));
        session()->put('locale', $code);
        $this->dispatch('language-changed', ['code' => $code]);
    }
}
