<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Current on-hand quantity of a quantity-tracked product in one store.
 *
 * Changed only by the inventory actions, together with a movement.
 *
 * @property int $id
 * @property int $store_id
 * @property int $product_definition_id
 * @property int $quantity
 */
class InventoryBalance extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['store_id', 'product_definition_id', 'quantity'];

    /**
     * @return array<string, string>
     */
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
}
