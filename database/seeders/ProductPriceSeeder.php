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
                    // Generate base price in TL
                    $basePrice = fake()->randomFloat(2, 100, 1000);
                    
                    // Create price in TRY
                    ProductPrice::create([
                        'product_id' => $product->id,
                        'currency_id' => $tryCurrency->id,
                        'base_price' => $basePrice,
                        'converted_price' => $basePrice,
                        'is_main_price' => true
                    ]);

                    // Create price in USD
                    $usdPrice = $currencyService->convertPrice(
                        $basePrice,
                        $tryCurrency,
                        $usdCurrency
                    );

                    ProductPrice::create([
                        'product_id' => $product->id,
                        'currency_id' => $usdCurrency->id,
                        'base_price' => $basePrice,
                        'converted_price' => $usdPrice,
                        'is_main_price' => false
                    ]);
                }
            });
        });
    }
}
