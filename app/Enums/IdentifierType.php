<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Identifiers of an individual inventory unit.
 */
enum IdentifierType: string
{
    case IMEI = 'imei';
    case SERIAL = 'serial';

    public function label(): string
    {
        return __('inventory.identifier_types.'.$this->value);
    }
}
