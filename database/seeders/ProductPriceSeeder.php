<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Currency;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Services\CurrencyService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductPriceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $currencyService = app(CurrencyService::class);
        $tryCurrency = Currency::where('code', 'TRY')->firstOrFail();
        $usdCurrency = Currency::where('code', 'USD')->firstOrFail();

        // Use transaction for better performance and data integrity
        DB::transaction(function () use ($currencyService, $tryCurrency, $usdCurrency) {
            // Create prices in chunks for better performance
            Product::chunk(100, function ($products) use ($currencyService, $tryCurrency, $usdCurrency) {
                foreach ($products as $product) {

                    $base_price = fake()->randomFloat(2, 100, 1000);

                    // Create retail price (TL only)
                    ProductPrice::create([
                        'product_id' => $product->id,
                        'currency_id' => $tryCurrency->id,
                        'base_price' => $base_price,
                        'converted_price' => $base_price,
                        'is_main_price' => true
                    ]);

                    // Create price in USD
                    $price_usd = $currencyService->convertPrice(
                        $base_price,
                        $tryCurrency,
                        $usdCurrency
                    );

                    ProductPrice::create([
                        'product_id' => $product->id,
                        'currency_id' => $usdCurrency->id,
                        'base_price' => $base_price,
                        'converted_price' => $price_usd,
                        'is_main_price' => false
                    ]);
                }
            });
        });
    }
}
