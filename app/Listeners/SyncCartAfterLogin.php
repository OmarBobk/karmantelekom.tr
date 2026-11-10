<?php

namespace App\Listeners;

use App\Livewire\Frontend\Cart\CartComponent;
use App\Services\CartService;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;

class SyncCartAfterLogin
{
    public function __construct(
        protected Request $request,
        protected CartService $carts,
    ) {}

    /**
     * When the buyer signs in to an empty stored cart, let the browser's guest cart be adopted
     * once. The grant is the cart's revision at sign-in, so any change to the cart before the
     * adoption, such as another tab's, voids it. Signing in replaces any earlier account's grant.
     */
    public function handle(Login $event): void
    {
        if (! $this->request->hasSession()) {
            return;
        }

        $grant = $this->carts->adoptionGrant((int) $event->user->getAuthIdentifier());

        if ($grant === null) {
            $this->request->session()->forget(CartComponent::ADOPTION_GRANT);
        } else {
            $this->request->session()->put(CartComponent::ADOPTION_GRANT, $grant);
        }
    }
}
