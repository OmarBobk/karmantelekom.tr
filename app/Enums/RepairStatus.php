<?php

declare(strict_types=1);

namespace App\Enums;

enum RepairStatus: string
{
    case OPEN = 'open';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return __('devices.repair_statuses.'.$this->value);
    }
}
