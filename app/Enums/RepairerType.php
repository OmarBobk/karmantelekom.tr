<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Who performs a repair. An external repair means the device physically leaves the store.
 */
enum RepairerType: string
{
    case IN_HOUSE = 'in_house';
    case EXTERNAL = 'external';

    public function label(): string
    {
        return __('devices.repairer_types.'.$this->value);
    }
}
