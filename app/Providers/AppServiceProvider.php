<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Route::domain(config('app.subdomain'))
            ->group(function () {
                Route::middleware('web')
                    ->group(base_path('routes/subdomain.php'));
            });
    }
}
