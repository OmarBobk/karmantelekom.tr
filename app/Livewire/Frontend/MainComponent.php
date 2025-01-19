<?php

namespace App\Livewire\Frontend;

use App\Models\Section;
use Livewire\Attributes\Layout;
use Livewire\Component;

class MainComponent extends Component
{
    public $activeCategory = 0;
    public $sections;

    public function mount()
    {
        $this->sections = Section::with(['products' => function($query) {
            $query->with(['images' => function($query) {
                $query->where('is_primary', true);
            }])->orderBy('section_products.ordering');
        }])
        ->where('position', 'main.slider')
        ->where('is_active', true)
        ->where('scrollable', true)
        ->orderBy('order')
        ->get();
    }

    // Slider Component
    public $scrollPosition = 0;
    public $isScrolledLeft = true;
    public $isScrolledRight = false;

    #[Layout('layouts.frontend')]
    public function render()
    {
        return view('livewire.frontend.main-component');
    }

    public function setActiveCategory($index)
    {
        $this->activeCategory = $index;
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
        usleep(800000);

        $this->dispatch('cart-updated', [
            'message' => 'Product added to cart successfully!',
            'type' => 'success'
        ]);
    }
}
