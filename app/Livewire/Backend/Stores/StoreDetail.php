<?php

declare(strict_types=1);

namespace App\Livewire\Backend\Stores;

use App\Actions\Pos\SaveCardTerminal;
use App\Actions\Stores\AssignEmployeeToStore;
use App\Actions\Stores\RemoveEmployeeFromStore;
use App\Actions\Stores\SetEmployeePosAccess;
use App\Livewire\Backend\OperationalLocale;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\OperationalStoreSetupSeeder;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

class StoreDetail extends Component
{
    #[Locked]
    public Store $store;

    public ?int $selectedEmployeeId = null;

    #[Locked]
    public ?int $employeeToRemoveId = null;

    public string $terminalLabel = '';

    public string $terminalMerchantNumber = '';

    public string $terminalNumber = '';

    public function boot(): void
    {
        OperationalLocale::apply();
    }

    public function mount(Store $store): void
    {
        $this->authorize('view', $store);

        $this->store = $store;
    }

    public function assign(AssignEmployeeToStore $assignEmployeeToStore): void
    {
        $this->authorize('manageEmployees', $this->store);

        $this->validate(
            ['selectedEmployeeId' => ['required', 'integer', 'exists:users,id']],
            __('stores.validation'),
            ['selectedEmployeeId' => __('stores.attributes.employee')],
        );

        $employee = User::findOrFail($this->selectedEmployeeId);

        try {
            $assigned = $assignEmployeeToStore(auth()->user(), $employee, $this->store);
        } catch (ValidationException) {
            throw ValidationException::withMessages([
                'selectedEmployeeId' => __('stores.messages.not_employee'),
            ]);
        }

        $this->reset('selectedEmployeeId');
        $this->dispatch('notify', [
            'type' => $assigned ? 'success' : 'info',
            'message' => __($assigned ? 'stores.messages.assigned' : 'stores.messages.already_assigned', ['name' => $employee->name]),
        ]);
    }

    public function confirmRemoval(int $userId): void
    {
        $this->authorize('manageEmployees', $this->store);

        $this->employeeToRemoveId = $this->store->employees()->whereKey($userId)->firstOrFail()->id;
    }

    public function cancelRemoval(): void
    {
        $this->reset('employeeToRemoveId');
    }

    public function remove(RemoveEmployeeFromStore $removeEmployeeFromStore): void
    {
        $this->authorize('manageEmployees', $this->store);

        if ($this->employeeToRemoveId === null) {
            return;
        }

        $employee = User::findOrFail($this->employeeToRemoveId);
        $removed = $removeEmployeeFromStore(auth()->user(), $employee, $this->store);

        $this->reset('employeeToRemoveId');
        $this->dispatch('notify', [
            'type' => $removed ? 'success' : 'info',
            'message' => __($removed ? 'stores.messages.removed' : 'stores.messages.already_removed', ['name' => $employee->name]),
        ]);
    }

    /**
     * Grant or revoke the counter permission. It is held by the user, so it applies in every
     * store the employee is assigned to.
     */
    public function setPosAccess(SetEmployeePosAccess $setEmployeePosAccess, int $userId, bool $grant): void
    {
        $this->authorize('managePosAccess', Store::class);

        $employee = $this->store->employees()->whereKey($userId)->firstOrFail();
        $changed = $setEmployeePosAccess(auth()->user(), $employee, $grant);

        $this->dispatch('notify', [
            'type' => $changed ? 'success' : 'info',
            'message' => __($grant ? 'pos.access.granted' : 'pos.access.revoked', ['name' => $employee->name]),
        ]);
    }

    public function addTerminal(SaveCardTerminal $saveCardTerminal): void
    {
        $this->authorize('manageCardTerminals', $this->store);

        $saveCardTerminal->create(auth()->user(), $this->store, [
            'label' => $this->terminalLabel,
            'merchant_number' => $this->terminalMerchantNumber,
            'terminal_number' => $this->terminalNumber,
        ]);

        $this->reset('terminalLabel', 'terminalMerchantNumber', 'terminalNumber');
        $this->dispatch('notify', ['type' => 'success', 'message' => __('pos.terminals.added')]);
    }

    public function setTerminalActive(SaveCardTerminal $saveCardTerminal, int $terminalId, bool $active): void
    {
        $this->authorize('manageCardTerminals', $this->store);

        $saveCardTerminal->setActive(auth()->user(), $this->store, $terminalId, $active);
    }

    /**
     * @return Collection<int, User>
     */
    private function assignedEmployees(): Collection
    {
        return $this->store->employees()
            ->with([
                'stores' => fn ($stores) => $stores->whereKeyNot($this->store->id)->orderBy('code'),
                'permissions:id,name',
            ])
            ->orderBy('name')
            ->get();
    }

    /**
     * Users holding the employee role who are not yet assigned to this store.
     *
     * @return Collection<int, User>
     */
    private function availableEmployees(): Collection
    {
        return User::query()
            ->whereHas('roles', fn (Builder $roles) => $roles->where('name', 'employee'))
            ->whereDoesntHave('stores', fn (Builder $stores) => $stores->whereKey($this->store->id))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }

    #[Layout('layouts.backend')]
    public function render(): View
    {
        $employees = $this->assignedEmployees();

        return view('livewire.backend.stores.store-detail', [
            'employees' => $employees,
            'availableEmployees' => $this->availableEmployees(),
            'employeeToRemove' => $this->employeeToRemoveId !== null
                ? $employees->firstWhere('id', $this->employeeToRemoveId)
                : null,
            'canManagePos' => auth()->user()->can('managePosAccess', Store::class),
            'terminals' => $this->store->cardTerminals()->orderByDesc('is_active')->orderBy('label')->get(),
            'posPermission' => OperationalStoreSetupSeeder::USE_POS_PERMISSION,
            'locale' => OperationalLocale::resolve(),
            'textDirection' => OperationalLocale::direction(),
        ])->title($this->store->name.' — '.__('stores.title'));
    }
}
