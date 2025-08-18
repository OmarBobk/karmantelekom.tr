<?php

namespace App\Models;

use App\Traits\Searchable;
use Database\Factories\ShopFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Shop extends Model
{
    /** @use HasFactory<ShopFactory> */
    use HasFactory, Searchable;
    protected $guarded = [];

    public array $searchable_columns = [
        'name'
    ];

    public array $return_from_search = [];


    /**
     * Get the attributes that should be cast.
     *
     * How to use:
     * $shop = Shop::find(1);
     * $links = $shop->links; // Returns array
     * $totalPrices = $shop->total_prices; // Returns decimal
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'links' => 'array',
            'total_prices' => 'decimal:2',
        ];
    }

    /**
     * Get all orders for this shop
     *
     * How to use:
     * $shop = Shop::find(1);
     * $orders = $shop->orders; // Returns collection of orders
     * foreach($shop->orders as $order) {
     *     echo $order->id;
     * }
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get the user associated with this shop (owner or salesperson)
     *
     * How to use:
     * $shop = Shop::find(1);
     * $user = $shop->user; // Returns associated User model
     * echo $user->name;
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the shop owner (if user is a shop owner)
     *
     * How to use:
     * $shop = Shop::find(1);
     * $owner = $shop->owner; // Returns User model if owner exists
     * if($owner) {
     *     echo $owner->name;
     * }
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Get the assigned salesperson (if user is a salesperson)
     *
     * How to use:
     * $shop = Shop::find(1);
     * $salesperson = $shop->salesperson; // Returns User model if salesperson exists
     * if($salesperson) {
     *     echo $salesperson->name;
     * }
     */
    public function salesperson(): BelongsTo
    {
        return $this->belongsTo(User::class, 'salesperson_id');
    }

    /**
     * Scope to get shops by user role
     *
     * How to use:
     * $user = User::find(1);
     * $shops = Shop::byUserRole($user)->get();
     * foreach($shops as $shop) {
     *     echo $shop->name;
     * }
     */
    public function scopeByUserRole($query, User $user)
    {
        if ($user->hasRole('shop_owner')) {
            return $query->where('owner_id', $user->id);
        } elseif ($user->hasRole('salesperson')) {
            return $query->where('salesperson_id', $user->id);
        }

        return $query->where('owner_id', 0); // No results for other roles
    }

    /**
     * Scope to get shops visible to a user based on their role
     *
     * How to use:
     * $user = Auth::user();
     * $shops = Shop::visibleTo($user)->get();
     * // Or for guest users:
     * $shops = Shop::visibleTo()->get();
     */
    public function scopeVisibleTo($query, $user = null)
    {
        if (!$user) {
            return $query->where('owner_id', 0);
        }

        if ($user->hasRole('admin')) {
            return $query; // Admin can see all shops
        }

        if ($user->hasRole('shop_owner')) {
            return $query->where('owner_id', $user->id);
        }

        if ($user->hasRole('salesperson')) {
            return $query->where('salesperson_id', $user->id);
        }

        return $query->where('owner_id', 0); // Other roles see nothing
    }

    /**
     * Get orders for current month
     *
     * How to use:
     * $shop = Shop::find(1);
     * $monthlyOrders = $shop->monthlyOrders;
     * foreach($monthlyOrders as $order) {
     *     echo $order->created_at;
     * }
     */
    public function monthlyOrders(): HasMany
    {
        return $this->hasMany(Order::class)
            ->whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year);
    }

    /**
     * Get count of orders for current month
     *
     * How to use:
     * $shop = Shop::find(1);
     * $count = $shop->monthly_orders_count;
     * echo "Orders this month: " . $count;
     */
    public function getMonthlyOrdersCountAttribute(): int
    {
        return $this->monthlyOrders()->count();
    }
}
