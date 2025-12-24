<?php

declare(strict_types=1);

namespace App\Livewire\Frontend;

use App\Models\Product;
use App\Models\Category;
use App\Models\Setting;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Illuminate\Support\Collection;

class SearchComponent extends Component
{
    public string $searchQuery = '';
    public bool $isLoading = false;
    public array $searchResults = [];

    public bool $arePricesEnabled = false;
    private bool $arePricesEnabledLoaded = false;

    public function mount(): void
    {
        $this->searchResults = [];
        $this->loadArePricesEnabled();
    }

    private function loadArePricesEnabled(): void
    {
        if (!$this->arePricesEnabledLoaded) {
            $this->arePricesEnabled = Setting::where('key', 'product_prices')->first()->value === 'enabled';
            $this->arePricesEnabledLoaded = true;
        }
    }

    public function updatedSearchQuery(): void
    {
        $this->search();
    }

    public function resetSearch(): void
    {
        $this->searchQuery = '';
        $this->searchResults = [];
        $this->isLoading = false;
    }

    public function search(): void
    {
        if (strlen($this->searchQuery) < 2) {
            $this->searchResults = [];
            return;
        }

        $this->isLoading = true;

        // Search in products
        $products = Product::where('is_active', true)
            ->with('images', 'prices')
            ->search(
                $this->searchQuery,
                'id',
                'asc'
            )
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'image' => Storage::url($product->images->where('is_primary', true)->first()->image_url),
                    'title' => $product->translated_name,
                    'description' => $product->translated_description,
                    'category' => 'Product',
                    'price' => $this->arePricesEnabled ? $product->prices->first()->getFormattedPrice() : null,
                    'url' => '#',
                ];
            });

        $this->searchResults = $products->toArray();
        $this->isLoading = false;
    }

    public function render()
    {
        return view('livewire.frontend.search-component');
    }
}
