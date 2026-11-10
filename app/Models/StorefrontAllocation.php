<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stock a branch makes eligible for the storefront: an online quantity of a quantity-tracked
 * product (inventory_unit_id NULL), or one exact device (quantity 1 while eligible, 0 after it
 * is withdrawn). It caps what the storefront may show and never changes on-hand stock.
 *
 * @property int $id
 * @property int $store_id
 * @property int $product_definition_id
 * @property int|null $inventory_unit_id
 * @property int $quantity
 * @property int $updated_by
 */
class StorefrontAllocation extends Model
{
    /**
     * Highest online quantity a branch may set for one product.
     */
    public const MAX_QUANTITY = 100000;

    protected $fillable = ['store_id', 'product_definition_id', 'inventory_unit_id', 'quantity', 'updated_by'];

    protected function casts(): array
    {
        return ['quantity' => 'integer'];
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
     * @return BelongsTo<InventoryUnit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(InventoryUnit::class, 'inventory_unit_id');
    }
}
