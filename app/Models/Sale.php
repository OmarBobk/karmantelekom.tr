<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Currency;
use App\Services\Purchasing\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * A completed POS sale. Append-only: it never depends on current catalog prices or names,
 * and corrections will be separate records.
 *
 * @property int $id
 * @property int $store_id
 * @property int $user_id
 * @property int|null $customer_id
 * @property int $checkout_attempt_id
 * @property string $number
 * @property int $sequence
 * @property Currency $currency
 * @property string $subtotal
 * @property string $discount_total
 * @property string $total
 * @property string|null $cash_tendered
 * @property string $change_given
 * @property Carbon $completed_at
 */
class Sale extends Model
{
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'store_id', 'user_id', 'customer_id', 'checkout_attempt_id', 'number', 'sequence', 'currency',
        'subtotal', 'discount_total', 'total', 'cash_tendered', 'change_given', 'completed_at',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Sales are append-only.'));
        static::deleting(fn () => throw new LogicException('Sales are append-only.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'currency' => Currency::class,
            'sequence' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    /** Stored times are UTC; the counter and slips show the branches' local time. */
    public const DISPLAY_TIMEZONE = 'Europe/Istanbul';

    public static function displayTime(?Carbon $at): string
    {
        return $at === null ? '' : $at->copy()->timezone(self::DISPLAY_TIMEZONE)->format('Y-m-d H:i');
    }

    public static function formatNumber(Store $store, int $sequence): string
    {
        return sprintf('%s-%06d', $store->code, $sequence);
    }

    /**
     * A real Istanbul calendar date in Y-m-d form, or null ("2026-02-30" and "2026-2-3" are refused).
     */
    public static function localDate(string $value): ?CarbonImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, self::DISPLAY_TIMEZONE);

        return $date !== null && $date->format('Y-m-d') === $value ? $date : null;
    }

    /**
     * Why an Istanbul date range is unusable ('invalid_date' or 'reversed'), or null when it is valid.
     */
    public static function localRangeError(string $from, string $to): ?string
    {
        $start = self::localDate($from);
        $end = self::localDate($to);

        if ($start === null || $end === null) {
            return 'invalid_date';
        }

        return $start->greaterThan($end) ? 'reversed' : null;
    }

    /**
     * Sales completed on the given Istanbul calendar days, inclusive: from the start of $from up to,
     * but excluding, the start of the day after $to, both converted to UTC.
     *
     * @param  Builder<Sale>  $query
     */
    public function scopeCompletedOnLocalDays(Builder $query, CarbonImmutable $from, CarbonImmutable $to): void
    {
        $start = $from->setTimezone(self::DISPLAY_TIMEZONE)->startOfDay();
        $end = $to->setTimezone(self::DISPLAY_TIMEZONE)->startOfDay()->addDay();

        $query->where('completed_at', '>=', $start->utc())->where('completed_at', '<', $end->utc());
    }

    /**
     * Count and recorded total of the sales in the query, aggregated in SQL.
     *
     * @param  Builder<Sale>  $query
     * @return array{count: int, total_minor: int}
     */
    public static function recordedSummary(Builder $query): array
    {
        $row = $query->toBase()->reorder()->selectRaw('count(*) as sale_count, coalesce(sum(total), 0) as recorded_total')->first();

        return ['count' => (int) $row->sale_count, 'total_minor' => Money::fromDecimal((string) $row->recorded_total)];
    }

    /**
     * Count and recorded total per store for the Istanbul days, aggregated in SQL. Stores
     * without sales are absent; callers supply zeros.
     *
     * @param  list<int>  $storeIds
     * @return array<int, array{count: int, total_minor: int}>
     */
    public static function recordedTotalsByStore(array $storeIds, CarbonImmutable $from, CarbonImmutable $to): array
    {
        if ($storeIds === []) {
            return [];
        }

        return self::query()
            ->whereIn('store_id', $storeIds)
            ->completedOnLocalDays($from, $to)
            ->toBase()
            ->selectRaw('store_id, count(*) as sale_count, sum(total) as recorded_total')
            ->groupBy('store_id')
            ->get()
            ->mapWithKeys(fn (object $row): array => [(int) $row->store_id => [
                'count' => (int) $row->sale_count,
                'total_minor' => Money::fromDecimal((string) $row->recorded_total),
            ]])
            ->all();
    }

    /**
     * Sales the user may see in this store: the owner sees every sale, employees their own.
     *
     * @param  Builder<Sale>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user, Store $store): void
    {
        $query->where('store_id', $store->id);

        if (! $user->can('viewAllSales', $store)) {
            $query->where('user_id', $user->id);
        }
    }

    public function totalMinor(): int
    {
        return Money::fromDecimal($this->total);
    }

    public function subtotalMinor(): int
    {
        return Money::fromDecimal($this->subtotal);
    }

    public function discountTotalMinor(): int
    {
        return Money::fromDecimal($this->discount_total);
    }

    public function changeGivenMinor(): int
    {
        return Money::fromDecimal($this->change_given);
    }

    /**
     * The original amount left on credit when the sale was completed; 0 for a fully paid sale.
     */
    public function creditMinor(): int
    {
        return $this->debt?->originalAmountMinor() ?? 0;
    }

    /**
     * The amount actually received at the counter: the total less the original credit.
     */
    public function paidMinor(): int
    {
        return $this->totalMinor() - $this->creditMinor();
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
    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<CheckoutAttempt, $this>
     */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(CheckoutAttempt::class, 'checkout_attempt_id');
    }

    /**
     * @return HasMany<SaleLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(SaleLine::class)->orderBy('line_number');
    }

    /**
     * @return HasMany<SalePayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class)->orderBy('id');
    }

    /**
     * @return HasOne<CustomerDebt, $this>
     */
    public function debt(): HasOne
    {
        return $this->hasOne(CustomerDebt::class);
    }
}
