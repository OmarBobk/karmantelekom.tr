<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InspectionOutcome;
use App\Enums\UnitCondition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One inspection of a used device. Append-only.
 *
 * @property int $id
 * @property int $inventory_unit_id
 * @property int $store_id
 * @property int $inventory_movement_id
 * @property InspectionOutcome $outcome
 * @property UnitCondition $condition_before
 * @property UnitCondition $condition_after
 * @property string|null $findings
 * @property int $user_id
 * @property Carbon $inspected_at
 */
class DeviceInspection extends Model
{
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'inventory_unit_id',
        'store_id',
        'inventory_movement_id',
        'outcome',
        'condition_before',
        'condition_after',
        'findings',
        'user_id',
        'inspected_at',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Device inspections are append-only.'));
        static::deleting(fn () => throw new LogicException('Device inspections are append-only.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'outcome' => InspectionOutcome::class,
            'condition_before' => UnitCondition::class,
            'condition_after' => UnitCondition::class,
            'inspected_at' => 'datetime',
        ];
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
    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
