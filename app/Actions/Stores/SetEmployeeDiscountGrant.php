<?php

declare(strict_types=1);

namespace App\Actions\Stores;

use App\Exceptions\Purchasing\InvalidAmountException;
use App\Models\PosDiscountGrant;
use App\Models\Store;
use App\Models\User;
use App\Services\Purchasing\Money;
use Database\Seeders\OperationalStoreSetupSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class SetEmployeeDiscountGrant
{
    /**
     * Set or revoke an employee's counter discount limit.
     *
     * A percentage (up to two decimals, above 0 and at most 100) grants the discount permission
     * and stores the limit in basis points; blank revokes both. Like counter access, the grant
     * is held by the user and only takes effect in stores where they may use the counter.
     * Returns false when nothing changed.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $actor, User $employee, ?string $maxPercent): bool
    {
        Gate::forUser($actor)->authorize('managePosAccess', Store::class);

        if (! $employee->roles()->where('name', OperationalStoreSetupSeeder::EMPLOYEE_ROLE)->exists()
            || $employee->roles()->where('name', OperationalStoreSetupSeeder::OWNER_ROLE)->exists()) {
            throw ValidationException::withMessages(['employee' => __('pos.access.not_employee')]);
        }

        $basisPoints = self::parsePercent($maxPercent);
        $permission = Permission::findByName(OperationalStoreSetupSeeder::POS_DISCOUNT_PERMISSION);

        $changed = DB::transaction(function () use ($actor, $employee, $basisPoints, $permission): bool {
            User::query()->whereKey($employee->id)->lockForUpdate()->firstOrFail();

            $grant = $employee->posDiscountGrant()->first();
            $hasPermission = $employee->permissions()->whereKey($permission->id)->exists();
            $previous = $hasPermission ? $grant?->max_discount_basis_points : null;

            $unchanged = $basisPoints === null
                ? ! $hasPermission && $grant === null
                : $previous === $basisPoints;

            if ($unchanged) {
                return false;
            }

            if ($basisPoints === null) {
                $employee->revokePermissionTo($permission);
                $grant?->delete();
            } else {
                $employee->givePermissionTo($permission);
                PosDiscountGrant::query()->updateOrCreate(['user_id' => $employee->id], [
                    'max_discount_basis_points' => $basisPoints,
                    'granted_by' => $actor->id,
                ]);
            }

            $event = match (true) {
                $basisPoints === null => 'pos_discount_revoked',
                $previous === null => 'pos_discount_granted',
                default => 'pos_discount_changed',
            };

            activity('employee_permission')
                ->performedOn($employee)
                ->causedBy($actor)
                ->event($event)
                ->withProperties([
                    'permission' => $permission->name,
                    'user_id' => $employee->id,
                    'user_name' => $employee->name,
                    'old_max_discount_basis_points' => $previous,
                    'new_max_discount_basis_points' => $basisPoints,
                    'store_codes' => $employee->stores()->orderBy('code')->pluck('code')->all(),
                ])
                ->log($basisPoints === null
                    ? "{$actor->name} revoked counter discounts from {$employee->name}"
                    : "{$actor->name} allowed {$employee->name} counter discounts up to ".PosDiscountGrant::formatBasisPoints($basisPoints).'%');

            return true;
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $changed;
    }

    /**
     * @throws ValidationException
     */
    private static function parsePercent(?string $maxPercent): ?int
    {
        $input = Str::of((string) $maxPercent)->trim()->rtrim('%')->trim()->value();

        if ($input === '') {
            return null;
        }

        try {
            return Money::parse($input, PosDiscountGrant::FULL_BASIS_POINTS);
        } catch (InvalidAmountException) {
            throw ValidationException::withMessages(['discount_percent' => __('pos.access.discount_percent_invalid')]);
        }
    }
}
