<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Optional low-stock threshold for one product definition at one branch.
 *
 * Absence of a row means the product is unset and never enters the low-stock list.
 * Setting or clearing a threshold never changes stock or creates a movement.
 *
 * @property int $id
 * @property int $store_id
 * @property int $product_definition_id
 * @property int $threshold
 */
class InventoryStockThreshold extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'store_id',
        'product_definition_id',
        'threshold',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'threshold' => 'integer',
        ];
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
