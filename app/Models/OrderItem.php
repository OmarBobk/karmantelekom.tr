<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Product Model
 *
 * Represents a product in the e-commerce system with support for both retail and wholesale operations.
 * Products can have multiple prices in different currencies and types (retail/wholesale).
 *
 * @property int $quantity Quantity of the product in the order item
 * @property int $price Price of the product in the order item
 * @property int $subtotal Subtotal for the order item (calculated as price * quantity)
 * @property-read Order $order Order relationship
 * @property-read Product $product Product relationship
 */
class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'price',
        'subtotal',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getFormattedPriceAttribute(): string
    {
        return number_format($this->price, 2);
    }

    public function getFormattedSubtotalAttribute(): string
    {
        return number_format($this->subtotal, 2);
    }

    protected static function boot(): void
    {
        parent::boot();

        // Model events
        static::creating(function ($orderItem) {
            $orderItem->subtotal = $orderItem->calculateSubtotal();
        });

        // Model Event
        static::updating(function ($orderItem) {
            $orderItem->subtotal = $orderItem->calculateSubtotal();
        });
    }

    public function calculateSubtotal(): float
    {
        return ($this->price * $this->quantity);
    }
}
