<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Address extends Model
{
    /**
    // Get full address as string
    $address->full_address; // "123 Main St, New York, NY, 10001"

    // Get coordinates as array
    $address->coordinates; // ['lat' => 40.7128, 'lng' => -74.0060]

    // Set as primary (automatically unsets others)
    $address->setAsPrimary();

    // Scopes
    Address::primary()->get(); // Only primary addresses
    Address::nonPrimary()->get(); // Only non-primary addresses
     */
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'label',
        'address_line',
        'city',
        'postal_code',
        'state',
        'latitude',
        'longitude',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    /**
     * Get the shop that owns the address.
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Scope a query to only include primary addresses.
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    /**
     * Scope a query to only include non-primary addresses.
     */
    public function scopeNonPrimary($query)
    {
        return $query->where('is_primary', false);
    }

    /**
     * Get the full address as a string.
     */
    public function getFullAddressAttribute(): string
    {
        $parts = [
            $this->address_line,
            $this->city,
            $this->state,
            $this->postal_code,
        ];

        return implode(', ', array_filter($parts));
    }

    /**
     * Get the coordinates as an array.
     */
    public function getCoordinatesAttribute(): ?array
    {
        if ($this->latitude && $this->longitude) {
            return [
                'lat' => (float) $this->latitude,
                'lng' => (float) $this->longitude,
            ];
        }

        return null;
    }

    /**
     * Set this address as primary and unset others for the same shop.
     */
    public function setAsPrimary(): void
    {
        // Remove primary status from other addresses of the same shop
        $this->shop->addresses()
            ->where('id', '!=', $this->id)
            ->update(['is_primary' => false]);

        // Set this address as primary
        $this->update(['is_primary' => true]);
    }

    /**
     * Boot method to handle model events.
     */
    protected static function boot()
    {
        parent::boot();

        // When creating a new address, if it's primary, unset others
        static::creating(function ($address) {
            if ($address->is_primary) {
                $address->shop->addresses()
                    ->update(['is_primary' => false]);
            }
        });

        // When updating an address, if it's being set as primary, unset others
        static::updating(function ($address) {
            if ($address->is_primary && $address->isDirty('is_primary')) {
                $address->shop->addresses()
                    ->where('id', '!=', $address->id)
                    ->update(['is_primary' => false]);
            }
        });
    }
}
