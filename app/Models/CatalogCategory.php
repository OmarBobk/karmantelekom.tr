<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CatalogCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Flat operational catalog category. Independent of the e-commerce Category tree.
 *
 * @property int $id
 * @property string $name
 * @property bool $is_active
 */
class CatalogCategory extends Model
{
    /** @use HasFactory<CatalogCategoryFactory> */
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
