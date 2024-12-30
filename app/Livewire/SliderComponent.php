<?php

namespace App\Livewire;

use Livewire\Component;

class SliderComponent extends Component
{
    public $activeCategory = 0;
    public $scrollPosition = 0;
    public $isScrolledLeft = true;
    public $isScrolledRight = false;

    public function setActiveCategory($index)
    {
        $this->activeCategory = $index;
        $this->scrollPosition = 0;
        $this->resetScroll();
    }

    public function scrollLeft()
    {
        $this->dispatch('scroll-left');
    }

    public function scrollRight()
    {
        $this->dispatch('scroll-right');
    }

    public function resetScroll()
    {
        $this->dispatch('reset-scroll');
    }

    public function render()
    {
        return view('livewire.slider-component');
    }
}
