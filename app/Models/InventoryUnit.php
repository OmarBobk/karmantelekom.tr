<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\IdentifierType;
use App\Enums\UnitCondition;
use App\Enums\UnitStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One individually tracked item (serialized product or used device) held by a store.
 *
 * Units and their identifiers are kept after removal so device identity is never reused.
 *
 * @property int $id
 * @property int $store_id
 * @property int $product_definition_id
 * @property UnitStatus $status
 * @property UnitCondition $condition
 * @property Carbon|null $removed_at
 */
class InventoryUnit extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['store_id', 'product_definition_id', 'status', 'condition', 'removed_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => UnitStatus::class,
            'condition' => UnitCondition::class,
            'removed_at' => 'datetime',
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

    /**
     * @return HasMany<InventoryUnitIdentifier, $this>
     */
    public function identifiers(): HasMany
    {
        return $this->hasMany(InventoryUnitIdentifier::class)->orderBy('type')->orderBy('position');
    }

    /**
     * @return HasMany<InventoryMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function primaryIdentifierValue(): ?string
    {
        $identifiers = $this->relationLoaded('identifiers') ? $this->identifiers : $this->identifiers()->get();

        return ($identifiers->firstWhere('type', IdentifierType::IMEI) ?? $identifiers->first())?->value;
    }
}
