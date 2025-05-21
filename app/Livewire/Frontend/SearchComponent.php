<?php

declare(strict_types=1);

namespace App\Livewire\Frontend;

use App\Models\Product;
use App\Models\Category;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Illuminate\Support\Collection;

class SearchComponent extends Component
{
    public string $searchQuery = '';
    public bool $isLoading = false;
    public array $searchResults = [];

    public function mount(): void
    {
        $this->searchResults = [];
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
//
//        // Search in products
//        $products = Product::where('name', 'like', '%' . $this->searchQuery . '%')
//            ->orWhere('description', 'like', '%' . $this->searchQuery . '%')
//            ->take(5)
//            ->get()
//            ->map(function ($product) {
//                return [
//                    'title' => $product->name,
//                    'category' => 'Product',
//                    'url' => '#',
//                ];
//            });

        // Search in products
        $products = Product::where('is_active', true)
            ->with('images')
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
