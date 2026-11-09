<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\StoreFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
     * Shortest and longest hold a branch may configure for approved Store Network reservations.
     */
    public const MIN_RESERVATION_MINUTES = 15;

    public const MAX_RESERVATION_MINUTES = 10080;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'inventory_setup_completed_at' => 'datetime',
            'reservation_minutes' => 'integer',
        ];
    }

    /**
     * Whether opening stock may still be recorded in this branch.
     */
    public function inventorySetupIsOpen(): bool
    {
        return $this->inventory_setup_completed_at === null;
    }

    /**
     * The owner who closed this branch's initial inventory setup.
     *
     * @return BelongsTo<User, $this>
     */
    public function inventorySetupCompleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inventory_setup_completed_by');
    }

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
     * Quantity balances held by this store.
     *
     * @return HasMany<InventoryBalance, $this>
     */
    public function inventoryBalances(): HasMany
    {
        return $this->hasMany(InventoryBalance::class);
    }

    /**
     * Individually tracked units held by this store. Named for scoped route bindings ({unit}).
     *
     * @return HasMany<InventoryUnit, $this>
     */
    public function units(): HasMany
    {
        return $this->hasMany(InventoryUnit::class);
    }

    /**
     * @return HasMany<InventoryMovement, $this>
     */
    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    /**
     * @return HasMany<Purchase, $this>
     */
    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    /**
     * @return HasMany<Sale, $this>
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /**
     * @return HasMany<CardTerminal, $this>
     */
    public function cardTerminals(): HasMany
    {
        return $this->hasMany(CardTerminal::class);
    }

    /**
     * @return HasMany<CheckoutAttempt, $this>
     */
    public function checkoutAttempts(): HasMany
    {
        return $this->hasMany(CheckoutAttempt::class);
    }

    /**
     * @return HasMany<Customer, $this>
     */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
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
