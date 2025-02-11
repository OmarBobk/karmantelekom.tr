<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Builder;

/**
 * Product Model
 * 
 * Represents a product in the e-commerce system with support for both retail and wholesale operations.
 * Products can have multiple prices in different currencies and types (retail/wholesale).
 * 
 * @property string $name Product name
 * @property string $slug URL-friendly version of name
 * @property string $serial Unique serial number (optional)
 * @property string $code Product code/SKU
 * @property string $description Detailed product description
 * @property int $category_id Foreign key to categories table
 * @property int $supplier_id Foreign key to suppliers table
 * @property bool $is_retail_active Whether product is visible in retail
 * @property bool $is_wholesale_active Whether product is visible in wholesale
 * @property-read Category $category Product category relationship
 * @property-read Supplier $supplier Product supplier relationship
 * @property-read \Illuminate\Database\Eloquent\Collection<ProductPrice> $prices Product prices in different currencies
 * @property-read \Illuminate\Database\Eloquent\Collection<ProductImage> $images Product images
 * @property-read \Illuminate\Database\Eloquent\Collection<Tag> $tags Product tags
 * @property-read \Illuminate\Database\Eloquent\Collection<Section> $sections Product sections
 * @property-read \Illuminate\Database\Eloquent\Collection<Order> $orders Related orders
 */
class Product extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'serial',
        'code',
        'description',
        'category_id',
        'supplier_id',
        'is_retail_active',
        'is_wholesale_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_retail_active' => 'boolean',
        'is_wholesale_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Boot the model.
     * Automatically generates a slug from the name when creating a new product.
     */
    protected static function boot(): void
    {
        parent::boot();
        
        static::creating(function (Product $product): void {
            if (empty($product->slug)) {
                $product->slug = Str::slug($product->name);
            }
        });
    }

    /**
     * Scope to filter products based on user's role and visibility settings.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeVisibleToUser(Builder $query): Builder
    {
        if (auth()->check() && auth()->user()->hasAnyRole(['admin', 'salesperson', 'shop_owner'])) {
            return $query->where('is_wholesale_active', true);
        }
        
        return $query->where('is_retail_active', true);
    }

    /**
     * Scope to get only retail-active products.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeRetailActive(Builder $query): Builder
    {
        return $query->where('is_retail_active', true);
    }

    /**
     * Scope to get only wholesale-active products.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeWholesaleActive(Builder $query): Builder
    {
        return $query->where('is_wholesale_active', true);
    }

    /**
     * Get the category that owns the product.
     *
     * @return BelongsTo<Category, Product>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the supplier that owns the product.
     *
     * @return BelongsTo<Supplier, Product>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Get the prices for the product.
     *
     * @return HasMany<ProductPrice>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    /**
     * Get the images for the product.
     *
     * @return HasMany<ProductImage>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    /**
     * Get the tags associated with the product.
     *
     * @return BelongsToMany<Tag>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'product_tags');
    }

    /**
     * Get the sections associated with the product.
     *
     * @return BelongsToMany<Section>
     */
    public function sections(): BelongsToMany
    {
        return $this->belongsToMany(Section::class, 'section_products');
    }

    /**
     * Get the orders associated with the product.
     *
     * @return BelongsToMany<Order>
     */
    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(Order::class, 'order_items')
            ->withPivot(['quantity', 'price']);
    }

    /**
     * Check if the product is visible to the current user based on their role.
     *
     * @return bool
     */
    public function isVisibleToCurrentUser(): bool
    {
        if (auth()->check() && auth()->user()->hasAnyRole(['admin', 'salesperson', 'shop_owner'])) {
            return $this->is_wholesale_active;
        }
        
        return $this->is_retail_active;
    }

    /**
     * Get the primary image URL for the product.
     *
     * @return string
     */
    public function getPrimaryImageUrlAttribute(): string
    {
        return $this->images()
            ->where('is_primary', true)
            ->first()?->image_url ?? 'products/default.jpg';
    }

    /**
     * Get the retail price for the product in the specified currency.
     *
     * @param string $currencyCode
     * @return ?ProductPrice
     */
    public function getRetailPrice(string $currencyCode = 'TRY'): ?ProductPrice
    {
        return $this->prices()
            ->whereHas('currency', fn($q) => $q->where('code', $currencyCode))
            ->where('price_type', 'retail')
            ->first();
    }

    /**
     * Get the wholesale price for the product in the specified currency.
     *
     * @param string $currencyCode
     * @return ?ProductPrice
     */
    public function getWholesalePrice(string $currencyCode = 'TRY'): ?ProductPrice
    {
        return $this->prices()
            ->whereHas('currency', fn($q) => $q->where('code', $currencyCode))
            ->where('price_type', 'wholesale')
            ->first();
    }
}
