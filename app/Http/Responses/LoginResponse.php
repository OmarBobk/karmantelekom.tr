<?php

namespace App\Http\Responses;

use App\Models\Store;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return response()->json(['two_factor' => false]);
        }

        $user = Auth::user();

        // Shop owners who have not created a shop yet must finish onboarding first.
        if ($user && $user->hasRole('shop_owner') && ! $user->ownedShop) {
            return redirect()->route('shop.create');
        }

        // Backend staff land on the subscriber subdomain home (owners are redirected onward).
        if ($user && $user->hasRole(['admin', 'salesperson'])) {
            $destination = $user->can('viewOperationsDashboard', Store::class)
                ? route('subdomain.operations.dashboard')
                : route('subdomain.main');

            return redirect()->intended($destination);
        }

        return redirect()->intended(route('main'));
    }
}
