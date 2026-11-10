<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TrackingMode;
use App\Services\Purchasing\Money;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * One priced line of a storefront order and, once confirmed, its stock hold: held_quantity for
 * quantity stock or active_unit_id for one device. A hold is active until hold_expires_at, or
 * while a counter checkout holds the payment claim, and is released by clearing both columns.
 *
 * @property int $id
 * @property int $storefront_order_id
 * @property int $order_item_id
 * @property int $store_id
 * @property int $product_id
 * @property int $product_definition_id
 * @property TrackingMode $tracking_mode
 * @property int $quantity
 * @property string $unit_price
 * @property string|null $proposed_unit_price
 * @property int|null $inventory_unit_id
 * @property int|null $proposed_unit_id
 * @property int $held_quantity
 * @property int|null $active_unit_id
 * @property CarbonInterface|null $hold_expires_at
 * @property int|null $claimed_attempt_id
 * @property CarbonInterface|null $claimed_at
 */
class StorefrontOrderLine extends Model
{
    protected $fillable = [
        'storefront_order_id', 'order_item_id', 'store_id', 'product_id', 'product_definition_id', 'tracking_mode', 'quantity',
        'unit_price', 'proposed_unit_price', 'inventory_unit_id', 'proposed_unit_id', 'held_quantity', 'active_unit_id',
        'hold_expires_at', 'claimed_attempt_id', 'claimed_at',
    ];

    protected function casts(): array
    {
        return [
            'tracking_mode' => TrackingMode::class,
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'proposed_unit_price' => 'decimal:2',
            'held_quantity' => 'integer',
            'hold_expires_at' => 'datetime',
            'claimed_at' => 'datetime',
        ];
    }

    public function tracksUnits(): bool
    {
        return $this->tracking_mode->tracksIndividualItems();
    }

    public function unitPriceMinor(): int
    {
        return Money::fromDecimal((string) $this->unit_price);
    }

    public function proposedUnitPriceMinor(): ?int
    {
        return $this->proposed_unit_price === null ? null : Money::fromDecimal((string) $this->proposed_unit_price);
    }

    /**
     * Whether this line holds stock now: a hold not yet past its deadline, or one a counter
     * checkout claimed when it started payment.
     */
    public function holdsStock(?CarbonInterface $at = null): bool
    {
        if ($this->held_quantity === 0 && $this->active_unit_id === null) {
            return false;
        }

        return $this->claimed_attempt_id !== null || ($this->hold_expires_at !== null && $this->hold_expires_at->gt($at ?? now()));
    }

    /**
     * Restrict a line query to lines holding stock now; the SQL form of holdsStock().
     */
    public static function whereHolding(Builder|QueryBuilder $query, string $table = 'storefront_order_lines'): void
    {
        $query->where(fn ($held) => $held->where("{$table}.held_quantity", '>', 0)->orWhereNotNull("{$table}.active_unit_id"))
            ->where(fn ($active) => $active->whereNotNull("{$table}.claimed_attempt_id")->orWhere("{$table}.hold_expires_at", '>', now()));
    }

    /**
     * @return BelongsTo<StorefrontOrder, $this>
     */
    public function storefrontOrder(): BelongsTo
    {
        return $this->belongsTo(StorefrontOrder::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<ProductDefinition, $this>
     */
    public function productDefinition(): BelongsTo
    {
        return $this->belongsTo(ProductDefinition::class);
    }

    /**
     * @return BelongsTo<InventoryUnit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(InventoryUnit::class, 'inventory_unit_id');
    }

    /**
     * @return BelongsTo<InventoryUnit, $this>
     */
    public function proposedUnit(): BelongsTo
    {
        return $this->belongsTo(InventoryUnit::class, 'proposed_unit_id');
    }
}
