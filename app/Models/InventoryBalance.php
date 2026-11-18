<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QuantityCostStatus;
use App\Services\Purchasing\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Current on-hand quantity of a quantity-tracked product in one store.
 *
 * Changed only by the inventory actions, together with a movement. Recorded-cost state covers
 * the entire remaining quantity (empty, known, or unknown) — never a known subset.
 *
 * @property int $id
 * @property int $store_id
 * @property int $product_definition_id
 * @property int $quantity
 * @property QuantityCostStatus $cost_status
 * @property string|null $cost_currency
 * @property string|null $cost_total
 * @property int|null $active_cost_declaration_id
 */
class InventoryBalance extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'store_id',
        'product_definition_id',
        'quantity',
        'cost_status',
        'cost_currency',
        'cost_total',
        'active_cost_declaration_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'cost_status' => QuantityCostStatus::class,
        ];
    }

    public function costTotalMinor(): ?int
    {
        if ($this->cost_status !== QuantityCostStatus::KNOWN || $this->cost_total === null) {
            return null;
        }

        return Money::fromDecimal($this->cost_total);
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<ProductDefinition, $this>
     */
    public function productDefinition(): BelongsTo
    {
        return $this->belongsTo(ProductDefinition::class);
    }

    /**
     * @return BelongsTo<InventoryCostDeclaration, $this>
     */
    public function activeCostDeclaration(): BelongsTo
    {
        return $this->belongsTo(InventoryCostDeclaration::class, 'active_cost_declaration_id');
    }
}
