<?php

declare(strict_types=1);

namespace App\Actions\Stores;

use App\Models\Store;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RemoveEmployeeFromStore
{
    /**
     * Remove a user's membership from a store, regardless of their current roles.
     *
     * Returns true when a membership was removed, false when the user was not assigned.
     *
     * @throws AuthorizationException
     */
    public function __invoke(User $actor, User $employee, Store $store): bool
    {
        Gate::forUser($actor)->authorize('manageEmployees', $store);

        return DB::transaction(function () use ($actor, $employee, $store): bool {
            Store::query()->whereKey($store->getKey())->lockForUpdate()->firstOrFail();

            if ($store->employees()->detach($employee->getKey()) === 0) {
                return false;
            }

            activity('store_assignment')
                ->performedOn($store)
                ->causedBy($actor)
                ->event('employee_removed')
                ->withProperties([
                    'store_id' => $store->id,
                    'store_code' => $store->code,
                    'user_id' => $employee->id,
                    'user_name' => $employee->name,
                ])
                ->log("{$employee->name} was removed from store {$store->code} by {$actor->name}");

            return true;
        });
    }
}
