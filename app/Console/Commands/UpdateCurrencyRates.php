<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\CurrencyService;
use Illuminate\Console\Command;

class UpdateCurrencyRates extends Command
{
    protected $signature = 'currency:update-rates';
    protected $description = 'Update currency exchange rates from API';

    public function handle(CurrencyService $currencyService): int
    {
        try {
            $this->info('Updating currency exchange rates...');
            $currencyService->updateExchangeRates();
            $this->info('Exchange rates updated successfully!');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to update exchange rates: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
