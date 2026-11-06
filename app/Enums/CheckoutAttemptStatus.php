<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Lifecycle of a persisted POS checkout attempt.
 *
 * OPEN attempts are unresolved: their reviewed cart may still change until a card approval
 * is recorded, after which the payload is frozen. COMPLETED attempts own exactly one sale.
 * CANCELLED attempts never had a card approval. VOIDED attempts had a card approval that was
 * confirmed voided on the terminal and resolved by the owner.
 */
enum CheckoutAttemptStatus: string
{
    case OPEN = 'open';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case VOIDED = 'voided';

    public function label(): string
    {
        return __('pos.attempt_statuses.'.$this->value);
    }
}
