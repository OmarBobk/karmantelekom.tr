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
        'tr_name',
        'ar_name',
        'description',
        'order',
        'is_active',
        'scrollable',
        'position'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'scrollable' => 'boolean',
        'order' => 'integer',
        'position' => SectionPosition::class
    ];

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();
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

    public function getActiveProducts(): BelongsToMany
    {
        return $this->products()->where('is_active', true);
    }

    /**
     * Get the translated name based on the current locale.
     *
     * @return string
     */
    public function getTranslatedNameAttribute(): string
    {
        return match (app()->getLocale()) {
            'tr' => $this->tr_name ?: $this->name,
            'ar' => $this->ar_name ?: $this->name,
            default => $this->name,
        };
    }
}
