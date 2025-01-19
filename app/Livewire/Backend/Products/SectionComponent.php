<?php
declare(strict_types=1);

namespace App\Livewire\Backend\Products;

use App\Models\Product;
use App\Models\Section;
use App\Models\Tag;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;

class SectionComponent extends Component
{
    use WithPagination;

    // Add this property to manage different pagination states
    protected $paginationTheme = 'tailwind';
    
    // Add a method to get the pagination query string
    public function getQueryString()
    {
        return array_merge(parent::getQueryString(), [
            'page' => ['except' => 1, 'as' => 'p'],
            'productsPage' => ['except' => 1, 'as' => 'pp']
        ]);
    }

    public $showModal = false;
    public $editingSection = null;
    public $selectedProducts = [];
    public $searchTerm = '';
    public $productSearchTerm = '';
    public $productOrders = [];
    public $selectAllProducts = false;
    
    // Form properties
    public $sectionId;
    public $name = '';
    public $description = '';
    public $order = 0;

    //TODO: Positions should be set from the settings page like this: sidebar positions: ['main', 'sidebar', 'footer']
    public $position = 'main';
    public $is_active = false;
    public $scrollable = false;


    protected $rules = [
        'name' => 'required|min:3|max:255',
        'description' => 'nullable|max:1000',
        'order' => 'required|integer|min:0',
        'position' => 'required|in:main,sidebar,footer',
        'is_active' => 'boolean',
        'scrollable' => 'boolean'
    ];

    public function mount()
    {
        $this->resetForm();
    }

    public function resetForm()
    {
        $this->reset(['name', 'description', 'order', 'position', 'is_active', 'scrollable', 'sectionId', 'selectedProducts']);
        $this->editingSection = null;
    }

    public function create()
    {
        $this->resetForm();
        $this->reset('productOrders');
        $this->showModal = true;
    }

    public function edit(Section $section)
    {
        $this->editingSection = $section;
        $this->sectionId = $section->id;
        $this->name = $section->name;
        $this->description = $section->description;
        $this->order = $section->order;
        $this->position = $section->position;
        $this->is_active = $section->is_active;
        $this->scrollable = $section->scrollable;
        
        // Load selected products and their orders
        $this->selectedProducts = $section->products->pluck('id')->toArray();
        $this->productOrders = $section->products->pluck('pivot.ordering', 'id')->toArray();
        
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate();

        // If order is 0, set it to highest order + 1
        if ($this->order === 0) {
            $this->order = Section::max('order') + 1;
        }

        $section = $this->editingSection ?? new Section();
        $section->fill([
            'name' => $this->name,
            'description' => $this->description,
            'order' => $this->order,
            'position' => $this->position,
            'is_active' => $this->is_active,
            'scrollable' => $this->scrollable,
        ]);
        $section->save();

        // Sync products with ordering
        if (!empty($this->selectedProducts)) {
            $productsWithOrder = collect($this->selectedProducts)->mapWithKeys(function ($productId) {
                return [$productId => ['ordering' => $this->productOrders[$productId] ?? 0]];
            })->all();
            $section->products()->sync($productsWithOrder);
        } else {
            $section->products()->detach();
        }

        $this->dispatch('notify', [
            [
                'message' => $this->editingSection ? 'Section updated successfully!' : 'Section created successfully!',
                'type' => 'success'
            ]
        ]);

        $this->showModal = false;
        $this->resetForm();
        $this->reset('productOrders');
    }

    public function delete(Section $section)
    {
        $section->delete();
        $this->dispatch('notify', [
            [
                'message' => 'Section deleted successfully!',
                'type' => 'success'
            ]
        ]);
    }

    public function toggleActive(Section $section)
    {
        $section->is_active = !$section->is_active;
        $section->save();
        
        $this->dispatch('section-updated', sectionId: $section->id);
    }

    public function toggleScrollable(Section $section)
    {
        $section->scrollable = !$section->scrollable;
        $section->save();
        
        $this->dispatch('section-updated', sectionId: $section->id);
    }

    #[On('section-updated')] 
    public function refreshSection($sectionId)
    {
        // Only refresh the specific section that changed
    }

    #[Computed]
    public function sections()
    {
        return Section::with('products')
            ->when($this->searchTerm, function ($query) {
                $query->where('name', 'like', '%' . $this->searchTerm . '%')
                    ->orWhere('description', 'like', '%' . $this->searchTerm . '%');
            })
            ->orderBy('order')
            ->paginate(10, ['*'], 'page');
    }

    #[Computed]
    public function filteredProducts()
    {
        $products = Product::with(['images', 'tags'])
            ->when($this->productSearchTerm, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->productSearchTerm . '%')
                        ->orWhere('code', 'like', '%' . $this->productSearchTerm . '%')
                        ->orWhere('serial', 'like', '%' . $this->productSearchTerm . '%');
                });
            })
            ->paginate(10, ['*'], 'productsPage');

        // Map through the items to initialize product orders
        $products->through(function ($product) {
            if ($this->editingSection) {
                $pivot = $this->editingSection->products->find($product->id)?->pivot;
                if (!isset($this->productOrders[$product->id])) {
                    $this->productOrders[$product->id] = $pivot?->ordering ?? 0;
                }
            }
            return $product;
        });

        return $products;
    }

    #[Layout('layouts.backend')]
    public function render()
    {
        return view('livewire.backend.products.section-component', [
            'sections' => $this->sections,
            'products' => $this->filteredProducts
        ]);
    }

    // Add this method to reset pagination when searching products
    public function updatedProductSearchTerm()
    {
        $this->resetPage('productsPage');
    }

    // Add this method to reset pagination when searching sections
    public function updatedSearchTerm()
    {
        $this->resetPage('page');
    }

    protected function maintainProductOrder(): void
    {
        // Get the highest existing non-zero order
        $maxOrder = empty($this->productOrders) ? 0 : max(empty(array_filter($this->productOrders)) ? [0] : array_filter($this->productOrders));

        // For each selected product
        foreach ($this->selectedProducts as $productId) {
            // If product doesn't have an order or has order 0
            if (!isset($this->productOrders[$productId]) || $this->productOrders[$productId] == 0) {
                $this->productOrders[$productId] = $maxOrder + 1;
                $maxOrder++;
            }
        }

        // Remove orders for unselected products
        $this->productOrders = array_intersect_key(
            $this->productOrders, 
            array_flip($this->selectedProducts)
        );
    }

    public function updatedSelectedProducts(): void
    {
        $this->maintainProductOrder();
    }

    public function updatedSelectAllProducts($value)
    {
        if ($value) {
            // When selecting all products
            $highestOrder = empty($this->productOrders) ? 0 : max($this->productOrders);
            
            // Get all product IDs from the current page
            $allProductIds = $this->filteredProducts->pluck('id')->toArray();
            
            // Set orders only for newly selected products
            foreach ($allProductIds as $productId) {
                if (!isset($this->productOrders[$productId])) {
                    $this->productOrders[$productId] = ++$highestOrder;
                }
            }
            
            $this->selectedProducts = $allProductIds;
        } else {
            // When deselecting all products
            $this->selectedProducts = [];
            $this->productOrders = [];
        }
    }

    public function removeProduct(string $productId): void
    {
        // Remove from selected products
        $this->selectedProducts = array_values(array_diff($this->selectedProducts, [$productId]));
        
        // Remove from product orders
        unset($this->productOrders[$productId]);
        
        // Maintain order sequence
        $this->maintainProductOrder();
    }

    // Add this method to handle the order field updates
    public function updatedOrder($value)
    {
        if ($value === '0' || $value === 0) {
            $this->order = Section::max('order') + 1;
        }
    }

    // Add this new computed property to get all selected products
    #[Computed]
    public function selectedProductsList()
    {
        if (empty($this->selectedProducts)) {
            return collect();
        }

        return Product::with(['images' => function ($query) {
            $query->where('is_primary', true);
        }])
        ->whereIn('id', $this->selectedProducts)
        ->get()
        ->sortBy(function ($product) {
            return $this->productOrders[$product->id] ?? PHP_INT_MAX;
        });
    }
}
