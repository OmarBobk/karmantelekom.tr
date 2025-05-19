<?php

namespace App\Models;

use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    /** @use HasFactory<TagFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'text_color',
        'background_color',
        'border_color',
        'icon',
        'is_active',
        'display_order',
        'tr_name',
        'ar_name',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_tags');
    }


    /**
     * Get the translated name based on the current locale.
     *
     * @return string
     */
    public function getTranslatedNameAttribute(): string
    {
        return match (app()->getLocale()) {
            'TR', 'tr' => $this->tr_name ?: $this->name,
            'AR', 'ar' => $this->ar_name ?: $this->name,
            default => $this->name,
        };
    }

}
