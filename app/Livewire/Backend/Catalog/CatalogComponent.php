<?php

declare(strict_types=1);

namespace App\Livewire\Backend\Catalog;

use App\Models\Currency;
use App\Models\Product;
use App\Models\Tag;
use App\Models\WholesaleProduct;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Backend Catalog Management Component
 *
 * Handles all wholesale product catalog management operations including:
 * - Listing wholesale products with filtering and sorting
 * - Creating new wholesale products
 * - Editing existing wholesale products
 * - Managing wholesale product tags
 * - Bulk operations
 */
class CatalogComponent extends Component
{
    use WithPagination;

    // Search and Filter Properties
    /** @var string Search query for filtering wholesale products */
    public string $search = '';

    /** @var string Field to sort wholesale products by */
    public string $sortField = 'created_at';

    /** @var string Sort direction (ASC/DESC) */
    public string $sortDirection = 'DESC';

    /** @var string Filter by wholesale product status */
    public string $status = '';

    /** @var string Filter by product */
    public string $product = '';

    /** @var string Filter by currency */
    public string $currency = '';

    /** @var array<string> Selected wholesale product IDs for bulk actions */
    public array $selectedProducts = [];

    /** @var bool Whether all wholesale products are selected */
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

    // Edit State
    /** @var ?WholesaleProduct Wholesale product being edited */
    public ?WholesaleProduct $editingProduct = null;

    /** @var bool Whether edit modal is open */
    public $editModalOpen = false;

    /** @var array Edit form data */
    public $editForm = [
        'product_id' => '',
        'price' => '',
        'min_qty' => 1,
        'max_qty' => 10,
        'currency_id' => '',
        'is_active' => true,
        'tags' => []
    ];

    // Add State
    /** @var bool Whether add modal is open */
    public $addModalOpen = false;

    /** @var array Add form data */
    public $addForm = [
        'product_id' => '',
        'price' => '',
        'min_qty' => 1,
        'max_qty' => 10,
        'currency_id' => '',
        'is_active' => true,
        'tags' => []
    ];

    // Data Collections
    /** @var Collection<Product> Available products */
    public $products;

    /** @var Collection<Tag> Available tags */
    public $allTags;

    /** @var Collection<Currency> Available currencies */
    public $currencies;

    /** @var array<string> Wholesale product status options */
    public array $statuses = ['active', 'inactive'];

    // Query String Parameters
    protected $queryString = [
        'search' => ['except' => ''],
        'sortField' => ['except' => 'created_at'],
        'sortDirection' => ['except' => 'DESC'],
        'status' => ['except' => ''],
        'product' => ['except' => ''],
        'currency' => ['except' => '']
    ];

    /**
     * Validation rules for the component
     *
     * @var array<string, string>
     */
    protected $rules = [
        // Add Form Rules
        'addForm.product_id' => 'required|exists:products,id',
        'addForm.price' => 'required|numeric|min:0',
        'addForm.min_qty' => 'required|integer|min:1',
        'addForm.max_qty' => 'required|integer|min:1|gte:addForm.min_qty',
        'addForm.currency_id' => 'required|exists:currencies,id',
        'addForm.is_active' => 'boolean',
        'addForm.tags' => 'array',
        'addForm.tags.*' => 'exists:tags,id',

        // Edit Form Rules
        'editForm.product_id' => 'required|exists:products,id',
        'editForm.price' => 'required|numeric|min:0',
        'editForm.min_qty' => 'required|integer|min:1',
        'editForm.max_qty' => 'required|integer|min:1|gte:editForm.min_qty',
        'editForm.currency_id' => 'required|exists:currencies,id',
        'editForm.is_active' => 'boolean',
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
        'editForm.product_id' => 'product',
        'editForm.price' => 'price',
        'editForm.min_qty' => 'minimum quantity',
        'editForm.max_qty' => 'maximum quantity',
        'editForm.currency_id' => 'currency',
        'editForm.is_active' => 'visibility',
        'editForm.tags' => 'tags',

        // Add Form Attributes
        'addForm.product_id' => 'product',
        'addForm.price' => 'price',
        'addForm.min_qty' => 'minimum quantity',
        'addForm.max_qty' => 'maximum quantity',
        'addForm.currency_id' => 'currency',
        'addForm.is_active' => 'visibility',
        'addForm.tags' => 'tags'
    ];

    /**
     * Mount the component and load initial data
     */
    public function mount(): void
    {
        $this->products = Product::with(['images', 'category'])->orderBy('name')->get();
        $this->allTags = Tag::orderBy('display_order')->get();
        $this->currencies = Currency::where('is_active', true)->orderBy('code')->get();
        $this->initializeAddForm();
    }

    /**
     * Initialize the add form with default values
     */
    private function initializeAddForm(): void
    {
        $this->addForm = [
            'product_id' => '',
            'price' => '',
            'min_qty' => 1,
            'max_qty' => 10,
            'currency_id' => $this->currencies->where('code', 'TRY')->first()?->id ?? '',
            'is_active' => true,
            'tags' => []
        ];
        $this->resetValidation('addForm.*');
        $this->reset(['addForm']);
    }

    public function updatingAddModalOpen(): void
    {
        $this->initializeAddForm();
    }

    public function openAddModal(): void
    {
        $this->addModalOpen = true;
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
            $this->selectedProducts = $this->wholesaleProducts->pluck('id')->map(fn($id) => (string) $id)->toArray();
        } else {
            $this->selectedProducts = [];
        }
    }

    public function confirmDelete(int $productId): void
    {
        $this->editingProduct = WholesaleProduct::find($productId);
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
                'message' => 'Wholesale product deleted successfully!'
            ]);
        }
    }

    public function editProduct(int $productId): void
    {
        $this->resetValidation();
        $this->editingProduct = WholesaleProduct::with(['product.images', 'product.category', 'tags', 'currency'])->find($productId);

        if (!$this->editingProduct) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Wholesale product not found!'
            ]);
            return;
        }

        $this->editForm = [
            'id' => $this->editingProduct->id,
            'product_id' => $this->editingProduct->product_id,
            'price' => $this->editingProduct->price,
            'min_qty' => $this->editingProduct->min_qty,
            'max_qty' => $this->editingProduct->max_qty,
            'currency_id' => $this->editingProduct->currency_id,
            'is_active' => $this->editingProduct->is_active,
            'tags' => $this->editingProduct->tags->pluck('id')->toArray()
        ];

        $this->editModalOpen = true;
    }

    /**
     * Update a wholesale product
     */
    public function updateProduct(): void
    {
        $this->validate($this->getEditRules());

        try {
            DB::beginTransaction();

            logger()->info('Starting wholesale product update transaction', [
                'wholesale_product_id' => $this->editingProduct->id,
                'form_data' => $this->editForm
            ]);

            // Update basic wholesale product information
            $this->editingProduct->update([
                'product_id' => $this->editForm['product_id'],
                'price' => $this->editForm['price'],
                'min_qty' => $this->editForm['min_qty'],
                'max_qty' => $this->editForm['max_qty'],
                'currency_id' => $this->editForm['currency_id'],
                'is_active' => $this->editForm['is_active']
            ]);

            // Update tags
            $this->editingProduct->tags()->sync($this->editForm['tags']);

            DB::commit();

            $this->reset(['editModalOpen', 'editingProduct', 'editForm']);
            $this->dispatch('closeModal');

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Wholesale product updated successfully!'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            logger()->error('Error updating wholesale product', [
                'wholesale_product_id' => $this->editingProduct->id,
                'error' => $e->getMessage()
            ]);

            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Failed to update wholesale product: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Get the paginated wholesale products list with all necessary relationships
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getWholesaleProductsProperty(): LengthAwarePaginator
    {
        return WholesaleProduct::query()
            ->with([
                'product' => function($query) {
                    $query->with(['images' => function($q) {
                        $q->orderBy('is_primary', 'desc');
                    }, 'category']);
                },
                'tags',
                'currency'
            ])
            ->when($this->search, fn($query) =>
                $query->whereHas('product', function($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('code', 'like', '%' . $this->search . '%')
                      ->orWhere('tr_name', 'like', '%' . $this->search . '%')
                      ->orWhere('ar_name', 'like', '%' . $this->search . '%');
                })
            )
            ->when($this->status === 'active', fn($query) =>
                $query->where('is_active', true)
            )
            ->when($this->status === 'inactive', fn($query) =>
                $query->where('is_active', false)
            )
            ->when($this->product, fn($query) =>
                $query->where('product_id', $this->product)
            )
            ->when($this->currency, fn($query) =>
                $query->where('currency_id', $this->currency)
            )
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(10);
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

    public function updatedAddModalOpen($value): void
    {
        if (!$value) {
            $this->initializeAddForm();
        }
    }

    /**
     * Process bulk actions on selected wholesale products
     *
     * @throws \Exception When database transaction fails
     */
    public function processBulkAction(): void
    {
        if (empty($this->selectedProducts)) {
            $this->dispatch('notify', [
                'type' => 'warning',
                'message' => 'Please select wholesale products to perform bulk action.'
            ]);
            return;
        }

        try {
            DB::beginTransaction();

            switch ($this->bulkAction) {
                case 'delete':
                    WholesaleProduct::whereIn('id', $this->selectedProducts)->delete();
                    $message = count($this->selectedProducts) . ' wholesale products deleted successfully.';
                    break;

                case 'activate':
                    WholesaleProduct::whereIn('id', $this->selectedProducts)
                        ->update(['is_active' => true]);
                    $message = count($this->selectedProducts) . ' wholesale products enabled.';
                    break;

                case 'deactivate':
                    WholesaleProduct::whereIn('id', $this->selectedProducts)
                        ->update(['is_active' => false]);
                    $message = count($this->selectedProducts) . ' wholesale products disabled.';
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
                    'message' => 'Please select wholesale products to perform bulk action.'
                ]);
                $this->bulkAction = '';
                return;
            }

            $this->pendingBulkAction = $this->bulkAction;
            $this->bulkActionMessage = match($this->bulkAction) {
                'delete' => 'Are you sure you want to delete ' . count($this->selectedProducts) . ' selected wholesale products?',
                'activate' => 'Are you sure you want to enable ' . count($this->selectedProducts) . ' selected wholesale products?',
                'deactivate' => 'Are you sure you want to disable ' . count($this->selectedProducts) . ' selected wholesale products?',
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

    /**
     * Create a new wholesale product
     */
    public function createProduct(): void
    {
        $this->validate($this->getAddRules());

        try {
            logger()->info('Starting wholesale product creation process', [
                'form_data' => $this->addForm
            ]);

            DB::beginTransaction();

            // Create the wholesale product
            $wholesaleProduct = WholesaleProduct::create([
                'product_id' => $this->addForm['product_id'],
                'price' => $this->addForm['price'],
                'min_qty' => $this->addForm['min_qty'],
                'max_qty' => $this->addForm['max_qty'],
                'currency_id' => $this->addForm['currency_id'],
                'is_active' => $this->addForm['is_active']
            ]);

            logger()->info('Wholesale product created successfully', [
                'wholesale_product_id' => $wholesaleProduct->id,
                'product_id' => $wholesaleProduct->product_id
            ]);

            // Sync tags if any are selected
            if (!empty($this->addForm['tags'])) {
                $wholesaleProduct->tags()->sync($this->addForm['tags']);
            }

            DB::commit();

            logger()->info('Wholesale product creation completed successfully', [
                'wholesale_product_id' => $wholesaleProduct->id
            ]);

            $this->addModalOpen = false;
            $this->reset(['addForm']);

            $this->dispatch('closeAddModal');
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Wholesale product created successfully!'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            logger()->error('Error creating wholesale product', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Failed to create wholesale product: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Get dynamic validation rules for adding a wholesale product
     *
     * @return array<string, string>
     */
    protected function getAddRules(): array
    {
        return [
            'addForm.product_id' => 'required|exists:products,id',
            'addForm.price' => 'required|numeric|min:0',
            'addForm.min_qty' => 'required|integer|min:1',
            'addForm.max_qty' => 'required|integer|min:1|gte:addForm.min_qty',
            'addForm.currency_id' => 'required|exists:currencies,id',
            'addForm.is_active' => 'boolean',
            'addForm.tags' => 'array',
            'addForm.tags.*' => 'exists:tags,id'
        ];
    }

    /**
     * Get dynamic validation rules for editing a wholesale product
     *
     * @return array<string, string>
     */
    protected function getEditRules(): array
    {
        return [
            'editForm.product_id' => 'required|exists:products,id',
            'editForm.price' => 'required|numeric|min:0',
            'editForm.min_qty' => 'required|integer|min:1',
            'editForm.max_qty' => 'required|integer|min:1|gte:editForm.min_qty',
            'editForm.currency_id' => 'required|exists:currencies,id',
            'editForm.is_active' => 'boolean',
            'editForm.tags' => 'array',
            'editForm.tags.*' => 'exists:tags,id'
        ];
    }

    /**
     * Render the component
     *
     * @return \Illuminate\View\View
     */
    #[Layout('layouts.backend')]
    #[Title('Catalog Manager')]
    public function render()
    {
        return view('livewire.backend.catalog.catalog-component', [
            'wholesaleProducts' => $this->wholesaleProducts
        ]);
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
     * Toggle wholesale product visibility
     */
    public function toggleStatus(int $productId): void
    {
        try {
            DB::beginTransaction();

            $wholesaleProduct = WholesaleProduct::findOrFail($productId);
            $wholesaleProduct->is_active = !$wholesaleProduct->is_active;
            $wholesaleProduct->save();

            DB::commit();

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => $wholesaleProduct->is_active ? 'Wholesale product enabled' : 'Wholesale product disabled'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            logger()->error('Error toggling wholesale product visibility', [
                'wholesale_product_id' => $productId,
                'error' => $e->getMessage()
            ]);

            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Failed to update wholesale product visibility'
            ]);
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
}
