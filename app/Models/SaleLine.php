<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TrackingMode;
use App\Services\Purchasing\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use LogicException;

/**
 * One sold item with snapshots of the reviewed product, device, and commercial values.
 * Its inventory movement references it.
 *
 * @property int $id
 * @property int $sale_id
 * @property int $line_number
 * @property int $product_definition_id
 * @property int|null $inventory_unit_id
 * @property TrackingMode $tracking_mode
 * @property string $product_name
 * @property string|null $product_model
 * @property string|null $sku
 * @property string|null $barcode
 * @property string|null $unit_identifier
 * @property string|null $unit_condition
 * @property int $quantity
 * @property string $list_unit_price
 * @property string $unit_price
 * @property string $unit_discount List unit price minus unit price.
 * @property string $discount_amount
 * @property string $line_total
 * @property string|null $adjustment_reason The discount reason the cashier gave.
 * @property string|null $discount_limit owner, percentage or device_minimum; NULL without a discount.
 * @property int|null $discount_basis_points The employee's percentage limit when it applied.
 * @property string|null $discount_lowest_unit_price The lowest unit price the limit allowed at review.
 * @property int|null $discounted_by
 */
class SaleLine extends Model
{
    public const MORPH_ALIAS = 'sale_line';

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'sale_id', 'line_number', 'product_definition_id', 'inventory_unit_id', 'tracking_mode',
        'product_name', 'product_model', 'sku', 'barcode', 'unit_identifier', 'unit_condition',
        'quantity', 'list_unit_price', 'unit_price', 'unit_discount', 'discount_amount', 'line_total', 'adjustment_reason',
        'discount_limit', 'discount_basis_points', 'discount_lowest_unit_price', 'discounted_by',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Sale lines are append-only.'));
        static::deleting(fn () => throw new LogicException('Sale lines are append-only.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tracking_mode' => TrackingMode::class,
            'quantity' => 'integer',
            'line_number' => 'integer',
            'discount_basis_points' => 'integer',
        ];
    }

    public function isDiscounted(): bool
    {
        return $this->discount_limit !== null;
    }

    public function listUnitPriceMinor(): int
    {
        return Money::fromDecimal($this->list_unit_price);
    }

    public function unitPriceMinor(): int
    {
        return Money::fromDecimal($this->unit_price);
    }

    public function discountMinor(): int
    {
        return Money::fromDecimal($this->discount_amount);
    }

    public function lineTotalMinor(): int
    {
        return Money::fromDecimal($this->line_total);
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function discountedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'discounted_by');
    }

    /**
     * @return BelongsTo<InventoryUnit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(InventoryUnit::class, 'inventory_unit_id');
    }

    /**
     * @return MorphMany<InventoryMovement, $this>
     */
    public function movements(): MorphMany
    {
        return $this->morphMany(InventoryMovement::class, 'reference');
    }
}
