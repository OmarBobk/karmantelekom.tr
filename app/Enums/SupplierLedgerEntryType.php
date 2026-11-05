<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Append-only supplier account entries. Positive amounts increase what the business owes.
 */
enum SupplierLedgerEntryType: string
{
    case PURCHASE_POSTED = 'purchase_posted';
    case PURCHASE_REVERSED = 'purchase_reversed';
    case PAYMENT = 'payment';
    case PAYMENT_REVERSED = 'payment_reversed';

    public function label(): string
    {
        return __('purchases.ledger_types.'.$this->value);
    }
}
