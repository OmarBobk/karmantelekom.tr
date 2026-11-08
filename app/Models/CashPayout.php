<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CashPayoutResolution;
use App\Enums\CashPayoutStatus;
use App\Enums\ExpenseCategory;
use App\Services\Purchasing\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Cash reserved on the open session for an expense before staff hand it out. It carries the
 * expense details shown when it was started, so confirming records exactly that expense.
 *
 * A pending payout reduces the cash available for other payouts and blocks closing its session.
 * It completes once, when its expense and outgoing movement are recorded, or the owner releases
 * it after verifying the cash was not handed out or was returned. Never deleted.
 *
 * @property int $id
 * @property int $store_id
 * @property int $cashbox_session_id
 * @property string $amount
 * @property ExpenseCategory $category
 * @property string $description
 * @property Carbon $expense_date
 * @property string|null $reference
 * @property int|null $operational_supplier_id
 * @property int|null $inventory_unit_id
 * @property CashPayoutStatus $status
 * @property int $started_by
 * @property Carbon $started_at
 * @property Carbon|null $completed_at
 * @property int|null $released_by
 * @property Carbon|null $released_at
 * @property CashPayoutResolution|null $resolution
 * @property string|null $release_note
 */
class CashPayout extends Model
{
    public const MAX_NOTE_LENGTH = 500;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'store_id', 'cashbox_session_id', 'amount', 'category', 'description', 'expense_date', 'reference',
        'operational_supplier_id', 'inventory_unit_id', 'status', 'started_by', 'started_at', 'completed_at', 'released_by', 'released_at', 'resolution', 'release_note',
    ];

    protected static function booted(): void
    {
        static::updating(function (CashPayout $payout): void {
            if ($payout->getOriginal('status') !== CashPayoutStatus::PENDING) {
                throw new LogicException('Completed and released cash payouts are final.');
            }
        });
        static::deleting(fn () => throw new LogicException('Cash payouts are kept.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => ExpenseCategory::class,
            'expense_date' => 'date',
            'status' => CashPayoutStatus::class,
            'resolution' => CashPayoutResolution::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<CashPayout>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', CashPayoutStatus::PENDING);
    }

    public function isPending(): bool
    {
        return $this->status === CashPayoutStatus::PENDING;
    }

    public function amountMinor(): int
    {
        return Money::fromDecimal($this->amount);
    }

    /**
     * @return BelongsTo<CashboxSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(CashboxSession::class, 'cashbox_session_id');
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
    public function releaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    /**
     * @return HasOne<Expense, $this>
     */
    public function expense(): HasOne
    {
        return $this->hasOne(Expense::class);
    }

    /**
     * @return BelongsTo<OperationalSupplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(OperationalSupplier::class, 'operational_supplier_id');
    }

    /**
     * @return BelongsTo<InventoryUnit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(InventoryUnit::class, 'inventory_unit_id');
    }
}
