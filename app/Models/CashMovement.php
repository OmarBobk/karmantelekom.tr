<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CashMovementType;
use App\Services\Purchasing\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Physical cash that entered a store's cashbox during a session, attributed to the employee who
 * recorded it. Exactly one per source: each sale cash payment, customer cash payment, and cash
 * intake posts at most once. Sales post the net cash kept, after change. Append-only.
 *
 * @property int $id
 * @property int $store_id
 * @property int $cashbox_session_id
 * @property CashMovementType $type
 * @property string $amount
 * @property int|null $cash_intake_id
 * @property int|null $sale_payment_id
 * @property int|null $customer_payment_id
 * @property int $user_id
 * @property Carbon $occurred_at
 */
class CashMovement extends Model
{
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'store_id', 'cashbox_session_id', 'type', 'amount', 'cash_intake_id', 'sale_payment_id', 'customer_payment_id',
        'user_id', 'occurred_at',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Cash movements are append-only.'));
        static::deleting(fn () => throw new LogicException('Cash movements are append-only.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CashMovementType::class,
            'occurred_at' => 'datetime',
        ];
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
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<SalePayment, $this>
     */
    public function salePayment(): BelongsTo
    {
        return $this->belongsTo(SalePayment::class);
    }

    /**
     * @return BelongsTo<CustomerPayment, $this>
     */
    public function customerPayment(): BelongsTo
    {
        return $this->belongsTo(CustomerPayment::class);
    }
}
