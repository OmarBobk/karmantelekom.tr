<?php

namespace Database\Seeders;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ShopSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get users
        $ahmad = User::where('email', 'ahmad@gmail.com')->first();
        $zain = User::where('email', 'zain@gmail.com')->first();
        $karman_owner = User::where('email', 'karmantelekom@gmail.com')->first();
        $cephane_owner = User::where('email', 'cephane@gmail.com')->first();
        $zore_owner = User::where('email', 'zore@gmail.com')->first();
        $simex_owner = User::where('email', 'simex@gmail.com')->first();

        // Create Karman Store (owned by Karman Telekom, assigned to Ahmad)
        $karman_store = Shop::create([
            'name' => 'Karman Store',
            'phone' => '5353402539',
            'links' => [
                'facebook' => 'https://www.facebook.com/karman.store',
                'instagram' => 'https://www.instagram.com/karman.store',
                'website' => 'https://karmantelekom.com',
                'email' => 'info@karmantelekom.com',
                'google_map' => 'https://maps.app.goo.gl/HTJwzmAicJta89CQ9',
            ],
            'address' => 'Şemsitebrizi, Şerafettin Cd. No:67B, 42090 Karatay/Konya',
            'owner_id' => $karman_owner->id, // Shop owner
            'salesperson_id' => $ahmad->id, // Assigned salesperson
        ]);

        // Create Cephane İletişim (owned by Cephane Owner, assigned to Ahmad)
        $cephane = Shop::create([
            'name' => 'Cephane İletişim',
            'phone' => '5551234567',
            'links' => [
                'facebook' => 'https://www.facebook.com/cephane.store',
                'instagram' => 'https://www.instagram.com/cephane.store',
                'website' => 'https://cephane.com',
                'email' => 'info@cephane.com',
                'google_map' => 'https://maps.app.goo.gl/7qtJbhCbHP1g5bUb7',
            ],
            'address' => 'Sahibiata, Atatürk Cd. No: 15, 42040 Meram/Konya',
            'owner_id' => $cephane_owner->id, // Shop owner
            'salesperson_id' => $ahmad->id, // Assigned salesperson
        ]);

        // Create Zore İletişim (owned by Zore Owner, no salesperson assigned yet)
        $zore = Shop::create([
            'name' => 'Zore İletişim',
            'phone' => '5559876543',
            'links' => [
                'facebook' => 'https://www.facebook.com/zore.store',
                'instagram' => 'https://www.instagram.com/zore.store',
                'website' => 'https://zore.com',
                'email' => 'info@zore.com',
                'google_map' => 'https://maps.app.goo.gl/7qtJbhCbHP1g5bUb7',
            ],
            'address' => 'Sahibiata, Atatürk Cd. No: 15, 42040 Meram/Konya',
            'owner_id' => $zore_owner->id, // Shop owner
            'salesperson_id' => null, // No salesperson assigned
        ]);

        // Create Simex İletişim (owned by Simex Owner, assigned to Zain)
        $simex = Shop::create([
            'name' => 'Simex İletişim',
            'phone' => '5554567890',
            'links' => [
                'facebook' => 'https://www.facebook.com/simex.store',
                'instagram' => 'https://www.instagram.com/simex.store',
                'website' => 'https://simex.com',
                'email' => 'info@simex.com',
                'google_map' => 'https://maps.app.goo.gl/7qtJbhCbHP1g5bUb7',
            ],
            'address' => 'Sahibiata, Atatürk Cd. No: 15, 42040 Meram/Konya',
            'owner_id' => $simex_owner->id, // Shop owner
            'salesperson_id' => $zain->id, // Assigned salesperson
        ]);

        // Create additional shops for testing different scenarios
        $test_shop_1 = Shop::create([
            'name' => 'Test Shop 1',
            'phone' => '5551111111',
            'links' => [
                'facebook' => 'https://www.facebook.com/test1',
                'instagram' => 'https://www.instagram.com/test1',
                'email' => 'test1@example.com',
            ],
            'address' => 'Test Address 1, Konya',
            'owner_id' => $karman_owner->id, // Same owner as Karman Store
            'salesperson_id' => $zain->id, // Different salesperson
        ]);

        $test_shop_2 = Shop::create([
            'name' => 'Test Shop 2',
            'phone' => '5552222222',
            'links' => [
                'facebook' => 'https://www.facebook.com/test2',
                'instagram' => 'https://www.instagram.com/test2',
                'email' => 'test2@example.com',
            ],
            'address' => 'Test Address 2, Konya',
            'owner_id' => $cephane_owner->id, // Same owner as Cephane
            'salesperson_id' => null, // No salesperson assigned
        ]);

        // Output summary
        $this->command->info('Shops created successfully:');
        $this->command->info('- Karman Store (Owner: ' . $karman_owner->name . ', Salesperson: ' . $ahmad->name . ')');
        $this->command->info('- Cephane İletişim (Owner: ' . $cephane_owner->name . ', Salesperson: ' . $ahmad->name . ')');
        $this->command->info('- Zore İletişim (Owner: ' . $zore_owner->name . ', Salesperson: None)');
        $this->command->info('- Simex İletişim (Owner: ' . $simex_owner->name . ', Salesperson: ' . $zain->name . ')');
        $this->command->info('- Test Shop 1 (Owner: ' . $karman_owner->name . ', Salesperson: ' . $zain->name . ')');
        $this->command->info('- Test Shop 2 (Owner: ' . $cephane_owner->name . ', Salesperson: None)');
    }
}
