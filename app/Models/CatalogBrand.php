<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CatalogBrandFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Brand of operational catalog products. Independent of the e-commerce catalog.
 *
 * @property int $id
 * @property string $name
 * @property bool $is_active
 */
class CatalogBrand extends Model
{
    /** @use HasFactory<CatalogBrandFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = ['name', 'is_active'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['is_active' => true];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * @return HasMany<ProductDefinition, $this>
     */
    public function productDefinitions(): HasMany
    {
        return $this->hasMany(ProductDefinition::class);
    }
}
