<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SectionProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'section_id',
        'product_id',
        'ordering'
    ];

    protected $casts = [
        'ordering' => 'integer'
    ];

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
