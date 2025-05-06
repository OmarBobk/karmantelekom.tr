<?php

namespace App\Livewire\Backend\Ads;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Analytics\Facades\Analytics;
use Spatie\Analytics\Period;

class AdsComponent extends Component
{
    #[Layout('layouts.backend')]
    #[Title('Ads Manager')]
    public function render()
    {
        return view('livewire.backend.ads.ads-component');
    }
}
