<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Kinds of inventory movement supported before purchasing and sales exist.
 */
enum MovementType: string
{
    case OPENING_BALANCE = 'opening_balance';
    case ADJUSTMENT_INCREASE = 'adjustment_increase';
    case ADJUSTMENT_DECREASE = 'adjustment_decrease';

    /**
     * Reasons that may accompany this movement type.
     *
     * @return list<AdjustmentReason>
     */
    public function allowedReasons(): array
    {
        return match ($this) {
            self::OPENING_BALANCE => [AdjustmentReason::OPENING_BALANCE],
            self::ADJUSTMENT_INCREASE => [AdjustmentReason::COUNT_CORRECTION, AdjustmentReason::FOUND],
            self::ADJUSTMENT_DECREASE => [AdjustmentReason::COUNT_CORRECTION, AdjustmentReason::DAMAGED, AdjustmentReason::LOST],
        };
    }

    public function allows(AdjustmentReason $reason): bool
    {
        return in_array($reason, $this->allowedReasons(), true);
    }

    public function requiresNote(): bool
    {
        return $this === self::ADJUSTMENT_DECREASE;
    }

    public function label(): string
    {
        return __('inventory.movement_types.'.$this->value);
    }
}
