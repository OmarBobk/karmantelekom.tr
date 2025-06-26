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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'links' => 'array',
            'total_prices' => 'decimal:2',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getSalespersonAttribute()
    {
        return $this->user ?? new User([
            'id' => 0,
            'name' => 'Unassigned',
            'email' => 'Unassigned',
        ]);
    }

    public function monthlyOrders(): HasMany
    {
        return $this->hasMany(Order::class)
            ->whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year);
    }

    public function getMonthlyOrdersCountAttribute(): int
    {
        return $this->monthlyOrders()->count();
    }

    public function scopeVisibleTo($query, $user)
    {
        if ($user->hasRole('admin')) {
            return $query;
        }

        return $query->where('user_id', $user->id);
    }
}
