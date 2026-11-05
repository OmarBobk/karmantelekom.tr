<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\Permission\Guard;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Repeatable operational-store setup for an existing database.
 *
 * Creates missing initial stores, the `employee` role and the owner permissions
 * listed in OWNER_PERMISSIONS, and grants only those permissions to the existing
 * `admin` role. It never creates users or removes existing roles, permissions,
 * or store details.
 */
class OperationalStoreSetupSeeder extends Seeder
{
    public const OWNER_ROLE = 'admin';

    public const EMPLOYEE_ROLE = 'employee';

    public const MANAGE_STORES_PERMISSION = 'manage_stores';

    /**
     * Operational permissions granted only to the owner role.
     *
     * @var list<string>
     */
    public const OWNER_PERMISSIONS = [
        self::MANAGE_STORES_PERMISSION,
        'manage_catalog',
        'manage_suppliers',
        'view_inventory',
        'adjust_inventory',
        'manage_purchases',
        'receive_purchases',
        'pay_suppliers',
        'service_devices',
        'manage_device_costs',
        'correct_unit_identity',
    ];

    public function run(): void
    {
        $guard = Guard::getDefaultName(User::class);
        $registrar = app(PermissionRegistrar::class);

        $ownerRole = Role::query()
            ->where('name', self::OWNER_ROLE)
            ->where('guard_name', $guard)
            ->first();

        if ($ownerRole === null) {
            throw new RuntimeException(sprintf(
                'Operational store setup aborted: the "%s" role does not exist for the "%s" guard. No changes were made.',
                self::OWNER_ROLE,
                $guard,
            ));
        }

        $registrar->forgetCachedPermissions();

        DB::transaction(function () use ($ownerRole, $guard): void {
            $this->call(StoreSeeder::class);

            Role::findOrCreate(self::EMPLOYEE_ROLE, $guard);

            $ownerRole->givePermissionTo(array_map(
                fn (string $permission): Permission => Permission::findOrCreate($permission, $guard),
                self::OWNER_PERMISSIONS,
            ));
        });

        $registrar->forgetCachedPermissions();
    }
}
