<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PurchaseStatus;
use App\Enums\TrackingMode;
use App\Services\Purchasing\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

/**
 * One invoice line. Product facts are snapshotted so later catalog edits never change a posted invoice.
 *
 * @property int $id
 * @property int $purchase_id
 * @property int $line_number
 * @property int $product_definition_id
 * @property TrackingMode $tracking_mode
 * @property string $product_name
 * @property string|null $product_model
 * @property string|null $product_sku
 * @property int $quantity
 * @property string $unit_cost
 * @property string $line_total
 */
class PurchaseLine extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'purchase_id',
        'line_number',
        'product_definition_id',
        'tracking_mode',
        'product_name',
        'product_model',
        'product_sku',
        'quantity',
        'unit_cost',
        'line_total',
    ];

    protected static function booted(): void
    {
        $guard = function (self $line): void {
            $status = Purchase::query()->whereKey($line->purchase_id)->value('status');

            if ($status !== PurchaseStatus::DRAFT->value) {
                throw new LogicException('Lines of a non-draft purchase are immutable.');
            }
        };

        static::updating($guard);
        static::deleting($guard);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tracking_mode' => TrackingMode::class,
            'quantity' => 'integer',
            'line_number' => 'integer',
        ];
    }

    public function unitCostMinor(): int
    {
        return Money::fromDecimal($this->unit_cost);
    }

    public function lineTotalMinor(): int
    {
        return Money::fromDecimal($this->line_total);
    }

    /**
     * @return BelongsTo<Purchase, $this>
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    /**
     * @return BelongsTo<ProductDefinition, $this>
     */
    public function productDefinition(): BelongsTo
    {
        return $this->belongsTo(ProductDefinition::class);
    }

    /**
     * @return HasOne<PurchaseReceiptLine, $this>
     */
    public function receiptLine(): HasOne
    {
        return $this->hasOne(PurchaseReceiptLine::class);
    }
}
