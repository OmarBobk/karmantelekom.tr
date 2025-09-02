<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Traits\Searchable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


/**
 * Order Model
 *
 *
 * @property OrderStatus $status Status of the order
 * @property string $notes Additional notes for the order
 * @property float $total_price Total price of the order
 * @property-read User $customer Customer who placed the order
* @property-read Shop $shop Shop where the order was placed
 */

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'user_id',
        'status',
        'total_price',
        'notes',
    ];

    protected $casts = [
        'status' => OrderStatus::class,        // Casts to the OrderStatus enum
        'total_price' => 'decimal:2',          // Ensures 2 decimal places for prices
        'created_at' => 'datetime',            // Ensures proper datetime handling
        'updated_at' => 'datetime'
    ];

    protected $appends = [
        'items_count',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Get the customer who placed the order
     *
     * DON'T USE THIS
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the shop's assigned salesperson
     */
    public function shopSalesperson(): BelongsTo
    {
        return $this->shop->salesperson();
    }

    // Keep the old relationship for backward compatibility but mark as deprecated
    /**
     * @deprecated Use customer() instead. This returns the customer, not a salesperson.
     */
    public function salesperson(): BelongsTo
    {
        return $this->customer();
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'order_items')->withPivot(['quantity', 'price']);
    }

    /**
     * Get the formatted total price of the order
     *
     * $order->formatted_total_price; // Returns "1,234.56"
     *
     * @return string
     */
    public function getFormattedTotalPriceAttribute(): string
    {
        return number_format($this->total_price, 2);
    }

    /**
     * Get the status color of the order
     *
     * $order->status_color; // Returns "bg-blue-100 text-blue-800"
     *
     * @return string
     */
    public function getItemsCountAttribute(): int
    {
        return $this->items->sum('quantity');
    }


}
