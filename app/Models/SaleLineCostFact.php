<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Currency;
use App\Enums\DeviceCostCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Frozen cost evidence for one sale line, captured at completion.
 *
 * @property int $id
 * @property int $sale_line_id
 * @property string $fact_type
 * @property string $inclusion
 * @property string|null $category
 * @property Currency|null $currency
 * @property string|null $amount
 * @property int|null $purchase_receipt_unit_id
 * @property int|null $device_cost_entry_id
 * @property bool $entry_reversed_at_sale
 * @property string|null $note
 */
class SaleLineCostFact extends Model
{
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'sale_line_id',
        'fact_type',
        'inclusion',
        'category',
        'currency',
        'amount',
        'purchase_receipt_unit_id',
        'device_cost_entry_id',
        'entry_reversed_at_sale',
        'note',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Sale line cost facts are append-only.'));
        static::deleting(fn () => throw new LogicException('Sale line cost facts are append-only.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'currency' => Currency::class,
            'category' => DeviceCostCategory::class,
            'entry_reversed_at_sale' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<SaleLine, $this>
     */
    public function saleLine(): BelongsTo
    {
        return $this->belongsTo(SaleLine::class);
    }

    /**
     * @return BelongsTo<DeviceCostEntry, $this>
     */
    public function deviceCostEntry(): BelongsTo
    {
        return $this->belongsTo(DeviceCostEntry::class);
    }

    /**
     * @return BelongsTo<PurchaseReceiptUnit, $this>
     */
    public function purchaseReceiptUnit(): BelongsTo
    {
        return $this->belongsTo(PurchaseReceiptUnit::class);
    }
}
