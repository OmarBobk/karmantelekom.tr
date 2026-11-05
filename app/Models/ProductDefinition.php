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
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
 * @property int|null $catalog_brand_id
 * @property int|null $catalog_category_id
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
        'catalog_brand_id',
        'catalog_category_id',
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
     * Match definitions whose name or model contains the term.
     *
     * @param  Builder<ProductDefinition>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = Str::trim((string) $term);

        if ($term === '') {
            return;
        }

        $pattern = '%'.addcslashes($term, '\\%_').'%';

        $query->where(fn (Builder $matches) => $matches
            ->where('name', 'like', $pattern)
            ->orWhere('model', 'like', $pattern));
    }

    /**
     * Whether any store holds stock records or movements for this definition.
     */
    public function hasInventoryHistory(): bool
    {
        return $this->movements()->exists()
            || $this->balances()->exists()
            || $this->units()->exists();
    }

    /**
     * @return BelongsTo<CatalogBrand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(CatalogBrand::class, 'catalog_brand_id');
    }

    /**
     * @return BelongsTo<CatalogCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(CatalogCategory::class, 'catalog_category_id');
    }

    /**
     * @return HasMany<InventoryBalance, $this>
     */
    public function balances(): HasMany
    {
        return $this->hasMany(InventoryBalance::class);
    }

    /**
     * @return HasMany<InventoryUnit, $this>
     */
    public function units(): HasMany
    {
        return $this->hasMany(InventoryUnit::class);
    }

    /**
     * @return HasMany<InventoryMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
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
