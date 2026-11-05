<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use Database\Seeders\OperationalStoreSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class OperationalStoreSetupSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    #[Test]
    public function setup_is_repeatable_and_preserves_existing_data(): void
    {
        Permission::create(['name' => 'manage_users']);
        Role::create(['name' => 'admin'])->givePermissionTo('manage_users');
        Role::create(['name' => 'salesperson']);
        $owner = User::factory()->create()->assignRole('admin');
        $salesperson = User::factory()->create()->assignRole('salesperson');
        $userCount = User::count();

        $this->seed(OperationalStoreSetupSeeder::class);

        $this->assertSame(['KT01', 'KT02'], Store::orderBy('code')->pluck('code')->all());
        $this->assertTrue(Role::where('name', 'employee')->where('guard_name', 'web')->exists());
        foreach (OperationalStoreSetupSeeder::OWNER_PERMISSIONS as $permission) {
            $this->assertTrue(Permission::where('name', $permission)->where('guard_name', 'web')->exists());
        }

        Store::where('code', 'KT02')->update(['name' => 'Kadıköy Branch', 'phone' => '+90 216 000 0002']);
        $counts = [Store::count(), Role::count(), Permission::count()];

        $this->seed(OperationalStoreSetupSeeder::class);

        $this->assertSame($counts, [Store::count(), Role::count(), Permission::count()]);
        $this->assertDatabaseHas('stores', ['code' => 'KT02', 'name' => 'Kadıköy Branch', 'phone' => '+90 216 000 0002']);
        $this->assertSame($userCount, User::count());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $admin = Role::findByName('admin');
        $this->assertEqualsCanonicalizing(
            ['manage_users', 'manage_stores', 'manage_catalog', 'manage_suppliers', 'view_inventory', 'adjust_inventory', 'manage_purchases', 'receive_purchases', 'pay_suppliers', 'service_devices', 'manage_device_costs', 'correct_unit_identity'],
            $admin->permissions->pluck('name')->all(),
        );
        $this->assertTrue(Role::findByName('salesperson')->permissions->isEmpty());
        $this->assertTrue(Role::findByName('employee')->permissions->isEmpty());
        $this->assertTrue($owner->fresh()->hasRole('admin'));
        $this->assertSame(['salesperson'], $salesperson->fresh()->getRoleNames()->all());
    }

    #[Test]
    public function setup_fails_without_writing_when_admin_role_is_missing(): void
    {
        $this->assertThrows(
            fn () => $this->seed(OperationalStoreSetupSeeder::class),
            RuntimeException::class,
            'the "admin" role does not exist for the "web" guard. No changes were made.',
        );

        $this->assertSame(0, Store::count());
        $this->assertSame(0, Role::count());
        $this->assertSame(0, Permission::count());
    }
}
