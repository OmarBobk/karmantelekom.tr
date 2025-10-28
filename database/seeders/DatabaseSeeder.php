<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            CurrencySeeder::class,
//            CategorySeeder::class,
//            TagSeeder::class,
//            ProductSeeder::class,
            ProductPriceSeeder::class,
            SectionSeeder::class,
//            ShopSeeder::class,
//            AddressSeeder::class,
            // SalespersonAssignmentSeeder::class, // Uncomment if you want to use it
            SettingSeeder::class,
        ]);
    }
}
