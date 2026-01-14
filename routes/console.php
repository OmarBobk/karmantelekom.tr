<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();


Artisan::command('back-up-database', function () {
    BackUpDatabase::dispatch();
    $this->info('Database backup job has been dispatched!');
})->purpose('Back up the database');