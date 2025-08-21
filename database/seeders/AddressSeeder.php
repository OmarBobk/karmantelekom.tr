<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Shop;
use App\Models\Address;

class AddressSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all shops
        $shops = Shop::all();

        foreach ($shops as $shop) {
            // Create a primary address for each shop
            Address::factory()->primary()->create([
                'shop_id' => $shop->id,
                'label' => 'Head Office',
            ]);

            // Create 1-3 additional addresses for each shop
            $additionalAddresses = rand(1, 3);
            for ($i = 0; $i < $additionalAddresses; $i++) {
                Address::factory()->nonPrimary()->create([
                    'shop_id' => $shop->id,
                ]);
            }
        }
    }
}
