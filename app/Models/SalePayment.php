<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Currency;
use App\Enums\SalePaymentMethod;
use App\Services\Purchasing\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One final payment allocation of a completed sale. Append-only.
 *
 * @property int $id
 * @property int $sale_id
 * @property int $store_id
 * @property int $user_id
 * @property SalePaymentMethod $method
 * @property Currency $currency
 * @property string $amount
 * @property string|null $cash_tendered
 * @property string|null $change_given
 * @property int|null $card_approval_id
 * @property int|null $card_terminal_id
 * @property string|null $terminal_reference
 * @property Carbon $occurred_at
 */
class SalePayment extends Model
{
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'sale_id', 'store_id', 'user_id', 'method', 'currency', 'amount', 'cash_tendered',
        'change_given', 'card_approval_id', 'card_terminal_id', 'terminal_reference', 'occurred_at',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Sale payments are append-only.'));
        static::deleting(fn () => throw new LogicException('Sale payments are append-only.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'method' => SalePaymentMethod::class,
            'currency' => Currency::class,
            'occurred_at' => 'datetime',
        ];
    }

    public function amountMinor(): int
    {
        return Money::fromDecimal($this->amount);
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * @return BelongsTo<CardTerminal, $this>
     */
    public function terminal(): BelongsTo
    {
        return $this->belongsTo(CardTerminal::class, 'card_terminal_id');
    }

    /**
     * @return BelongsTo<CardApproval, $this>
     */
    public function approval(): BelongsTo
    {
        return $this->belongsTo(CardApproval::class, 'card_approval_id');
    }
}
