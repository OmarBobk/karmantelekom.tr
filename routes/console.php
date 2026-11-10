<?php

declare(strict_types=1);

use App\Actions\Storefront\ExpireBranchOrders;
use App\Jobs\BackUpDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('back-up-database', function (): void {
    BackUpDatabase::dispatch();

    $this->info('Database backup job has been dispatched!');
})->purpose('Back up the database');

Artisan::command('storefront:expire-orders', function (ExpireBranchOrders $expire): void {
    $this->info("Expired {$expire()} storefront orders.");
})->purpose('Expire confirmed storefront orders past their pickup window');

Schedule::command('sitemap:generate')->daily();
Schedule::command('storefront:expire-orders')->everyFiveMinutes()->withoutOverlapping();
Schedule::job(new BackUpDatabase)->dailyAt('01:00');
