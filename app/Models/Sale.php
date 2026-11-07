<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Currency;
use App\Services\Purchasing\Money;
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
