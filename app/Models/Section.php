<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Section extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'order',
        'is_active',
        'scrollable',
        'position'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'scrollable' => 'boolean',
        'order' => 'integer'
    ];

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'section_products')
            ->withPivot('ordering')
            ->orderBy('section_products.ordering', 'asc');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order', 'asc');
    }
}
