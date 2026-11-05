<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CatalogIdentifierType;
use App\Enums\TrackingMode;
use Database\Factories\ProductDefinitionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A reusable operational catalog entry shared by all stores.
 *
 * Holds product information only: creating a definition never creates stock or
 * inventory movements. Not to be confused with Product, the e-commerce listing.
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string|null $model
 * @property TrackingMode $tracking_mode
 * @property bool $is_active
 * @property string|null $sku
 * @property string|null $barcode
 */
class ProductDefinition extends Model
{
    /** @use HasFactory<ProductDefinitionFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'description',
        'model',
        'tracking_mode',
        'is_active',
        'sku',
        'barcode',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tracking_mode' => TrackingMode::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * Trim an identifier and treat blank values as absent. Always returns a string
     * so barcodes keep their leading zeros.
     */
    public static function normalizeIdentifier(string|int|null $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = Str::trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    /**
     * Find the definition with this exact identifier of the given type.
     */
    public static function findByIdentifier(CatalogIdentifierType $type, string|int|null $value): ?self
    {
        $identifier = self::normalizeIdentifier($value);

        if ($identifier === null) {
            return null;
        }

        return static::query()->withIdentifier($type, $identifier)->first();
    }

    /**
     * @param  Builder<ProductDefinition>  $query
     */
    public function scopeWithIdentifier(Builder $query, CatalogIdentifierType $type, string $identifier): void
    {
        $query->where($type->column(), $identifier);
    }

    /**
     * @param  Builder<ProductDefinition>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @return Attribute<string|null, string|int|null>
     */
    protected function sku(): Attribute
    {
        return Attribute::make(set: fn (string|int|null $value): ?string => self::normalizeIdentifier($value));
    }

    /**
     * @return Attribute<string|null, string|int|null>
     */
    protected function barcode(): Attribute
    {
        return Attribute::make(set: fn (string|int|null $value): ?string => self::normalizeIdentifier($value));
    }
}
