<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use LogicException;

/**
 * Goods received for one invoice line; its inventory movements reference it.
 *
 * @property int $id
 * @property int $purchase_receipt_id
 * @property int $purchase_line_id
 * @property int $product_definition_id
 * @property int $quantity
 * @property string $unit_cost
 */
class PurchaseReceiptLine extends Model
{
    public const MORPH_ALIAS = 'purchase_receipt_line';

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = ['purchase_receipt_id', 'purchase_line_id', 'product_definition_id', 'quantity', 'unit_cost'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Purchase receipt lines are append-only.'));
        static::deleting(fn () => throw new LogicException('Purchase receipt lines are append-only.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }

    /**
     * @return BelongsTo<PurchaseReceipt, $this>
     */
    public function receipt(): BelongsTo
    {
        return $this->belongsTo(PurchaseReceipt::class, 'purchase_receipt_id');
    }

    /**
     * @return BelongsTo<PurchaseLine, $this>
     */
    public function purchaseLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseLine::class);
    }

    /**
     * @return HasMany<PurchaseReceiptUnit, $this>
     */
    public function units(): HasMany
    {
        return $this->hasMany(PurchaseReceiptUnit::class);
    }

    /**
     * @return MorphMany<InventoryMovement, $this>
     */
    public function movements(): MorphMany
    {
        return $this->morphMany(InventoryMovement::class, 'reference');
    }
}
