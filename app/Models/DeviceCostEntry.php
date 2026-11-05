<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Currency;
use App\Enums\DeviceCostCategory;
use App\Services\Purchasing\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One attributable cost of a used device.
 *
 * A NULL amount means unknown; 0.00 is a confirmed zero. Amounts are never edited; the
 * only permitted change is a one-time reversal.
 *
 * @property int $id
 * @property int $inventory_unit_id
 * @property int $store_id
 * @property int|null $device_repair_id
 * @property DeviceCostCategory $category
 * @property string|null $description
 * @property Currency|null $currency
 * @property string|null $amount
 * @property int $inventory_movement_id
 * @property int|null $replaces_entry_id
 * @property int $user_id
 * @property Carbon $recorded_at
 * @property Carbon|null $reversed_at
 * @property int|null $reversed_by
 * @property string|null $reversal_reason
 * @property int|null $reversal_movement_id
 */
class DeviceCostEntry extends Model
{
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'inventory_unit_id',
        'store_id',
        'device_repair_id',
        'category',
        'description',
        'currency',
        'amount',
        'inventory_movement_id',
        'replaces_entry_id',
        'user_id',
        'recorded_at',
        'reversed_at',
        'reversed_by',
        'reversal_reason',
        'reversal_movement_id',
    ];

    /**
     * @var list<string>
     */
    private const REVERSAL_COLUMNS = ['reversed_at', 'reversed_by', 'reversal_reason', 'reversal_movement_id'];

    protected static function booted(): void
    {
        static::updating(function (DeviceCostEntry $entry): void {
            if ($entry->getOriginal('reversed_at') !== null
                || array_diff(array_keys($entry->getDirty()), self::REVERSAL_COLUMNS) !== []) {
                throw new LogicException('Device costs are never edited; they can only be reversed once.');
            }
        });
        static::deleting(fn () => throw new LogicException('Device costs are kept permanently.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => DeviceCostCategory::class,
            'currency' => Currency::class,
            'recorded_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    public function isUnknown(): bool
    {
        return $this->amount === null;
    }

    public function isReversed(): bool
    {
        return $this->reversed_at !== null;
    }

    public function amountMinor(): ?int
    {
        return $this->amount === null ? null : Money::fromDecimal($this->amount);
    }

    /**
     * @return BelongsTo<InventoryUnit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(InventoryUnit::class, 'inventory_unit_id');
    }

    /**
     * @return BelongsTo<DeviceRepair, $this>
     */
    public function repair(): BelongsTo
    {
        return $this->belongsTo(DeviceRepair::class, 'device_repair_id');
    }

    /**
     * @return BelongsTo<DeviceCostEntry, $this>
     */
    public function replaces(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaces_entry_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reverser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }
}
