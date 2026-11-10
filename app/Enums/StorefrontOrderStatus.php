<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Stored status of a storefront order for branch stock.
 *
 * PENDING orders hold no stock. CONFIRMED orders hold their lines' stock until the pickup
 * deadline, or longer while a counter checkout holds the payment claim; a confirmed order past
 * its deadline without a claim is effectively expired before anything stores EXPIRED. REJECTED
 * is the branch declining a pending order. FULFILLED, CANCELLED, EXPIRED, and REJECTED are final.
 */
enum StorefrontOrderStatus: string
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case FULFILLED = 'fulfilled';
    case CANCELLED = 'cancelled';
    case EXPIRED = 'expired';
    case REJECTED = 'rejected';

    /**
     * @return list<self>
     */
    public static function closed(): array
    {
        return [self::FULFILLED, self::CANCELLED, self::EXPIRED, self::REJECTED];
    }

    public function label(): string
    {
        return __('main.branch_orders.statuses.'.$this->value);
    }

    /**
     * The legacy order status shown by existing order screens.
     */
    public function orderStatus(): OrderStatus
    {
        return match ($this) {
            self::PENDING => OrderStatus::PENDING,
            self::CONFIRMED => OrderStatus::CONFIRMED,
            self::FULFILLED => OrderStatus::DELIVERED,
            self::CANCELLED, self::EXPIRED, self::REJECTED => OrderStatus::CANCELED,
        };
    }
}
