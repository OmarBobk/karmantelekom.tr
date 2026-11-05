<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Identifiers that point to a product definition.
 *
 * IMEI and serial numbers identify individual devices and are intentionally absent.
 */
enum CatalogIdentifierType: string
{
    case SKU = 'sku';
    case BARCODE = 'barcode';

    public function column(): string
    {
        return $this->value;
    }
}
