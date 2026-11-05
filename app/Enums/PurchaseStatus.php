<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Lifecycle of a supplier invoice. Only drafts are editable; posted facts are never changed.
 */
enum PurchaseStatus: string
{
    case DRAFT = 'draft';
    case POSTED = 'posted';
    case CANCELLED = 'cancelled';
    case REVERSED = 'reversed';

    public function label(): string
    {
        return __('purchases.statuses.'.$this->value);
    }
}
