<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * State of an externally approved card terminal payment recorded on a checkout attempt.
 */
enum CardApprovalStatus: string
{
    /** Approved on the terminal and not yet used by a completed sale. */
    case APPROVED = 'approved';

    /** Used as the card payment of exactly one completed sale. */
    case CAPTURED = 'captured';

    /** Confirmed voided on the terminal; never part of a sale. */
    case VOIDED = 'voided';

    public function label(): string
    {
        return __('pos.approval_statuses.'.$this->value);
    }
}
