<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ShopCreationMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if the user is authenticated
        if (Auth::check()) {
            $user = Auth::user();

            // Check if the user has the "shop_owner" role
            if ($user->hasRole('shop_owner')) {
                // Check if the user has created a shop
                if (!$user->ownedShop) {
                    // If they haven't created a shop, redirect to shop creation
                    if (!$request->is('shop/create') && !$request->is('shop/store')) {
                        return redirect()->route('shop.create');
                    }
                }
            }
        }

        return $next($request);
    }
}
