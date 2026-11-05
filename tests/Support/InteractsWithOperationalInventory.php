<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\User;
use Database\Seeders\OperationalStoreSetupSeeder;
use Illuminate\Support\Facades\App;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

trait InteractsWithOperationalInventory
{
    /**
     * Create the roles and owner-only operational permissions used by the application.
     */
    protected function setUpOperationalRoles(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = Role::create(['name' => 'admin']);

        foreach (OperationalStoreSetupSeeder::OWNER_PERMISSIONS as $permission) {
            $admin->givePermissionTo(Permission::create(['name' => $permission]));
        }

        foreach (['employee', 'salesperson'] as $role) {
            Role::create(['name' => $role]);
        }
    }

    protected function createOwner(array $attributes = []): User
    {
        return User::factory()->create($attributes)->assignRole('admin');
    }

    protected function revokeFromOwners(string $permission): void
    {
        Role::findByName('admin')->revokePermissionTo($permission);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Complete a 14-digit body with its Luhn check digit.
     */
    protected static function imei(string $body): string
    {
        $sum = 0;

        foreach (array_reverse(str_split($body)) as $index => $digit) {
            $digit = (int) $digit;

            if ($index % 2 === 0) {
                $digit *= 2;

                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $sum += $digit;
        }

        return $body.((10 - $sum % 10) % 10);
    }

    protected function useEnglishLocale(): void
    {
        config(['app.locale' => 'en']);
        App::setLocale('en');
    }
}
