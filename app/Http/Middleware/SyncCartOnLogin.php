<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\CartService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SyncCartOnLogin
{
    public function __construct(private CartService $cartService)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->fullUrl() != config('app.subdomain')) {
            if (auth()->check()) {
                $this->cartService->syncSessionCartToUser(auth()->user());
            }
        }

        return $next($request);
    }
}
