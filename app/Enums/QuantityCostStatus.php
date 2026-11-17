<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Cost state of the entire on-hand quantity pool for one branch and product.
 *
 * Known means recorded cost covers every unit currently on hand. There is never a known subset.
 */
enum QuantityCostStatus: string
{
    case EMPTY = 'empty';
    case KNOWN = 'known';
    case UNKNOWN = 'unknown';
}
