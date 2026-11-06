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
 * @property string $discount_amount
 * @property string $line_total
 * @property string|null $adjustment_reason
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
        'quantity', 'list_unit_price', 'unit_price', 'discount_amount', 'line_total', 'adjustment_reason',
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
        ];
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
