<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CashboxSessionStatus;
use App\Enums\CashIntakeStatus;
use App\Enums\CashMovementDirection;
use App\Services\Purchasing\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One counted period of a store's cashbox. At most one is open per store: open_store_id holds
 * the store id while open and is cleared on closing. A closed session is final.
 *
 * Cash posting locks the store row in share mode as its last lock; opening, closing, and cash
 * payouts lock it exclusively first, so a close sees every committed movement and no posting can
 * follow it, and payouts see every reservation made before them.
 *
 * @property int $id
 * @property int $store_id
 * @property int|null $open_store_id
 * @property CashboxSessionStatus $status
 * @property int $opened_by
 * @property Carbon $opened_at
 * @property string|null $opening_expected
 * @property string $opening_counted
 * @property string|null $opening_difference
 * @property string|null $opening_note
 * @property int|null $closed_by
 * @property Carbon|null $closed_at
 * @property string|null $expected_closing
 * @property string|null $counted_closing
 * @property string|null $closing_difference
 * @property string|null $closing_note
 */
class CashboxSession extends Model
{
    public const MAX_NOTE_LENGTH = 500;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'store_id', 'open_store_id', 'status', 'opened_by', 'opened_at', 'opening_expected', 'opening_counted',
        'opening_difference', 'opening_note', 'closed_by', 'closed_at', 'expected_closing', 'counted_closing',
        'closing_difference', 'closing_note',
    ];

    protected static function booted(): void
    {
        static::updating(function (CashboxSession $session): void {
            if ($session->getOriginal('status') !== CashboxSessionStatus::OPEN) {
                throw new LogicException('Closed cashbox sessions are final.');
            }
        });
        static::deleting(fn () => throw new LogicException('Cashbox sessions are kept.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CashboxSessionStatus::class,
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * Cash tracking for a store starts with its first session; before that, cash sales and
     * payments are recorded without a session, as they were before tracking existed.
     */
    public static function trackingStarted(Store $store): bool
    {
        return self::query()->where('store_id', $store->id)->exists();
    }

    /**
     * Whether tracking started, as a current read under the store row's share lock. Opening locks
     * the store row exclusively first, so a first session opened by a transaction that committed
     * after this one's snapshot is still seen, and none can open until this one commits. Like
     * lockOpenShared, take it after every other lock.
     */
    public static function lockTrackingStarted(Store $store): bool
    {
        Store::query()->whereKey($store->id)->sharedLock()->first();

        return self::query()->where('store_id', $store->id)->sharedLock()->value('id') !== null;
    }

    /**
     * The store's open session, read after locking the store row in share mode: no session can
     * open or close until this transaction commits, while other postings proceed. Take it after
     * every other lock. Opening and closing lock the store row exclusively as their first
     * statement, so the store row is the single lock point between them and postings.
     */
    public static function lockOpenShared(Store $store): ?self
    {
        Store::query()->whereKey($store->id)->sharedLock()->first();

        return self::query()->where('open_store_id', $store->id)->sharedLock()->first();
    }

    public static function openFor(Store $store): ?self
    {
        return self::query()->where('open_store_id', $store->id)->first();
    }

    /**
     * @param  Builder<CashboxSession>  $query
     */
    public function scopeClosed(Builder $query): void
    {
        $query->where('status', CashboxSessionStatus::CLOSED);
    }

    public function isOpen(): bool
    {
        return $this->status === CashboxSessionStatus::OPEN;
    }

    public function openingCountedMinor(): int
    {
        return Money::fromDecimal($this->opening_counted);
    }

    public function countedClosingMinor(): ?int
    {
        return $this->counted_closing === null ? null : Money::fromDecimal($this->counted_closing);
    }

    /**
     * Opening count plus cash moved in, minus cash moved out, in this session.
     */
    public function expectedMinor(): int
    {
        return $this->openingCountedMinor() + $this->cashInMinor() - $this->cashOutMinor();
    }

    public function cashInMinor(): int
    {
        return Money::fromDecimal((string) $this->movements()->where('direction', CashMovementDirection::IN)->sum('amount'));
    }

    public function cashOutMinor(): int
    {
        return Money::fromDecimal((string) $this->movements()->where('direction', CashMovementDirection::OUT)->sum('amount'));
    }

    /**
     * Expected cash not already reserved by pending expense or supplier payouts: the most a new
     * payout may take.
     */
    public function availableForPayoutMinor(): int
    {
        return $this->expectedMinor()
            - Money::fromDecimal((string) $this->payouts()->pending()->sum('amount'))
            - Money::fromDecimal((string) $this->supplierPayouts()->pending()->sum('amount'));
    }

    public function pendingIntakeCount(): int
    {
        return $this->intakes()->where('status', CashIntakeStatus::PENDING)->count();
    }

    public function pendingPayoutCount(): int
    {
        return $this->payouts()->pending()->count() + $this->supplierPayouts()->pending()->count();
    }

    /**
     * @return HasMany<CashPayout, $this>
     */
    public function payouts(): HasMany
    {
        return $this->hasMany(CashPayout::class);
    }

    /**
     * @return HasMany<SupplierCashPayout, $this>
     */
    public function supplierPayouts(): HasMany
    {
        return $this->hasMany(SupplierCashPayout::class);
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * @return HasMany<CashMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    /**
     * @return HasMany<CashIntake, $this>
     */
    public function intakes(): HasMany
    {
        return $this->hasMany(CashIntake::class);
    }
}
