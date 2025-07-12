<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            [
                'key' => 'site_name',
                'type' => 'string',
                'group' => 'general',
                'value' => 'İndirimGo',
                'is_public' => false,
            ],
            [
                'key' => 'phone_number_1',
                'type' => 'string',
                'group' => 'general',
                'value' => '+90 (535) 340-2539',
                'is_public' => false,
            ],
            [
                'key' => 'phone_number_2',
                'type' => 'string',
                'group' => 'general',
                'value' => '',
                'is_public' => false,
            ],
            [
                'key' => 'phone_number_3',
                'type' => 'string',
                'group' => 'general',
                'value' => '',
                'is_public' => false,
            ],
            [
                'key' => 'contact_email',
                'type' => 'string',
                'group' => 'general',
                'value' => 'karmanomar9696@gmail.com',
                'is_public' => false,
            ],
            [
                'key' => 'product_prices',
                'type' => 'string',
                'group' => 'general',
                'value' => 'enabled',
                'is_public' => false,
            ],
            [
                'key' => 'facebook_url',
                'type' => 'string',
                'group' => 'social',
                'value' => 'https://facebook.com',
                'is_public' => false,
            ],
            [
                'key' => 'twitter_url',
                'type' => 'string',
                'group' => 'social',
                'value' => 'https://twitter.com',
                'is_public' => false,
            ],
            [
                'key' => 'instagram_url',
                'type' => 'string',
                'group' => 'social',
                'value' => 'https://instagram.com',
                'is_public' => false,
            ],
            [
                'key' => 'whatsapp_number',
                'type' => 'string',
                'group' => 'social',
                'value' => '905353402539',
                'is_public' => false,
            ],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
