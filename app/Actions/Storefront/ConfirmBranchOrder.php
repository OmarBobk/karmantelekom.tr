<?php

declare(strict_types=1);

namespace App\Actions\Storefront;

use App\Actions\Inventory\InventoryLedger;
use App\Actions\Network\ListingEligibility;
use App\Actions\Network\ReservationLocks;
use App\Actions\Pos\FinalizeCheckout;
use App\Enums\ReservationStatus;
use App\Enums\StorefrontChannel;
use App\Enums\StorefrontOrderStatus;
use App\Models\InventoryBalance;
use App\Models\InventoryUnit;
use App\Models\NetworkReservation;
use App\Models\Product;
use App\Models\ProductDefinition;
use App\Models\Store;
use App\Models\StorefrontAllocation;
use App\Models\StorefrontListing;
use App\Models\StorefrontOrder;
use App\Models\StorefrontOrderLine;
use App\Models\User;
use App\Services\Idempotency;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Confirm a pending storefront order at its branch and hold its stock, in one transaction.
 *
 * Lock order: the order and its lines, product definitions (ascending), the branch's quantity
 * balances, the chosen devices (ascending), their Store Network reservations, the storefront
 * products (shared, ascending) and their listings (shared), then other orders' holds; the same
 * order counter payment starts and finalization take, and linking also locks the definition,
 * then the product, then the listing. Under those locks the storefront product must still be
 * active and linked to the line's definition, and the listing published to the order's channel,
 * which the buyer must still be allowed to see; each quantity must fit the online allocation and
 * the balance less what other orders hold and what counter checkouts that started payment are
 * selling; and each chosen device must be allocated, in the branch, available, priced, and
 * neither reserved, held, nor being paid for at the counter. Every line must still cost its
 * agreed price in the order's channel (StorefrontPricing); a different price first needs the
 * buyer's acceptance of a new proposal.
 *
 * The pickup window starts now. Repeating the same confirmation returns the order unchanged.
 */
final class ConfirmBranchOrder
{
    public function __construct(private readonly InventoryLedger $inventoryLedger) {}

    /**
     * @param  array<int|string, mixed>  $deviceChoices  Chosen device id by order line id, for device lines.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $actor, Store $store, int $storefrontOrderId, array $deviceChoices, mixed $pickupHours): StorefrontOrder
    {
        Gate::forUser($actor)->authorize('manageStorefrontOrders', $store);

        $order = StorefrontOrder::query()->where('store_id', $store->id)->findOrFail($storefrontOrderId);
        $hours = filter_var($pickupHours, FILTER_VALIDATE_INT);

        if ($hours === false || $hours < 1 || $hours > StorefrontOrder::MAX_PICKUP_HOURS) {
            throw ValidationException::withMessages(['pickup_hours' => __('storefront.orders.errors.pickup_hours', ['max' => StorefrontOrder::MAX_PICKUP_HOURS])]);
        }

        $choices = collect($deviceChoices)
            ->mapWithKeys(fn (mixed $unitId, int|string $lineId): array => [(int) $lineId => filter_var($unitId, FILTER_VALIDATE_INT) ?: null])
            ->sortKeys();
        $fingerprint = Idempotency::fingerprint(['operation' => 'confirm_branch_order', 'order' => $order->id, 'devices' => $choices->all(), 'hours' => $hours]);
        $channels = StorefrontChannel::audienceOf($order->buyer()->firstOrFail());

        return DB::transaction(fn (): StorefrontOrder => $this->confirm($actor, $store, $order->id, $choices, $hours, $fingerprint, $channels), FinalizeCheckout::DEADLOCK_ATTEMPTS);
    }

    /**
     * @param  Collection<int, int|null>  $choices
     * @param  list<StorefrontChannel>  $channels
     *
     * @throws ValidationException
     */
    private function confirm(User $actor, Store $store, int $orderId, Collection $choices, int $hours, string $fingerprint, array $channels): StorefrontOrder
    {
        $order = StorefrontOrder::query()->whereKey($orderId)->lockForUpdate()->firstOrFail();

        if ($order->isConfirmed() && $order->confirmation_fingerprint === $fingerprint) {
            return $order;
        }

        if (! $order->isPending()) {
            throw ValidationException::withMessages(['order' => __('storefront.orders.errors.not_pending', ['status' => $order->displayStatus()->label()])]);
        }

        if ($order->awaitsPriceAcceptance()) {
            throw ValidationException::withMessages(['order' => __('storefront.orders.errors.awaiting_buyer')]);
        }

        /** @var Collection<int, StorefrontOrderLine> $lines */
        $lines = StorefrontOrderLine::query()->where('storefront_order_id', $order->id)->orderBy('id')->lockForUpdate()->get();
        $deviceLines = $lines->filter(fn (StorefrontOrderLine $line): bool => $line->tracksUnits());
        $quantityLines = $lines->reject(fn (StorefrontOrderLine $line): bool => $line->tracksUnits());
        $chosen = $deviceLines->mapWithKeys(fn (StorefrontOrderLine $line): array => [$line->id => $choices->get($line->id) ?? $line->proposed_unit_id]);

        if ($chosen->contains(null) || $chosen->unique()->count() !== $chosen->count()) {
            throw ValidationException::withMessages(['devices' => __('storefront.orders.errors.choose_devices')]);
        }

        if ($order->accepted_price_proposal !== null
            && $deviceLines->contains(fn (StorefrontOrderLine $line): bool => $line->proposed_unit_id !== null && $chosen->get($line->id) !== $line->proposed_unit_id)) {
            throw ValidationException::withMessages(['devices' => __('storefront.orders.errors.accepted_devices')]);
        }

        $definitions = $this->inventoryLedger->lockDefinitions($lines->pluck('product_definition_id')->all());
        $quantityIds = $quantityLines->pluck('product_definition_id')->unique()->sort()->values()->all();
        $balances = InventoryBalance::query()->where('store_id', $store->id)->whereIn('product_definition_id', $quantityIds)
            ->orderBy('product_definition_id')->lockForUpdate()->get()->keyBy('product_definition_id');
        $unitIds = $chosen->values()->sort()->values()->all();
        $units = InventoryUnit::query()->whereKey($unitIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $reservations = NetworkReservation::query()->whereIn('inventory_unit_id', $unitIds)
            ->whereIn('status', [ReservationStatus::PENDING, ReservationStatus::APPROVED])->orderBy('id')->lockForUpdate()->get();
        $productIds = $lines->pluck('product_id')->unique()->sort()->values();
        $products = Product::query()->whereKey($productIds)->orderBy('id')->sharedLock()->get(['id', 'is_active'])->keyBy('id');
        $listings = StorefrontListing::query()->whereIn('product_id', $productIds)->sharedLock()->get()->keyBy('product_id');
        $allocations = StorefrontAllocation::query()->where('store_id', $store->id)->whereIn('product_definition_id', $definitions->keys())->sharedLock()->get();
        $heldElsewhere = BranchOrderHolds::heldQuantities($store->id, $quantityIds, $order->id, lock: true);
        $unitsHeldElsewhere = BranchOrderHolds::unitHolds($unitIds, lock: true);
        $expiredUnitHolds = StorefrontOrderLine::query()->whereIn('active_unit_id', $unitIds)->whereKeyNot($unitsHeldElsewhere->pluck('id')->all())->lockForUpdate()->get();

        $startedQuantities = BranchOrderHolds::paymentStartedQuantities($store->id, $quantityIds);
        $errors = [];

        foreach ($lines as $line) {
            $definition = $definitions->get($line->product_definition_id);
            $listing = $listings->get($line->product_id);
            $name = $definition?->name ?? '';

            if ($definition === null || ! $definition->is_active || ! ($products->get($line->product_id)?->is_active) || $listing === null
                || $listing->product_definition_id !== $definition->id || ! in_array($order->channel, $channels, true) || ! $listing->isPublishedTo($order->channel)) {
                $errors["lines.{$line->id}"] = __('storefront.orders.errors.not_published', ['name' => $name]);

                continue;
            }

            $unit = $line->tracksUnits() ? $units->get($chosen->get($line->id)) : null;
            $problem = $line->tracksUnits()
                ? $this->deviceProblem($store, $definition, $unit, $allocations, $reservations, $unitsHeldElsewhere)
                : $this->quantityProblem($line, $quantityLines, $allocations, $balances, $heldElsewhere, $startedQuantities);
            $price = StorefrontPricing::itemPriceMinor($line->product_id, $definition, $unit, $order->channel, (int) $lines->where('product_id', $line->product_id)->sum('quantity'));

            if ($problem === null && $price === null) {
                $problem = __('storefront.orders.errors.price_unavailable');
            } elseif ($problem === null && $price !== $line->unitPriceMinor()) {
                $problem = __('storefront.orders.errors.price_differs');
            }

            if ($problem !== null) {
                $errors["lines.{$line->id}"] = "{$name}: {$problem}";
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        if ($expiredUnitHolds->isNotEmpty()) {
            StorefrontOrderLine::query()->whereKey($expiredUnitHolds->modelKeys())->update(['active_unit_id' => null]);
        }

        $now = now();
        $expiresAt = $now->copy()->addHours($hours);

        foreach ($lines as $line) {
            $line->update($line->tracksUnits()
                ? ['inventory_unit_id' => $chosen->get($line->id), 'active_unit_id' => $chosen->get($line->id), 'hold_expires_at' => $expiresAt]
                : ['held_quantity' => $line->quantity, 'hold_expires_at' => $expiresAt]);
        }

        $order->update([
            'status' => StorefrontOrderStatus::CONFIRMED,
            'confirmation_fingerprint' => $fingerprint,
            'confirmed_by' => $actor->id,
            'confirmed_at' => $now,
            'pickup_hours' => $hours,
            'expires_at' => $expiresAt,
        ]);
        $order->order()->update(['status' => StorefrontOrderStatus::CONFIRMED->orderStatus()]);

        BranchOrderHolds::audit($order, $actor, 'storefront_order_confirmed', "confirmed by {$store->code}", ['expires_at' => $expiresAt->toIso8601String(), 'pickup_hours' => $hours]);

        return $order;
    }

    /**
     * @param  Collection<int, StorefrontOrderLine>  $quantityLines
     * @param  Collection<int, StorefrontAllocation>  $allocations
     * @param  Collection<int, InventoryBalance>  $balances
     * @param  array<int, int>  $heldElsewhere
     * @param  array<int, int>  $startedQuantities
     */
    private function quantityProblem(StorefrontOrderLine $line, Collection $quantityLines, Collection $allocations, Collection $balances, array $heldElsewhere, array $startedQuantities): ?string
    {
        $definitionId = $line->product_definition_id;
        $requested = $quantityLines->where('product_definition_id', $definitionId)->sum('quantity');
        $held = $heldElsewhere[$definitionId] ?? 0;
        $allocation = (int) ($allocations->first(fn (StorefrontAllocation $allocation): bool => $allocation->product_definition_id === $definitionId && $allocation->inventory_unit_id === null)?->quantity ?? 0);
        $sellable = (int) ($balances->get($definitionId)?->quantity ?? 0) - $held - ($startedQuantities[$definitionId] ?? 0);

        return match (true) {
            $allocation - $held < $requested => __('storefront.orders.errors.allocation_short', ['available' => max(0, $allocation - $held)]),
            $sellable < $requested => __('storefront.orders.errors.stock_unavailable', ['available' => max(0, $sellable)]),
            default => null,
        };
    }

    /**
     * Call after paymentStartedQuantities(): ReservationLocks::paymentStartedFor() must come
     * after the locks and before other consistent reads.
     *
     * @param  Collection<int, StorefrontAllocation>  $allocations
     * @param  Collection<int, NetworkReservation>  $reservations
     * @param  Collection<int, StorefrontOrderLine>  $unitsHeldElsewhere
     */
    private function deviceProblem(Store $store, ProductDefinition $definition, ?InventoryUnit $unit, Collection $allocations, Collection $reservations, Collection $unitsHeldElsewhere): ?string
    {
        if ($unit === null || $unit->store_id !== $store->id || $unit->product_definition_id !== $definition->id) {
            return __('storefront.orders.errors.device_not_here');
        }

        $allocated = $allocations->contains(fn (StorefrontAllocation $allocation): bool => $allocation->inventory_unit_id === $unit->id && $allocation->quantity > 0);
        $ineligible = ListingEligibility::problem($definition, $unit);

        return match (true) {
            ! $allocated => __('storefront.orders.errors.device_not_allocated'),
            $ineligible !== null => $ineligible,
            $reservations->contains(fn (NetworkReservation $reservation): bool => $reservation->inventory_unit_id === $unit->id && $reservation->blocksSale()) => __('storefront.orders.errors.device_reserved'),
            $unitsHeldElsewhere->has($unit->id) => __('storefront.orders.errors.device_held'),
            ReservationLocks::paymentStartedFor($unit) => __('storefront.orders.errors.payment_started'),
            default => null,
        };
    }
}
