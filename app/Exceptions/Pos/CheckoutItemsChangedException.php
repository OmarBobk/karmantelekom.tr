<?php

declare(strict_types=1);

namespace App\Exceptions\Pos;

use Illuminate\Validation\ValidationException;

/**
 * Reviewed items are no longer sellable as reviewed: stock, device state, price, or the
 * cashier's discount limit changed.
 *
 * Unlike payment input errors, this is recorded on the checkout attempt as a confirmed failure
 * so the cashier and owner can see it after a reload.
 */
class CheckoutItemsChangedException extends ValidationException
{
    public const UNAVAILABLE = 'unavailable';

    public const PRICE_CHANGED = 'price_changed';

    public const DISCOUNT_CHANGED = 'discount_changed';

    public string $failureCode = self::UNAVAILABLE;

    /**
     * @param  array<string, string>  $messages
     */
    public static function because(string $failureCode, array $messages): self
    {
        $exception = static::withMessages($messages);
        $exception->failureCode = $failureCode;

        return $exception;
    }
}
