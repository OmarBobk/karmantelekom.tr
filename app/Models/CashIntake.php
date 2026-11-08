<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CashIntakeSource;
use App\Enums\CashIntakeStatus;
use App\Services\Purchasing\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * Cash a sale or customer payment is about to receive, bound to the open cashbox session before
 * staff are told to accept it. While pending it blocks closing that session, and a customer
 * intake reserves its amount of the customer's balance. Only completion or the owner's audited
 * release ends it; the record is kept either way.
 *
 * pending_source ("attempt:ID" or "customer:ID") is set only while pending, so each checkout
 * attempt and each customer has at most one pending intake.
 *
 * @property int $id
 * @property int $store_id
 * @property int $cashbox_session_id
 * @property CashIntakeSource $source_type
 * @property int|null $checkout_attempt_id
 * @property int|null $customer_id
 * @property string $amount
 * @property CashIntakeStatus $status
 * @property string|null $pending_source
 * @property string $request_key
 * @property int $started_by
 * @property Carbon $started_at
 * @property Carbon|null $completed_at
 * @property int|null $released_by
 * @property Carbon|null $released_at
 * @property string|null $release_note
 */
class CashIntake extends Model
{
    public const MAX_NOTE_LENGTH = 500;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'store_id', 'cashbox_session_id', 'source_type', 'checkout_attempt_id', 'customer_id', 'amount', 'status',
        'pending_source', 'request_key', 'started_by', 'started_at', 'completed_at', 'released_by', 'released_at',
        'release_note',
    ];

    protected static function booted(): void
    {
        static::updating(function (CashIntake $intake): void {
            if ($intake->getOriginal('status') !== CashIntakeStatus::PENDING || $intake->isDirty(['amount', 'cashbox_session_id', 'source_type', 'checkout_attempt_id', 'customer_id', 'started_by'])) {
                throw new LogicException('Only a pending cash intake can be completed or released.');
            }
        });
        static::deleting(fn () => throw new LogicException('Cash intakes are kept.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source_type' => CashIntakeSource::class,
            'status' => CashIntakeStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    public static function attemptSource(CheckoutAttempt|int $attempt): string
    {
        return 'attempt:'.($attempt instanceof CheckoutAttempt ? $attempt->id : $attempt);
    }

    public static function customerSource(Customer|int $customer): string
    {
        return 'customer:'.($customer instanceof Customer ? $customer->id : $customer);
    }

    /**
     * The source's pending intake, row-locked by primary key, which must be the intake the
     * confirming screen displayed (null when it displayed none). A released or completed intake
     * is never swapped for a newer pending one: the confirmation is refused instead.
     *
     * Call only while holding the source's own lock (attempt or customer row): intakes are
     * created and ended under it.
     *
     * @throws ValidationException
     */
    public static function lockDisplayed(string $pendingSource, ?int $displayedId): ?self
    {
        $pendingId = self::query()->where('pending_source', $pendingSource)->value('id');

        if ($pendingId !== $displayedId) {
            throw ValidationException::withMessages(['cashbox' => __('pos.cashbox.errors.intake_changed')]);
        }

        return $pendingId === null ? null : self::query()->whereKey($pendingId)->lockForUpdate()->first();
    }

    /**
     * @param  Builder<CashIntake>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', CashIntakeStatus::PENDING);
    }

    public function isPending(): bool
    {
        return $this->status === CashIntakeStatus::PENDING;
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
     * @return BelongsTo<CheckoutAttempt, $this>
     */
    public function checkoutAttempt(): BelongsTo
    {
        return $this->belongsTo(CheckoutAttempt::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    /**
     * @return HasOne<CashMovement, $this>
     */
    public function movement(): HasOne
    {
        return $this->hasOne(CashMovement::class);
    }
}
