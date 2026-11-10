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
     * by its first sync. The grant is the cart's current revision, so any change to the cart
     * before that sync, such as another tab's, voids it.
     */
    public function handle(Login $event): void
    {
        if (! $this->request->hasSession()) {
            return;
        }

        $cart = $this->carts->getOrCreateCart((int) $event->user->getAuthIdentifier(), null);

        if ($cart->items()->exists()) {
            $this->request->session()->forget(CartComponent::ADOPTION_GRANT);

            return;
        }

        $this->request->session()->put(CartComponent::ADOPTION_GRANT, CartService::revision($cart));
    }
}
