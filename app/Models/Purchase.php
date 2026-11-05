<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Currency;
use App\Enums\PurchaseStatus;
use App\Enums\PurchaseTaxTreatment;
use App\Services\Purchasing\Money;
use Database\Factories\PurchaseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use LogicException;

/**
 * A supplier invoice for one store. Amounts are DECIMAL strings; use the *Minor() helpers for arithmetic.
 *
 * @property int $id
 * @property int $store_id
 * @property int $operational_supplier_id
 * @property Currency $currency
 * @property PurchaseTaxTreatment $tax_treatment
 * @property PurchaseStatus $status
 * @property string|null $supplier_invoice_number
 * @property string|null $invoice_number_key
 * @property Carbon|null $invoice_date
 * @property Carbon|null $due_date
 * @property string $items_total
 * @property string $additional_charges
 * @property string|null $additional_charges_note
 * @property string $total
 * @property string $paid_total
 * @property string|null $notes
 * @property int $revision
 * @property int|null $correction_of_purchase_id
 * @property int|null $root_purchase_id
 * @property Carbon|null $posted_at
 * @property Carbon|null $reversed_at
 * @property string|null $reversal_reason
 * @property Carbon|null $received_at
 */
class Purchase extends Model
{
    /** @use HasFactory<PurchaseFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'store_id',
        'operational_supplier_id',
        'currency',
        'tax_treatment',
        'status',
        'supplier_invoice_number',
        'invoice_number_key',
        'invoice_date',
        'due_date',
        'items_total',
        'additional_charges',
        'additional_charges_note',
        'total',
        'paid_total',
        'notes',
        'revision',
        'correction_of_purchase_id',
        'root_purchase_id',
        'supplier_name',
        'supplier_tax_number',
        'created_by',
        'posted_by',
        'posted_at',
        'cancelled_by',
        'cancelled_at',
        'reversed_by',
        'reversed_at',
        'reversal_reason',
        'received_at',
    ];

    protected static function booted(): void
    {
        static::deleting(fn () => throw new LogicException('Purchases are never deleted; cancel or reverse them.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'currency' => Currency::class,
            'tax_treatment' => PurchaseTaxTreatment::class,
            'status' => PurchaseStatus::class,
            'invoice_date' => 'date',
            'due_date' => 'date',
            'revision' => 'integer',
            'posted_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'reversed_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    /**
     * Trim outer whitespace and fold case; internal spaces and punctuation are kept.
     */
    public static function normalizeInvoiceNumber(?string $value): ?string
    {
        $value = Str::trim((string) $value);

        return $value === '' ? null : mb_convert_case($value, MB_CASE_FOLD, 'UTF-8');
    }

    public function itemsTotalMinor(): int
    {
        return Money::fromDecimal($this->items_total);
    }

    public function additionalChargesMinor(): int
    {
        return Money::fromDecimal($this->additional_charges);
    }

    public function totalMinor(): int
    {
        return Money::fromDecimal($this->total);
    }

    public function paidTotalMinor(): int
    {
        return Money::fromDecimal($this->paid_total);
    }

    /**
     * What is still owed on this invoice; zero unless it is posted.
     */
    public function outstandingMinor(): int
    {
        return $this->status === PurchaseStatus::POSTED ? $this->totalMinor() - $this->paidTotalMinor() : 0;
    }

    public function rootId(): int
    {
        return $this->root_purchase_id ?? $this->id;
    }

    public function isDraft(): bool
    {
        return $this->status === PurchaseStatus::DRAFT;
    }

    public function isPosted(): bool
    {
        return $this->status === PurchaseStatus::POSTED;
    }

    public function isReceived(): bool
    {
        return $this->received_at !== null;
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<OperationalSupplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(OperationalSupplier::class, 'operational_supplier_id');
    }

    /**
     * @return HasMany<PurchaseLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseLine::class)->orderBy('line_number');
    }

    /**
     * @return HasOne<PurchaseReceipt, $this>
     */
    public function receipt(): HasOne
    {
        return $this->hasOne(PurchaseReceipt::class);
    }

    /**
     * @return HasMany<SupplierPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    /**
     * @return HasMany<SupplierLedgerEntry, $this>
     */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(SupplierLedgerEntry::class);
    }

    /**
     * @return BelongsTo<Purchase, $this>
     */
    public function correctionOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'correction_of_purchase_id');
    }

    /**
     * @return HasMany<Purchase, $this>
     */
    public function corrections(): HasMany
    {
        return $this->hasMany(self::class, 'correction_of_purchase_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reverser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}
