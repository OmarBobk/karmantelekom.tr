<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Currency;
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
}
