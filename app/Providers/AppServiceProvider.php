<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
        if (Str::startsWith(request()->uri()->host(), 'sub.')) {
            Route::get('/login', function () {
                return redirect()->away(config('app.url') . '/login');
            })->name('login');
        }

        Route::domain(config('app.subdomain'))
            ->name('subdomain.')
            ->group(function () {
                Route::middleware(['web', 'auth', 'role.redirect'])
                    ->group(base_path('routes/subdomain.php'));
            });
    }
}
