<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Frozen sale-line classification for recorded-cost margin reporting.
 */
enum SaleLineCostStatus: string
{
    case KNOWN = 'known';
    case UNKNOWN = 'unknown';
    case EXCLUDED_NETWORK = 'excluded_network';
}
