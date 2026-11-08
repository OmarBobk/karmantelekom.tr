<?php

declare(strict_types=1);

namespace App\Models;

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
 * TRY cash reserved on the open session and against one invoice before staff hand it to the
 * supplier. It carries the payment details and the request key of the submission that started
 * it, so confirming records exactly that supplier payment and a retry of the same form replays.
 *
 * A pending payout reduces the cash available for other payouts and the invoice amount open to
 * other payments, and blocks closing its session. It completes once, when its payment, ledger
 * entry, and outgoing movement are recorded, or the owner releases it after verifying the cash
 * was not handed out or was returned. Never deleted.
 *
 * @property int $id
 * @property int $store_id
 * @property int $purchase_id
 * @property int $cashbox_session_id
 * @property string $amount
 * @property Carbon $paid_on
 * @property string $reference
 * @property string $reference_key
 * @property string|null $note
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
class SupplierCashPayout extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'store_id', 'purchase_id', 'cashbox_session_id', 'amount', 'paid_on', 'reference', 'reference_key', 'note',
        'request_key', 'request_fingerprint', 'status', 'started_by', 'started_at', 'completed_at', 'released_by',
        'released_at', 'resolution', 'release_note',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = ['request_fingerprint'];

    protected static function booted(): void
    {
        static::updating(function (SupplierCashPayout $payout): void {
            if ($payout->getOriginal('status') !== CashPayoutStatus::PENDING) {
                throw new LogicException('Completed and released supplier cash payouts are final.');
            }
        });
        static::deleting(fn () => throw new LogicException('Supplier cash payouts are kept.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'paid_on' => 'date',
            'status' => CashPayoutStatus::class,
            'resolution' => CashPayoutResolution::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<SupplierCashPayout>  $query
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
     * @return BelongsTo<Purchase, $this>
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
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
     * @return HasOne<SupplierPayment, $this>
     */
    public function payment(): HasOne
    {
        return $this->hasOne(SupplierPayment::class);
    }
}
