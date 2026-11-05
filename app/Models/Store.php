<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\StoreFactory;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Limit the query to stores the user may operate in.
     *
     * Admins reach every store; employees reach only stores they are assigned to;
     * guests and any other role reach none. Roles and memberships are re-queried
     * so stale loaded relations never grant access.
     *
     * @param  Builder<Store>  $query
     */
    public function scopeAccessibleTo(Builder $query, ?User $user): void
    {
        if ($user === null) {
            $query->whereRaw('0 = 1');

            return;
        }

        if (self::userCurrentlyHasRole($user, 'admin')) {
            return;
        }

        if (! self::userCurrentlyHasRole($user, 'employee')) {
            $query->whereRaw('0 = 1');

            return;
        }

        $query->whereHas('employees', fn (Builder $employees) => $employees->whereKey($user->getKey()));
    }

    /**
     * Determine whether the user may operate in this store.
     */
    public function isAccessibleBy(?User $user): bool
    {
        return static::query()->accessibleTo($user)->whereKey($this->getKey())->exists();
    }

    private static function userCurrentlyHasRole(User $user, string $role): bool
    {
        return $user->roles()->where('name', $role)->exists();
    }
}
