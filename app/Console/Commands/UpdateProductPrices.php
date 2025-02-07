<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Currency;
use App\Models\ProductPrice;
use App\Services\CurrencyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateProductPrices extends Command
{
    protected $signature = 'products:update-prices';
    protected $description = 'Update all product prices based on current exchange rates';

    public function handle(CurrencyService $currencyService): int
    {
        $this->info('Starting to update product prices...');

        $defaultCurrency = Currency::where('is_default', true)->firstOrFail();
        $currencies = Currency::where('is_default', false)
            ->where('is_active', true)
            ->get();

        DB::beginTransaction();

        try {
            // Get all main prices (in TRY)
            ProductPrice::where('is_main_price', true)
                ->chunk(100, function ($mainPrices) use ($currencyService, $currencies, $defaultCurrency) {
                    foreach ($mainPrices as $mainPrice) {
                        $this->updatePricesForProduct(
                            $mainPrice,
                            $currencyService,
                            $currencies,
                            $defaultCurrency
                        );
                    }
                });

            DB::commit();
            $this->info('Successfully updated all product prices.');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Failed to update product prices: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function updatePricesForProduct(
        ProductPrice $mainPrice,
        CurrencyService $currencyService,
        $currencies,
        Currency $defaultCurrency
    ): void {
        // Only create USD prices for wholesale price type
        foreach ($currencies as $currency) {
            // Skip if trying to create retail price in USD
            if ($mainPrice->price_type === ProductPrice::TYPE_RETAIL && $currency->code === 'USD') {
                continue;
            }

            $convertedPrice = $currencyService->convertPrice(
                (float)$mainPrice->base_price,
                $defaultCurrency,
                $currency
            );

            ProductPrice::updateOrCreate(
                [
                    'product_id' => $mainPrice->product_id,
                    'currency_id' => $currency->id,
                    'price_type' => $mainPrice->price_type,
                ],
                [
                    'base_price' => $mainPrice->base_price,
                    'converted_price' => $convertedPrice,
                    'is_main_price' => false
                ]
            );
        }
    }
}
