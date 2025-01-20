<?php

namespace App\Livewire\Frontend;

use App\Models\Section;
use Livewire\Attributes\Layout;
use Livewire\Component;

class MainComponent extends Component
{
    public $activeCategory = 0;
    public $sections;

    protected $listeners = ['currencyChanged' => 'loadSections()'];

    public function mount()
    {
        $this->loadSections();
    }

    protected function loadSections()
    {
        $currency = session('currency', config('app.currency', '$'));
        
        $this->sections = Section::with(['products' => function($query) use ($currency) {
            $query->with([
                'images' => function($query) {
                    $query->where('is_primary', true);
                },
                'prices' => function($query) use ($currency) {
                    $query->where('currency', $currency);
                }
            ])
            ->where('status', 'active')
            ->orderBy('section_products.ordering');
        }])
        ->where('position', 'main.slider')
        ->where('is_active', true)
        ->where('scrollable', true)
        ->orderBy('order')
        ->get();
    }

    protected function loadContentSections()
    {
        $currency = session('currency', config('app.currency', '$'));
        
        $sections = Section::with(['products' => function($query) use ($currency) {
            $query->with([
                'images' => function($query) {
                    $query->orderBy('is_primary', 'desc');
                },
                'prices' => function($query) use ($currency) {
                    $query->where('currency', $currency);
                }
            ])
            ->where('status', 'active')
            ->orderBy('section_products.ordering');
        }])
        ->where('position', 'main.content')
        ->where('is_active', true)
        ->orderBy('order')
        ->get();

        return $sections;
    }

    public function getContentSectionsProperty()
    {
        return $this->loadContentSections();
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
