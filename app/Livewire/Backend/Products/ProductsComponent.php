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
        'is_retail_active' => false,
        'is_wholesale_active' => false,
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

    // Add State
    /** @var bool Whether add modal is open */
    public $addModalOpen = false;
    
    /** @var array Add form data */
    public $addForm = [
        'name' => '',
        'slug' => '',
        'serial' => '',
        'code' => '',
        'is_retail_active' => false,
        'is_wholesale_active' => false,
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
    
    /** @var Collection<Supplier> Available suppliers */
    public $suppliers;
    
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
        'addForm.is_retail_active' => 'boolean',
        'addForm.is_wholesale_active' => 'boolean',
        'addForm.description' => 'required|min:10|max:1000',
        'addForm.category_id' => 'required|exists:categories,id',
        'addForm.supplier_id' => 'required|exists:suppliers,id',
        'addForm.prices.*.price' => 'required|numeric|min:0',
        'addForm.prices.0.price' => 'required|numeric|min:0',
        'addForm.prices.1.price' => 'required|numeric|min:0',
        'addForm.prices.2.price' => 'required|numeric|min:0',
        'newProductImages.*' => 'image|max:2048',
        'addForm.tags' => 'array',
        'addForm.tags.*' => 'exists:tags,id',
        
        // Edit Form Rules
        'editForm.name' => 'required|min:3|max:255',
        'editForm.slug' => 'required',
        'editForm.serial' => 'nullable',
        'editForm.code' => 'required',
        'editForm.is_retail_active' => 'boolean',
        'editForm.is_wholesale_active' => 'boolean',
        'editForm.description' => 'required|min:10|max:1000',
        'editForm.category_id' => 'required|exists:categories,id',
        'editForm.supplier_id' => 'required|exists:suppliers,id',
        'editForm.prices.*.price' => 'required|numeric|min:0',
        'editForm.prices.0.price' => 'required|numeric|min:0',
        'editForm.prices.1.price' => 'required|numeric|min:0',
        'editForm.prices.2.price' => 'required|numeric|min:0',
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
        'editForm.is_retail_active' => 'retail visibility',
        'editForm.is_wholesale_active' => 'wholesale visibility',
        'editForm.description' => 'description',
        'editForm.category_id' => 'category',
        'editForm.supplier_id' => 'supplier',
        'editForm.prices.*.price' => 'price',
        'editForm.prices.*.currency' => 'currency',
        'editForm.prices.*.price_type' => 'price type',
        'newImages.*' => 'image',
        
        // Add Form Attributes
        'addForm.name' => 'name',
        'addForm.slug' => 'slug',
        'addForm.serial' => 'serial number',
        'addForm.code' => 'product code',
        'addForm.is_retail_active' => 'retail visibility',
        'addForm.is_wholesale_active' => 'wholesale visibility',
        'addForm.description' => 'description',
        'addForm.category_id' => 'category',
        'addForm.supplier_id' => 'supplier',
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
        $this->categories = Category::all();
        $this->suppliers = Supplier::all();
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
            'is_retail_active' => false,
            'is_wholesale_active' => false,
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
            'is_retail_active' => $this->editingProduct->is_retail_active,
            'is_wholesale_active' => $this->editingProduct->is_wholesale_active,
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

    /**
     * Update an existing product with its associated prices, tags, and images
     * 
     * @throws \Exception When database transaction fails
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
                'is_retail_active' => $this->editForm['is_retail_active'],
                'is_wholesale_active' => $this->editForm['is_wholesale_active'],
                'description' => $this->editForm['description'],
                'category_id' => $this->editForm['category_id'],
                'supplier_id' => $this->editForm['supplier_id']
            ]);

            // Update or create retail price in TRY
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

            // Update or create wholesale price in TRY
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

            // Update or create wholesale price in USD
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
                'message' => app()->environment('local') 
                    ? 'Error: ' . $e->getMessage() 
                    : 'Error updating product. Please try again.'
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
                'supplier',
                'tags',
                'prices' => function($query) {
                    $query->with('currency')
                        ->whereIn('price_type', ['retail', 'wholesale'])
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
            ->when($this->status === 'retail_active', fn($query) =>
                $query->where('is_retail_active', true)
            )
            ->when($this->status === 'retail_inactive', fn($query) =>
                $query->where('is_retail_active', false)
            )
            ->when($this->status === 'wholesale_active', fn($query) =>
                $query->where('is_wholesale_active', true)
            )
            ->when($this->status === 'wholesale_inactive', fn($query) =>
                $query->where('is_wholesale_active', false)
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

                case 'activate_retail':
                    Product::whereIn('id', $this->selectedProducts)
                        ->update(['is_retail_active' => true]);
                    $message = count($this->selectedProducts) . ' products enabled for retail visibility.';
                    break;

                case 'deactivate_retail':
                    Product::whereIn('id', $this->selectedProducts)
                        ->update(['is_retail_active' => false]);
                    $message = count($this->selectedProducts) . ' products disabled for retail visibility.';
                    break;

                case 'activate_wholesale':
                    Product::whereIn('id', $this->selectedProducts)
                        ->update(['is_wholesale_active' => true]);
                    $message = count($this->selectedProducts) . ' products enabled for wholesale visibility.';
                    break;

                case 'deactivate_wholesale':
                    Product::whereIn('id', $this->selectedProducts)
                        ->update(['is_wholesale_active' => false]);
                    $message = count($this->selectedProducts) . ' products disabled for wholesale visibility.';
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
                'activate_retail' => 'Are you sure you want to enable retail visibility for ' . count($this->selectedProducts) . ' selected products?',
                'deactivate_retail' => 'Are you sure you want to disable retail visibility for ' . count($this->selectedProducts) . ' selected products?',
                'activate_wholesale' => 'Are you sure you want to enable wholesale visibility for ' . count($this->selectedProducts) . ' selected products?',
                'deactivate_wholesale' => 'Are you sure you want to disable wholesale visibility for ' . count($this->selectedProducts) . ' selected products?',
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
     * Create a new product with associated prices, tags, and images
     * 
     * @throws \Exception When database transaction fails
     */
    public function createProduct(): void
    {
        DB::enableQueryLog();
        $this->validate($this->getAddRules());

        // Validate that at least one visibility option is enabled
        if (!$this->addForm['is_retail_active'] && !$this->addForm['is_wholesale_active']) {
            $this->addError('addForm.is_retail_active', 'At least one visibility option must be enabled.');
            $this->addError('addForm.is_wholesale_active', 'At least one visibility option must be enabled.');
            return;
        }

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
                'is_retail_active' => $this->addForm['is_retail_active'],
                'is_wholesale_active' => $this->addForm['is_wholesale_active'],
                'description' => $this->addForm['description'],
                'category_id' => $this->addForm['category_id'],
                'supplier_id' => $this->addForm['supplier_id'],
            ]);

            // Create retail price in TRY
            $product->prices()->create([
                'currency_id' => 1, // TRY
                'price_type' => 'retail',
                'base_price' => $this->addForm['prices'][0]['price'],
                'converted_price' => $this->addForm['prices'][0]['price'], // For TRY, base and converted are same
            ]);

            // Create wholesale price in TRY
            $product->prices()->create([
                'currency_id' => 1, // TRY
                'price_type' => 'wholesale',
                'base_price' => $this->addForm['prices'][1]['price'],
                'converted_price' => $this->addForm['prices'][1]['price'], // For TRY, base and converted are same
            ]);

            // Create wholesale price in USD
            $product->prices()->create([
                'currency_id' => 2, // USD
                'price_type' => 'wholesale',
                'base_price' => $this->addForm['prices'][2]['price'],
                'converted_price' => $this->addForm['prices'][2]['price'], // For USD prices, this will be updated by the scheduled job
            ]);

            // Sync tags if any are selected
            if (!empty($this->addForm['tags'])) {
                $product->tags()->sync($this->addForm['tags']);
            }

            // Handle product images
            if ($this->newProductImages) {
                foreach ($this->newProductImages as $index => $image) {
                    $path = $image->store('products', 'public');
                    $product->images()->create([
                        'image_url' => $path,
                        'is_primary' => $index === 0 // First image is primary
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
                'message' => app()->environment('local') 
                    ? 'Error: ' . $e->getMessage() 
                    : 'Error creating product. Please try again.'
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
            'addForm.is_retail_active' => 'boolean',
            'addForm.is_wholesale_active' => 'boolean',
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
            'editForm.is_retail_active' => 'boolean',
            'editForm.is_wholesale_active' => 'boolean',
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
        $products = Product::select('id', 'is_retail_active', 'is_wholesale_active')->get();
        
        $this->productStatuses = $products->mapWithKeys(function ($product) {
            return [
                $product->id => [
                    'retail' => $product->is_retail_active,
                    'wholesale' => $product->is_wholesale_active
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
     * Toggle the retail visibility status of a product
     * 
     * @param int $productId The ID of the product
     */
    public function toggleStatus(int $productId, string $type = 'retail'): void
    {
        try {
            DB::beginTransaction();

            $product = Product::findOrFail($productId);
            
            // Toggle visibility based on the type parameter
            if ($type === 'wholesale') {
                $product->is_wholesale_active = !$product->is_wholesale_active;
                $message = $product->is_wholesale_active ? 'Wholesale visibility enabled' : 'Wholesale visibility disabled';
            } else {
                $product->is_retail_active = !$product->is_retail_active;
                $message = $product->is_retail_active ? 'Retail visibility enabled' : 'Retail visibility disabled';
            }

            $product->save();

            // Update local state
            $this->loadProductStatuses();

            DB::commit();

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => $message
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            logger()->error('Error toggling product visibility', [
                'product_id' => $productId,
                'type' => $type,
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

    public function toggleEditFormRetailVisibility(): void
    {
        $this->editForm['is_retail_active'] = !$this->editForm['is_retail_active'];
    }

    public function toggleEditFormWholesaleVisibility(): void
    {
        $this->editForm['is_wholesale_active'] = !$this->editForm['is_wholesale_active'];
    }

    public function toggleAddFormRetailVisibility(): void
    {
        $this->addForm['is_retail_active'] = !$this->addForm['is_retail_active'];
    }

    public function toggleAddFormWholesaleVisibility(): void
    {
        $this->addForm['is_wholesale_active'] = !$this->addForm['is_wholesale_active'];
    }
}
