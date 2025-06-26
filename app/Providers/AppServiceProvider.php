<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use App\Services\CurrencyService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CurrencyService::class, function ($app) {
            return new CurrencyService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
//        if (Str::startsWith(request()->uri()->host(), 'sub.') && !Auth::check()) {
//            abort(Response::redirectTo(config('app.url') . '/404'));
//        }

        if (app()->environment('local')) {
            config(['cache.default' => 'array']);
        }

        Route::domain(config('app.subdomain'))
            ->middleware(['web', 'auth', 'role.redirect'])
            ->name('subdomain.')
            ->group(base_path('routes/subdomain.php'));

        // Force HTTPS in production
        if (app()->environment('production') && !app()->environment('local')) {
            \URL::forceScheme('https');
        }
    }
}
