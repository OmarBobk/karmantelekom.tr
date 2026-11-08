<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CashMovementDirection;
use App\Enums\CashMovementType;
use App\Services\Purchasing\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Physical cash that entered or left a store's cashbox during a session, attributed to the
 * employee who recorded it. The amount is always positive and the direction says which way it
 * moved. Exactly one per source: each sale cash payment, customer cash payment, cash intake,
 * cash payout, and expense posts at most once. Sales post the net cash kept, after change.
 * Append-only.
 *
 * @property int $id
 * @property int $store_id
 * @property int $cashbox_session_id
 * @property CashMovementType $type
 * @property CashMovementDirection $direction
 * @property string $amount
 * @property int|null $cash_intake_id
 * @property int|null $sale_payment_id
 * @property int|null $customer_payment_id
 * @property int|null $cash_payout_id
 * @property int|null $expense_id
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
        'store_id', 'cashbox_session_id', 'type', 'direction', 'amount', 'cash_intake_id', 'sale_payment_id',
        'customer_payment_id', 'cash_payout_id', 'expense_id', 'user_id', 'occurred_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (CashMovement $movement): void {
            if ($movement->direction !== $movement->type->direction()) {
                throw new LogicException("A {$movement->type->value} movement must move cash {$movement->type->direction()->value}.");
            }
        });
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
            'direction' => CashMovementDirection::class,
            'occurred_at' => 'datetime',
        ];
    }

    public function amountMinor(): int
    {
        return Money::fromDecimal($this->amount);
    }

    /**
     * The effect on the drawer: positive for cash in, negative for cash out.
     */
    public function signedMinor(): int
    {
        return $this->direction === CashMovementDirection::OUT ? -$this->amountMinor() : $this->amountMinor();
    }

    /**
     * @return BelongsTo<Expense, $this>
     */
    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
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
