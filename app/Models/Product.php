<?php

namespace App\Models;

use App\Traits\Searchable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * Product Model
 *
 * Represents a product in the e-commerce system with support for both retail and wholesale operations.
 * Products can have multiple prices in different currencies and types (retail/wholesale).
 *
 * @property string $name Product name
 * @property string $tr_name Product name
 * @property string $ar_name Product name
 * @property string $slug URL-friendly version of the name
 * @property string $tr_slug URL-friendly version of the name
 * @property string $ar_slug URL-friendly version of the name
 * @property string $serial Unique serial number (optional)
 * @property string $code Product code/SKU
 * @property string $description Detailed product description
 * @property string $tr_description Detailed product description
 * @property string $ar_description Detailed product description
 * @property int $category_id Foreign key to categorize table
 * @property bool $is_active Whether the product is active
 * @property string $translated_name Translated name based on the current locale.
 * @property string $translated_description Translated description based on the current locale.
 * @property string $translated_slug Translated slug based on the current locale.
 * @property-read Category $category Product category relationship
 * @property-read Collection<ProductPrice> $prices Product prices in different currencies
 * @property-read Collection<ProductImage> $images Product images
 * @property-read Collection<Tag> $tags Product tags
 * @property-read Collection<Section> $sections Product sections
 * @property-read Collection<Order> $orders Related orders
 */
class Product extends Model
{
    use HasFactory, HasSlug, Searchable;

    public array $searchable_columns = [
        'code', 'name', 'description', 'serial', 'tr_name', 'ar_name', 'tr_description', 'ar_description'
    ];

    public array $return_from_search = [];

    protected $guarded = [];

    protected $appends = ['translated_name', 'translated_description', 'translated_slug'];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean'
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
     * Scope a query to only include active products.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
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

    public function tag()
    {
        return $this->tags()->first() ?? null;
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
     * Check if the product is visible to the current user.
     *
     * @return bool
     */
    public function isVisibleToCurrentUser(): bool
    {
        return $this->is_active;
    }

    /**
     * Scope to filter products based on the user's role and visibility settings.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeVisibleToUser(Builder $query): Builder
    {
        return $query->where('is_active', true);
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
     * Get the price for the product in the specified currency.
     *
     * @param string $currencyCode
     * @return ?ProductPrice
     */
    public function getPrice(string $currencyCode = 'TRY'): ?ProductPrice
    {
        return $this->prices()
            ->whereHas('currency', fn($q) => $q->where('code', $currencyCode))
            ->first();
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
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
            'tr' => $this->tr_name ?: $this->name,
            'AR' => $this->ar_name ?: $this->name,
            'ar' => $this->ar_name ?: $this->name,
            default => $this->name,
        };
    }

    /**
     * Get the translated description based on the current locale.
     *
     * @return string
     */
    public function getTranslatedDescriptionAttribute(): string
    {
        return match (app()->getLocale()) {
            'TR' => $this->tr_description ?: $this->description,
            'tr' => $this->tr_description ?: $this->description,
            'AR' => $this->ar_description ?: $this->description,
            'ar' => $this->ar_description ?: $this->description,
            default => $this->description,
        };
    }

    /**
     * Get the translated slug based on the current locale.
     *
     * @return string
     */
    public function getTranslatedSlugAttribute(): string
    {
        return match (app()->getLocale()) {
            'TR', 'tr' => $this->tr_slug ?: $this->slug,
            'AR', 'ar' => $this->ar_slug ?: $this->slug,
            default => $this->slug,
        };
    }
}
