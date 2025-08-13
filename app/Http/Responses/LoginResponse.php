<?php

namespace App\Http\Responses;

use App\Models\Cart;
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

        // Check if user is a shop owner without a shop
        if ($user && $user->hasRole('shop_owner') && !$user->ownedShop) {
            return redirect()->route('shop.create');
        }

        // Default redirect to main page
        return redirect()->intended(route('main'));
    }
}
