<?php

namespace App\Livewire\Frontend;

use Livewire\Attributes\Layout;
use Livewire\Component;

class MainComponent extends Component
{

    // Slider Component
    public $activeCategory = 0;
    public $scrollPosition = 0;
    public $isScrolledLeft = true;
    public $isScrolledRight = false;


    #[Layout('layouts.frontend')]
    public function render()
    {
        return view('livewire.frontend.main-component');
    }

    // Slider Component
    public function setActiveCategory($index)
    {
        // Simulate loading delay
        usleep(500000); // 0.5 seconds delay

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

    public function addToCart($productId, $quantity)
    {
        // Simulate network delay
        usleep(800000); // 0.8 seconds delay

        // Add your cart logic here
        $this->dispatch('cart-updated', [
            'message' => 'Product added to cart successfully!',
            'type' => 'success'
        ]);
    }
}
