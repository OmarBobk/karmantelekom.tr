<?php

declare(strict_types=1);

namespace App\Livewire\Backend\Stores;

use App\Livewire\Backend\OperationalLocale;
use App\Models\Store;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

class StoresIndex extends Component
{
    public bool $formOpen = false;

    #[Locked]
    public ?int $editingStoreId = null;

    public string $code = '';

    public string $name = '';

    public string $phone = '';

    public string $address = '';

    public function boot(): void
    {
        OperationalLocale::apply();
    }

    public function mount(): void
    {
        $this->authorize('viewAny', Store::class);
    }

    public function openCreateForm(): void
    {
        $this->authorize('create', Store::class);

        $this->resetForm();
        $this->formOpen = true;
    }

    public function openEditForm(int $storeId): void
    {
        $store = Store::findOrFail($storeId);
        $this->authorize('update', $store);

        $this->resetForm();
        $this->editingStoreId = $store->id;
        $this->code = $store->code;
        $this->name = $store->name;
        $this->phone = (string) $store->phone;
        $this->address = (string) $store->address;
        $this->formOpen = true;
    }

    public function closeForm(): void
    {
        $this->resetForm();
        $this->formOpen = false;
    }

    public function save(): void
    {
        $store = $this->editingStoreId !== null ? Store::findOrFail($this->editingStoreId) : null;

        $this->authorize($store ? 'update' : 'create', $store ?? Store::class);

        $validated = $this->validate($this->rules($store));
        $details = [
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?: null,
            'address' => $validated['address'] ?: null,
        ];

        DB::transaction(function () use ($store, $validated, $details): void {
            if ($store === null) {
                $created = Store::create(['code' => $validated['code'], ...$details]);
                $this->logStoreChange($created, 'store_created', "Store {$created->code} was created");

                return;
            }

            $store->update($details);

            if ($store->wasChanged()) {
                $this->logStoreChange($store, 'store_updated', "Store {$store->code} was updated", [
                    'changes' => $store->getChanges(),
                ]);
            }
        });

        $message = $store ? __('stores.messages.updated') : __('stores.messages.created');

        $this->closeForm();
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => $message,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(?Store $store = null): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
        ];

        if ($store === null) {
            $rules['code'] = ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('stores', 'code')];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return __('stores.validation');
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return __('stores.attributes');
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function logStoreChange(Store $store, string $event, string $description, array $extra = []): void
    {
        activity('store_management')
            ->performedOn($store)
            ->causedBy(auth()->user())
            ->event($event)
            ->withProperties(['store_id' => $store->id, 'store_code' => $store->code, ...$extra])
            ->log($description);
    }

    private function resetForm(): void
    {
        $this->reset(['editingStoreId', 'code', 'name', 'phone', 'address']);
        $this->resetErrorBag();
    }

    #[Layout('layouts.backend')]
    public function render(): View
    {
        return view('livewire.backend.stores.stores-index', [
            'stores' => Store::query()->withCount('employees')->orderBy('code')->get(),
            'locale' => OperationalLocale::resolve(),
            'textDirection' => OperationalLocale::direction(),
        ])->title(__('stores.title'));
    }
}
