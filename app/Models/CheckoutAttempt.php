<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CardApprovalStatus;
use App\Enums\CheckoutAttemptStatus;
use App\Enums\Currency;
use App\Services\Purchasing\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * A reviewed POS cart persisted before payment is taken, so an unresolved payment attempt
 * survives reloads, lost responses, and new logins.
 *
 * @property int $id
 * @property string $key
 * @property int $store_id
 * @property int $user_id
 * @property CheckoutAttemptStatus $status
 * @property int $revision
 * @property Currency $currency
 * @property list<array<string, mixed>> $lines
 * @property string $subtotal
 * @property string $discount_total
 * @property string $total
 * @property string $payload_hash
 * @property Carbon|null $frozen_at
 * @property int|null $terminal_payment_terminal_id
 * @property string|null $terminal_payment_amount
 * @property int|null $terminal_payment_started_by
 * @property Carbon|null $terminal_payment_started_at
 * @property string|null $finalize_fingerprint
 * @property string|null $failure_code
 * @property string|null $failure_message
 * @property Carbon|null $failed_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $cancelled_at
 * @property int|null $resolved_by
 * @property Carbon|null $resolved_at
 * @property string|null $resolution_note
 */
class CheckoutAttempt extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'key', 'store_id', 'user_id', 'status', 'revision', 'currency', 'lines',
        'subtotal', 'discount_total', 'total', 'payload_hash', 'frozen_at',
        'terminal_payment_terminal_id', 'terminal_payment_amount', 'terminal_payment_started_by',
        'terminal_payment_started_at', 'finalize_fingerprint', 'failure_code', 'failure_message', 'failed_at',
        'completed_at', 'cancelled_at', 'resolved_by', 'resolved_at', 'resolution_note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CheckoutAttemptStatus::class,
            'currency' => Currency::class,
            'lines' => 'array',
            'revision' => 'integer',
            'frozen_at' => 'datetime',
            'terminal_payment_started_at' => 'datetime',
            'failed_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<CheckoutAttempt>  $query
     */
    public function scopeUnresolved(Builder $query): void
    {
        $query->where('status', CheckoutAttemptStatus::OPEN);
    }

    /**
     * @param  Builder<CheckoutAttempt>  $query
     */
    public function scopePendingTerminalPayment(Builder $query): void
    {
        $query->where('status', CheckoutAttemptStatus::OPEN)
            ->whereNotNull('terminal_payment_started_at')
            ->whereDoesntHave('cardApproval');
    }

    public function isOpen(): bool
    {
        return $this->status === CheckoutAttemptStatus::OPEN;
    }

    public function isFrozen(): bool
    {
        return $this->frozen_at !== null;
    }

    public function hasStartedTerminalPayment(): bool
    {
        return $this->terminal_payment_started_at !== null;
    }

    /**
     * The cashier was told to charge the card, but no approval has reached the app yet: the card
     * may or may not have been charged, so the attempt can be neither discarded nor paid in cash.
     */
    public function hasPendingTerminalPayment(): bool
    {
        if (! $this->hasStartedTerminalPayment()) {
            return false;
        }

        return $this->relationLoaded('cardApproval') ? $this->cardApproval === null : ! $this->cardApproval()->exists();
    }

    public function terminalPaymentAmountMinor(): ?int
    {
        return $this->terminal_payment_amount === null ? null : Money::fromDecimal($this->terminal_payment_amount);
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

    /**
     * Card amount already approved on a terminal and still awaiting (or captured by) the sale.
     */
    public function approvedCardMinor(): int
    {
        $approval = $this->relationLoaded('cardApproval') ? $this->cardApproval : $this->cardApproval()->first();

        return $approval !== null && $approval->status !== CardApprovalStatus::VOIDED ? $approval->amountMinor() : 0;
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
     * @return BelongsTo<User, $this>
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * @return BelongsTo<CardTerminal, $this>
     */
    public function terminalPaymentTerminal(): BelongsTo
    {
        return $this->belongsTo(CardTerminal::class, 'terminal_payment_terminal_id');
    }

    /**
     * @return HasOne<CardApproval, $this>
     */
    public function cardApproval(): HasOne
    {
        return $this->hasOne(CardApproval::class);
    }

    /**
     * The attempt's approval, row-locked by primary key. Call only while holding this attempt's
     * row lock: approvals are created under it, so the unlocked lookup cannot miss one, and no
     * gap lock is taken on the attempt index (concurrent approvals would deadlock on insert).
     */
    public function lockCardApproval(): ?CardApproval
    {
        $approvalId = $this->cardApproval()->value('id');

        return $approvalId === null ? null : CardApproval::query()->whereKey($approvalId)->lockForUpdate()->first();
    }

    /**
     * @return HasOne<Sale, $this>
     */
    public function sale(): HasOne
    {
        return $this->hasOne(Sale::class);
    }
}
