<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Result of inspecting a used device: ready for sale, or held for repair.
 */
enum InspectionOutcome: string
{
    case READY = 'ready';
    case NEEDS_REPAIR = 'needs_repair';

    public function resultingStatus(): UnitStatus
    {
        return match ($this) {
            self::READY => UnitStatus::AVAILABLE,
            self::NEEDS_REPAIR => UnitStatus::INSPECTION,
        };
    }

    public function label(): string
    {
        return __('devices.inspection_outcomes.'.$this->value);
    }
}
