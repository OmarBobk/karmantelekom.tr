<?php

declare(strict_types=1);

namespace App\Livewire\Backend\Suppliers;

use App\Livewire\Backend\Concerns\UsesOperationalLocale;
use App\Models\OperationalSupplier;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class SuppliersIndex extends Component
{
    use UsesOperationalLocale;
    use WithPagination;

    private const FIELDS = ['name', 'contactName', 'phone', 'email', 'taxNumber', 'address', 'notes'];

    #[Url(except: '')]
    public string $search = '';

    public bool $formOpen = false;

    #[Locked]
    public ?int $editingSupplierId = null;

    public string $name = '';

    public string $contactName = '';

    public string $phone = '';

    public string $email = '';

    public string $taxNumber = '';

    public string $address = '';

    public string $notes = '';

    public bool $isActive = true;

    public function mount(): void
    {
        $this->authorize('viewAny', OperationalSupplier::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function openCreateForm(): void
    {
        $this->authorize('create', OperationalSupplier::class);

        $this->resetForm();
        $this->formOpen = true;
    }

    public function openEditForm(int $supplierId): void
    {
        $supplier = OperationalSupplier::query()->findOrFail($supplierId);
        $this->authorize('update', $supplier);

        $this->resetForm();
        $this->editingSupplierId = $supplier->id;
        $this->name = $supplier->name;
        $this->contactName = (string) $supplier->contact_name;
        $this->phone = (string) $supplier->phone;
        $this->email = (string) $supplier->email;
        $this->taxNumber = (string) $supplier->tax_number;
        $this->address = (string) $supplier->address;
        $this->notes = (string) $supplier->notes;
        $this->isActive = $supplier->is_active;
        $this->formOpen = true;
    }

    public function closeForm(): void
    {
        $this->resetForm();
        $this->formOpen = false;
    }

    public function save(): void
    {
        $supplier = $this->editingSupplierId !== null
            ? OperationalSupplier::query()->findOrFail($this->editingSupplierId)
            : null;

        $this->authorize($supplier ? 'update' : 'create', $supplier ?? OperationalSupplier::class);

        foreach (self::FIELDS as $field) {
            $this->{$field} = Str::trim($this->{$field});
        }

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'contactName' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'email' => ['nullable', 'email', 'max:255'],
            'taxNumber' => ['nullable', 'string', 'max:64', Rule::unique('operational_suppliers', 'tax_number')->ignore($supplier?->id)],
            'address' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'isActive' => ['boolean'],
        ], attributes: [
            'name' => __('suppliers.fields.name'),
            'contactName' => __('suppliers.fields.contact_name'),
            'phone' => __('suppliers.fields.phone'),
            'email' => __('suppliers.fields.email'),
            'taxNumber' => __('suppliers.fields.tax_number'),
            'address' => __('suppliers.fields.address'),
            'notes' => __('suppliers.fields.notes'),
        ]);

        $attributes = [
            'name' => $validated['name'],
            'contact_name' => $validated['contactName'] ?: null,
            'phone' => $validated['phone'] ?: null,
            'email' => $validated['email'] ?: null,
            'tax_number' => $validated['taxNumber'] ?: null,
            'address' => $validated['address'] ?: null,
            'notes' => $validated['notes'] ?: null,
            'is_active' => $validated['isActive'],
        ];

        $supplier ? $supplier->update($attributes) : OperationalSupplier::query()->create($attributes);

        $this->closeForm();
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => $supplier ? __('suppliers.messages.updated') : __('suppliers.messages.created'),
        ]);
    }

    private function resetForm(): void
    {
        $this->reset(['editingSupplierId', 'isActive', ...self::FIELDS]);
        $this->resetErrorBag();
    }

    #[Layout('layouts.backend')]
    public function render(): View
    {
        $this->authorize('viewAny', OperationalSupplier::class);

        $term = Str::trim($this->search);
        $pattern = '%'.addcslashes($term, '\\%_').'%';

        return view('livewire.backend.suppliers.suppliers-index', [
            'suppliers' => OperationalSupplier::query()
                ->when($term !== '', fn (Builder $query) => $query->where(fn (Builder $matches) => $matches
                    ->where('name', 'like', $pattern)
                    ->orWhere('contact_name', 'like', $pattern)
                    ->orWhere('phone', 'like', $pattern)))
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->paginate(25),
            'isFiltering' => $term !== '',
            ...$this->localeViewData(),
        ])->title(__('suppliers.title'));
    }
}
