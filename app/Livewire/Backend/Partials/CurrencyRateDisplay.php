<?php

declare(strict_types=1);

namespace App\Livewire\Backend\Partials;

use App\Models\Currency;
use App\Services\CurrencyService;
use Livewire\Component;

/**
 * CurrencyRateDisplay Component
 *
 * Displays the current USD to TRY exchange rate
 */
class CurrencyRateDisplay extends Component
{
    public string $rateDisplay = '';

    public function mount(): void
    {
        $this->loadRate();
    }

    public function loadRate(): void
    {
        try {
            // Get USD currency from database
            $usdCurrency = Currency::where('code', 'USD')
                ->where('is_active', true)
                ->first();

            if ($usdCurrency && $usdCurrency->exchange_rate) {
                // Format the rate as "1$ = X.XX TL"
                $rate = number_format((float) (1 / $usdCurrency->exchange_rate), 2, '.', '');
                $this->rateDisplay = "1$ = {$rate}TL";
            } else {
                // Fallback: try to get rate from CurrencyService
                $currencyService = app(CurrencyService::class);
                $rate = $currencyService->getExchangeRate('USD', 'TRY');
                $formattedRate = number_format((float) $rate, 2, '.', '');
                $this->rateDisplay = "1$ = {$formattedRate}TL";
            }
        } catch (\Exception $e) {
            // If all else fails, show a default rate
            $this->rateDisplay = "1$ = 41.60TL";
        }
    }

    public function render()
    {
        return view('livewire.backend.partials.currency-rate-display');
    }
}
