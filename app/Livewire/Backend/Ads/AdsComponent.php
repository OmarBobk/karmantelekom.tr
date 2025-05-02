<?php

namespace App\Livewire\Backend\Ads;

use Livewire\Attributes\Layout;
use Livewire\Component;
use Spatie\Analytics\Facades\Analytics;
use Spatie\Analytics\Period;

class AdsComponent extends Component
{
    #[Layout('layouts.backend')]
    public function render()
    {

        Analytics::fetchVisitorsAndPageViews(Period::days(7));
        return view('livewire.backend.ads.ads-component');
    }
}
