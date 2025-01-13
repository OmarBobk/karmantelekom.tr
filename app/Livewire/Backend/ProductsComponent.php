<?php

declare(strict_types=1);

namespace App\Livewire\Backend;

use App\Models\Product;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProductsComponent extends Component
{
    use WithPagination;

    // Search and Filter Properties
    public string $search = '';
    public string $sortField = 'name';
    public string $sortDirection = 'asc';
    public string $status = '';
    public string $category = '';
    public array $selectedProducts = [];
    public bool $selectAll = false;

    // Bulk Actions
    public string $bulkAction = '';

    // Modal States
    public bool $showDeleteModal = false;
    public bool $showEditModal = false;
    public ?Product $editingProduct = null;

    // Filters
    public array $categories = [];
    public array $statuses = ['active', 'inactive'];
    public array $priceRange = [
        'min' => 0,
        'max' => 1000
    ];

    protected $queryString = [
        'search' => ['except' => ''],
        'sortField' => ['except' => 'name'],
        'sortDirection' => ['except' => 'asc'],
        'status' => ['except' => ''],
        'category' => ['except' => '']
    ];

    public function mount(): void
    {
        $this->categories = ['Electronics', 'Fashion', 'Home', 'Books']; // Replace with actual categories
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function updatedSelectAll($value): void
    {
        if ($value) {
            $this->selectedProducts = $this->products->pluck('id')->map(fn($id) => (string) $id)->toArray();
        } else {
            $this->selectedProducts = [];
        }
    }

    public function confirmDelete(int $productId): void
    {
        $this->editingProduct = Product::find($productId);
        $this->showDeleteModal = true;
    }

    public function deleteProduct(): void
    {
        if ($this->editingProduct) {
            $this->editingProduct->delete();
            $this->showDeleteModal = false;
            $this->editingProduct = null;
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Product deleted successfully!'
            ]);
        }
    }

    public function editProduct(int $productId): void
    {
        $this->editingProduct = Product::find($productId);
        $this->showEditModal = true;
    }

    public function getProductsProperty(): LengthAwarePaginator
    {
        return Product::query()
            ->when($this->search, fn($query) => 
                $query->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('sku', 'like', '%' . $this->search . '%')
            )
            ->when($this->status, fn($query) => 
                $query->where('status', $this->status)
            )
            ->when($this->category, fn($query) => 
                $query->where('category', $this->category)
            )
            ->when($this->priceRange, fn($query) => 
                $query->whereBetween('price', [$this->priceRange['min'], $this->priceRange['max']])
            )
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(10);
    }

    #[Layout('layouts.backend')]
    public function render()
    {
        return view('livewire.backend.products-component', [
            'products' => $this->products
        ]);
    }
}
