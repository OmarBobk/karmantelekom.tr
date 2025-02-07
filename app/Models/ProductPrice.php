<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPrice extends Model
{
    use HasFactory;

    public const TYPE_RETAIL = 'retail';
    public const TYPE_WHOLESALE = 'wholesale';

    protected $fillable = [
        'product_id',
        'currency_id',
        'price_type',
        'base_price',
        'converted_price',
        'is_main_price'
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'converted_price' => 'decimal:2',
        'is_main_price' => 'boolean'
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function getFormattedPrice(): string
    {
        if (!$this->currency) {
            return 'N/A';
        }

        // Convert the price to float before passing to getFormattedAmount
        $price = (float) $this->converted_price;
        return $this->currency->getFormattedAmount($price);
    }

    public static function getPriceTypes(): array
    {
        return [
            self::TYPE_RETAIL => 'Retail Price',
            self::TYPE_WHOLESALE => 'Wholesale Price'
        ];
    }
}
