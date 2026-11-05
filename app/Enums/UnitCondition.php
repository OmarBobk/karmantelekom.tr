<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Physical condition of an individually tracked unit.
 *
 * New serialized units are always NEW; used devices are graded A, B, C, or FOR_PARTS.
 */
enum UnitCondition: string
{
    case NEW = 'new';
    case A = 'a';
    case B = 'b';
    case C = 'c';
    case FOR_PARTS = 'for_parts';

    /**
     * @return list<self>
     */
    public static function allowedFor(TrackingMode $mode): array
    {
        return match ($mode) {
            TrackingMode::SERIALIZED => [self::NEW],
            TrackingMode::USED_DEVICE => [self::A, self::B, self::C, self::FOR_PARTS],
            TrackingMode::QUANTITY => [],
        };
    }

    public function label(): string
    {
        return __('inventory.conditions.'.$this->value);
    }
}
