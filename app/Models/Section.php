<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Enums\SectionPosition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class Section extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'order',
        'is_active',
        'scrollable',
        'position',
        'is_wholesale_active',
        'is_retail_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'scrollable' => 'boolean',
        'is_wholesale_active' => 'boolean',
        'is_retail_active' => 'boolean',
        'order' => 'integer',
        'position' => SectionPosition::class
    ];

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (Section $section) {
            if ($section->is_wholesale_active && $section->is_retail_active) {
                throw ValidationException::withMessages([
                    'is_wholesale_active' => 'A section cannot be both wholesale and retail active.',
                    'is_retail_active' => 'A section cannot be both wholesale and retail active.',
                ]);
            }

            if (!$section->is_wholesale_active && !$section->is_retail_active) {
                throw ValidationException::withMessages([
                    'is_wholesale_active' => 'A section must be either wholesale or retail active.',
                    'is_retail_active' => 'A section must be either wholesale or retail active.',
                ]);
            }
        });
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'section_products')
            ->withPivot('ordering')
            ->orderBy('section_products.ordering', 'asc');
    }

    public function scopeActive($query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query): Builder
    {
        return $query->orderBy('order', 'asc');
    }

    public function scopeWholesaleActive($query): Builder
    {
        return $query->where('is_wholesale_active', true)
                    ->whereHas('products', function ($query) {
                        $query->wholesaleActive();
                    });
    }

    public function scopeRetailActive($query): Builder
    {
        return $query->where('is_retail_active', true)
                    ->whereHas('products', function ($query) {
                        $query->retailActive();
                    });
    }

    public function getActiveProducts(): BelongsToMany
    {
        $query = $this->products();
        
        if (auth()->check() && auth()->user()->hasAnyRole(['admin', 'salesperson', 'shop_owner'])) {
            return $query->wholesaleActive();
        }
        
        return $query->retailActive();
    }
}
