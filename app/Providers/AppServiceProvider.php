<?php

namespace App\Providers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
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
//        if (Str::startsWith(request()->uri()->host(), 'sub.') && !Auth::check()) {
//            abort(Response::redirectTo(config('app.url') . '/404'));
//        }

        Route::domain(config('app.subdomain'))
            ->name('subdomain.')
            ->group(function () {
                Route::middleware(['role.redirect'])
                    ->group(base_path('routes/subdomain.php'));
            });
    }
}
