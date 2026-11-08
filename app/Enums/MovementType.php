<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Kinds of inventory movement.
 *
 * Stock movements change a quantity. Unit events record a used device's inspection,
 * repair, cost, or identity history with a zero quantity; the database only accepts
 * a zero quantity for these types.
 */
enum MovementType: string
{
    case OPENING_BALANCE = 'opening_balance';
    case ADJUSTMENT_INCREASE = 'adjustment_increase';
    case ADJUSTMENT_DECREASE = 'adjustment_decrease';
    case PURCHASE_RECEIPT = 'purchase_receipt';
    case INSPECTION = 'inspection';
    case REPAIR_STARTED = 'repair_started';
    case REPAIR_COMPLETED = 'repair_completed';
    case REPAIR_CANCELLED = 'repair_cancelled';
    case DEVICE_COST_RECORDED = 'device_cost_recorded';
    case DEVICE_COST_REVERSED = 'device_cost_reversed';
    case IDENTIFIER_CORRECTED = 'identifier_corrected';
    case REVIEW_APPROVED = 'review_approved';
    case SALE = 'sale';
    case TRANSFER_OUT = 'transfer_out';
    case TRANSFER_IN = 'transfer_in';

    /**
     * Reasons an operator may choose for this movement type. Physical counts record
     * AdjustmentReason::PHYSICAL_COUNT themselves and are not choosable here.
     *
     * @return list<AdjustmentReason>
     */
    public function allowedReasons(): array
    {
        return match ($this) {
            self::OPENING_BALANCE => [AdjustmentReason::OPENING_BALANCE],
            self::ADJUSTMENT_INCREASE => [AdjustmentReason::COUNT_CORRECTION, AdjustmentReason::FOUND],
            self::ADJUSTMENT_DECREASE => [AdjustmentReason::COUNT_CORRECTION, AdjustmentReason::DAMAGED, AdjustmentReason::LOST],
            self::PURCHASE_RECEIPT => [AdjustmentReason::PURCHASE],
            self::INSPECTION => [AdjustmentReason::INSPECTION],
            self::REPAIR_STARTED, self::REPAIR_COMPLETED, self::REPAIR_CANCELLED => [AdjustmentReason::REPAIR],
            self::DEVICE_COST_RECORDED, self::DEVICE_COST_REVERSED => [AdjustmentReason::DEVICE_COST],
            self::IDENTIFIER_CORRECTED => [AdjustmentReason::IDENTITY_CORRECTION],
            self::REVIEW_APPROVED => [AdjustmentReason::REVIEW],
            self::SALE => [AdjustmentReason::SALE],
            self::TRANSFER_OUT, self::TRANSFER_IN => [AdjustmentReason::TRANSFER],
        };
    }

    public function allows(AdjustmentReason $reason): bool
    {
        return in_array($reason, $this->allowedReasons(), true);
    }

    /**
     * Zero-quantity events that never change stock.
     */
    public function isUnitEvent(): bool
    {
        return in_array($this, self::unitEvents(), true);
    }

    /**
     * @return list<self>
     */
    public static function unitEvents(): array
    {
        return [
            self::INSPECTION,
            self::REPAIR_STARTED,
            self::REPAIR_COMPLETED,
            self::REPAIR_CANCELLED,
            self::DEVICE_COST_RECORDED,
            self::DEVICE_COST_REVERSED,
            self::IDENTIFIER_CORRECTED,
            self::REVIEW_APPROVED,
        ];
    }

    /**
     * @return list<self>
     */
    public static function costEvents(): array
    {
        return [self::DEVICE_COST_RECORDED, self::DEVICE_COST_REVERSED];
    }

    public function label(): string
    {
        return __('inventory.movement_types.'.$this->value);
    }
}
