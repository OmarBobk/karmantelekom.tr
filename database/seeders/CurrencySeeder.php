<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        Currency::firstOrCreate(
            ['code' => 'TRY'],
            [
                'name' => 'Turkish Lira',
                'symbol' => '₺',
                'is_default' => true,
            ]
        );

        Currency::firstOrCreate(
            ['code' => 'USD'],
            [
                'name' => 'US Dollar',
                'symbol' => '$',
                'is_default' => false,
            ]
        );
    }
} 