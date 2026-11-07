<?php

declare(strict_types=1);

namespace App\Actions\Stores;

use App\Models\Store;
use App\Models\User;
use Database\Seeders\OperationalStoreSetupSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class SetEmployeePosAccess
{
    /**
     * Grant or revoke an employee's counter access.
     *
     * The permission is held by the user, so it applies in every store the employee is
     * currently assigned to; store reach still comes only from assignments. Returns false
     * when nothing changed.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $actor, User $employee, bool $grant): bool
    {
        Gate::forUser($actor)->authorize('managePosAccess', Store::class);

        if (! $employee->roles()->where('name', OperationalStoreSetupSeeder::EMPLOYEE_ROLE)->exists()
            || $employee->roles()->where('name', OperationalStoreSetupSeeder::OWNER_ROLE)->exists()) {
            throw ValidationException::withMessages(['employee' => __('pos.access.not_employee')]);
        }

        $permission = Permission::findByName(OperationalStoreSetupSeeder::USE_POS_PERMISSION);

        $changed = DB::transaction(function () use ($actor, $employee, $grant, $permission): bool {
            User::query()->whereKey($employee->id)->lockForUpdate()->firstOrFail();

            $hasDirect = $employee->permissions()->whereKey($permission->id)->exists();

            if ($hasDirect === $grant) {
                return false;
            }

            $grant ? $employee->givePermissionTo($permission) : $employee->revokePermissionTo($permission);

            activity('employee_permission')
                ->performedOn($employee)
                ->causedBy($actor)
                ->event($grant ? 'pos_access_granted' : 'pos_access_revoked')
                ->withProperties([
                    'permission' => $permission->name,
                    'user_id' => $employee->id,
                    'user_name' => $employee->name,
                    'store_codes' => $employee->stores()->orderBy('code')->pluck('code')->all(),
                ])
                ->log($grant
                    ? "{$actor->name} granted counter access to {$employee->name}"
                    : "{$actor->name} revoked counter access from {$employee->name}");

            return true;
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $changed;
    }
}
