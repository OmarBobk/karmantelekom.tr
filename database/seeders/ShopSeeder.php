<?php

namespace Database\Seeders;

use App\Models\Shop;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ShopSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

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
            'user_id' => 2, // Ahmad Salesperson
        ]);

        $cephane = Shop::create([
            'name' => 'Cephane İletişim',
            'phone' => '',
            'links' => [
                'facebook' => 'https://www.facebook.com/karman.store',
                'instagram' => 'https://www.instagram.com/karman.store',
                'website' => 'https://karmantelekom.com',
                'email' => 'info@karmantelekom.com',
                'google_map' => 'https://maps.app.goo.gl/7qtJbhCbHP1g5bUb7',
                ],
            'address' => 'Sahibiata, Atatürk Cd. No: 15, 42040 Meram/Konya',
            'user_id' => 2, // Ahmad Salesperson
        ]);

        $zore = Shop::create([
            'name' => 'Zore İletişim',
            'phone' => '',
            'links' => [
                'facebook' => 'https://www.facebook.com/karman.store',
                'instagram' => 'https://www.instagram.com/karman.store',
                'website' => 'https://karmantelekom.com',
                'email' => 'info@karmantelekom.com',
                'google_map' => 'https://maps.app.goo.gl/7qtJbhCbHP1g5bUb7',
            ],
            'address' => 'Sahibiata, Atatürk Cd. No: 15, 42040 Meram/Konya',
        ]);

        $simex = Shop::create([
            'name' => 'Simex İletişim',
            'phone' => '',
            'links' => [
                'facebook' => 'https://www.facebook.com/karman.store',
                'instagram' => 'https://www.instagram.com/karman.store',
                'website' => 'https://karmantelekom.com',
                'email' => 'info@karmantelekom.com',
                'google_map' => 'https://maps.app.goo.gl/7qtJbhCbHP1g5bUb7',
            ],
            'address' => 'Sahibiata, Atatürk Cd. No: 15, 42040 Meram/Konya',
            'user_id' => 9, // Zain Salesperson
        ]);
    }
}
