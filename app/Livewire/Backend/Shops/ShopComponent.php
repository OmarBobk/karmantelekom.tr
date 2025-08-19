<?php

namespace App\Livewire\Backend\Shops;

use App\Events\ShopAssigned;
use App\Events\ShopCreated;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

class ShopComponent extends Component
{
    use WithPagination;

    public string $search = '';
    public string $sortField = 'name';
    public string $sortDirection = 'asc';
    public int $perPage = 10;
    public bool $showCreateModal = false;
    public bool $showEditModal = false;
    public ?Shop $editingShop = null;

    // Filter properties
    public string $createdAtFilter = '';
    public ?string $salespersonFilter = null;

    // Form properties
    public string $name = '';
    public string $phone = '';
    public string $address = '';
    public array $links = [];
    public array $newLink = ['type' => '', 'url' => ''];
    public ?int $salesperson_id = null;

    protected array $queryString = [
        'search' => ['except' => ''],
        'sortField' => ['except' => 'name'],
        'sortDirection' => ['except' => 'asc'],
        'perPage' => ['except' => 10],
        'createdAtFilter' => ['except' => ''],
        'salespersonFilter' => ['except' => ''],
    ];

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'address' => ['required', 'string'],
            'links' => ['array'],
            'links.*' => ['required', 'max:255'],
            'salesperson_id' => ['nullable', 'exists:users,id'],
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingCreatedAtFilter(): void
    {
        $this->resetPage();
    }

    public function updatingSalespersonFilter(): void
    {
        $this->resetPage();
    }

    public function clearAllFilters(): void
    {
        $this->reset(['search', 'createdAtFilter', 'salespersonFilter']);
        $this->resetPage();
    }

    public function addLink(): void
    {
        $this->validate([
            'newLink.type' => ['required', 'string', 'max:255'],
            'newLink.url' => ['required', 'max:255'],
        ]);

        // Convert type to lowercase and remove spaces for consistency
        $type = strtolower(str_replace(' ', '_', $this->newLink['type']));

        // Add the link to the links array
        $this->links[$type] = $this->newLink['url'];

        // Reset the newLink form
        $this->newLink = ['type' => '', 'url' => ''];

        // Reset validation errors for the newLink fields
        $this->resetValidation(['newLink.type', 'newLink.url']);
    }

    public function removeLink(string $type): void
    {
        unset($this->links[$type]);
    }

    public function create(): void
    {
        try {
            $this->authorize('create', Shop::class);
            $this->resetValidation();
            $this->reset(['name', 'phone', 'address', 'links', 'salesperson_id', 'newLink']);
            $this->showCreateModal = true;

        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Failed Show Create Shop Modal. Please try again. Error: ' . $e->getMessage(),
            ]);
        }
    }

    public function save(): void
    {
        try {
            $this->authorize('create', Shop::class);
            $this->validate();

            $previousSalespersonId = null;

            // Check if a salesperson is being assigned
            $isSalespersonAssignment = !empty($this->salesperson_id);

            // Create shop
            $shop = Shop::create([
                'name' => $this->name,
                'phone' => $this->phone,
                'address' => $this->address,
                'links' => $this->links,
                'salesperson_id' => $this->salesperson_id,
            ]);

            // Dispatch ShopCreated event
            ShopCreated::dispatch($shop, auth()->id());

            // Fire ShopAssigned event if a salesperson is assigned
            if ($isSalespersonAssignment) {
                $salesperson = User::find($this->salesperson_id);
                if ($salesperson) {
                    event(new ShopAssigned($shop, $salesperson, auth()->user(), null));
                }
            }

            $this->showCreateModal = false;
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Shop created successfully!'
            ]);
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Failed to create shop. Please try again.'
            ]);
        }
    }

    public function edit(Shop $shop): void
    {
        try {
            $this->authorize('update', $shop);

            $this->editingShop = $shop;
            $this->name = $shop->name;
            $this->phone = $shop->phone;
            $this->address = $shop->address;
            $this->links = $shop->links ?? [];
            $this->salesperson_id = $shop->salesperson_id;

            $this->resetValidation();
            $this->showEditModal = true;
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Failed to edit shop. Please try again. Error: ' . $e->getMessage()
            ]);
        }
    }

    public function update(): void
    {
        try {
            $this->authorize('update', $this->editingShop);
            $this->validate();

            // Store the previous salesperson for comparison
            $previousSalespersonId = $this->editingShop->salesperson_id;
            $previousSalesperson = $previousSalespersonId ? User::find($previousSalespersonId) : null;

            // Check if this is a salesperson assignment change
            $isSalespersonChange = $this->salesperson_id && $this->salesperson_id !== $previousSalespersonId;

            // Update shop with or without activity logging based on salesperson change
            if ($isSalespersonChange) {
                // Update shop without activity logging when salesperson is being assigned
//                $this->editingShop->disableLogging();
                $this->editingShop->update([
                    'name' => $this->name,
                    'phone' => $this->phone,
                    'address' => $this->address,
                    'links' => $this->links,
                    'salesperson_id' => $this->salesperson_id,
                ]);
            } else {
                // Update shop with normal activity logging
                $this->editingShop->update([
                    'name' => $this->name,
                    'phone' => $this->phone,
                    'address' => $this->address,
                    'links' => $this->links,
                    'salesperson_id' => $this->salesperson_id,
                ]);
            }

            // Fire ShopAssigned event if salesperson changed
            if ($isSalespersonChange) {
                $newSalesperson = User::find($this->salesperson_id);
                if ($newSalesperson) {
                    event(new ShopAssigned($this->editingShop, $newSalesperson, auth()->user(), $previousSalesperson));
                }
            }

            $this->showEditModal = false;
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Shop updated successfully!'
            ]);
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Failed to update shop. Please try again.'
            ]);
        }
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

    public function delete(Shop $shop): void
    {
        try {
            $this->authorize('delete', $shop);

            $shop->delete();
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Shop deleted successfully.'
            ]);
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    #[Layout('layouts.backend')]
    #[Title('Shops')]
    public function render()
    {
        try {
            $this->authorize('viewAny', Shop::class);

            $query = Shop::query()
                ->when($this->search, function ($query) {
                    $query->search($this->search, $this->sortField, $this->sortDirection);
                }, function ($query) {
                    $query->orderBy($this->sortField, $this->sortDirection);
                })
                ->when($this->createdAtFilter, function ($query) {
                    $this->applyCreatedAtFilter($query);
                })
                ->when($this->salespersonFilter, function ($query) {
                    if ($this->salespersonFilter === 'unassigned') {
                        $query->whereNull('salesperson_id');
                    } else {
                        $query->where('salesperson_id', (int) $this->salespersonFilter);
                    }
                });

            $user = auth()->user();

            // Role-based filtering
            if (!$user->hasRole('admin')) {
                $query->visibleTo($user);
            }

            $shops = $query->withCount('monthlyOrders')
                ->with(['owner', 'salesperson'])
                ->paginate($this->perPage);

            $salespeople = \App\Models\User::role('salesperson')->get();
            $shopOwners = \App\Models\User::role('shop_owner')->get();

            return view('livewire.backend.shops.shop-component', [
                'shops' => $shops,
                'salespeople' => $salespeople,
                'shopOwners' => $shopOwners
            ]);

        } catch (Throwable $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => $e->getMessage()
            ]);

            return view('livewire.backend.shops.shop-component', [
                'shops' => new LengthAwarePaginator(
                    collect(), // Empty collection
                    0,         // Total items
                    $this->perPage, // Items per page
                    $this->page ?? 1 // Current page (optional)
                ),
                'salespeople' => collect()
            ]);
        }
    }

    private function applyCreatedAtFilter($query): void
    {
        $now = now();
        
        switch ($this->createdAtFilter) {
            case 'today':
                $query->whereDate('created_at', $now->toDateString());
                break;
            case 'yesterday':
                $query->whereDate('created_at', $now->subDay()->toDateString());
                break;
            case 'this_week':
                $query->whereBetween('created_at', [$now->startOfWeek(), $now->endOfWeek()]);
                break;
            case 'last_week':
                $query->whereBetween('created_at', [$now->subWeek()->startOfWeek(), $now->subWeek()->endOfWeek()]);
                break;
            case 'this_month':
                $query->whereMonth('created_at', $now->month)
                      ->whereYear('created_at', $now->year);
                break;
            case 'last_month':
                $query->whereMonth('created_at', $now->subMonth()->month)
                      ->whereYear('created_at', $now->subMonth()->year);
                break;
            case 'this_year':
                $query->whereYear('created_at', $now->year);
                break;
            case 'last_year':
                $query->whereYear('created_at', $now->subYear()->year);
                break;
        }
    }
}
