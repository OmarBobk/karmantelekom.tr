<?php

declare(strict_types=1);

namespace App\Livewire\Backend\Products;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Tag;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use App\Services\CurrencyService;

class ProductsComponent extends Component
{
    use WithPagination;
    use WithFileUploads;

    // Search and Filter Properties
    public string $search = '';
    public string $sortField = 'created_at';
    public string $sortDirection = 'DESC';
    public string $status = '';
    public string $category = '';
    public ?string $dateField = '';
    public ?string $dateDirection = 'DESC';
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
    public array $dateFields = ['created_at', 'updated_at'];
    protected $queryString = [
        'search' => ['except' => ''],
        'sortField' => ['except' => 'name'],
        'sortDirection' => ['except' => 'asc'],
        'status' => ['except' => ''],
        'category' => ['except' => ''],
        'dateField' => ['except' => ''],
        'dateDirection' => ['except' => 'DESC']
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
        'prices' => [
            ['price' => '', 'currency' => 'TRY', 'price_type' => 'retail'],
            ['price' => '', 'currency' => 'TRY', 'price_type' => 'wholesale'],
            ['price' => '', 'currency' => 'USD', 'price_type' => 'wholesale']
        ],
        'tags' => []
    ];
    public $currentImages = [];
    public $newImages = [];


    public $addModalOpen = false;
    public $addForm = [
        'name' => '',
        'slug' => '',
        'serial' => '',
        'code' => '',
        'status' => 'inactive',
        'description' => '',
        'category_id' => '',
        'supplier_id' => '',
        'prices' => [
            ['price' => '', 'currency' => 'TRY', 'price_type' => 'retail'],
            ['price' => '', 'currency' => 'TRY', 'price_type' => 'wholesale'],
            ['price' => '', 'currency' => 'USD', 'price_type' => 'wholesale']
        ],
        'tags' => []
    ];
    public $newProductImages = [];

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

    protected $rules = [];

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
        'newImages.*' => 'image',
        'addForm.name' => 'name',
        'addForm.slug' => 'slug',
        'addForm.serial' => 'serial number',
        'addForm.code' => 'product code',
        'addForm.status' => 'status',
        'addForm.description' => 'description',
        'addForm.category_id' => 'category',
        'addForm.supplier_id' => 'supplier',
        'addForm.prices.*.price' => 'price',
        'newProductImages.*' => 'image',
    ];

    protected $listeners = [
        'upload:started' => 'handleUploadStarted',
        'upload:finished' => 'handleUploadFinished',
        'upload:errored' => 'handleUploadErrored',
        'upload:progress' => 'handleUploadProgress',
        'closeModal' => 'handleModalClose',
        'closeAddModal' => 'handleAddModalClose'
    ];

    public array $productStatuses = [];

    // Add this property to store all available tags
    public $allTags;

    public float $exchangeRate;
    protected CurrencyService $currencyService;

    public function boot(CurrencyService $currencyService)
    {
        $this->currencyService = $currencyService;
    }

    public function mount(): void
    {
        $this->categories = Category::all();
        $this->suppliers = Supplier::all();
        $this->allTags = Tag::orderBy('display_order')->get();
        $this->initializeAddForm();
        $this->loadProductStatuses();
        $this->loadExchangeRate();
    }

    private function initializeAddForm(): void
    {
        $this->addForm = [
            'name' => '',
            'slug' => '',
            'serial' => '',
            'code' => '',
            'status' => 'inactive',
            'description' => '',
            'category_id' => '',
            'supplier_id' => '',
            'prices' => [
                ['price' => '', 'currency' => 'TRY', 'price_type' => 'retail'],
                ['price' => '', 'currency' => 'TRY', 'price_type' => 'wholesale'],
                ['price' => '', 'currency' => 'USD', 'price_type' => 'wholesale']
            ],
            'tags' => []
        ];
        $this->newProductImages = [];
        $this->resetValidation('addForm.*');
        $this->reset(['addForm', 'newProductImages']);
    }


    public function updatingAddModalOpen(): void
    {
        $this->initializeAddForm();
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

        // Get all prices
        $prices = collect($this->editingProduct->prices);

        // Initialize price array with default values
        $formattedPrices = [
            ['price' => '', 'currency' => 'TRY', 'price_type' => 'retail'],
            ['price' => '', 'currency' => 'TRY', 'price_type' => 'wholesale'],
            ['price' => '', 'currency' => 'USD', 'price_type' => 'wholesale']
        ];

        // Fill in actual prices if they exist
        if ($retailTryPrice = $prices->first(fn($p) => $p->currency->code === 'TRY' && $p->price_type === 'retail')) {
            $formattedPrices[0]['price'] = $retailTryPrice->base_price;
            $formattedPrices[0]['id'] = $retailTryPrice->id;
        }

        if ($wholesaleTryPrice = $prices->first(fn($p) => $p->currency->code === 'TRY' && $p->price_type === 'wholesale')) {
            $formattedPrices[1]['price'] = $wholesaleTryPrice->base_price;
            $formattedPrices[1]['id'] = $wholesaleTryPrice->id;
        }

        if ($wholesaleUsdPrice = $prices->first(fn($p) => $p->currency->code === 'USD' && $p->price_type === 'wholesale')) {
            $formattedPrices[2]['price'] = $wholesaleUsdPrice->converted_price;
            $formattedPrices[2]['id'] = $wholesaleUsdPrice->id;
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
            'prices' => $formattedPrices,
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
        $this->validate($this->getEditRules());

        DB::enableQueryLog();

        try {
            DB::beginTransaction();

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

            // Update the productStatuses array to reflect the new status
            $this->productStatuses[$this->editingProduct->id] = $this->editForm['status'] === 'active';

            // Update or create prices
            $this->editingProduct->prices()->updateOrCreate(
                [
                    'id' => $this->editForm['prices'][0]['id'] ?? null,
                    'currency_id' => 1, // TRY
                    'price_type' => 'retail'
                ],
                [
                    'base_price' => $this->editForm['prices'][0]['price'],
                    'converted_price' => $this->editForm['prices'][0]['price'], // For TRY, base and converted are same
                ]
            );

            $this->editingProduct->prices()->updateOrCreate(
                [
                    'id' => $this->editForm['prices'][1]['id'] ?? null,
                    'currency_id' => 1, // TRY
                    'price_type' => 'wholesale'
                ],
                [
                    'base_price' => $this->editForm['prices'][1]['price'],
                    'converted_price' => $this->editForm['prices'][1]['price'], // For TRY, base and converted are same
                ]
            );

            $this->editingProduct->prices()->updateOrCreate(
                [
                    'id' => $this->editForm['prices'][2]['id'] ?? null,
                    'currency_id' => 2, // USD
                    'price_type' => 'wholesale'
                ],
                [
                    'base_price' => $this->editForm['prices'][2]['price'],
                    'converted_price' => $this->editForm['prices'][2]['price'], // For USD prices, this will be updated by the scheduled job
                ]
            );

            // Handle new images
            if (!empty($this->newImages)) {
                $hasExistingImages = $this->editingProduct->images()->exists();

                foreach ($this->newImages as $index => $image) {
                    $path = $image->store('products', 'public');
                    $this->editingProduct->images()->create([
                        'image_url' => $path,
                        'is_primary' => !$hasExistingImages && $index === 0
                    ]);
                }
            }

            // Update tags
            $this->editingProduct->tags()->sync($this->editForm['tags']);

            DB::commit();

            $this->cleanupTemporaryFiles();
            $this->reset(['editModalOpen', 'editingProduct', 'editForm']);
            $this->dispatch('closeModal');

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Product updated successfully!'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            logger()->error('Error updating product', [
                'product_id' => $this->editingProduct->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->dispatch('notify', [
                'type' => 'error',
                'message' => app()->environment('local') ? 'Error: ' . $e->getMessage() : 'Error updating product. Please try again.'
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

    public function generateSlug($form = 'edit'): void
    {
        $name = $form === 'edit' ? $this->editForm['name'] : $this->addForm['name'];
        $slug = Str::slug($name);
        $originalSlug = $slug;
        $counter = 1;

        while (Product::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter++;
        }

        if ($form === 'edit') {
            $this->editForm['slug'] = $slug;
        } else {
            $this->addForm['slug'] = $slug;
        }
    }

    public function getProductsProperty(): LengthAwarePaginator
    {
        return Product::query()
            ->with(['category', 'supplier', 'tags', 'prices' => function($query) {
                $query->with('currency')
                    ->whereIn('price_type', ['retail', 'wholesale'])
                    ->whereHas('currency', function($q) {
                        $q->whereIn('code', ['TRY', 'USD']);
                    });
            }, 'images' => function($query) {
                $query->orderBy('is_primary', 'desc');
            }])
            ->when($this->search, fn($query) =>
                $query->where(function($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('code', 'like', '%' . $this->search . '%')
                      ->orWhere('serial', 'like', '%' . $this->search . '%');
                })
            )
            ->when($this->status, fn($query) =>
                $query->where('status', $this->status)
            )
            ->when($this->category, fn($query) =>
                $query->whereHas('category', fn($q) =>
                    $q->where('id', $this->category)
                )
            )
            ->when($this->dateField, fn($query) =>
                $query->orderBy($this->dateField, $this->dateDirection)
            )
            ->when(!$this->dateField, fn($query) =>
                $query->orderBy($this->sortField, $this->sortDirection)
            )
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
            'newImages.*' => 'image|max:2048' // 5MB Max
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

    public function createProduct()
    {
        DB::enableQueryLog();
        $this->validate($this->getAddRules());

        try {
            DB::beginTransaction();

            logger()->info('Starting product creation transaction', [
                'form_data' => $this->addForm
            ]);

            $product = Product::create([
                'name' => $this->addForm['name'],
                'slug' => $this->addForm['slug'],
                'code' => $this->addForm['code'],
                'serial' => $this->addForm['serial'],
                'status' => $this->addForm['status'],
                'description' => $this->addForm['description'],
                'category_id' => $this->addForm['category_id'],
                'supplier_id' => $this->addForm['supplier_id'],
            ]);

            // Create prices
            $product->prices()->create([
                'currency_id' => 1, // TRY
                'price_type' => 'retail',
                'base_price' => $this->addForm['prices'][0]['price'],
                'converted_price' => $this->addForm['prices'][0]['price'], // For TRY, base and converted are same
            ]);

            $product->prices()->create([
                'currency_id' => 1, // TRY
                'price_type' => 'wholesale',
                'base_price' => $this->addForm['prices'][1]['price'],
                'converted_price' => $this->addForm['prices'][1]['price'], // For TRY, base and converted are same
            ]);

            $product->prices()->create([
                'currency_id' => 2, // USD
                'price_type' => 'wholesale',
                'base_price' => $this->addForm['prices'][2]['price'],
                'converted_price' => $this->addForm['prices'][2]['price'], // For USD prices, this will be updated by the scheduled job
            ]);

            // Sync tags
            if (!empty($this->addForm['tags'])) {
                $product->tags()->sync($this->addForm['tags']);
            }

            // Handle images
            if ($this->newProductImages) {
                foreach ($this->newProductImages as $index => $image) {
                    $path = $image->store('products', 'public');
                    $product->images()->create([
                        'image_url' => $path,
                        'is_primary' => $index === 0
                    ]);
                }
            }

            DB::commit();

            $this->addModalOpen = false;
            $this->reset(['addForm', 'newProductImages']);

            $this->dispatch('closeAddModal');
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Product created successfully!'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            logger()->error('Error creating product', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Error creating product. Please try again.'
            ]);
        }
    }

    // Add this new method for add product validation rules
    protected function getAddRules(): array
    {
        return [
            'addForm.name' => 'required|min:3|max:255',
            'addForm.slug' => 'required|unique:products,slug',
            'addForm.serial' => 'nullable|unique:products,serial',
            'addForm.code' => 'required|unique:products,code',
            'addForm.status' => 'required|in:active,inactive',
            'addForm.description' => 'required|min:10|max:1000',
            'addForm.category_id' => 'required|exists:categories,id',
            'addForm.supplier_id' => 'required|exists:suppliers,id',
            'addForm.prices.*.price' => 'required|numeric|min:0',
            'addForm.prices.0.price' => 'required|numeric|min:0',
            'addForm.prices.1.price' => 'required|numeric|min:0',
            'addForm.prices.2.price' => 'required|numeric|min:0',
            'newProductImages.*' => 'image|max:2048',
            'addForm.tags' => 'array',
            'addForm.tags.*' => 'exists:tags,id'
        ];
    }

    #[Layout('layouts.backend')]
    public function render()
    {
        return view('livewire.backend.products.products-component', [
            'products' => $this->products
        ]);
    }

    // Add this method to get dynamic rules for editing
    protected function getEditRules(): array
    {
        return [
            'editForm.name' => 'required|min:3',
            'editForm.slug' => 'required|unique:products,slug,' . ($this->editingProduct?->id ?? ''),
            'editForm.serial' => 'nullable|unique:products,serial,' . ($this->editingProduct?->id ?? ''),
            'editForm.code' => 'required|unique:products,code,' . ($this->editingProduct?->id ?? ''),
            'editForm.status' => 'required|in:active,inactive',
            'editForm.description' => 'required|min:10',
            'editForm.category_id' => 'required|exists:categories,id',
            'editForm.supplier_id' => 'nullable|exists:suppliers,id',
            'editForm.prices.*.price' => 'required|numeric|min:0',
            'editForm.prices.0.price' => 'required|numeric|min:0',
            'editForm.prices.1.price' => 'required|numeric|min:0',
            'editForm.prices.2.price' => 'required|numeric|min:0',
            'newImages.*' => 'image|max:2048',
        ];
    }

    public function loadProductStatuses(): void
    {
        $this->productStatuses = Product::pluck('status', 'id')
            ->map(fn ($status) => $status === 'active')
            ->toArray();
    }

    public function toggleStatus(int $productId): void
    {
        try {
            DB::beginTransaction();

            $product = Product::findOrFail($productId);
            $newStatus = $product->status === 'active' ? 'inactive' : 'active';

            $product->update(['status' => $newStatus]);

            // Update local state
            $this->productStatuses[$productId] = $newStatus === 'active';

            DB::commit();

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => "Product status updated to " . ucfirst($newStatus)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            logger()->error('Error toggling product status', [
                'product_id' => $productId,
                'error' => $e->getMessage()
            ]);

            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Failed to update product status'
            ]);

            // Revert the local state
            $this->loadProductStatuses();
        }
    }

    public function toggleTag($tagId): void
    {
        if (!isset($this->editForm['tags'])) {
            $this->editForm['tags'] = [];
        }

        if (in_array($tagId, $this->editForm['tags'])) {
            $this->editForm['tags'] = array_values(array_diff($this->editForm['tags'], [$tagId]));
        } else {
            $this->editForm['tags'][] = $tagId;
        }
    }

    public function toggleAddTag($tagId): void
    {
        if (!isset($this->addForm['tags'])) {
            $this->addForm['tags'] = [];
        }

        if (in_array($tagId, $this->addForm['tags'])) {
            $this->addForm['tags'] = array_values(array_diff($this->addForm['tags'], [$tagId]));
        } else {
            $this->addForm['tags'][] = $tagId;
        }
    }

    private function loadExchangeRate(): void
    {
        try {
            $this->exchangeRate = $this->currencyService->getExchangeRate('TRY', 'USD');
        } catch (\Exception $e) {
            logger()->error('Error loading exchange rate: ' . $e->getMessage());
            $this->exchangeRate = 0.033; // Fallback exchange rate
        }
    }

    public function updatedEditFormPrices($value, $key): void
    {
        if (str_contains($key, '.price')) {
            $this->validateOnly("editForm.prices.*.price");
        }
    }

    public function updatedAddFormPrices($value, $key): void
    {
        if (str_contains($key, '.price')) {
            $this->validateOnly("addForm.prices.*.price");
        }
    }
}
