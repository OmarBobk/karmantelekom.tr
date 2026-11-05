<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Currency;
use App\Enums\SupplierPaymentMethod;
use App\Services\Purchasing\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use LogicException;

/**
 * A payment against one posted invoice, in the invoice currency. Reversal keeps the row.
 *
 * @property int $id
 * @property int $purchase_id
 * @property int $operational_supplier_id
 * @property int $store_id
 * @property Currency $currency
 * @property string $amount
 * @property Carbon $paid_on
 * @property SupplierPaymentMethod $method
 * @property string $reference
 * @property string $reference_key
 * @property string|null $note
 * @property int $user_id
 * @property string $idempotency_key
 * @property string $request_fingerprint
 * @property int|null $reversed_by
 * @property Carbon|null $reversed_at
 * @property string|null $reversal_reason
 */
class SupplierPayment extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'purchase_id',
        'operational_supplier_id',
        'store_id',
        'currency',
        'amount',
        'paid_on',
        'method',
        'reference',
        'reference_key',
        'note',
        'user_id',
        'idempotency_key',
        'request_fingerprint',
        'reversed_by',
        'reversed_at',
        'reversal_reason',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = ['request_fingerprint'];

    private const REVERSAL_FIELDS = ['reversed_by', 'reversed_at', 'reversal_reason', 'updated_at'];

    protected static function booted(): void
    {
        static::updating(function (self $payment): void {
            if ($payment->getOriginal('reversed_at') !== null || array_diff(array_keys($payment->getDirty()), self::REVERSAL_FIELDS) !== []) {
                throw new LogicException('Only an active payment may change, and only by being reversed.');
            }
        });
        static::deleting(fn () => throw new LogicException('Supplier payments are never deleted; reverse them.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'currency' => Currency::class,
            'method' => SupplierPaymentMethod::class,
            'paid_on' => 'date',
            'reversed_at' => 'datetime',
        ];
    }

    /**
     * Trim outer whitespace and fold case; internal spaces and punctuation are kept.
     */
    public static function normalizeReference(?string $value): ?string
    {
        $value = Str::trim((string) $value);

        return $value === '' ? null : mb_convert_case($value, MB_CASE_FOLD, 'UTF-8');
    }

    public function amountMinor(): int
    {
        return Money::fromDecimal($this->amount);
    }

    public function isReversed(): bool
    {
        return $this->reversed_at !== null;
    }

    /**
     * @return BelongsTo<Purchase, $this>
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reverser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }
}
