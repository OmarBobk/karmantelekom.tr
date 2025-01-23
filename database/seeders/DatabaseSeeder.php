<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // Creating Users inside RolesAndPermissionsSeeder
            RolesAndPermissionsSeeder::class,
            TagSeeder::class,
            ProductSeeder::class,
        ]);

        $this->call(SectionProductSeeder::class);
    }
}
