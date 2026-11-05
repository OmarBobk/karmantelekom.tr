<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\IdentifierType;
use App\Enums\RepairStatus;
use App\Enums\UnitCondition;
use App\Enums\UnitStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
 * @property int $lifecycle_revision
 * @property Carbon|null $removed_at
 */
class InventoryUnit extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['store_id', 'product_definition_id', 'status', 'condition', 'lifecycle_revision', 'removed_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => UnitStatus::class,
            'condition' => UnitCondition::class,
            'lifecycle_revision' => 'integer',
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

    /**
     * The invoice price this unit was acquired at, when it arrived through a purchase receipt.
     *
     * @return HasOne<PurchaseReceiptUnit, $this>
     */
    public function acquisition(): HasOne
    {
        return $this->hasOne(PurchaseReceiptUnit::class);
    }

    /**
     * @return HasMany<DeviceInspection, $this>
     */
    public function inspections(): HasMany
    {
        return $this->hasMany(DeviceInspection::class);
    }

    /**
     * @return HasMany<DeviceRepair, $this>
     */
    public function repairs(): HasMany
    {
        return $this->hasMany(DeviceRepair::class);
    }

    /**
     * @return HasOne<DeviceRepair, $this>
     */
    public function openRepair(): HasOne
    {
        return $this->hasOne(DeviceRepair::class)->where('status', RepairStatus::OPEN);
    }

    /**
     * @return HasMany<DeviceCostEntry, $this>
     */
    public function costEntries(): HasMany
    {
        return $this->hasMany(DeviceCostEntry::class);
    }

    /**
     * @return HasMany<InventoryUnitIdentifierCorrection, $this>
     */
    public function identifierCorrections(): HasMany
    {
        return $this->hasMany(InventoryUnitIdentifierCorrection::class);
    }

    public function primaryIdentifierValue(): ?string
    {
        $identifiers = $this->relationLoaded('identifiers') ? $this->identifiers : $this->identifiers()->get();

        return ($identifiers->firstWhere('type', IdentifierType::IMEI) ?? $identifiers->first())?->value;
    }
}
