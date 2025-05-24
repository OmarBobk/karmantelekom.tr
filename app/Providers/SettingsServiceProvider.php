<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\SettingsService;
use Carbon\Laravel\ServiceProvider;

class SettingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('settings', function ($app) {
            return new SettingsService();
        });
    }
}
