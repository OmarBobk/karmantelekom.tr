<?php

namespace App\Livewire\Backend\Shops;

use App\Models\Shop;
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

    // Form properties
    public string $name = '';
    public string $phone = '';
    public string $address = '';
    public array $links = [];
    public array $newLink = ['type' => '', 'url' => ''];
    public ?int $user_id = null;

    protected array $queryString = [
        'search' => ['except' => ''],
        'sortField' => ['except' => 'name'],
        'sortDirection' => ['except' => 'asc'],
        'perPage' => ['except' => 10],
    ];

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'address' => ['required', 'string'],
            'links' => ['array'],
            'links.*' => ['required', 'max:255'],
            'user_id' => ['nullable', 'exists:users,id'],
        ];
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
            $this->reset(['name', 'phone', 'address', 'links', 'user_id', 'newLink']);
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
            $shop = Shop::create([
                'name' => $this->name,
                'phone' => $this->phone,
                'address' => $this->address,
                'links' => $this->links,
                'user_id' => $this->user_id,
            ]);

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
            $this->user_id = $shop->user_id;
            
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

            $this->editingShop->update([
                'name' => $this->name,
                'phone' => $this->phone,
                'address' => $this->address,
                'links' => $this->links,
                'user_id' => $this->user_id,
            ]);

            $this->showEditModal = false;
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Shop updated successfully!'
            ]);
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Failed to update shop. Please try again. Error: ' . $e->getMessage()
            ]);
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
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
}
