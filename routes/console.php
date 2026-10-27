<?php

declare(strict_types=1);

use App\Jobs\BackUpDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('back-up-database', function (): void {
    BackUpDatabase::dispatch();

    $this->info('Database backup job has been dispatched!');
})->purpose('Back up the database');

Schedule::command('sitemap:generate')->daily();
Schedule::job(new BackUpDatabase)->dailyAt('01:00');
