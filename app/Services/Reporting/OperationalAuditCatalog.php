<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Models\CashboxSession;
use App\Models\InventoryUnit;
use App\Models\NetworkReservation;
use App\Models\ProductDefinition;
use App\Models\Store;
use App\Models\StorefrontListing;
use App\Models\User;
use App\Policies\StorePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

/**
 * Explicit allowlist of operational activity-log events shown on the audit review page.
 *
 * Events are keyed by log_name then event name so identically named events in different logs
 * (for example listing_published) keep separate authorization. Raw descriptions and property
 * payloads are never shown. Missing or malformed store_id never broadens access.
 */
final class OperationalAuditCatalog
{
    public const PER_PAGE = 25;

    /**
     * @var array<string, array<string, array{scope: 'branch'|'global', ability: string, subject?: string, system?: bool, summary: string}>>
     */
    private const LOGS = [
        'store_network' => [
            'listing_published' => ['scope' => 'branch', 'ability' => 'manageReservations', 'subject' => 'listing', 'summary' => 'network_listing_published'],
            'listing_unpublished' => ['scope' => 'branch', 'ability' => 'manageReservations', 'subject' => 'listing', 'summary' => 'network_listing_unpublished'],
            'reservation_requested' => ['scope' => 'branch', 'ability' => 'manageReservations', 'subject' => 'reservation', 'summary' => 'reservation_requested'],
            'reservation_approved' => ['scope' => 'branch', 'ability' => 'manageReservations', 'subject' => 'reservation', 'summary' => 'reservation_approved'],
            'reservation_rejected' => ['scope' => 'branch', 'ability' => 'manageReservations', 'subject' => 'reservation', 'summary' => 'reservation_rejected'],
            'reservation_cancelled' => ['scope' => 'branch', 'ability' => 'manageReservations', 'subject' => 'reservation', 'summary' => 'reservation_cancelled'],
            'reservation_dispatched' => ['scope' => 'branch', 'ability' => 'manageReservations', 'subject' => 'reservation', 'summary' => 'reservation_dispatched'],
            'reservation_received' => ['scope' => 'branch', 'ability' => 'manageReservations', 'subject' => 'reservation', 'summary' => 'reservation_received'],
            'reservation_fulfilled' => ['scope' => 'branch', 'ability' => 'manageReservations', 'subject' => 'reservation', 'summary' => 'reservation_fulfilled'],
            'reservation_claimed' => ['scope' => 'branch', 'ability' => 'manageReservations', 'subject' => 'reservation', 'summary' => 'reservation_claimed'],
            'reservation_claim_released' => ['scope' => 'branch', 'ability' => 'manageReservations', 'subject' => 'reservation', 'summary' => 'reservation_claim_released'],
            'reservation_hold_changed' => ['scope' => 'branch', 'ability' => 'manageReservations', 'subject' => 'store', 'summary' => 'reservation_hold_changed'],
        ],
        'cashbox' => [
            'cash_intake_released' => ['scope' => 'branch', 'ability' => 'manageCashbox', 'subject' => 'cashbox_session', 'summary' => 'cash_intake_released'],
            'cashbox_transfer_released' => ['scope' => 'branch', 'ability' => 'manageCashbox', 'subject' => 'cashbox_session', 'summary' => 'cashbox_transfer_released'],
            'cash_payout_released' => ['scope' => 'branch', 'ability' => 'manageCashbox', 'subject' => 'cashbox_session', 'summary' => 'cash_payout_released'],
            'supplier_cash_payout_released' => ['scope' => 'branch', 'ability' => 'manageCashbox', 'subject' => 'cashbox_session', 'summary' => 'supplier_cash_payout_released'],
        ],
        'checkout_resolution' => [
            'card_approval_voided' => ['scope' => 'branch', 'ability' => 'resolveCheckoutAttempts', 'subject' => 'checkout_attempt', 'summary' => 'card_approval_voided'],
            'terminal_payment_cleared' => ['scope' => 'branch', 'ability' => 'resolveCheckoutAttempts', 'subject' => 'checkout_attempt', 'summary' => 'terminal_payment_cleared'],
        ],
        'selling_price' => [
            'unit_price_set' => ['scope' => 'branch', 'ability' => 'setUnitSellingPrices', 'subject' => 'unit', 'summary' => 'unit_price_set'],
            'definition_price_set' => ['scope' => 'global', 'ability' => 'manage_catalog', 'subject' => 'definition', 'summary' => 'definition_price_set'],
        ],
        'employee_permission' => [
            'pos_access_granted' => ['scope' => 'global', 'ability' => 'manage_stores', 'summary' => 'pos_access_granted'],
            'pos_access_revoked' => ['scope' => 'global', 'ability' => 'manage_stores', 'summary' => 'pos_access_revoked'],
            'pos_discount_granted' => ['scope' => 'global', 'ability' => 'manage_stores', 'summary' => 'pos_discount_granted'],
            'pos_discount_changed' => ['scope' => 'global', 'ability' => 'manage_stores', 'summary' => 'pos_discount_changed'],
            'pos_discount_revoked' => ['scope' => 'global', 'ability' => 'manage_stores', 'summary' => 'pos_discount_revoked'],
        ],
        'store_management' => [
            'store_created' => ['scope' => 'branch', 'ability' => 'manage_stores', 'subject' => 'store', 'summary' => 'store_created'],
            'store_updated' => ['scope' => 'branch', 'ability' => 'manage_stores', 'subject' => 'store', 'summary' => 'store_updated'],
        ],
        'store_assignment' => [
            'employee_assigned' => ['scope' => 'branch', 'ability' => 'manage_stores', 'subject' => 'store', 'summary' => 'employee_assigned'],
            'employee_removed' => ['scope' => 'branch', 'ability' => 'manage_stores', 'subject' => 'store', 'summary' => 'employee_removed'],
        ],
        'storefront' => [
            'device_allocated' => ['scope' => 'branch', 'ability' => 'allocateStorefrontStock', 'subject' => 'unit', 'summary' => 'device_allocated'],
            'device_withdrawn' => ['scope' => 'branch', 'ability' => 'allocateStorefrontStock', 'subject' => 'unit', 'summary' => 'device_withdrawn'],
            'allocation_set' => ['scope' => 'branch', 'ability' => 'allocateStorefrontStock', 'subject' => 'definition_store', 'summary' => 'allocation_set'],
            'storefront_order_submitted' => ['scope' => 'branch', 'ability' => 'manageStorefrontOrders', 'subject' => 'storefront_order', 'summary' => 'storefront_order_submitted'],
            'storefront_order_confirmed' => ['scope' => 'branch', 'ability' => 'manageStorefrontOrders', 'subject' => 'storefront_order', 'summary' => 'storefront_order_confirmed'],
            'storefront_order_rejected' => ['scope' => 'branch', 'ability' => 'manageStorefrontOrders', 'subject' => 'storefront_order', 'summary' => 'storefront_order_rejected'],
            'storefront_order_cancelled' => ['scope' => 'branch', 'ability' => 'manageStorefrontOrders', 'subject' => 'storefront_order', 'summary' => 'storefront_order_cancelled'],
            'storefront_order_price_change_requested' => ['scope' => 'branch', 'ability' => 'manageStorefrontOrders', 'subject' => 'storefront_order', 'summary' => 'storefront_order_price_change_requested'],
            'storefront_order_price_accepted' => ['scope' => 'branch', 'ability' => 'manageStorefrontOrders', 'subject' => 'storefront_order', 'summary' => 'storefront_order_price_accepted'],
            'storefront_order_expired' => ['scope' => 'branch', 'ability' => 'manageStorefrontOrders', 'subject' => 'storefront_order', 'system' => true, 'summary' => 'storefront_order_expired'],
            'listing_created' => ['scope' => 'global', 'ability' => 'publish_storefront', 'subject' => 'storefront_listing', 'summary' => 'storefront_listing_created'],
            'listing_linked' => ['scope' => 'global', 'ability' => 'publish_storefront', 'subject' => 'storefront_listing', 'summary' => 'storefront_listing_linked'],
            'listing_unlinked' => ['scope' => 'global', 'ability' => 'publish_storefront', 'subject' => 'storefront_listing', 'summary' => 'storefront_listing_unlinked'],
            'listing_published' => ['scope' => 'global', 'ability' => 'publish_storefront', 'subject' => 'storefront_listing', 'summary' => 'storefront_listing_published'],
            'listing_withdrawn' => ['scope' => 'global', 'ability' => 'publish_storefront', 'subject' => 'storefront_listing', 'summary' => 'storefront_listing_withdrawn'],
        ],
    ];

    /**
     * @return list<string>
     */
    public static function logNames(): array
    {
        return array_keys(self::LOGS);
    }

    /**
     * @return array{scope: 'branch'|'global', ability: string, subject?: string, system?: bool, summary: string}|null
     */
    public static function definition(string $logName, string $event): ?array
    {
        return self::LOGS[$logName][$event] ?? null;
    }

    public static function isSystemGenerated(string $logName, string $event): bool
    {
        return (self::definition($logName, $event)['system'] ?? false) === true;
    }

    /**
     * @return list<array{log: string, event: string, summary: string}>
     */
    public static function visibleEventChoices(User $user): array
    {
        $stores = Store::query()->accessibleTo($user)->get();
        $policy = app(StorePolicy::class);
        $choices = [];

        foreach (self::LOGS as $logName => $events) {
            foreach ($events as $event => $definition) {
                if (! self::definitionVisible($user, $stores, $policy, $definition)) {
                    continue;
                }

                $choices[] = [
                    'log' => $logName,
                    'event' => $event,
                    'summary' => __('reports.audit.events.'.$definition['summary']),
                ];
            }
        }

        return $choices;
    }

    public static function userCanAccessAny(User $user): bool
    {
        return self::visibleEventChoices($user) !== [];
    }

    /**
     * @return Collection<int, Store>
     */
    public static function visibleStores(User $user): Collection
    {
        $stores = Store::query()->accessibleTo($user)->orderBy('code')->get();
        $policy = app(StorePolicy::class);

        return $stores->filter(function (Store $store) use ($user, $policy): bool {
            foreach (self::LOGS as $events) {
                foreach ($events as $definition) {
                    if ($definition['scope'] === 'branch' && self::allowsBranch($user, $store, $definition['ability'], $policy)) {
                        return true;
                    }
                }
            }

            return false;
        })->values();
    }

    /**
     * @param  Builder<Activity>  $query
     * @return Builder<Activity>
     */
    public static function restrictQuery(Builder $query, User $user): Builder
    {
        $stores = Store::query()->accessibleTo($user)->get();
        $policy = app(StorePolicy::class);
        $clauses = [];

        foreach (self::LOGS as $logName => $events) {
            foreach ($events as $event => $definition) {
                if ($definition['scope'] === 'global') {
                    if (self::allowsGlobal($user, $definition['ability'])) {
                        $clauses[] = ['log' => $logName, 'event' => $event, 'scope' => 'global', 'store_ids' => []];
                    }

                    continue;
                }

                $storeIds = $stores
                    ->filter(fn (Store $store): bool => self::allowsBranch($user, $store, $definition['ability'], $policy))
                    ->modelKeys();

                if ($storeIds !== []) {
                    $clauses[] = ['log' => $logName, 'event' => $event, 'scope' => 'branch', 'store_ids' => $storeIds];
                }
            }
        }

        return $query->whereIn('log_name', self::logNames())->where(function (Builder $visible) use ($clauses): void {
            if ($clauses === []) {
                $visible->whereRaw('0 = 1');

                return;
            }

            foreach ($clauses as $clause) {
                $visible->orWhere(function (Builder $row) use ($clause): void {
                    $row->where('log_name', $clause['log'])->where('event', $clause['event']);

                    if ($clause['scope'] === 'global') {
                        $row->where(function (Builder $props): void {
                            $props->whereNull('properties')
                                ->orWhereNull('properties->store_id')
                                ->orWhere('properties->store_id', '');
                        });

                        return;
                    }

                    $row->where(function (Builder $store) use ($clause): void {
                        foreach ($clause['store_ids'] as $storeId) {
                            $store->orWhere('properties->store_id', $storeId);
                        }
                    });
                });
            }
        });
    }

    /**
     * @param  Collection<int, Store>  $storesById
     * @return array{
     *     id: int,
     *     created_at: Carbon|null,
     *     actor: string,
     *     actor_is_system: bool,
     *     branch: ?string,
     *     area: string,
     *     summary: string,
     *     link_url: ?string,
     *     link_label: ?string
     * }
     */
    public static function present(Activity $activity, User $user, Collection $storesById): array
    {
        $logName = (string) $activity->log_name;
        $event = (string) $activity->event;
        $definition = self::definition($logName, $event) ?? [
            'summary' => 'unknown',
            'scope' => 'branch',
            'ability' => '',
        ];
        $properties = $activity->properties instanceof Collection
            ? $activity->properties
            : collect($activity->properties ?? []);
        $storeId = self::numericId($properties->get('store_id'));
        $store = $storeId === null ? null : $storesById->get($storeId);

        return [
            'id' => $activity->id,
            'created_at' => $activity->created_at,
            'actor' => self::actorLabel($activity, $logName, $event),
            'actor_is_system' => self::isSystemGenerated($logName, $event),
            'branch' => $definition['scope'] === 'global' ? null : $store?->code,
            'area' => $logName,
            'summary' => __('reports.audit.events.'.$definition['summary']),
            ...self::sourceLink($activity, $definition, $user, $store, $properties),
        ];
    }

    /**
     * @param  Collection<int, Store>  $stores
     * @param  array{scope: string, ability: string, subject?: string, system?: bool, summary: string}  $definition
     */
    private static function definitionVisible(User $user, Collection $stores, StorePolicy $policy, array $definition): bool
    {
        if ($definition['scope'] === 'global') {
            return self::allowsGlobal($user, $definition['ability']);
        }

        foreach ($stores as $store) {
            if (self::allowsBranch($user, $store, $definition['ability'], $policy)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{scope: string, ability: string, subject?: string, system?: bool, summary: string}  $definition
     * @return array{link_url: ?string, link_label: ?string}
     */
    private static function sourceLink(Activity $activity, array $definition, User $user, ?Store $store, Collection $properties): array
    {
        return match ($definition['subject'] ?? null) {
            'reservation' => self::reservationLink($activity, $user, $store),
            'listing', 'unit' => self::unitLink($properties, $user, $store),
            'cashbox_session' => self::cashboxSessionLink($properties, $user, $store),
            'store' => self::storeLink($store, $user),
            'definition' => self::catalogLink($user),
            'definition_store' => self::productInventoryLink($properties, $user, $store),
            'storefront_order' => self::storefrontOrdersLink($user, $store),
            'storefront_listing' => $user->can('viewAny', StorefrontListing::class)
                ? ['link_url' => route('subdomain.storefront'), 'link_label' => __('reports.audit.link_storefront')]
                : ['link_url' => null, 'link_label' => null],
            default => ['link_url' => null, 'link_label' => null],
        };
    }

    /**
     * @return array{link_url: ?string, link_label: ?string}
     */
    private static function reservationLink(Activity $activity, User $user, ?Store $store): array
    {
        if ($store === null || ! $user->can('manageReservations', $store)) {
            return ['link_url' => null, 'link_label' => null];
        }

        $reservation = in_array($activity->subject_type, [NetworkReservation::MORPH_ALIAS, NetworkReservation::class], true)
            ? NetworkReservation::query()->find($activity->subject_id)
            : null;

        if ($reservation === null) {
            return ['link_url' => null, 'link_label' => null];
        }

        $tab = $reservation->owning_store_id === $store->id ? 'incoming' : 'outgoing';

        return [
            'link_url' => route('pos.reservations', [$store, 'tab' => $tab]),
            'link_label' => __('reports.audit.link_reservation'),
        ];
    }

    /**
     * @return array{link_url: ?string, link_label: ?string}
     */
    private static function unitLink(Collection $properties, User $user, ?Store $store): array
    {
        $unitId = self::numericId($properties->get('inventory_unit_id'));

        if ($unitId === null) {
            return ['link_url' => null, 'link_label' => null];
        }

        $unit = InventoryUnit::query()->find($unitId);

        if ($unit === null || ! $user->can('viewInventory', $unit->store)) {
            return ['link_url' => null, 'link_label' => null];
        }

        // Prefer the acting branch when it still holds the device; otherwise open the current branch.
        $linkStore = ($store !== null && $unit->store_id === $store->id) ? $store : $unit->store;

        return [
            'link_url' => route('subdomain.inventory.unit', [$linkStore, $unit]),
            'link_label' => __('reports.audit.link_device'),
        ];
    }

    /**
     * @return array{link_url: ?string, link_label: ?string}
     */
    private static function cashboxSessionLink(Collection $properties, User $user, ?Store $store): array
    {
        $sessionId = self::numericId($properties->get('cashbox_session_id'));

        if ($sessionId === null || $store === null || ! $user->can('manageCashbox', $store)) {
            return ['link_url' => null, 'link_label' => null];
        }

        if (! CashboxSession::query()->whereKey($sessionId)->where('store_id', $store->id)->exists()) {
            return ['link_url' => null, 'link_label' => null];
        }

        return [
            'link_url' => route('pos.cashbox.session', [$store, $sessionId]),
            'link_label' => __('reports.audit.link_cashbox_session'),
        ];
    }

    /**
     * @return array{link_url: ?string, link_label: ?string}
     */
    private static function storeLink(?Store $store, User $user): array
    {
        if ($store === null || ! $user->can('view', $store)) {
            return ['link_url' => null, 'link_label' => null];
        }

        return [
            'link_url' => route('subdomain.stores.show', $store),
            'link_label' => __('reports.audit.link_store'),
        ];
    }

    /**
     * @return array{link_url: ?string, link_label: ?string}
     */
    private static function catalogLink(User $user): array
    {
        if (! $user->can('viewAny', ProductDefinition::class)) {
            return ['link_url' => null, 'link_label' => null];
        }

        return [
            'link_url' => route('subdomain.catalog.definitions'),
            'link_label' => __('reports.audit.link_catalog'),
        ];
    }

    /**
     * @return array{link_url: ?string, link_label: ?string}
     */
    private static function productInventoryLink(Collection $properties, User $user, ?Store $store): array
    {
        $definitionId = self::numericId($properties->get('product_definition_id'));

        if ($definitionId === null || $store === null || ! $user->can('viewInventory', $store)) {
            return ['link_url' => null, 'link_label' => null];
        }

        if (! ProductDefinition::query()->whereKey($definitionId)->exists()) {
            return ['link_url' => null, 'link_label' => null];
        }

        return [
            'link_url' => route('subdomain.inventory.product', [$store, $definitionId]),
            'link_label' => __('reports.audit.link_inventory'),
        ];
    }

    /**
     * @return array{link_url: ?string, link_label: ?string}
     */
    private static function storefrontOrdersLink(User $user, ?Store $store): array
    {
        if ($store === null || ! $user->can('manageStorefrontOrders', $store)) {
            return ['link_url' => null, 'link_label' => null];
        }

        return [
            'link_url' => route('pos.storefront-orders', $store),
            'link_label' => __('reports.audit.link_storefront_orders'),
        ];
    }

    private static function actorLabel(Activity $activity, string $logName, string $event): string
    {
        if (self::isSystemGenerated($logName, $event) && $activity->causer_id === null) {
            return __('reports.audit.system_actor');
        }

        if ($activity->causer_id === null) {
            return __('reports.audit.no_actor');
        }

        return $activity->causer?->name ?? __('reports.audit.no_actor');
    }

    private static function allowsBranch(User $user, Store $store, string $ability, StorePolicy $policy): bool
    {
        return match ($ability) {
            'manageReservations' => $policy->manageReservations($user, $store),
            'manageCashbox' => $policy->manageCashbox($user, $store),
            'resolveCheckoutAttempts' => $policy->resolveCheckoutAttempts($user, $store),
            'setUnitSellingPrices' => $policy->setUnitSellingPrices($user, $store),
            'allocateStorefrontStock' => $policy->allocateStorefrontStock($user, $store),
            'manageStorefrontOrders' => $policy->manageStorefrontOrders($user, $store),
            'manage_stores' => $policy->view($user, $store),
            default => false,
        };
    }

    private static function allowsGlobal(User $user, string $ability): bool
    {
        return match ($ability) {
            'manage_catalog' => $user->can('viewAny', ProductDefinition::class),
            'manage_stores' => $user->can('managePosAccess', Store::class),
            'publish_storefront' => $user->can('viewAny', StorefrontListing::class),
            default => false,
        };
    }

    private static function numericId(mixed $value): ?int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value) && (int) $value > 0) {
            return (int) $value;
        }

        return null;
    }
}
