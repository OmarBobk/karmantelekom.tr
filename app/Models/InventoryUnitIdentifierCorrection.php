<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\IdentifierType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Immutable before/after record of one identifier typo correction.
 *
 * @property int $id
 * @property int $inventory_unit_id
 * @property int $inventory_unit_identifier_id
 * @property IdentifierType $type
 * @property int $position
 * @property string $old_value
 * @property string $new_value
 * @property string $reason
 * @property int $inventory_movement_id
 * @property int $user_id
 * @property Carbon $corrected_at
 */
class InventoryUnitIdentifierCorrection extends Model
{
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'inventory_unit_id',
        'inventory_unit_identifier_id',
        'type',
        'position',
        'old_value',
        'new_value',
        'reason',
        'inventory_movement_id',
        'user_id',
        'corrected_at',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Identifier corrections are append-only.'));
        static::deleting(fn () => throw new LogicException('Identifier corrections are append-only.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => IdentifierType::class,
            'position' => 'integer',
            'corrected_at' => 'datetime',
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
