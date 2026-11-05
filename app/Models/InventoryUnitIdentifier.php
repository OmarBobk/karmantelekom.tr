<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\IdentifierType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An IMEI or serial number belonging to one inventory unit.
 *
 * IMEIs are unique system-wide; serial numbers are unique within a product definition.
 *
 * @property int $id
 * @property int $inventory_unit_id
 * @property int $product_definition_id
 * @property IdentifierType $type
 * @property int $position
 * @property string $value
 */
class InventoryUnitIdentifier extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['inventory_unit_id', 'product_definition_id', 'type', 'position', 'value'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => IdentifierType::class,
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<InventoryUnit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(InventoryUnit::class, 'inventory_unit_id');
    }
}
