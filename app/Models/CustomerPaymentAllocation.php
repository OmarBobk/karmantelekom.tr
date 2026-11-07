<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Purchasing\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * The part of one customer payment applied to one debt. Append-only.
 *
 * @property int $id
 * @property int $customer_payment_id
 * @property int $customer_debt_id
 * @property string $amount
 */
class CustomerPaymentAllocation extends Model
{
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = ['customer_payment_id', 'customer_debt_id', 'amount'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Payment allocations are append-only.'));
        static::deleting(fn () => throw new LogicException('Payment allocations are append-only.'));
    }

    public function amountMinor(): int
    {
        return Money::fromDecimal($this->amount);
    }

    /**
     * @return BelongsTo<CustomerPayment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(CustomerPayment::class, 'customer_payment_id');
    }

    /**
     * @return BelongsTo<CustomerDebt, $this>
     */
    public function debt(): BelongsTo
    {
        return $this->belongsTo(CustomerDebt::class, 'customer_debt_id');
    }
}
