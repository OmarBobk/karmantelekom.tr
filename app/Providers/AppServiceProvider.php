<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\PurchaseReceiptLine;
use App\Models\SaleLine;
use App\Models\User;
use App\Services\CurrencyService;
use App\Services\NotificationRecipientsService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Spatie\Activitylog\Models\Activity;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CurrencyService::class, function ($app) {
            return new CurrencyService;
        });

        $this->app->singleton(NotificationRecipientsService::class, function ($app) {
            return new NotificationRecipientsService;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Carbon::setLocale(app()->getLocale());

        Relation::morphMap([
            PurchaseReceiptLine::MORPH_ALIAS => PurchaseReceiptLine::class,
            SaleLine::MORPH_ALIAS => SaleLine::class,
        ]);
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

        // The counter is open to assigned employees, so it must stay outside the legacy admin group below.
        Route::domain(config('app.subdomain'))
            ->middleware(['web', 'auth'])
            ->prefix('pos')
            ->name('pos.')
            ->group(base_path('routes/pos.php'));

        Route::domain(config('app.subdomain'))
            ->middleware(['web', 'auth', 'role.redirect'])
            ->name('subdomain.')
            ->group(base_path('routes/subdomain.php'));

        // Force HTTPS in production
        if (app()->environment('production') && ! app()->environment('local')) {
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
