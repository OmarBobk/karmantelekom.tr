<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Currency;
use App\Services\Purchasing\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * The part of one completed sale the customer still owed when it was completed. Append-only:
 * it records the original obligation, and later payments and corrections will be separate records.
 *
 * @property int $id
 * @property int $store_id
 * @property int $customer_id
 * @property int $sale_id
 * @property Currency $currency
 * @property string $original_amount
 * @property int $authorized_by
 * @property int $user_id
 * @property Carbon $occurred_at
 */
class CustomerDebt extends Model
{
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'store_id', 'customer_id', 'sale_id', 'currency', 'original_amount', 'authorized_by', 'user_id', 'occurred_at',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Customer debts are append-only.'));
        static::deleting(fn () => throw new LogicException('Customer debts are append-only.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'currency' => Currency::class,
            'occurred_at' => 'datetime',
        ];
    }

    public function originalAmountMinor(): int
    {
        return Money::fromDecimal($this->original_amount);
    }

    /**
     * The original amount less recorded allocations. Needs allocations_sum_amount, loaded with
     * withSum('allocations', 'amount').
     */
    public function outstandingMinor(): int
    {
        if (! array_key_exists('allocations_sum_amount', $this->attributes)) {
            throw new LogicException('Load the allocation total with withSum(\'allocations\', \'amount\') first.');
        }

        return $this->originalAmountMinor() - Money::fromDecimal($this->attributes['allocations_sum_amount']);
    }

    /**
     * @return HasMany<CustomerPaymentAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(CustomerPaymentAllocation::class);
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
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
    public function authorizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_by');
    }
}
