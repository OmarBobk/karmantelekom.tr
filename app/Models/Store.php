<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\StoreFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * An operational Karman Telekom store location.
 *
 * Not to be confused with Shop, which represents a B2B customer shop.
 */
class Store extends Model
{
    /** @use HasFactory<StoreFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'code',
        'phone',
        'address',
    ];

    /**
     * Users assigned to this store as employees.
     *
     * @return BelongsToMany<User, $this>
     */
    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }
}
