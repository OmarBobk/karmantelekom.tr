<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RoleRedirectMiddleware
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

            // Check if the user has the "shop owner" role
            if (!$user->hasRole('admin|salesperson')) {
                // Redirect to a specific page if they try to access the dashboard or subdomain
                return redirect()->to(config('app.url') . '/404');
            }
        }

        return $next($request);
    }
}
