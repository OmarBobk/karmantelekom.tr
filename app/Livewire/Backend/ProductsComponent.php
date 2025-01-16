<?php

declare(strict_types=1);

namespace App\Livewire\Backend;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductsComponent extends Component
{
    use WithPagination;
    use WithFileUploads;

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

    // Upload Progress
    public $uploadProgress = [];
    public $iteration = 0;

    // Add these properties at the top of your class with other properties
    public bool $showBulkActionModal = false;
    public string $pendingBulkAction = '';
    public string $bulkActionMessage = '';
    public bool $showImageModal = false;
    public ?array $viewingImage = null;
    public bool $showProductImageModal = false;
    public ?array $viewingProductImage = null;
    public bool $showImageDeleteModal = false;
    public ?array $imageToDelete = null;

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

    protected $listeners = [
        'upload:started' => 'handleUploadStarted',
        'upload:finished' => 'handleUploadFinished',
        'upload:errored' => 'handleUploadErrored',
        'upload:progress' => 'handleUploadProgress',
        'closeModal' => 'handleModalClose'
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

        // Ensure we have both TL and $ prices
        $prices = collect($this->editingProduct->prices);
        $tlPrice = $prices->firstWhere('currency', 'TL') ?? ['price' => 0, 'currency' => 'TL', 'price_type' => 'retail'];
        $usdPrice = $prices->firstWhere('currency', '$') ?? ['price' => 0, 'currency' => '$', 'price_type' => 'retail'];

        $this->editForm = [
            'name' => $this->editingProduct->name,
            'slug' => $this->editingProduct->slug,
            'serial' => $this->editingProduct->serial,
            'code' => $this->editingProduct->code,
            'status' => $this->editingProduct->status,
            'description' => $this->editingProduct->description,
            'category_id' => $this->editingProduct->category_id,
            'supplier_id' => $this->editingProduct->supplier_id,
            'prices' => [
                [
                    'id' => $tlPrice['id'] ?? null,
                    'price' => $tlPrice['price'],
                    'currency' => 'TL',
                    'price_type' => $tlPrice['price_type']
                ],
                [
                    'id' => $usdPrice['id'] ?? null,
                    'price' => $usdPrice['price'],
                    'currency' => '$',
                    'price_type' => $usdPrice['price_type']
                ]
            ],
            'tags' => $this->editingProduct->tags->pluck('id')->toArray()
        ];

        $this->currentImages = $this->editingProduct->images->map(function($image) {
            return [
                'id' => $image->id,
                'url' => $image->image_url,
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

            // Update prices - maintain both TL and $ prices
            foreach ($this->editForm['prices'] as $priceData) {
                $this->editingProduct->prices()->updateOrCreate(
                    ['currency' => $priceData['currency']],
                    [
                        'price' => $priceData['price'],
                        'price_type' => $priceData['price_type']
                    ]
                );
            }

            // Log after prices update
            logger()->info('Product prices updated', [
                'product_id' => $this->editingProduct->id,
                'prices' => $this->editForm['prices']
            ]);

            // Handle new images
            if (!empty($this->newImages)) {
                $hasExistingImages = $this->editingProduct->images()->exists();
                
                foreach ($this->newImages as $index => $image) {
                    $path = $image->store('products', 'public');
                    $this->editingProduct->images()->create([
                        'image_url' => $path,
                        'is_primary' => !$hasExistingImages && $index === 0 // Make first image primary if no existing images
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

            $this->cleanupTemporaryFiles();
            $this->reset(['editModalOpen', 'editingProduct', 'editForm']);
            $this->dispatch('closeModal');

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
            Storage::disk('public')->delete($image->image_url);
            $image->delete();
            $this->currentImages = array_filter($this->currentImages, fn($img) => $img['id'] !== $imageId);

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Image deleted successfully!'
            ]);
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
            ->with(['category', 'prices', 'images' => function($query) {
                $query->orderBy('is_primary', 'desc');
            }])
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
                    $q->where('id', $this->category)
                )
            )
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(10);
    }

    public function handleUploadStarted($name)
    {
        $index = substr($name, strpos($name, '.') + 1);
        
        $this->uploadProgress['newImages.' . $index] = [
            'progress' => 0,
            'error' => null
        ];
    }

    public function handleUploadProgress($name, $progress)
    {
        $index = substr($name, strpos($name, '.') + 1);
        
        if (isset($this->uploadProgress['newImages.' . $index])) {
            $this->uploadProgress['newImages.' . $index]['progress'] = $progress;
        }
    }

    public function handleUploadFinished($name)
    {
        $index = substr($name, strpos($name, '.') + 1);
        
        if (isset($this->uploadProgress['newImages.' . $index])) {
            unset($this->uploadProgress['newImages.' . $index]);
        }
    }

    public function handleUploadErrored($name, $error)
    {
        $index = substr($name, strpos($name, '.') + 1);
        
        if (isset($this->uploadProgress['newImages.' . $index])) {
            $this->uploadProgress['newImages.' . $index]['error'] = $error;
        }
    }

    public function removeProgress($name)
    {
        if (isset($this->uploadProgress[$name])) {
            unset($this->uploadProgress[$name]);
        }
    }

    public function updatedNewImages()
    {
        $this->validate([
            'newImages.*' => 'image|max:5120' // 5MB Max
        ]);
        
        $this->iteration++;
    }

    public function removeTemporaryImage($index)
    {
        if (isset($this->newImages[$index])) {
            $image = $this->newImages[$index];
            
            // Delete the temporary file
            if ($image && method_exists($image, 'getFilename')) {
                $tmpPath = storage_path('app/livewire-tmp/' . $image->getFilename());
                if (file_exists($tmpPath)) {
                    unlink($tmpPath);
                }
            }
            
            // Remove from newImages array
            $newImages = [];
            foreach ($this->newImages as $i => $img) {
                if ($i !== $index) {
                    $newImages[] = $img;
                }
            }
            $this->newImages = $newImages;
            
            // Reset upload progress for this image
            unset($this->uploadProgress['newImages.' . $index]);
        }
    }

    private function cleanupTemporaryFiles(): void
    {
        // Clean up temporary files
        if (!empty($this->newImages)) {
            foreach ($this->newImages as $image) {
                if ($image && method_exists($image, 'getFilename')) {
                    // Delete the temporary file
                    $tmpPath = storage_path('app/livewire-tmp/' . $image->getFilename());
                    if (file_exists($tmpPath)) {
                        unlink($tmpPath);
                    }
                }
            }
        }
        
        // Reset the component state
        $this->reset(['newImages', 'uploadProgress']);
        $this->iteration++;
        
    }

    public function handleModalClose()
    {
        $this->editModalOpen = false;
    }

    // Add this method to handle bulk actions
    public function processBulkAction(): void
    {
        if (empty($this->selectedProducts)) {
            $this->dispatch('notify', [
                'type' => 'warning',
                'message' => 'Please select products to perform bulk action.'
            ]);
            return;
        }

        try {
            DB::beginTransaction();

            switch ($this->bulkAction) {
                case 'delete':
                    // Get products with their images
                    $products = Product::whereIn('id', $this->selectedProducts)
                        ->with('images')
                        ->get();

                    foreach ($products as $product) {
                        // Delete associated images from storage
                        foreach ($product->images as $image) {
                            Storage::disk('public')->delete($image->image_url);
                        }
                        $product->delete();
                    }

                    $message = count($this->selectedProducts) . ' products deleted successfully.';
                    break;

                case 'activate':
                    Product::whereIn('id', $this->selectedProducts)
                        ->update(['status' => 'active']);
                    $message = count($this->selectedProducts) . ' products activated successfully.';
                    break;

                case 'deactivate':
                    Product::whereIn('id', $this->selectedProducts)
                        ->update(['status' => 'inactive']);
                    $message = count($this->selectedProducts) . ' products deactivated successfully.';
                    break;

                default:
                    throw new \Exception('Invalid bulk action selected.');
            }

            DB::commit();

            // Reset selections and bulk action
            $this->selectedProducts = [];
            $this->selectAll = false;
            $this->bulkAction = '';

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => $message
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            logger()->error('Bulk action failed', [
                'action' => $this->bulkAction,
                'products' => $this->selectedProducts,
                'error' => $e->getMessage()
            ]);

            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Failed to process bulk action. Please try again.'
            ]);
        }
    }

    // Add this method to watch for bulk action changes
    public function updatedBulkAction(): void
    {
        if ($this->bulkAction) {
            if (empty($this->selectedProducts)) {
                $this->dispatch('notify', [
                    'type' => 'warning',
                    'message' => 'Please select products to perform bulk action.'
                ]);
                $this->bulkAction = '';
                return;
            }

            $this->pendingBulkAction = $this->bulkAction;
            $this->bulkActionMessage = match($this->bulkAction) {
                'delete' => 'Are you sure you want to delete ' . count($this->selectedProducts) . ' selected products?',
                'activate' => 'Are you sure you want to activate ' . count($this->selectedProducts) . ' selected products?',
                'deactivate' => 'Are you sure you want to deactivate ' . count($this->selectedProducts) . ' selected products?',
                default => 'Are you sure you want to proceed with this action?'
            };
            $this->showBulkActionModal = true;
            $this->bulkAction = ''; // Reset the select
        }
    }

    // Add this new method to confirm the bulk action
    public function confirmBulkAction(): void
    {
        $this->showBulkActionModal = false;
        $this->bulkAction = $this->pendingBulkAction;
        $this->processBulkAction();
        $this->pendingBulkAction = '';
    }

    // Add this new method to cancel the bulk action
    public function cancelBulkAction(): void
    {
        $this->showBulkActionModal = false;
        $this->pendingBulkAction = '';
        $this->bulkAction = '';
    }

    public function viewImage(string $imageUrl, string $productName): void
    {
        $this->viewingImage = [
            'url' => $imageUrl,
            'name' => $productName
        ];
        $this->showImageModal = true;
    }

    public function closeImageView(): void
    {
        $this->showImageModal = false;
        $this->viewingImage = null;
    }

    public function viewProductImage(string $imageUrl, string $productName): void
    {
        $this->viewingProductImage = [
            'url' => $imageUrl,
            'alt' => "Product image for {$productName}"
        ];
        $this->showProductImageModal = true;
    }

    public function closeProductImageView(): void
    {
        $this->showProductImageModal = false;
        $this->viewingProductImage = null;
    }

    public function confirmImageDelete($imageId, $imageName)
    {
        $this->imageToDelete = [
            'id' => $imageId,
            'name' => $imageName
        ];
        $this->showImageDeleteModal = true;
    }

    public function cancelImageDelete()
    {
        $this->showImageDeleteModal = false;
        $this->imageToDelete = null;
    }

    public function deleteImage()
    {
        if ($this->imageToDelete) {
            $this->removeImage($this->imageToDelete['id']);
            $this->showImageDeleteModal = false;
            $this->imageToDelete = null;
        }
    }

    #[Layout('layouts.backend')]
    public function render()
    {
        return view('livewire.backend.products-component', [
            'products' => $this->products
        ]);
    }
}
