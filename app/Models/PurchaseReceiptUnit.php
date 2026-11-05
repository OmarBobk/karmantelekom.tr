<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Currency;
use App\Services\Purchasing\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * The acquisition price of one received device.
 *
 * @property int $id
 * @property int $purchase_receipt_line_id
 * @property int $inventory_unit_id
 * @property Currency $currency
 * @property string $unit_cost
 */
class PurchaseReceiptUnit extends Model
{
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = ['purchase_receipt_line_id', 'inventory_unit_id', 'currency', 'unit_cost'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Purchase receipt units are append-only.'));
        static::deleting(fn () => throw new LogicException('Purchase receipt units are append-only.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['currency' => Currency::class];
    }

    public function unitCostMinor(): int
    {
        return Money::fromDecimal($this->unit_cost);
    }

    /**
     * @return BelongsTo<PurchaseReceiptLine, $this>
     */
    public function receiptLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseReceiptLine::class, 'purchase_receipt_line_id');
    }

    /**
     * @return BelongsTo<InventoryUnit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(InventoryUnit::class, 'inventory_unit_id');
    }
}
