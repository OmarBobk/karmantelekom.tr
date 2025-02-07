<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Currency extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'symbol',
        'exchange_rate',
        'is_default',
        'is_active'
    ];

    protected $casts = [
        'exchange_rate' => 'decimal:6',
        'is_default' => 'boolean',
        'is_active' => 'boolean'
    ];

    public function productPrices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    public function getFormattedAmount(float $amount): string
    {
        // Format with 2 decimal places and proper thousand separators
        $formattedNumber = number_format($amount, 2, '.', ',');
        
        // Handle symbol placement based on currency
        if ($this->code === 'TRY') {
            return $formattedNumber . ' ' . $this->symbol; // Symbol after amount for TRY
        }
        
        return $this->symbol . ' ' . $formattedNumber; // Symbol before amount for other currencies
    }

    public function convertToDefault(float $amount): float
    {
        return $amount / $this->exchange_rate;
    }

    public function convertFromDefault(float $amount): float
    {
        return $amount * $this->exchange_rate;
    }

    public function getCacheTag()
    {
        return 'currency_prices';
    }
}