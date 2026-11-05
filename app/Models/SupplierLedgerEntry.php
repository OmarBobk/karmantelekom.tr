<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Currency;
use App\Enums\SupplierLedgerEntryType;
use App\Services\Purchasing\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Append-only supplier account entry. Positive amounts are owed; negative amounts settle or cancel.
 *
 * @property int $id
 * @property int $operational_supplier_id
 * @property int $store_id
 * @property int $purchase_id
 * @property int|null $supplier_payment_id
 * @property Currency $currency
 * @property SupplierLedgerEntryType $type
 * @property string $amount
 * @property int $user_id
 * @property Carbon $occurred_at
 */
class SupplierLedgerEntry extends Model
{
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'operational_supplier_id',
        'store_id',
        'purchase_id',
        'supplier_payment_id',
        'currency',
        'type',
        'amount',
        'user_id',
        'occurred_at',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Supplier ledger entries are append-only.'));
        static::deleting(fn () => throw new LogicException('Supplier ledger entries are append-only.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'currency' => Currency::class,
            'type' => SupplierLedgerEntryType::class,
            'occurred_at' => 'datetime',
        ];
    }

    public function amountMinor(): int
    {
        return Money::fromDecimal($this->amount);
    }

    /**
     * @return BelongsTo<Purchase, $this>
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
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
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
