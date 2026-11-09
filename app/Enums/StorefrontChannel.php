<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\User;

/**
 * A storefront audience a linked listing is published to. Retail is the public storefront.
 * Wholesale is the price-tier catalog, which only the admin and customer_salesperson roles
 * may open (the `catalog` route). Unlinked legacy products keep their existing visibility.
 */
enum StorefrontChannel: string
{
    case RETAIL = 'retail';
    case WHOLESALE = 'wholesale';

    /**
     * Roles that may see the wholesale catalog; the `catalog` route middleware uses the same list.
     *
     * @var list<string>
     */
    public const WHOLESALE_ROLES = ['admin', 'customer_salesperson'];

    public function label(): string
    {
        return __('storefront.channels.'.$this->value);
    }

    /**
     * The publication timestamp column on storefront_listings.
     */
    public function column(): string
    {
        return $this->value.'_published_at';
    }

    /**
     * Channels the viewer belongs to. Everyone sees retail.
     *
     * @return list<self>
     */
    public static function audienceOf(?User $viewer): array
    {
        return $viewer?->hasAnyRole(self::WHOLESALE_ROLES) ? [self::RETAIL, self::WHOLESALE] : [self::RETAIL];
    }
}
