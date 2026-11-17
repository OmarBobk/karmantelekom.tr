<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\Currency;
use App\Enums\QuantityCostStatus;
use App\Exceptions\Purchasing\InvalidAmountException;
use App\Models\InventoryBalance;
use App\Services\Purchasing\Money;
use LogicException;

/**
 * Authoritative recorded-cost transitions for a quantity-tracked balance pool.
 *
 * Call only while the balance row is locked. Quantity and cost state are written together so
 * the database consistency check never sees an empty pool with stock or a known pool without cost.
 */
final class QuantityCostPool
{
    public const BASIS_VERSION = 'recorded-cost-v1';

    /**
     * Share of pool total T attributable to outbound quantity q from prior quantity Q.
     *
     * removed = floor(T * q / Q); when q = Q the entire residual is consumed.
     *
     * @throws InvalidAmountException
     */
    public static function shareOutbound(int $totalMinor, int $quantityBefore, int $quantityRemoved): int
    {
        if ($quantityBefore < 1 || $quantityRemoved < 1 || $quantityRemoved > $quantityBefore || $totalMinor < 0) {
            throw new LogicException('Invalid quantity-cost share inputs.');
        }

        if ($quantityRemoved === $quantityBefore) {
            return $totalMinor;
        }

        return self::floorRatio($totalMinor, $quantityRemoved, $quantityBefore);
    }

    /**
     * Increase on-hand quantity and attach a compatible TRY cost, or contaminate the whole pool.
     *
     * @param  int|null  $addedCostMinor  Total recorded cost of the inbound quantity; null means unknown inbound.
     */
    public static function increase(InventoryBalance $locked, int $addedQuantity, ?Currency $currency, ?int $addedCostMinor): void
    {
        if ($addedQuantity < 1) {
            throw new LogicException('Inbound quantity must be positive.');
        }

        $quantityBefore = (int) $locked->quantity;
        $quantityAfter = $quantityBefore + $addedQuantity;
        $compatible = $currency === Currency::TRY && $addedCostMinor !== null && $addedCostMinor >= 0;
        $priorStatus = self::status($locked);

        if (! $compatible) {
            self::write($locked, $quantityAfter, QuantityCostStatus::UNKNOWN, null, null);

            return;
        }

        if ($priorStatus === QuantityCostStatus::EMPTY
            || ($priorStatus === QuantityCostStatus::KNOWN && $locked->cost_currency === Currency::TRY->value)) {
            $priorTotal = $priorStatus === QuantityCostStatus::EMPTY ? 0 : Money::fromDecimal($locked->cost_total);
            $newTotal = Money::sum([$priorTotal, $addedCostMinor]);
            self::write($locked, $quantityAfter, QuantityCostStatus::KNOWN, Currency::TRY->value, Money::toDecimal($newTotal));

            return;
        }

        self::write($locked, $quantityAfter, QuantityCostStatus::UNKNOWN, null, null);
    }

    /**
     * Decrease on-hand quantity and remove proportional known cost, or clear on full depletion.
     *
     * @return int Recorded cost removed from a known pool (0 when unknown/empty).
     */
    public static function decrease(InventoryBalance $locked, int $removedQuantity): int
    {
        if ($removedQuantity < 1) {
            throw new LogicException('Outbound quantity must be positive.');
        }

        $quantityBefore = (int) $locked->quantity;

        if ($removedQuantity > $quantityBefore) {
            throw new LogicException('Cannot remove more quantity than on hand.');
        }

        $quantityAfter = $quantityBefore - $removedQuantity;
        $status = self::status($locked);
        $knownTry = $status === QuantityCostStatus::KNOWN && $locked->cost_currency === Currency::TRY->value;
        $totalBefore = $knownTry ? Money::fromDecimal($locked->cost_total) : 0;
        $removed = $knownTry ? self::shareOutbound($totalBefore, $quantityBefore, $removedQuantity) : 0;

        if ($quantityAfter === 0) {
            self::write($locked, 0, QuantityCostStatus::EMPTY, null, null);

            return $removed;
        }

        if (! $knownTry) {
            self::write($locked, $quantityAfter, QuantityCostStatus::UNKNOWN, null, null);

            return 0;
        }

        self::write(
            $locked,
            $quantityAfter,
            QuantityCostStatus::KNOWN,
            Currency::TRY->value,
            Money::toDecimal($totalBefore - $removed),
        );

        return $removed;
    }

    /**
     * Set quantity to an absolute counted total and apply cost rules for the signed difference.
     */
    public static function setQuantity(InventoryBalance $locked, int $newQuantity): int
    {
        if ($newQuantity < 0) {
            throw new LogicException('Quantity cannot be negative.');
        }

        $prior = (int) $locked->quantity;
        $difference = $newQuantity - $prior;

        if ($difference === 0) {
            return 0;
        }

        if ($difference > 0) {
            self::increase($locked, $difference, null, null);

            return 0;
        }

        return self::decrease($locked, abs($difference));
    }

    /**
     * Contaminate the current on-hand quantity as unknown (quantity unchanged).
     */
    public static function markUnknown(InventoryBalance $locked): void
    {
        $quantity = (int) $locked->quantity;

        if ($quantity === 0) {
            self::write($locked, 0, QuantityCostStatus::EMPTY, null, null);

            return;
        }

        self::write($locked, $quantity, QuantityCostStatus::UNKNOWN, null, null);
    }

    /**
     * Owner declaration of the total TRY cost of the current remaining quantity.
     */
    public static function declareTotal(InventoryBalance $locked, int $expectedQuantity, int $declaredTotalMinor): void
    {
        $quantity = (int) $locked->quantity;

        if ($quantity === 0 || $expectedQuantity !== $quantity) {
            throw new LogicException('Declaration quantity does not match the locked balance.');
        }

        if ($declaredTotalMinor < 0) {
            throw new InvalidAmountException(InvalidAmountException::FORMAT);
        }

        self::write($locked, $quantity, QuantityCostStatus::KNOWN, Currency::TRY->value, Money::toDecimal($declaredTotalMinor));
    }

    private static function status(InventoryBalance $locked): QuantityCostStatus
    {
        return $locked->cost_status instanceof QuantityCostStatus
            ? $locked->cost_status
            : QuantityCostStatus::from((string) ($locked->cost_status ?? QuantityCostStatus::EMPTY->value));
    }

    private static function write(
        InventoryBalance $locked,
        int $quantity,
        QuantityCostStatus $status,
        ?string $currency,
        ?string $total,
    ): void {
        $locked->forceFill([
            'quantity' => $quantity,
            'cost_status' => $status,
            'cost_currency' => $currency,
            'cost_total' => $total,
        ])->save();
    }

    /**
     * floor((total * numerator) / denominator) without intermediate overflow when possible.
     *
     * @throws InvalidAmountException
     */
    private static function floorRatio(int $total, int $numerator, int $denominator): int
    {
        if ($denominator < 1 || $numerator < 0 || $total < 0) {
            throw new LogicException('Invalid floor-ratio inputs.');
        }

        if ($numerator === 0 || $total === 0) {
            return 0;
        }

        if ($total <= intdiv(PHP_INT_MAX, $numerator)) {
            return intdiv($total * $numerator, $denominator);
        }

        $g = self::gcd($numerator, $denominator);
        $numerator = intdiv($numerator, $g);
        $denominator = intdiv($denominator, $g);

        if ($total <= intdiv(PHP_INT_MAX, $numerator)) {
            return intdiv($total * $numerator, $denominator);
        }

        throw new InvalidAmountException(InvalidAmountException::TOO_LARGE);
    }

    private static function gcd(int $a, int $b): int
    {
        $a = abs($a);
        $b = abs($b);

        while ($b !== 0) {
            [$a, $b] = [$b, $a % $b];
        }

        return $a === 0 ? 1 : $a;
    }
}
