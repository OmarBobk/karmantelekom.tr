<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use Database\Seeders\StoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class StorePolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::create(['name' => 'manage_stores']);
        Role::create(['name' => 'admin'])->givePermissionTo('manage_stores');

        foreach (['salesperson', 'shop_owner', 'customer'] as $role) {
            Role::create(['name' => $role]);
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function managementAbilities(): array
    {
        return [
            'viewAny' => ['viewAny'],
            'view' => ['view'],
            'create' => ['create'],
            'update' => ['update'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function deletionAbilities(): array
    {
        return [
            'delete' => ['delete'],
            'restore' => ['restore'],
            'forceDelete' => ['forceDelete'],
        ];
    }

    #[Test]
    #[DataProvider('managementAbilities')]
    public function admin_with_manage_stores_is_allowed(string $ability): void
    {
        $admin = User::factory()->create()->assignRole('admin');

        $this->assertTrue($admin->can($ability, $this->subjectFor($ability)));
    }

    #[Test]
    #[DataProvider('managementAbilities')]
    public function admin_without_manage_stores_is_denied(string $ability): void
    {
        Role::findByName('admin')->revokePermissionTo('manage_stores');
        $admin = User::factory()->create()->assignRole('admin');

        $this->assertFalse($admin->can($ability, $this->subjectFor($ability)));
    }

    #[Test]
    #[DataProvider('managementAbilities')]
    public function non_admins_are_denied_even_with_manage_stores(string $ability): void
    {
        $subject = $this->subjectFor($ability);

        $deniedUsers = [
            'no role' => User::factory()->create(),
            'salesperson' => User::factory()->create()->assignRole('salesperson'),
            'shop_owner' => User::factory()->create()->assignRole('shop_owner'),
            'customer' => User::factory()->create()->assignRole('customer'),
            'salesperson with direct manage_stores' => User::factory()->create()
                ->assignRole('salesperson')
                ->givePermissionTo('manage_stores'),
        ];

        foreach ($deniedUsers as $label => $user) {
            $this->assertFalse($user->can($ability, $subject), "Expected {$label} to be denied {$ability}.");
        }
    }

    #[Test]
    #[DataProvider('deletionAbilities')]
    public function deletion_is_denied_for_admin(string $ability): void
    {
        $admin = User::factory()->create()->assignRole('admin');

        $this->assertFalse($admin->can($ability, Store::factory()->create()));
    }

    #[Test]
    public function store_seeder_creates_initial_stores_once_and_preserves_existing_data(): void
    {
        $this->seed(StoreSeeder::class);

        Store::where('code', 'KT01')->firstOrFail()->update([
            'name' => 'Edited Store Name',
            'phone' => '+90 555 000 0000',
        ]);
        $additionalStore = Store::factory()->create(['code' => 'KT99']);

        $this->seed(StoreSeeder::class);

        $this->assertSame(3, Store::count());
        $this->assertSame(1, Store::where('code', 'KT01')->count());
        $this->assertSame(1, Store::where('code', 'KT02')->count());
        $this->assertDatabaseHas('stores', [
            'code' => 'KT01',
            'name' => 'Edited Store Name',
            'phone' => '+90 555 000 0000',
        ]);
        $this->assertDatabaseHas('stores', ['code' => 'KT02', 'name' => 'Karman Telekom — Store 2']);
        $this->assertModelExists($additionalStore);
    }

    private function subjectFor(string $ability): Store|string
    {
        return in_array($ability, ['viewAny', 'create'], true)
            ? Store::class
            : Store::factory()->create();
    }
}
