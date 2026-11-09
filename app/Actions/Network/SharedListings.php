<?php

declare(strict_types=1);

namespace App\Actions\Network;

use App\Enums\TrackingMode;
use App\Enums\UnitCondition;
use App\Enums\UnitStatus;
use App\Models\NetworkListing;
use App\Models\NetworkReservation;
use App\Models\Store;
use App\Services\Purchasing\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Other branches' shared devices as seen from one branch, rechecked against the device on every
 * call: still held by the publishing branch, available, not held by an active reservation, on an
 * active product, and priced. These mirror ListingEligibility and counter sale rules.
 *
 * Rows carry display fields plus the listing id and revision a reservation request is bound to.
 * Identifiers, costs, minimum prices, and record IDs of the device never leave this query.
 */
final class SharedListings
{
    public const PER_PAGE = 20;

    public const MAX_SEARCH_LENGTH = 80;

    /**
     * @return LengthAwarePaginator<int, array{key: int, revision: int, product: string, model: ?string, condition: UnitCondition, price_minor: int, store_name: string, store_code: string, published_at: string}>
     */
    public function __invoke(Store $viewer, string $search = '', ?UnitCondition $condition = null, ?int $owningStoreId = null): LengthAwarePaginator
    {
        $term = mb_substr(Str::trim($search), 0, self::MAX_SEARCH_LENGTH);
        $like = '%'.addcslashes($term, '%_\\').'%';
        $usedDevice = TrackingMode::USED_DEVICE->value;

        return NetworkListing::query()
            ->published()
            ->join('inventory_units as units', 'units.id', '=', 'network_listings.inventory_unit_id')
            ->join('product_definitions as definitions', 'definitions.id', '=', 'units.product_definition_id')
            ->join('stores', 'stores.id', '=', 'network_listings.store_id')
            ->whereColumn('units.store_id', 'network_listings.store_id')
            ->where('network_listings.store_id', '!=', $viewer->id)
            ->where('units.status', UnitStatus::AVAILABLE->value)
            ->where('definitions.is_active', true)
            ->whereIn('definitions.tracking_mode', [TrackingMode::SERIALIZED->value, $usedDevice])
            ->tap(fn (Builder $query) => ListingEligibility::wherePriced($query))
            ->tap(fn (Builder $query) => NetworkReservation::whereUnitNotHeld($query, 'units.id'))
            ->when($term !== '', fn (Builder $query) => $query->where(fn (Builder $match) => $match
                ->where('definitions.name', 'like', $like)
                ->orWhere('definitions.model', 'like', $like)))
            ->when($condition !== null, fn (Builder $query) => $query->where('units.condition', $condition->value))
            ->when($owningStoreId !== null, fn (Builder $query) => $query->where('network_listings.store_id', $owningStoreId))
            ->orderByDesc('network_listings.published_at')
            ->orderByDesc('network_listings.id')
            ->select([
                'network_listings.id as listing_key',
                'network_listings.revision as listing_revision',
                'network_listings.published_at as listed_at',
                'definitions.name as product_name',
                'definitions.model as product_model',
                'units.condition as unit_condition',
                DB::raw("case when definitions.tracking_mode = '{$usedDevice}' then units.selling_price else definitions.selling_price end as asking_price"),
                'stores.name as store_name',
                'stores.code as store_code',
            ])
            ->paginate(self::PER_PAGE)
            ->through(fn (NetworkListing $row): array => [
                'key' => (int) $row->getAttribute('listing_key'),
                'revision' => (int) $row->getAttribute('listing_revision'),
                'product' => (string) $row->getAttribute('product_name'),
                'model' => $row->getAttribute('product_model'),
                'condition' => UnitCondition::from((string) $row->getAttribute('unit_condition')),
                'price_minor' => Money::fromDecimal((string) $row->getAttribute('asking_price')),
                'store_name' => (string) $row->getAttribute('store_name'),
                'store_code' => (string) $row->getAttribute('store_code'),
                'published_at' => (string) $row->getAttribute('listed_at'),
            ]);
    }
}
