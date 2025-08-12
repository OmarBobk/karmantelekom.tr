<?php

namespace Database\Seeders;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Seeder;

class SalespersonAssignmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get users
        $ahmad = User::where('email', 'ahmad@gmail.com')->first();
        $zain = User::where('email', 'zain@gmail.com')->first();

        // Assign salespersons to shops (this would be done by admin in real app)
        $shops = Shop::all();

        foreach ($shops as $shop) {
            // Randomly assign salespersons (in real app, this would be manual)
            $salesperson = rand(0, 1) ? $ahmad : $zain;
            
            // Update shop with salesperson assignment
            // Note: This assumes you have a salesperson_id field or you want to track this differently
            $shop->update([
                'salesperson_id' => $salesperson->id ?? null, // If you add this field
            ]);
        }

        $this->command->info('Salesperson assignments completed.');
    }
}
