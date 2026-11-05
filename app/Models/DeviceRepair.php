<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RepairerType;
use App\Enums\RepairStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One repair of a used device. Opened once and closed once; nothing else ever changes.
 *
 * @property int $id
 * @property int $inventory_unit_id
 * @property int $store_id
 * @property RepairStatus $status
 * @property RepairerType $repairer_type
 * @property string|null $repairer_name
 * @property string $problem
 * @property string|null $work_performed
 * @property string|null $close_reason
 * @property int $started_movement_id
 * @property int|null $closed_movement_id
 * @property int $started_by
 * @property int|null $closed_by
 * @property Carbon $started_at
 * @property Carbon|null $returned_at
 * @property Carbon|null $closed_at
 */
class DeviceRepair extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'inventory_unit_id',
        'store_id',
        'status',
        'repairer_type',
        'repairer_name',
        'problem',
        'work_performed',
        'close_reason',
        'started_movement_id',
        'closed_movement_id',
        'started_by',
        'closed_by',
        'started_at',
        'returned_at',
        'closed_at',
    ];

    /**
     * Columns that may be written exactly once, when an open repair is closed.
     *
     * @var list<string>
     */
    private const CLOSING_COLUMNS = ['status', 'work_performed', 'close_reason', 'closed_movement_id', 'closed_by', 'returned_at', 'closed_at', 'updated_at'];

    protected static function booted(): void
    {
        static::updating(function (DeviceRepair $repair): void {
            if ($repair->getOriginal('status') !== RepairStatus::OPEN
                || $repair->status === RepairStatus::OPEN
                || array_diff(array_keys($repair->getDirty()), self::CLOSING_COLUMNS) !== []) {
                throw new LogicException('A repair can only be closed once.');
            }
        });
        static::deleting(fn () => throw new LogicException('Device repairs are kept permanently.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RepairStatus::class,
            'repairer_type' => RepairerType::class,
            'started_at' => 'datetime',
            'returned_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function isExternal(): bool
    {
        return $this->repairer_type === RepairerType::EXTERNAL;
    }

    /**
     * @return BelongsTo<InventoryUnit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(InventoryUnit::class, 'inventory_unit_id');
    }

    /**
     * @return HasMany<DeviceCostEntry, $this>
     */
    public function costEntries(): HasMany
    {
        return $this->hasMany(DeviceCostEntry::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
