<?php

declare(strict_types=1);

namespace App\Livewire\Backend\Products;

use App\Models\Category;
use App\Models\Product;
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

/**
 * Backend Products Management Component
 *
 * Handles all product management operations including:
 * - Listing products with filtering and sorting
 * - Creating new products
 * - Editing existing products
 * - Managing product images
 * - Managing product prices
 * - Bulk operations
 */
class ProductsComponent extends Component
{
    use WithPagination;
    use WithFileUploads;

    // Search and Filter Properties
    /** @var string Search query for filtering products */
    public string $search = '';

    /** @var string Field to sort products by */
    public string $sortField = 'created_at';

    /** @var string Sort direction (ASC/DESC) */
    public string $sortDirection = 'DESC';

    /** @var string Filter by product status */
    public string $status = '';

    /** @var string Filter by category */
    public string $category = '';

    /** @var ?string Filter by date field */
    public ?string $dateField = '';

    /** @var ?string Date field sort direction */
    public ?string $dateDirection = 'DESC';

    /** @var array<string> Selected product IDs for bulk actions */
    public array $selectedProducts = [];

    /** @var bool Whether all products are selected */
    public bool $selectAll = false;

    // Bulk Actions
    /** @var string Current bulk action to perform */
    public string $bulkAction = '';

    /** @var string Pending bulk action awaiting confirmation */
    public string $pendingBulkAction = '';

    /** @var string Message to display for bulk action confirmation */
    public string $bulkActionMessage = '';

    // Modal States
    /** @var bool Whether delete confirmation modal is shown */
    public bool $showDeleteModal = false;

    /** @var bool Whether edit modal is shown */
    public bool $showEditModal = false;

    /** @var bool Whether bulk action confirmation modal is shown */
    public bool $showBulkActionModal = false;

    /** @var bool Whether image preview modal is shown */
    public bool $showImageModal = false;

    /** @var bool Whether product image preview modal is shown */
    public bool $showProductImageModal = false;

    /** @var bool Whether image delete confirmation modal is shown */
    public bool $showImageDeleteModal = false;

    /** @var ?array<string, mixed> The image being viewed in the modal */
    public ?array $viewingImage = null;

    /** @var ?array<string, mixed> The product image being viewed in the modal */
    public ?array $viewingProductImage = null;

    /** @var ?array<string, mixed> The image to be deleted */
    public ?array $imageToDelete = null;

    // Edit State
    /** @var ?Product Product being edited */
    public ?Product $editingProduct = null;

    /** @var bool Whether edit modal is open */
    public $editModalOpen = false;

    /** @var array Edit form data */
    public $editForm = [
        'name' => '',
        'slug' => '',
        'serial' => '',
        'code' => '',
        'is_active' => true,
        'description' => '',
        'category_id' => '',
        'prices' => [
            ['price' => '', 'currency' => 'TRY'],
            ['price' => '', 'currency' => 'USD']
        ],
        'tags' => []
    ];

    // Add State
    /** @var bool Whether add modal is open */
    public $addModalOpen = false;

    /** @var array Add form data */
    public $addForm = [
        'name' => '',
        'slug' => '',
        'serial' => '',
        'code' => '',
        'is_active' => true,
        'description' => '',
        'category_id' => '',
        'prices' => [
            ['price' => '', 'currency' => 'TRY'],
            ['price' => '', 'currency' => 'USD']
        ],
        'tags' => []
    ];

    // Image Upload State
    /** @var array Current images for editing product */
    public $currentImages = [];

    /** @var array New images being uploaded for editing product */
    public $newImages = [];

    /** @var array New images being uploaded for new product */
    public $newProductImages = [];

    /** @var array Upload progress tracking */
    public $uploadProgress = [];

    /** @var int Iteration counter for file upload component */
    public $iteration = 0;

    // Data Collections
    /** @var Collection<Category> Available categories */
    public $categories;

    /** @var Collection<Tag> Available tags */
    public $allTags;

    /** @var array<string> Product status options */
    public array $statuses = ['active', 'inactive'];

    /** @var array<string> Date field options */
    public array $dateFields = ['created_at', 'updated_at'];

    /** @var array Product visibility statuses */
    public array $productStatuses = [];

    // Services
    /** @var CurrencyService Currency conversion service */
    protected CurrencyService $currencyService;

    /** @var float Current exchange rate */
    public float $exchangeRate;

    // Query String Parameters
    protected $queryString = [
        'search' => ['except' => ''],
        'sortField' => ['except' => 'name'],
        'sortDirection' => ['except' => 'asc'],
        'status' => ['except' => ''],
        'category' => ['except' => ''],
        'dateField' => ['except' => ''],
        'dateDirection' => ['except' => 'DESC']
    ];

    // Event Listeners
    protected $listeners = [
        'upload:started' => 'handleUploadStarted',
        'upload:finished' => 'handleUploadFinished',
        'upload:errored' => 'handleUploadErrored',
        'upload:progress' => 'handleUploadProgress',
        'closeModal' => 'handleModalClose',
        'closeAddModal' => 'handleAddModalClose'
    ];

    /**
     * Validation rules for the component
     *
     * @var array<string, string>
     */
    protected $rules = [
        // Add Form Rules
        'addForm.name' => 'required|min:3|max:255',
        'addForm.slug' => 'required|unique:products,slug',
        'addForm.serial' => 'nullable|unique:products,serial',
        'addForm.code' => 'required|unique:products,code',
        'addForm.is_active' => 'boolean',
        'addForm.description' => 'required|min:10|max:1000',
        'addForm.category_id' => 'required|exists:categories,id',
        'addForm.prices.*.price' => 'nullable|numeric|min:0',
        'newProductImages' => 'nullable|array',
        'newProductImages.*' => 'image|max:2048',
        'addForm.tags' => 'array',
        'addForm.tags.*' => 'exists:tags,id',

        // Edit Form Rules
        'editForm.name' => 'required|min:3|max:255',
        'editForm.slug' => 'required',
        'editForm.serial' => 'nullable',
        'editForm.code' => 'required',
        'editForm.is_active' => 'boolean',
        'editForm.description' => 'required|min:10|max:1000',
        'editForm.category_id' => 'required|exists:categories,id',
        'editForm.prices.*.price' => 'nullable|numeric|min:0',
        'newImages' => 'nullable|array',
        'newImages.*' => 'image|max:2048',
        'editForm.tags' => 'array',
        'editForm.tags.*' => 'exists:tags,id'
    ];

    /**
     * Custom attribute names for validation messages
     *
     * @var array<string, string>
     */
    protected $validationAttributes = [
        // Edit Form Attributes
        'editForm.name' => 'name',
        'editForm.slug' => 'slug',
        'editForm.serial' => 'serial number',
        'editForm.code' => 'product code',
        'editForm.is_active' => 'visibility',
        'editForm.description' => 'description',
        'editForm.category_id' => 'category',
        'editForm.prices.*.price' => 'price',
        'editForm.prices.*.currency' => 'currency',
        'newImages.*' => 'image',

        // Add Form Attributes
        'addForm.name' => 'name',
        'addForm.slug' => 'slug',
        'addForm.serial' => 'serial number',
        'addForm.code' => 'product code',
        'addForm.is_active' => 'visibility',
        'addForm.description' => 'description',
        'addForm.category_id' => 'category',
        'addForm.prices.*.price' => 'price',
        'newProductImages.*' => 'image',
    ];

    /**
     * Initialize the component with currency service
     */
    public function boot(CurrencyService $currencyService): void
    {
        $this->currencyService = $currencyService;
    }

    /**
     * Mount the component and load initial data
     */
    public function mount(): void
    {
        $this->categories = Category::with('children')->whereNull('parent_id')->get();
        $this->allTags = Tag::orderBy('display_order')->get();
        $this->initializeAddForm();
        $this->loadProductStatuses();
        $this->loadExchangeRate();
    }

    /**
     * Initialize the add form with default values
     */
    private function initializeAddForm(): void
    {
        $this->addForm = [
            'name' => '',
            'slug' => '',
            'serial' => '',
            'code' => '',
            'is_active' => true,
            'description' => '',
            'category_id' => '',
            'prices' => [
                ['price' => '', 'currency' => 'TRY'],
                ['price' => '', 'currency' => 'USD']
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

    public function editProduct(int $productId): void
    {
        $this->resetValidation();
        $this->editingProduct = Product::with(['category', 'prices', 'images', 'tags'])->find($productId);

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
            ['price' => '', 'currency' => 'TRY'],
            ['price' => '', 'currency' => 'USD']
        ];

        // Fill in actual prices if they exist
        if ($tryPrice = $prices->first(fn($p) => $p->currency->code === 'TRY')) {
            $formattedPrices[0]['price'] = $tryPrice->base_price;
            $formattedPrices[0]['id'] = $tryPrice->id;
        }

        if ($usdPrice = $prices->first(fn($p) => $p->currency->code === 'USD')) {
            $formattedPrices[1]['price'] = $usdPrice->converted_price;
            $formattedPrices[1]['id'] = $usdPrice->id;
        }

        $this->editForm = [
            'name' => $this->editingProduct->name,
            'slug' => $this->editingProduct->slug,
            'serial' => $this->editingProduct->serial,
            'code' => $this->editingProduct->code,
            'is_active' => $this->editingProduct->is_active,
            'description' => $this->editingProduct->description,
            'category_id' => $this->editingProduct->category_id,
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

    /**
     * Update a product
     */
    public function updateProduct(): void
    {
        $this->validate($this->getEditRules());

        try {
            DB::beginTransaction();

            logger()->info('Starting product update transaction', [
                'product_id' => $this->editingProduct->id,
                'form_data' => $this->editForm
            ]);

            // Update basic product information
            $this->editingProduct->update([
                'name' => $this->editForm['name'],
                'slug' => $this->editForm['slug'],
                'serial' => $this->editForm['serial'],
                'code' => $this->editForm['code'],
                'is_active' => $this->editForm['is_active'],
                'description' => $this->editForm['description'],
                'category_id' => $this->editForm['category_id']
            ]);

            // Update or create price in TRY
            $this->editingProduct->prices()->updateOrCreate(
                [
                    'id' => $this->editForm['prices'][0]['id'] ?? null,
                    'currency_id' => 1, // TRY
                ],
                [
                    'base_price' => $this->editForm['prices'][0]['price'] !== '' ? $this->editForm['prices'][0]['price'] : 0,
                    'converted_price' => $this->editForm['prices'][0]['price'] !== '' ? $this->editForm['prices'][0]['price'] : 0, // For TRY, base and converted are same
                ]
            );

            // Update or create price in USD
            $this->editingProduct->prices()->updateOrCreate(
                [
                    'id' => $this->editForm['prices'][1]['id'] ?? null,
                    'currency_id' => 2, // USD
                ],
                [
                    'base_price' => $this->editForm['prices'][1]['price'] !== '' ? $this->editForm['prices'][1]['price'] : 0,
                    'converted_price' => $this->editForm['prices'][1]['price'] !== '' ? $this->editForm['prices'][1]['price'] : 0, // For USD prices, this will be updated by the scheduled job
                ]
            );

            // Handle new images
            if (!empty($this->newImages)) {
                $hasExistingImages = $this->editingProduct->images()->exists();
                
                foreach ($this->newImages as $index => $image) {
                    try {
                        $path = $image->store('products', 'public');
                        $this->editingProduct->images()->create([
                            'image_url' => $path,
                            'is_primary' => !$hasExistingImages && $index === 0
                        ]);
                    } catch (\Exception $e) {
                        logger()->error('Error uploading image', [
                            'image_index' => $index,
                            'error' => $e->getMessage()
                        ]);
                        // Continue with other images if one fails
                        continue;
                    }
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
                'error' => $e->getMessage()
            ]);

            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Failed to update product: ' . $e->getMessage()
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

    /**
     * Generate a unique slug for the product
     *
     * @param string $form The form type ('edit' or 'add')
     */
    public function generateSlug(string $form = 'edit'): void
    {
        $name = $form === 'edit' ? $this->editForm['name'] : $this->addForm['name'];
        $slug = Str::slug($name);
        $originalSlug = $slug;
        $counter = 1;

        while (Product::where('slug', $slug)
            ->when($form === 'edit' && $this->editingProduct,
                fn($query) => $query->where('id', '!=', $this->editingProduct->id)
            )->exists()
        ) {
            $slug = $originalSlug . '-' . $counter++;
        }

        if ($form === 'edit') {
            $this->editForm['slug'] = $slug;
        } else {
            $this->addForm['slug'] = $slug;
        }
    }

    /**
     * Get the paginated products list with all necessary relationships
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getProductsProperty(): LengthAwarePaginator
    {
        return Product::query()
            ->with([
                'category',
                'tags',
                'prices' => function($query) {
                    $query->with('currency')
                        ->whereHas('currency', function($q) {
                            $q->whereIn('code', ['TRY', 'USD']);
                        });
                },
                'images' => function($query) {
                    $query->orderBy('is_primary', 'desc');
                }
            ])
            ->when($this->search, fn($query) =>
                $query->where(function($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('code', 'like', '%' . $this->search . '%')
                      ->orWhere('serial', 'like', '%' . $this->search . '%');
                })
            )
            ->when($this->status === 'active', fn($query) =>
                $query->where('is_active', true)
            )
            ->when($this->status === 'inactive', fn($query) =>
                $query->where('is_active', false)
            )
            ->when($this->category, fn($query) =>
                $query->whereHas('category', fn($q) =>
                    $q->where('id', $this->category)
                )
            )
            ->when($this->dateField, fn($query) =>
                $query->orderBy($this->dateField, $this->dateDirection)
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
            'newImages' => 'array',
            'newImages.*' => 'image|max:2048' // 2MB Max
        ]);

        $this->iteration++;
    }

    public function removeTemporaryImage($index, $type = 'edit'): void
    {
        if ($type === 'edit') {
            if (isset($this->newImages[$index])) {
                $image = $this->newImages[$index];

                // Delete the temporary file
                if ($image && method_exists($image, 'getFilename')) {
                    $tmpPath = storage_path('app/livewire-tmp/' . $image->getFilename());
                    if (file_exists($tmpPath)) {
                        unlink($tmpPath);
                    }
                }

                // Remove from newImages array using array_values to reindex
                $this->newImages = collect($this->newImages)
                    ->reject(fn($_, $i) => $i == $index)
                    ->values()
                    ->toArray();

                // Reset upload progress for this image
                unset($this->uploadProgress['newImages.' . $index]);
            }
        } else {
            if (isset($this->newProductImages[$index])) {
                $image = $this->newProductImages[$index];

                // Delete the temporary file
                if ($image && method_exists($image, 'getFilename')) {
                    $tmpPath = storage_path('app/livewire-tmp/' . $image->getFilename());
                    if (file_exists($tmpPath)) {
                        unlink($tmpPath);
                    }
                }

                // Remove from newProductImages array using array_values to reindex
                $this->newProductImages = collect($this->newProductImages)
                    ->reject(fn($_, $i) => $i == $index)
                    ->values()
                    ->toArray();

                // Reset upload progress for this image
                unset($this->uploadProgress['newProductImages.' . $index]);
            }
        }
    }

    public function updatedNewProductImages(): void
    {
        $this->validate([
            'newProductImages' => 'array',
            'newProductImages.*' => 'image|max:2048' // 2MB Max
        ]);

        $this->iteration++;
    }

    /**
     * Clean up temporary files after upload
     */
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

    public function handleAddModalClose()
    {
        $this->addModalOpen = false;
        $this->initializeAddForm();
    }

    /**
     * Process bulk actions on selected products
     *
     * @throws \Exception When database transaction fails
     */
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
                        ->update(['is_active' => true]);
                    $message = count($this->selectedProducts) . ' products enabled.';
                    break;

                case 'deactivate':
                    Product::whereIn('id', $this->selectedProducts)
                        ->update(['is_active' => false]);
                    $message = count($this->selectedProducts) . ' products disabled.';
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
                'activate' => 'Are you sure you want to enable ' . count($this->selectedProducts) . ' selected products?',
                'deactivate' => 'Are you sure you want to disable ' . count($this->selectedProducts) . ' selected products?',
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

    /**
     * Create a new product
     */
    public function createProduct(): void
    {
        DB::enableQueryLog();
        $this->validate($this->getAddRules());

        try {
            DB::beginTransaction();

            logger()->info('Starting product creation transaction', [
                'form_data' => $this->addForm
            ]);

            // Create the product
            $product = Product::create([
                'name' => $this->addForm['name'],
                'slug' => $this->addForm['slug'],
                'code' => $this->addForm['code'],
                'serial' => $this->addForm['serial'],
                'is_active' => $this->addForm['is_active'],
                'description' => $this->addForm['description'],
                'category_id' => $this->addForm['category_id']
            ]);

            // Create price in TRY
            $product->prices()->create([
                'currency_id' => 1, // TRY
                'base_price' => $this->addForm['prices'][0]['price'] !== '' ? $this->addForm['prices'][0]['price'] : 0,
                'converted_price' => $this->addForm['prices'][0]['price'] !== '' ? $this->addForm['prices'][0]['price'] : 0, // For TRY, base and converted are same
            ]);

            // Create price in USD
            $product->prices()->create([
                'currency_id' => 2, // USD
                'base_price' => $this->addForm['prices'][1]['price'] !== '' ? $this->addForm['prices'][1]['price'] : 0,
                'converted_price' => $this->addForm['prices'][1]['price'] !== '' ? $this->addForm['prices'][1]['price'] : 0, // For USD prices, this will be updated by the scheduled job
            ]);

            // Sync tags if any are selected
            if (!empty($this->addForm['tags'])) {
                $product->tags()->sync($this->addForm['tags']);
            }

            // Handle product images
            if (!empty($this->newProductImages)) {
                foreach ($this->newProductImages as $index => $image) {
                    try {
                        $path = $image->store('products', 'public');
                        $product->images()->create([
                            'image_url' => $path,
                            'is_primary' => $index === 0 // First image is primary
                        ]);
                    } catch (\Exception $e) {
                        logger()->error('Error uploading image', [
                            'image_index' => $index, 
                            'error' => $e->getMessage()
                        ]);
                        // Continue with other images if one fails
                        continue;
                    }
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
                'error' => $e->getMessage()
            ]);

            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Failed to create product: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Get dynamic validation rules for adding a product
     *
     * @return array<string, string>
     */
    protected function getAddRules(): array
    {
        return [
            'addForm.name' => 'required|min:3|max:255',
            'addForm.slug' => 'required|unique:products,slug',
            'addForm.serial' => 'nullable|unique:products,serial',
            'addForm.code' => 'required|unique:products,code',
            'addForm.is_active' => 'boolean',
            'addForm.description' => 'required|min:10|max:1000',
            'addForm.category_id' => 'required|exists:categories,id',
            'addForm.prices.*.price' => 'nullable|numeric|min:0',
            'newProductImages' => 'nullable|array',
            'newProductImages.*' => 'image|max:2048',
            'addForm.tags' => 'array',
            'addForm.tags.*' => 'exists:tags,id'
        ];
    }

    /**
     * Get dynamic validation rules for editing a product
     *
     * @return array<string, string>
     */
    protected function getEditRules(): array
    {
        return [
            'editForm.name' => 'required|min:3',
            'editForm.slug' => 'required|unique:products,slug,' . ($this->editingProduct?->id ?? ''),
            'editForm.serial' => 'nullable|unique:products,serial,' . ($this->editingProduct?->id ?? ''),
            'editForm.code' => 'required|unique:products,code,' . ($this->editingProduct?->id ?? ''),
            'editForm.is_active' => 'boolean',
            'editForm.description' => 'required|min:10',
            'editForm.category_id' => 'required|exists:categories,id',
            'editForm.prices.*.price' => 'nullable|numeric|min:0',
            'newImages' => 'nullable|array',
            'newImages.*' => 'image|max:2048',
        ];
    }

    /**
     * Render the component
     *
     * @return \Illuminate\View\View
     */
    #[Layout('layouts.backend')]
    public function render()
    {
        return view('livewire.backend.products.products-component', [
            'products' => $this->products
        ]);
    }

    /**
     * Load product statuses for all products
     */
    public function loadProductStatuses(): void
    {
        $products = Product::select('id', 'is_active')->get();

        $this->productStatuses = $products->mapWithKeys(function ($product) {
            return [
                $product->id => [
                    'active' => $product->is_active
                ]
            ];
        })->toArray();
    }

    /**
     * Toggle a tag selection in the edit form
     *
     * @param int $tagId The ID of the tag to toggle
     */
    public function toggleTag(int $tagId): void
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

    /**
     * Toggle a tag selection in the add form
     *
     * @param int $tagId The ID of the tag to toggle
     */
    public function toggleAddTag(int $tagId): void
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

    /**
     * Toggle product visibility
     */
    public function toggleStatus(int $productId): void
    {
        try {
            DB::beginTransaction();

            $product = Product::findOrFail($productId);
            $product->is_active = !$product->is_active;
            $product->save();

            // Update local state
            $this->loadProductStatuses();

            DB::commit();

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => $product->is_active ? 'Product enabled' : 'Product disabled'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            logger()->error('Error toggling product visibility', [
                'product_id' => $productId,
                'error' => $e->getMessage()
            ]);

            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Failed to update product visibility'
            ]);

            // Revert the local state
            $this->loadProductStatuses();
        }
    }

    /**
     * Load the current exchange rate from the currency service
     */
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

    public function toggleEditFormVisibility(): void
    {
        $this->editForm['is_active'] = !$this->editForm['is_active'];
    }

    public function toggleAddFormVisibility(): void
    {
        $this->addForm['is_active'] = !$this->addForm['is_active'];
    }

    /**
     * Get the category tree for the dropdown
     */
    public function getCategoryTree(): array
    {
        return $this->categories->map(function ($category) {
            return [
                'id' => $category->id,
                'name' => $category->name,
                'children' => $category->children->map(function ($child) {
                    return [
                        'id' => $child->id,
                        'name' => $child->name,
                        'children' => $child->children->map(function ($grandChild) {
                            return [
                                'id' => $grandChild->id,
                                'name' => $grandChild->name
                            ];
                        })->toArray()
                    ];
                })->toArray()
            ];
        })->toArray();
    }
}
