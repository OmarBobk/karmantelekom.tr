<?php

declare(strict_types=1);

namespace App\Livewire\Backend\Users;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

/**
 * Backend Users Management Component
 *
 * Handles all user management operations including:
 * - Listing users with filtering and sorting
 * - Creating new users
 * - Editing existing users
 * - Managing user roles
 * - Bulk operations
 */
class UsersComponent extends Component
{
    use WithPagination;

    // Search and Filter Properties
    /** @var string Search query for filtering users */
    public string $search = '';

    /** @var string Field to sort users by */
    public string $sortField = 'created_at';

    /** @var string Sort direction (ASC/DESC) */
    public string $sortDirection = 'DESC';

    /** @var string Filter by user role */
    public string $roleFilter = '';

    /** @var string Filter by email verification status */
    public string $verificationFilter = '';

    /** @var int Number of items per page */
    public int $perPage = 10;

    // User Form Properties
    /** @var string User name */
    public string $name = '';

    /** @var string User email */
    public string $email = '';

    /** @var string User password */
    public string $password = '';

    /** @var string User password confirmation */
    public string $password_confirmation = '';

    /** @var string Selected role for user */
    public string $selectedRole = '';

    /** @var bool Whether email should be verified */
    public bool $emailVerified = true;

    // Modal and Selection Properties
    /** @var bool Whether add user modal is open */
    public bool $addModalOpen = false;

    /** @var bool Whether edit user modal is open */
    public bool $editModalOpen = false;

    /** @var bool Whether delete confirmation modal is open */
    public bool $deleteModalOpen = false;

    /** @var int|null ID of user being edited */
    public ?int $editingUserId = null;

    /** @var int|null ID of user being deleted */
    public ?int $deletingUserId = null;

    /** @var array Array of selected user IDs for bulk operations */
    public array $selectedUsers = [];

    /** @var bool Whether all users on current page are selected */
    public bool $selectAll = false;

    /** @var string Bulk action to perform */
    public string $bulkAction = '';

    /**
     * Validation rules for user form
     */
    protected function rules(): array
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'selectedRole' => 'required|exists:roles,name',
            'emailVerified' => 'boolean',
        ];

        if ($this->editingUserId) {
            $rules['email'] = 'required|email|max:255|unique:users,email,' . $this->editingUserId;
            $rules['password'] = 'nullable|min:8|confirmed';
        } else {
            $rules['password'] = 'required|min:8|confirmed';
        }

        return $rules;
    }

    /**
     * Mount component with initial data
     */
    public function mount(): void
    {
        $this->authorize('manage_users');
    }

    /**
     * Update search and reset pagination
     */
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Update role filter and reset pagination
     */
    public function updatedRoleFilter(): void
    {
        $this->resetPage();
    }

    /**
     * Update verification filter and reset pagination
     */
    public function updatedVerificationFilter(): void
    {
        $this->resetPage();
    }

    /**
     * Update per page and reset pagination
     */
    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    /**
     * Handle bulk action selection
     */
    public function updatedBulkAction(): void
    {
        if ($this->bulkAction && count($this->selectedUsers) > 0) {
            $this->performBulkAction();
        }
    }

    /**
     * Toggle select all users on current page
     */
    public function updatedSelectAll(): void
    {
        if ($this->selectAll) {
            $this->selectedUsers = $this->getUsers()->pluck('id')->toArray();
        } else {
            $this->selectedUsers = [];
        }
    }

    /**
     * Sort by field
     */
    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'ASC' ? 'DESC' : 'ASC';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'ASC';
        }

        $this->resetPage();
    }

    /**
     * Open add user modal
     */
    public function openAddModal(): void
    {
        $this->resetForm();
        $this->addModalOpen = true;
    }

    /**
     * Open edit user modal
     */
    public function openEditModal(int $userId): void
    {
        $user = User::findOrFail($userId);
        
        $this->editingUserId = $userId;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->selectedRole = $user->roles->first()?->name ?? '';
        $this->emailVerified = $user->email_verified_at !== null;
        $this->password = '';
        $this->password_confirmation = '';
        
        $this->editModalOpen = true;
    }

    /**
     * Open delete confirmation modal
     */
    public function openDeleteModal(int $userId): void
    {
        $this->deletingUserId = $userId;
        $this->deleteModalOpen = true;
    }

    /**
     * Close all modals
     */
    public function closeModals(): void
    {
        $this->addModalOpen = false;
        $this->editModalOpen = false;
        $this->deleteModalOpen = false;
        $this->resetForm();
    }

    /**
     * Reset form fields
     */
    public function resetForm(): void
    {
        $this->editingUserId = null;
        $this->deletingUserId = null;
        $this->name = '';
        $this->email = '';
        $this->password = '';
        $this->password_confirmation = '';
        $this->selectedRole = '';
        $this->emailVerified = true;
        $this->resetValidation();
    }

    /**
     * Create new user
     */
    public function createUser(): void
    {
        $this->validate();

        $user = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => Hash::make($this->password),
            'email_verified_at' => $this->emailVerified ? now() : null,
        ]);

        $user->assignRole($this->selectedRole);

        session()->flash('message', 'User created successfully.');
        $this->closeModals();
        $this->resetPage();
    }

    /**
     * Update existing user
     */
    public function updateUser(): void
    {
        $this->validate();

        $user = User::findOrFail($this->editingUserId);
        
        $updateData = [
            'name' => $this->name,
            'email' => $this->email,
            'email_verified_at' => $this->emailVerified ? now() : null,
        ];

        if (!empty($this->password)) {
            $updateData['password'] = Hash::make($this->password);
        }

        $user->update($updateData);
        $user->syncRoles([$this->selectedRole]);

        session()->flash('message', 'User updated successfully.');
        $this->closeModals();
    }

    /**
     * Delete user
     */
    public function deleteUser(): void
    {
        $user = User::findOrFail($this->deletingUserId);
        
        // Prevent deleting current user
        if ($user->id === auth()->id()) {
            session()->flash('error', 'You cannot delete your own account.');
            $this->closeModals();
            return;
        }

        $user->delete();

        session()->flash('message', 'User deleted successfully.');
        $this->closeModals();
        $this->resetPage();
    }

    /**
     * Perform bulk action on selected users
     */
    public function performBulkAction(): void
    {
        if (empty($this->selectedUsers)) {
            return;
        }

        switch ($this->bulkAction) {
            case 'delete':
                $this->bulkDelete();
                break;
            case 'verify':
                $this->bulkVerify();
                break;
            case 'unverify':
                $this->bulkUnverify();
                break;
            case 'role_admin':
            case 'role_salesperson':
            case 'role_shop_owner':
            case 'role_customer':
                $this->bulkChangeRole(str_replace('role_', '', $this->bulkAction));
                break;
        }

        $this->selectedUsers = [];
        $this->selectAll = false;
        $this->bulkAction = '';
        $this->resetPage();
    }

    /**
     * Bulk delete users
     */
    private function bulkDelete(): void
    {
        // Prevent deleting current user
        $userIds = array_filter($this->selectedUsers, fn($id) => $id !== auth()->id());
        
        User::whereIn('id', $userIds)->delete();
        
        session()->flash('message', count($userIds) . ' users deleted successfully.');
    }

    /**
     * Bulk verify users
     */
    private function bulkVerify(): void
    {
        User::whereIn('id', $this->selectedUsers)->update([
            'email_verified_at' => now()
        ]);
        
        session()->flash('message', count($this->selectedUsers) . ' users verified successfully.');
    }

    /**
     * Bulk unverify users
     */
    private function bulkUnverify(): void
    {
        User::whereIn('id', $this->selectedUsers)->update([
            'email_verified_at' => null
        ]);
        
        session()->flash('message', count($this->selectedUsers) . ' users unverified successfully.');
    }

    /**
     * Bulk change user role
     */
    private function bulkChangeRole(string $role): void
    {
        $users = User::whereIn('id', $this->selectedUsers)->get();
        
        foreach ($users as $user) {
            $user->syncRoles([$role]);
        }
        
        session()->flash('message', count($this->selectedUsers) . ' users role changed to ' . $role . ' successfully.');
    }

    /**
     * Get users query with filters
     */
    private function getUsersQuery(): Builder
    {
        $query = User::with('roles');

        // Apply search filter
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('email', 'like', '%' . $this->search . '%');
            });
        }

        // Apply role filter
        if ($this->roleFilter) {
            $query->role($this->roleFilter);
        }

        // Apply verification filter
        if ($this->verificationFilter === 'verified') {
            $query->whereNotNull('email_verified_at');
        } elseif ($this->verificationFilter === 'unverified') {
            $query->whereNull('email_verified_at');
        }

        // Apply sorting
        $query->orderBy($this->sortField, $this->sortDirection);

        return $query;
    }

    /**
     * Get paginated users
     */
    private function getUsers(): LengthAwarePaginator
    {
        return $this->getUsersQuery()->paginate($this->perPage);
    }

    /**
     * Get available roles
     */
    private function getRoles()
    {
        return Role::orderBy('name')->get();
    }

    /**
     * Render component
     */
    #[Layout('layouts.backend')]
    #[Title('Users Management')]
    public function render()
    {
        return view('livewire.backend.users.users-component', [
            'users' => $this->getUsers(),
            'roles' => $this->getRoles(),
        ]);
    }
}
