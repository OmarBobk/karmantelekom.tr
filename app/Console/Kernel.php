<?php
declare(strict_types=1);

namespace App\Console;

use App\Services\CurrencyService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // Update currency exchange rates every hour
        $schedule->call(function () {
            app(CurrencyService::class)->updateExchangeRates();
        })->hourly();

        // Update product prices after exchange rates are updated
        $schedule->command('products:update-prices')
            ->hourly()
            ->after(function () {
                \Log::info('Product prices updated successfully');
            });
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
} 