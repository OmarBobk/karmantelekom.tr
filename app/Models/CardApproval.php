<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CardApprovalStatus;
use App\Enums\Currency;
use App\Services\Purchasing\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A card payment approved on an external terminal and recorded on a checkout attempt before
 * the sale is finalized. Kept separate from the sale's final payments.
 *
 * @property int $id
 * @property int $checkout_attempt_id
 * @property string $terminal_payment_key
 * @property int $store_id
 * @property int $card_terminal_id
 * @property string $reference
 * @property Currency $currency
 * @property string $amount
 * @property CardApprovalStatus $status
 * @property int $recorded_by
 * @property Carbon $recorded_at
 * @property string|null $void_reference
 * @property string|null $void_note
 * @property int|null $voided_by
 * @property Carbon|null $voided_at
 */
class CardApproval extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'checkout_attempt_id', 'terminal_payment_key', 'store_id', 'card_terminal_id', 'reference', 'currency', 'amount',
        'status', 'recorded_by', 'recorded_at', 'void_reference', 'void_note', 'voided_by', 'voided_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'currency' => Currency::class,
            'status' => CardApprovalStatus::class,
            'recorded_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function amountMinor(): int
    {
        return Money::fromDecimal($this->amount);
    }

    /**
     * @return BelongsTo<CheckoutAttempt, $this>
     */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(CheckoutAttempt::class, 'checkout_attempt_id');
    }

    /**
     * @return BelongsTo<CardTerminal, $this>
     */
    public function terminal(): BelongsTo
    {
        return $this->belongsTo(CardTerminal::class, 'card_terminal_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }
}
