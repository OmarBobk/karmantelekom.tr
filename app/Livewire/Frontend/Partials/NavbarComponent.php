<?php

namespace App\Livewire\Frontend\Partials;

use App\Models\Category;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;

class NavbarComponent extends Component
{
    public Collection $categories;
    public ?Category $currentCategory;

    public bool $isCatalog = false;

    public function mount(): void
    {
        $this->isCatalog = request()->routeIs('catalog');
        $this->loadCategories();
    }

    /**
     * @return Factory|View
     */
    public function render(): Factory|View
    {
        $this->currentCategory = null;
        if (request()->routeIs('products') && request('category') !== 'all') {
            $this->currentCategory = Category::whereSlug(request('category'))->first();

        }

        return view('livewire.frontend.partials.navbar-component');
    }

    private function loadCategories(): void
    {
        $this->categories = Category::with('children')
            ->whereNull('parent_id')
            ->where('status', true)
            ->orderBy('name')
            ->get();

    }
}
