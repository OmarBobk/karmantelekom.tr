<?php

namespace App\Livewire\Frontend;

use App\Models\Section;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
use function Laravel\Prompts\alert;

class MainComponent extends Component
{
    public $activeCategory = 0;
    public $sections;
    public $contentSections;

    public function mount()
    {
        $this->loadSections();
        $this->loadContentSections();
    }

    #[On('currencyChanged')]
    public function loadSections()
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

    #[On('currencyChanged')]
    public function loadContentSections()
    {
        $currency = session('currency', config('app.currency', '$'));
        
        $this->contentSections = Section::with(['products' => function($query) use ($currency) {
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

        // Dispatch an event to reinitialize Swiper after the content is updated
        $this->dispatch('content-sections-updated');
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
