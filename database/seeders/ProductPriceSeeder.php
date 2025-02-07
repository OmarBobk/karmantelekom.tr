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
                    // Generate base retail price in TL
                    $retailPrice = fake()->randomFloat(2, 100, 1000);
                    
                    // Calculate wholesale price (70% of retail)
                    $wholesalePriceTL = $retailPrice * 0.7;
                    
                    // Create retail price (TL only)
                    ProductPrice::create([
                        'product_id' => $product->id,
                        'currency_id' => $tryCurrency->id,
                        'price_type' => ProductPrice::TYPE_RETAIL,
                        'base_price' => $retailPrice,
                        'converted_price' => $retailPrice,
                        'is_main_price' => true
                    ]);

                    // Create wholesale price in TL
                    ProductPrice::create([
                        'product_id' => $product->id,
                        'currency_id' => $tryCurrency->id,
                        'price_type' => ProductPrice::TYPE_WHOLESALE,
                        'base_price' => $wholesalePriceTL,
                        'converted_price' => $wholesalePriceTL,
                        'is_main_price' => true
                    ]);

                    // Create wholesale price in USD (only for wholesale)
                    $wholesalePriceUSD = $currencyService->convertPrice(
                        $wholesalePriceTL,
                        $tryCurrency,
                        $usdCurrency
                    );

                    ProductPrice::create([
                        'product_id' => $product->id,
                        'currency_id' => $usdCurrency->id,
                        'price_type' => ProductPrice::TYPE_WHOLESALE,
                        'base_price' => $wholesalePriceTL,
                        'converted_price' => $wholesalePriceUSD,
                        'is_main_price' => false
                    ]);
                }
            });
        });
    }
}
