<?php

declare(strict_types=1);

namespace App\Providers;

use App\Facades\Settings;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use App\Services\CurrencyService;
use Spatie\Activitylog\Models\Activity;

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

        $this->app->singleton(\App\Services\NotificationRecipientsService::class, function ($app) {
            return new \App\Services\NotificationRecipientsService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Carbon\Carbon::setLocale(app()->getLocale());
//        if (Str::startsWith(request()->uri()->host(), 'sub.') && !Auth::check()) {
//            abort(Response::redirectTo(config('app.url') . '/404'));
//        }

        // Register event listeners
//        $this->registerEventListeners();

//        Activity::created(function ($activity) {
//            $recipient = User::whereId(1)->first();
//
//            if ($recipient) {
//                $recipient->notify(new \App\Notifications\NewActivityLogged($activity));
//            }
//        });

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

    /**
     * Register event listeners for the application.
     */
//    private function registerEventListeners(): void
//    {
//        \Illuminate\Support\Facades\Event::listen(
//            \App\Events\OrderCreated::class,
//            \App\Listeners\HandleOrderCreated::class
//        );
//
//        \Illuminate\Support\Facades\Event::listen(
//            \App\Events\OrderUpdated::class,
//            \App\Listeners\HandleOrderUpdated::class
//        );
//    }
}
