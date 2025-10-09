<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Currency;
use App\Models\ProductPrice;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CurrencyService
{
    private const CACHE_KEY = 'currency_rates';
    private const CACHE_TTL = 3600; // 1 hour

    public function updateExchangeRates(): void
    {
        try {
            $response = Http::get('https://api.exchangerate-api.com/v4/latest/TRY');

            if ($response->successful()) {
                $rates = $response->json()['rates'];

                // Apply configurable markup to approximate selling price
                $markupPercent = (float) env('FX_SELL_MARKUP_PERCENT', 0);
                $factor = 1 + ($markupPercent / 100.0);

                $adjustedRates = [];
                foreach ($rates as $code => $rate) {
                    if ($code === 'TRY') {
                        // Keep base currency at 1.0
                        $adjustedRates[$code] = 1.0;
                        continue;
                    }

                    $adjustedRates[$code] = round((float) $rate * $factor, 8);
                }

                Currency::query()
                    ->where('is_default', false)
                    ->where('is_active', true)
                    ->each(function (Currency $currency) use ($adjustedRates) {
                        if (isset($adjustedRates[$currency->code])) {
                            $currency->update([
                                'exchange_rate' => $adjustedRates[$currency->code]
                            ]);
                        }
                    });

                Cache::put(self::CACHE_KEY, $adjustedRates, self::CACHE_TTL);

                // Update product prices based on new exchange rates
                $this->updateProductPrices($adjustedRates);


            }
        } catch (\Exception $e) {
            Log::error('Failed to update exchange rates: ' . $e->getMessage());
        }
    }

    public function convertPrice(float $amount, Currency $fromCurrency, Currency $toCurrency): float
    {
        if ($fromCurrency->is_default) {
            return $toCurrency->convertFromDefault($amount);
        }

        if ($toCurrency->is_default) {
            return $fromCurrency->convertToDefault($amount);
        }

        $inDefaultCurrency = $fromCurrency->convertToDefault($amount);
        return $toCurrency->convertFromDefault($inDefaultCurrency);
    }

    public function getExchangeRate(string $fromCurrency, string $toCurrency): float
    {
        $rates = Cache::get(self::CACHE_KEY);

        if (!$rates) {
            $this->updateExchangeRates();
            $rates = Cache::get(self::CACHE_KEY);
        }

        if ($fromCurrency === 'TRY') {
            return $rates[$toCurrency] ?? 1.0;
        }

        if ($toCurrency === 'TRY') {
            return 1 / ($rates[$fromCurrency] ?? 1.0);
        }

        $tryRate = 1 / ($rates[$fromCurrency] ?? 1.0);
        return $tryRate * ($rates[$toCurrency] ?? 1.0);
    }

    /**
     * Update product prices based on new exchange rates.
     * Only updates TRY prices while keeping USD prices unchanged.
     *
     * @param array $adjustedRates The new exchange rates
     * @return void
     */
    private function updateProductPrices(array $adjustedRates): void
    {
        try {
            // Get USD to TRY exchange rate
            $usdToTryRate = $adjustedRates['USD'] ?? null;
            
            if (!$usdToTryRate) {
                Log::warning('USD to TRY exchange rate not found, skipping price updates');
                return;
            }

            // Get TRY currency model
            $tryCurrency = Currency::where('code', 'TRY')->first();
            if (!$tryCurrency) {
                Log::warning('TRY currency not found, skipping price updates');
                return;
            }

            // Update all TRY prices based on the new exchange rate
            ProductPrice::whereHas('currency', function ($query) {
                $query->where('code', 'TRY');
            })->chunk(100, function ($prices) use ($usdToTryRate) {
                foreach ($prices as $price) {
                    // Only update if this price has a corresponding USD price
                    $usdPrice = ProductPrice::where('product_id', $price->product_id)
                        ->whereHas('currency', function ($query) {
                            $query->where('code', 'USD');
                        })
                        ->first();

                    if ($usdPrice) {
                        // Calculate new TRY price: USD price * new exchange rate
                        $newTryPrice = round($usdPrice->base_price * $usdToTryRate, 2);
                        
                        // Update the TRY price
                        $price->update([
                            'base_price' => $newTryPrice,
                            'converted_price' => $newTryPrice
                        ]);

                        Log::info("Updated product {$price->product_id} TRY price from {$price->base_price} to {$newTryPrice} (USD: {$usdPrice->base_price}, Rate: {$usdToTryRate})");
                    }
                }
            });

            Log::info('Product prices updated successfully based on new exchange rates');

        } catch (\Exception $e) {
            Log::error('Failed to update product prices: ' . $e->getMessage());
        }
    }
}
