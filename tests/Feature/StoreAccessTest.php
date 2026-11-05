<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class StoreAccessTest extends TestCase
{
    use RefreshDatabase;

    private Store $storeOne;

    private Store $storeTwo;

    private Store $storeThree;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::create(['name' => 'manage_stores']);
        Role::create(['name' => 'admin'])->givePermissionTo('manage_stores');

        foreach (['employee', 'salesperson', 'shop_owner', 'customer'] as $role) {
            Role::create(['name' => $role]);
        }

        [$this->storeOne, $this->storeTwo, $this->storeThree] = Store::factory()->count(3)->create()->all();
    }

    #[Test]
    public function admin_accesses_all_stores_without_membership(): void
    {
        $admin = User::factory()->create()->assignRole('admin');

        $this->assertAccessibleStores($admin, [$this->storeOne, $this->storeTwo, $this->storeThree]);
    }

    #[Test]
    public function employee_accesses_only_assigned_stores(): void
    {
        $employee = $this->employeeAssignedTo($this->storeOne, $this->storeTwo);

        $this->assertAccessibleStores($employee, [$this->storeOne, $this->storeTwo]);
        $this->assertNull(Store::query()->accessibleTo($employee)->find($this->storeThree->id));
    }

    /**
     * @return array<string, array{\Closure(User): void}>
     */
    public static function accessRevocations(): array
    {
        return [
            'membership removed' => [fn (User $employee) => $employee->stores()->detach()],
            'employee role removed' => [fn (User $employee) => $employee->removeRole('employee')],
        ];
    }

    #[Test]
    #[DataProvider('accessRevocations')]
    public function revoked_access_applies_even_with_previously_loaded_relations(\Closure $revoke): void
    {
        $employee = $this->employeeAssignedTo($this->storeOne)->load(['stores', 'roles']);

        $revoke(User::findOrFail($employee->id));

        $this->assertCount(1, $employee->stores);
        $this->assertAccessibleStores($employee, []);
    }

    /**
     * @return array<string, array{?string}>
     */
    public static function usersWithoutOperationalAccess(): array
    {
        return [
            'guest' => [null],
            'no role' => [''],
            'salesperson' => ['salesperson'],
            'shop_owner' => ['shop_owner'],
            'customer' => ['customer'],
        ];
    }

    #[Test]
    #[DataProvider('usersWithoutOperationalAccess')]
    public function guests_and_non_operational_roles_are_denied_despite_membership(?string $role): void
    {
        $user = null;

        if ($role !== null) {
            $user = User::factory()->create();
            $user->stores()->attach($this->storeOne);

            if ($role !== '') {
                $user->assignRole($role);
            }
        }

        $this->assertAccessibleStores($user, []);
    }

    #[Test]
    public function employee_access_does_not_grant_store_management(): void
    {
        $employee = $this->employeeAssignedTo($this->storeOne);

        $this->assertTrue($employee->can('access', $this->storeOne));

        foreach (['view', 'update', 'manageEmployees', 'delete'] as $ability) {
            $this->assertFalse($employee->can($ability, $this->storeOne), "Employee must not be able to {$ability}.");
        }

        $this->assertFalse($employee->can('viewAny', Store::class));
        $this->assertFalse($employee->can('create', Store::class));
    }

    private function employeeAssignedTo(Store ...$stores): User
    {
        $employee = User::factory()->create()->assignRole('employee');
        $employee->stores()->attach(array_map(fn (Store $store) => $store->id, $stores));

        return $employee;
    }

    /**
     * Assert both the query scope and the access ability agree on exactly the expected stores.
     *
     * @param  list<Store>  $expected
     */
    private function assertAccessibleStores(?User $user, array $expected): void
    {
        $expectedIds = array_map(fn (Store $store) => $store->id, $expected);

        $this->assertEqualsCanonicalizing(
            $expectedIds,
            Store::query()->accessibleTo($user)->pluck('id')->all(),
        );

        foreach ([$this->storeOne, $this->storeTwo, $this->storeThree] as $store) {
            $this->assertSame(
                in_array($store->id, $expectedIds, true),
                Gate::forUser($user)->allows('access', $store),
                "Unexpected access result for store {$store->id}.",
            );
        }
    }
}
