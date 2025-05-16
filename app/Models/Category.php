<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'parent_id',
        'status',
        'tr_name',
        'ar_name',
        'description',
        'order',
        'is_active',
        'scrollable',
        'position'
    ];

    protected $casts = [
        'status' => 'boolean',
        'is_active' => 'boolean',
        'scrollable' => 'boolean',
        'order' => 'integer',
        'position' => SectionPosition::class
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($category) {
            $category->slug = $category->slug ?? Str::slug($category->name);
        });

        static::updating(function ($category) {
            if ($category->isDirty('name')) {
                $category->slug = Str::slug($category->name);
            }
        });
    }

    /**
     * Get the translated name based on the current locale.
     *
     * @return string
     */
    public function getTranslatedNameAttribute(): string
    {
        return match (app()->getLocale()) {
            'TR' => $this->tr_name ?: $this->name,
            'AR' => $this->ar_name ?: $this->name,
            default => $this->name,
        };
    }
}
