<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How the invoice's unit prices relate to KDV. Prices are always entered as the supplier
 * billed them (after discount); no tax breakdown is calculated in this phase.
 */
enum PurchaseTaxTreatment: string
{
    case KDV_INCLUDED = 'kdv_included';
    case NOT_APPLICABLE = 'not_applicable';

    public function label(): string
    {
        return __('purchases.tax_treatments.'.$this->value);
    }
}
