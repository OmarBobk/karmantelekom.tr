<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * General operating expense categories for a branch. Supplier payouts and device costs are
 * recorded through their own procedures, not as expenses.
 */
enum ExpenseCategory: string
{
    case RENT = 'rent';
    case UTILITIES = 'utilities';
    case SALARIES = 'salaries';
    case SUPPLIES = 'supplies';
    case MAINTENANCE = 'maintenance';
    case TRANSPORT = 'transport';
    case MARKETING = 'marketing';
    case TAXES_FEES = 'taxes_fees';
    case OTHER = 'other';

    public function label(): string
    {
        return __('pos.expenses.categories.'.$this->value);
    }
}
