<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Block
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // block condition
        if ($request->is('login') or $request->is('register') or $request->is('checkout')) {
            abort(403, 'This page is temporarily disabled.');
            // OR redirect
            // return redirect('/');
        }

        return $next($request);
    }
}
