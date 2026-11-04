<?php

declare(strict_types=1);

namespace App\Actions\Stores;

use App\Models\Store;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AssignEmployeeToStore
{
    /**
     * Assign an employee to a store.
     *
     * Returns true when a new membership was created, false when the employee was already assigned.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $actor, User $employee, Store $store): bool
    {
        Gate::forUser($actor)->authorize('manageEmployees', $store);

        if (! $employee->hasRole('employee')) {
            throw ValidationException::withMessages([
                'user' => __('Only users with the employee role can be assigned to a store.'),
            ]);
        }

        return DB::transaction(function () use ($actor, $employee, $store): bool {
            Store::query()->whereKey($store->getKey())->lockForUpdate()->firstOrFail();

            if ($store->employees()->whereKey($employee->getKey())->exists()) {
                return false;
            }

            $store->employees()->attach($employee->getKey());

            activity('store_assignment')
                ->performedOn($store)
                ->causedBy($actor)
                ->event('employee_assigned')
                ->withProperties([
                    'store_id' => $store->id,
                    'store_code' => $store->code,
                    'user_id' => $employee->id,
                    'user_name' => $employee->name,
                ])
                ->log("{$employee->name} was assigned to store {$store->code} by {$actor->name}");

            return true;
        });
    }
}
