<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AdjustmentReason;
use App\Enums\MovementType;
use App\Enums\UnitStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Append-only record of one inventory change in one store.
 *
 * @property int $id
 * @property int $store_id
 * @property int $product_definition_id
 * @property int|null $inventory_unit_id
 * @property MovementType $type
 * @property AdjustmentReason $reason
 * @property string|null $note
 * @property int $quantity_change
 * @property int|null $quantity_after
 * @property UnitStatus|null $unit_status_before
 * @property UnitStatus|null $unit_status_after
 * @property int $user_id
 * @property string $idempotency_key
 * @property string $request_fingerprint
 * @property Carbon $occurred_at
 */
class InventoryMovement extends Model
{
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'store_id',
        'product_definition_id',
        'inventory_unit_id',
        'type',
        'reason',
        'note',
        'quantity_change',
        'quantity_after',
        'unit_status_before',
        'unit_status_after',
        'user_id',
        'idempotency_key',
        'request_fingerprint',
        'occurred_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MovementType::class,
            'reason' => AdjustmentReason::class,
            'unit_status_before' => UnitStatus::class,
            'unit_status_after' => UnitStatus::class,
            'quantity_change' => 'integer',
            'quantity_after' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Inventory movements are append-only.'));
        static::deleting(fn () => throw new LogicException('Inventory movements are append-only.'));
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

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Future originating document (purchase receipt, sale, …).
     *
     * @return MorphTo<Model, $this>
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
