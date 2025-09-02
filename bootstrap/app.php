<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders()
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'role.redirect' => \App\Http\Middleware\RoleRedirectMiddleware::class,
            'shop.creation' => \App\Http\Middleware\ShopCreationMiddleware::class,
        ]);
        $middleware->web([
            \App\Http\Middleware\SetLocale::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Customize the response for unauthenticated users
//        $exceptions->renderable(function (AuthenticationException $e, Request $request) {
//            if ($request->expectsJson()) {
//                dd('bootstrap/app.php error');
//                return response()->json(['message' => 'Unauthenticated.'], 401);
//            }
//
//            // Redirect to your custom login route
//            return redirect()->to(config('app.url') . '/404');
//        });
    })->create();
