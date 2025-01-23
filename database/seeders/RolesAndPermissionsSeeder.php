<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Clear cache
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Define permissions
        $permissions = [
            'manage_users',
            'manage_sections',
            'manage_products',
            'view_sales',
            'create_orders',
            'edit_orders',
            'delete_orders',
        ];

        // Create permissions
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create roles and assign permissions
        // Admin: All permissions
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->givePermissionTo(Permission::all());

        // Salesperson: Limited permissions
        $salesperson = Role::firstOrCreate(['name' => 'salesperson']);
        $salesperson->givePermissionTo([
            'view_sales',
            'create_orders',
            'edit_orders',
        ]);

        // Shop Owner: Restricted permissions
        Role::firstOrCreate(['name' => 'shop_owner']);
//        $shopOwner = Role::firstOrCreate(['name' => 'shop_owner']);
//        $shopOwner->givePermissionTo([
//            'view_sales',
//            'create_orders',
//        ]);

        $admin = User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@gmail.com',
        ]);

        $admin->assignRole('admin');

        $ahmad = User::factory()->create([
            'name' => 'Ahmad',
            'email' => 'ahmad@gmail.com',
        ]);
        $ahmad->assignRole('salesperson');

        $karman_telekom = User::factory()->create([
            'name' => 'Karman Telekom',
            'email' => 'karmantelekom@gmail.com',
        ]);
        $karman_telekom->assignRole('shop_owner');

    }
}
