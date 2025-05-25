<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Currency;
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

                Currency::query()
                    ->where('is_default', false)
                    ->where('is_active', true)
                    ->each(function (Currency $currency) use ($rates) {
                        if (isset($rates[$currency->code])) {
                            $currency->update([
                                'exchange_rate' => $rates[$currency->code]
                            ]);
                        }
                    });

                Cache::put(self::CACHE_KEY, $rates, self::CACHE_TTL);
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
