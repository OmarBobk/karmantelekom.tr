<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\CartFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property int $user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection|CartItem[] $items
 * @property-read User $user
 * @property-read int $items_count
 * @property-read float $subtotal
 */
class Cart extends Model
{
    /** @use HasFactory<CartFactory> */
    use HasFactory;

    protected $fillable = ['user_id'];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = [
        'total',
        'subtotal',
        'items_count'
    ];

    /**
     * Get the cart items associated with the cart.
     */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * Get the user that owns the cart.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the subtotal of the cart (before tax).
     */
    public function getSubtotalAttribute(): float
    {
        return $this->items->sum('subtotal');
    }

    /**
     * Get the total number of items in the cart.
     */
    public function getItemsCountAttribute(): int
    {
        return $this->items->sum('quantity');
    }

    /**
     * Scope a query to only include active carts.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereHas('items');
    }

    /**
     * Scope a query to only include empty carts.
     */
    public function scopeEmpty(Builder $query): Builder
    {
        return $query->doesntHave('items');
    }

    /**
     * Check if the cart is empty.
     */
    public function isEmpty(): bool
    {
        return $this->items->isEmpty();
    }

    /**
     * Clear all items from the cart.
     */
    public function clear(): int
    {
        return $this->items()->delete();
    }

}
