<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StorefrontChannel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The explicit link between an operational product and its legacy storefront product, and the
 * channels it is published to. The legacy product stays the presentation record: its URLs,
 * images, translations, prices, and order references are never changed by linking.
 *
 * @property int $id
 * @property int $product_definition_id
 * @property int $product_id
 * @property int $linked_by
 * @property Carbon|null $retail_published_at
 * @property Carbon|null $wholesale_published_at
 */
class StorefrontListing extends Model
{
    protected $fillable = ['product_definition_id', 'product_id', 'linked_by', 'retail_published_at', 'wholesale_published_at'];

    protected function casts(): array
    {
        return [
            'retail_published_at' => 'datetime',
            'wholesale_published_at' => 'datetime',
        ];
    }

    public function isPublishedTo(StorefrontChannel $channel): bool
    {
        return $this->getAttribute($channel->column()) !== null;
    }

    public function isPublished(): bool
    {
        return $this->retail_published_at !== null || $this->wholesale_published_at !== null;
    }

    /**
     * Ids of the given storefront products that are linked to branch stock. Linked stock is
     * display-only until branch confirmation exists, so these cannot be carted or ordered.
     *
     * @param  iterable<int|string>  $productIds
     * @return list<int>
     */
    public static function linkedProductIds(iterable $productIds): array
    {
        $ids = collect($productIds)->map(fn (int|string $id): int => (int) $id)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return self::query()->whereIn('product_id', $ids)->pluck('product_id')->map(fn ($id): int => (int) $id)->all();
    }

    /**
     * @return BelongsTo<ProductDefinition, $this>
     */
    public function productDefinition(): BelongsTo
    {
        return $this->belongsTo(ProductDefinition::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function linker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'linked_by');
    }
}
