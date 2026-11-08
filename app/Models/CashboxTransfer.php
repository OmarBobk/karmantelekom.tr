<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CashboxTransferKind;
use App\Enums\CashPayoutResolution;
use App\Enums\CashPayoutStatus;
use App\Services\Purchasing\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * An owner deposit into or withdrawal from the drawer, persisted on the open session before any
 * cash is moved. It claims the request key of the form that started it, so a retry replays.
 *
 * A pending withdrawal reduces the cash available for other payouts; any pending transfer blocks
 * closing its session. It completes once, when its one internal-transfer movement is posted, or
 * the owner releases it after verifying the cash was never moved or was moved back. Never deleted.
 *
 * @property int $id
 * @property int $store_id
 * @property int $cashbox_session_id
 * @property CashboxTransferKind $kind
 * @property string $amount
 * @property string $reason
 * @property string $request_key
 * @property string $request_fingerprint
 * @property CashPayoutStatus $status
 * @property int $started_by
 * @property Carbon $started_at
 * @property Carbon|null $completed_at
 * @property int|null $released_by
 * @property Carbon|null $released_at
 * @property CashPayoutResolution|null $resolution
 * @property string|null $release_note
 */
class CashboxTransfer extends Model
{
    public const MAX_REASON_LENGTH = 500;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'store_id', 'cashbox_session_id', 'kind', 'amount', 'reason', 'request_key', 'request_fingerprint', 'status',
        'started_by', 'started_at', 'completed_at', 'released_by', 'released_at', 'resolution', 'release_note',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = ['request_fingerprint'];

    protected static function booted(): void
    {
        static::updating(function (CashboxTransfer $transfer): void {
            if ($transfer->getOriginal('status') !== CashPayoutStatus::PENDING) {
                throw new LogicException('Completed and released cashbox transfers are final.');
            }
        });
        static::deleting(fn () => throw new LogicException('Cashbox transfers are kept.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => CashboxTransferKind::class,
            'status' => CashPayoutStatus::class,
            'resolution' => CashPayoutResolution::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<CashboxTransfer>  $query
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
     * @return HasOne<CashMovement, $this>
     */
    public function movement(): HasOne
    {
        return $this->hasOne(CashMovement::class);
    }
}
