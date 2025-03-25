<?php

declare(strict_types=1);

namespace App\Facades;

use App\Services\CartService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Collection getCartItems()
 * @method static Collection syncCartItemsToDatabase(array $cartItems)
 * @method static Collection mergeGuestCartWithUserCart(array $guestCartItems)
 * @method static void clearCart()
 * @method static Collection getVisibleProducts()
 * 
 * @see \App\Services\CartService
 */
class Cart extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return CartService::class;
    }
} 