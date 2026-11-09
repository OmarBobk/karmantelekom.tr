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
 * `admin` role. `use_pos` is also granted to individual employees by the owner, and so is
 * `give_pos_discounts`, which no role holds. It never
 * creates users or removes existing roles, permissions, or store details.
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
        self::USE_POS_PERMISSION,
        'set_selling_prices',
        self::SELL_ON_CREDIT_PERMISSION,
        self::RECEIVE_CUSTOMER_PAYMENTS_PERMISSION,
        self::MANAGE_CASHBOX_PERMISSION,
        self::MANAGE_EXPENSES_PERMISSION,
        self::SHARE_LISTINGS_PERMISSION,
        self::MANAGE_RESERVATIONS_PERMISSION,
        self::ALLOCATE_STOREFRONT_STOCK_PERMISSION,
        self::PUBLISH_STOREFRONT_PERMISSION,
    ];

    /**
     * Making a branch's stock eligible for the storefront: an online quantity or exact devices. Owner only.
     */
    public const ALLOCATE_STOREFRONT_STOCK_PERMISSION = 'allocate_storefront_stock';

    /**
     * Linking, creating, publishing, and withdrawing storefront listings in the publisher
     * workspace. Held by the owner role; may also be granted directly to a publisher.
     */
    public const PUBLISH_STOREFRONT_PERMISSION = 'publish_storefront';

    /**
     * Publishing a branch's devices to Store Network and withdrawing them. Owner only.
     */
    public const SHARE_LISTINGS_PERMISSION = 'share_listings';

    /**
     * Approving, rejecting, and dispatching other branches' reservations of this branch's
     * devices, and setting the branch's reservation hold. Owner only.
     */
    public const MANAGE_RESERVATIONS_PERMISSION = 'manage_reservations';

    /**
     * Opening and closing cashbox sessions and releasing cash intakes and payouts. Owner only.
     */
    public const MANAGE_CASHBOX_PERMISSION = 'manage_cashbox';

    /**
     * Recording general branch expenses, including cash paid out of the open session. Owner only.
     */
    public const MANAGE_EXPENSES_PERMISSION = 'manage_expenses';

    /**
     * Recording later customer payments toward their debts. Owner only for now.
     */
    public const RECEIVE_CUSTOMER_PAYMENTS_PERMISSION = 'receive_customer_payments';

    /**
     * Leaving part or all of a store customer's sale unpaid at the counter. Owner only for now.
     */
    public const SELL_ON_CREDIT_PERMISSION = 'sell_on_credit';

    /**
     * Counter access. The owner holds it through the admin role; employees only receive it
     * directly from the owner, never through the employee role.
     */
    public const USE_POS_PERMISSION = 'use_pos';

    /**
     * Counter discounts for employees, always paired with an owner-set maximum percentage.
     * Only granted directly to employees; the owner's own price override does not use it.
     */
    public const POS_DISCOUNT_PERMISSION = 'give_pos_discounts';

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

            Permission::findOrCreate(self::POS_DISCOUNT_PERMISSION, $guard);
        });

        $registrar->forgetCachedPermissions();
    }
}
