<?php

declare(strict_types=1);

namespace App\Livewire\Backend;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
    public $categories;
    public $suppliers;
    public array $statuses = ['active', 'inactive'];
    protected $queryString = [
        'search' => ['except' => ''],
        'sortField' => ['except' => 'name'],
        'sortDirection' => ['except' => 'asc'],
        'status' => ['except' => ''],
        'category' => ['except' => '']
    ];

    // Edit Modal Properties
    public $editModalOpen = false;
    public $editForm = [
        'name' => '',
        'slug' => '',
        'serial' => '',
        'code' => '',
        'status' => '',
        'description' => '',
        'category_id' => '',
        'supplier_id' => '',
        'prices' => [],
        'tags' => []
    ];
    public $currentImages = [];
    public $newImages = [];

    protected $rules = [
        'editForm.name' => 'required|min:3',
        'editForm.slug' => 'required',
        'editForm.serial' => 'nullable',
        'editForm.code' => 'required',
        'editForm.status' => 'required|in:active,inactive',
        'editForm.description' => 'required|min:10',
        'editForm.category_id' => 'required|exists:categories,id',
        'editForm.supplier_id' => 'nullable|exists:suppliers,id',
        'editForm.prices.*.price' => 'required|numeric|min:0',
        'editForm.prices.*.currency' => 'required|string|max:10',
        'editForm.prices.*.price_type' => 'required|in:retail,wholesale',
        'newImages.*' => 'image|max:2048'
    ];

    protected $validationAttributes = [
        'editForm.name' => 'name',
        'editForm.slug' => 'slug',
        'editForm.serial' => 'serial number',
        'editForm.code' => 'product code',
        'editForm.status' => 'status',
        'editForm.description' => 'description',
        'editForm.category_id' => 'category',
        'editForm.supplier_id' => 'supplier',
        'editForm.prices.*.price' => 'price',
        'editForm.prices.*.currency' => 'currency',
        'editForm.prices.*.price_type' => 'price type',
        'newImages.*' => 'image'
    ];

    public function mount(): void
    {
        $this->categories = Category::all();
        $this->suppliers = Supplier::all();
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

    public function editProduct($productId)
    {
        $this->resetValidation();
        $this->editingProduct = Product::with(['category', 'supplier', 'prices', 'images', 'tags'])->find($productId);

        if (!$this->editingProduct) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Product not found!'
            ]);
            return;
        }

        $this->editForm = [
            'name' => $this->editingProduct->name,
            'slug' => $this->editingProduct->slug,
            'serial' => $this->editingProduct->serial,
            'code' => $this->editingProduct->code,
            'status' => $this->editingProduct->status,
            'description' => $this->editingProduct->description,
            'category_id' => $this->editingProduct->category_id,
            'supplier_id' => $this->editingProduct->supplier_id,
            'prices' => $this->editingProduct->prices->map(function($price) {
                return [
                    'id' => $price->id,
                    'price' => $price->price,
                    'currency' => $price->currency,
                    'price_type' => $price->price_type
                ];
            })->toArray(),
            'tags' => $this->editingProduct->tags->pluck('id')->toArray()
        ];

        $this->currentImages = $this->editingProduct->images->map(function($image) {
            return [
                'id' => $image->id,
                'url' => $image->url,
                'is_primary' => $image->is_primary
            ];
        })->toArray();

        $this->editModalOpen = true;
    }

    public function updateProduct()
    {
        DB::enableQueryLog();
        $this->validate();

        try {
            DB::beginTransaction();

            // Log the start of transaction
            logger()->info('Starting product update transaction', [
                'product_id' => $this->editingProduct->id,
                'form_data' => $this->editForm
            ]);


            $this->editingProduct->update([
                'name' => $this->editForm['name'],
                'slug' => $this->editForm['slug'],
                'serial' => $this->editForm['serial'],
                'code' => $this->editForm['code'],
                'status' => $this->editForm['status'],
                'description' => $this->editForm['description'],
                'category_id' => $this->editForm['category_id'],
                'supplier_id' => $this->editForm['supplier_id']
            ]);
            // Log after basic update
            logger()->info('Basic product details updated', ['product_id' => $this->editingProduct->id]);

            // Update prices
            $this->editingProduct->prices()->delete();
            foreach ($this->editForm['prices'] as $price) {
                $this->editingProduct->prices()->create([
                    'price' => $price['price'],
                    'currency' => $price['currency'],
                    'price_type' => $price['price_type']
                ]);
            }
            // Log after prices update
            logger()->info('Product prices updated', [
                'product_id' => $this->editingProduct->id,
                'prices' => $this->editForm['prices']
            ]);
            // Handle new images
            if (!empty($this->newImages)) {
                foreach ($this->newImages as $image) {
                    $path = $image->store('products', 'public');
                    $this->editingProduct->images()->create([
                        'url' => $path,
                        'is_primary' => false
                    ]);
                }
                logger()->info('New images added', [
                    'product_id' => $this->editingProduct->id,
                    'image_count' => count($this->newImages)
                ]);
            }

            // Update tags
            $this->editingProduct->tags()->sync($this->editForm['tags']);
            logger()->info('Tags synced', [
                'product_id' => $this->editingProduct->id,
                'tags' => $this->editForm['tags']
            ]);

            DB::commit();
            logger()->info('Transaction committed successfully', ['product_id' => $this->editingProduct->id]);

            $this->editModalOpen = false;

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Product updated successfully!'
            ]);

            logger()->info('SQL Queries:', ['queries' => DB::getQueryLog()]);
        } catch (\Exception $e) {
            DB::rollBack();
            logger()->error('Error updating product', [
                'product_id' => $this->editingProduct->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $errorMessage = app()->environment('local')
                ? 'Error: ' . $e->getMessage()
                : 'Error updating product. Please try again.';

            $this->dispatch('notify', [
                'type' => 'error',
                'message' => $errorMessage
            ]);
        }
    }

    public function removeImage($imageId)
    {
        $image = $this->editingProduct->images()->find($imageId);
        if ($image) {
            Storage::disk('public')->delete($image->url);
            $image->delete();
            $this->currentImages = array_filter($this->currentImages, fn($img) => $img['id'] !== $imageId);
        }
    }

    public function setPrimaryImage($imageId)
    {
        $this->editingProduct->images()->update(['is_primary' => false]);
        $this->editingProduct->images()->where('id', $imageId)->update(['is_primary' => true]);

        $this->currentImages = array_map(function($image) use ($imageId) {
            $image['is_primary'] = $image['id'] === $imageId;
            return $image;
        }, $this->currentImages);
    }

    public function generateSlug(): void
    {
        $slug = Str::slug($this->editForm['name']);
        $originalSlug = $slug;
        $count = 1;

        // Use a more efficient approach to check for unique slug
        while (Product::where('slug', $slug)
                ->where('id', '!=', $this->editingProduct?->id)
                ->exists()) {
            $slug = $originalSlug . '-' . $count++;
        }

        $this->editForm['slug'] = $slug;
    }

    public function getProductsProperty(): LengthAwarePaginator
    {
        return Product::query()
            ->with(['category', 'prices', 'images'])
            ->when($this->search, fn($query) => 
                $query->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('code', 'like', '%' . $this->search . '%')
                    ->orWhere('serial', 'like', '%' . $this->search . '%')
                    ->orWhereHas('prices', fn($q) => 
                        $q->where('price', 'like', '%' . $this->search . '%')
                    )
            )
            ->when($this->status, fn($query) => 
                $query->where('status', $this->status)
            )
            ->when($this->category, fn($query) => 
                $query->whereHas('category', fn($q) => 
                    $q->where('name', $this->category)
                )
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
