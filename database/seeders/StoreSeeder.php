<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Store;
use Illuminate\Database\Seeder;

class StoreSeeder extends Seeder
{
    /**
     * Initial operational stores keyed by their stable code.
     *
     * @var array<string, array{name: string}>
     */
    public const INITIAL_STORES = [
        'KT01' => ['name' => 'Karman Telekom — Store 1'],
        'KT02' => ['name' => 'Karman Telekom — Store 2'],
    ];

    /**
     * Create any missing initial stores without overwriting edited details.
     */
    public function run(): void
    {
        foreach (self::INITIAL_STORES as $code => $attributes) {
            Store::firstOrCreate(['code' => $code], $attributes);
        }
    }
}
