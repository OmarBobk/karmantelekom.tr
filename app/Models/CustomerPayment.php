<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Currency;
use App\Enums\CustomerPaymentMethod;
use App\Services\Purchasing\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use LogicException;

/**
 * A payment a store customer made toward their open debts, allocated oldest sale first.
 * Append-only: reversals and corrections will be separate records.
 *
 * @property int $id
 * @property int $store_id
 * @property int $customer_id
 * @property Currency $currency
 * @property string $amount
 * @property CustomerPaymentMethod $method
 * @property string|null $receipt_number
 * @property string|null $reference
 * @property string|null $reference_key
 * @property string|null $note
 * @property int $user_id
 * @property string $idempotency_key
 * @property string $request_fingerprint
 * @property Carbon $received_at
 */
class CustomerPayment extends Model
{
    public const UPDATED_AT = null;

    public const MAX_REFERENCE_LENGTH = 64;

    public const MAX_NOTE_LENGTH = 500;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'store_id', 'customer_id', 'currency', 'amount', 'method', 'receipt_number', 'reference', 'reference_key',
        'note', 'user_id', 'idempotency_key', 'request_fingerprint', 'received_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = ['request_fingerprint'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Customer payments are append-only.'));
        static::deleting(fn () => throw new LogicException('Customer payments are append-only.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'currency' => Currency::class,
            'method' => CustomerPaymentMethod::class,
            'received_at' => 'datetime',
        ];
    }

    /**
     * The comparison form of an external reference: no whitespace, upper case.
     */
    public static function normalizeReference(?string $reference): ?string
    {
        $key = mb_strtoupper((string) preg_replace('/\s+/u', '', Str::trim((string) $reference)));

        return $key === '' ? null : $key;
    }

    public static function formatReceiptNumber(Store $store, int $sequence): string
    {
        return sprintf('%s-CP-%06d', $store->code, $sequence);
    }

    public function amountMinor(): int
    {
        return Money::fromDecimal($this->amount);
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
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return HasMany<CustomerPaymentAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(CustomerPaymentAllocation::class)->orderBy('id');
    }
}
