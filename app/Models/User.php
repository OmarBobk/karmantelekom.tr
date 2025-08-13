<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Relations\HasOne;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasRoles;

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'email_verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * How to use:
     * $user = User::find(1);
     * $emailVerifiedDate = $user->email_verified_at; // Returns Carbon instance
     * $hashedPassword = $user->password; // Returns hashed password
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get all orders for the user.
     * 
     * How to use:
     * $user = User::find(1);
     * $userOrders = $user->orders; // Returns collection of orders
     * foreach($user->orders as $order) {
     *     echo $order->id;
     * }
     *
     * @return HasMany
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get the user's owned shop (for shop owners)
     * 
     * How to use:
     * $user = User::find(1);
     * if($user->hasRole('shop_owner')) {
     *     $shop = $user->ownedShop; // Returns single shop
     * }
     *
     * @return HasOne
     */
    public function ownedShop(): HasOne
    {
        return $this->hasOne(Shop::class, 'owner_id');
    }

    /**
     * Get shops assigned to this user as a salesperson
     * 
     * How to use:
     * $user = User::find(1);
     * if($user->hasRole('salesperson')) {
     *     $shops = $user->assignedShops; // Returns collection
     * }
     *
     * @return HasMany
     */
    public function assignedShops(): HasMany
    {
        return $this->hasMany(Shop::class, 'salesperson_id');
    }

    /**
     * Get all shops related to this user (owned + assigned)
     * 
     * How to use:
     * $user = User::find(1);
     * $allShops = $user->allRelatedShops; // Returns collection
     *
     * @return Collection
     */
    public function getAllRelatedShopsAttribute()
    {
        $shops = collect();
        
        if ($this->ownedShop) {
            $shops->push($this->ownedShop);
        }
        
        $shops = $shops->merge($this->assignedShops);
        
        return $shops->unique('id');
    }

    /**
     * Check if user is a shop owner
     * 
     * How to use:
     * $user = User::find(1);
     * if($user->isShopOwner()) {
     *     echo "User is a shop owner";
     * }
     *
     * @return bool
     */
    public function isShopOwner(): bool
    {
        return $this->hasRole('shop_owner');
    }

    /**
     * Check if user is a salesperson
     * 
     * How to use:
     * $user = User::find(1);
     * if($user->isSalesperson()) {
     *     echo "User is a salesperson";
     * }
     *
     * @return bool
     */
    public function isSalesperson(): bool
    {
        return $this->hasRole('salesperson');
    }
}
