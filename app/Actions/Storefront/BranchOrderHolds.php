<?php

declare(strict_types=1);

namespace App\Actions\Storefront;

use App\Enums\CheckoutAttemptStatus;
use App\Enums\StorefrontOrderStatus;
use App\Models\CheckoutAttempt;
use App\Models\InventoryBalance;
use App\Models\Sale;
use App\Models\StorefrontOrder;
use App\Models\StorefrontOrderLine;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Stock held by confirmed storefront orders, and the checks that keep counter sales, Store
 * Network reservations, and storefront availability from using it.
 *
 * Holds live on order lines, which every stock check locks last: after the order, product
 * definitions, balances, devices, and Store Network reservations, the order counter checkout
 * and Store Network already use. A branch confirmation takes the same locks before it writes
 * holds, so whichever commits first wins. A counter checkout linked to the order may sell the
 * stock it holds; when it starts payment it claims the holds, which then outlast the pickup
 * deadline until the sale completes or its funds are resolved.
 */
final class BranchOrderHolds
{
    /**
     * Whether storefront orders for branch stock are switched on.
     */
    public static function enabled(): bool
    {
        return (bool) config('storefront.branch_orders');
    }

    /**
     * Quantity held now per product definition at a branch, by orders other than the one given.
     * With $lock, a locking read for use under the balance locks.
     *
     * @param  list<int>  $definitionIds
     * @return array<int, int>
     */
    public static function heldQuantities(int $storeId, array $definitionIds, ?int $exceptOrderId = null, bool $lock = false): array
    {
        if ($definitionIds === []) {
            return [];
        }

        $lines = StorefrontOrderLine::query()
            ->where('store_id', $storeId)
            ->whereIn('product_definition_id', $definitionIds)
            ->where('held_quantity', '>', 0)
            ->tap(fn (EloquentBuilder $query) => StorefrontOrderLine::whereHolding($query))
            ->when($exceptOrderId !== null, fn (EloquentBuilder $query) => $query->where('storefront_order_id', '!=', $exceptOrderId))
            ->orderBy('id')
            ->when($lock, fn (EloquentBuilder $query) => $query->lockForUpdate())
            ->get(['id', 'product_definition_id', 'held_quantity']);

        $held = [];

        foreach ($lines as $line) {
            $held[$line->product_definition_id] = ($held[$line->product_definition_id] ?? 0) + $line->held_quantity;
        }

        return $held;
    }

    /**
     * Lines holding any of the given devices now, keyed by device id. With $lock, a locking
     * read for use under the device locks.
     *
     * @param  list<int>  $unitIds
     * @return Collection<int, StorefrontOrderLine>
     */
    public static function unitHolds(array $unitIds, bool $lock = false): Collection
    {
        if ($unitIds === []) {
            return new Collection;
        }

        return StorefrontOrderLine::query()
            ->whereIn('active_unit_id', $unitIds)
            ->tap(fn (EloquentBuilder $query) => StorefrontOrderLine::whereHolding($query))
            ->orderBy('id')
            ->when($lock, fn (EloquentBuilder $query) => $query->lockForUpdate())
            ->get()
            ->keyBy('active_unit_id');
    }

    /**
     * Restrict a device query to devices no storefront order holds; the SQL form of unitHolds().
     */
    public static function whereUnitNotHeld(EloquentBuilder|QueryBuilder $query, string $unitColumn): void
    {
        $query->whereNotExists(fn (QueryBuilder $held) => $held->selectRaw('1')
            ->from('storefront_order_lines')
            ->whereColumn('storefront_order_lines.active_unit_id', $unitColumn)
            ->tap(fn (QueryBuilder $lines) => StorefrontOrderLine::whereHolding($lines)));
    }

    /**
     * Quantity of each product in open counter checkouts at the branch that have started payment
     * and are not collecting a storefront order (whose stock its hold already counts).
     *
     * Call under the balance locks and before any other non-locking read in the transaction.
     * Payment starts take the same balance locks before freezing, so this first consistent
     * read sees every start that committed before the locks were granted, and a later start
     * sees the new hold through its own locking read.
     *
     * @param  list<int>  $definitionIds
     * @return array<int, int>
     */
    public static function paymentStartedQuantities(int $storeId, array $definitionIds): array
    {
        $started = [];

        $attempts = CheckoutAttempt::query()
            ->where('store_id', $storeId)
            ->where('status', CheckoutAttemptStatus::OPEN)
            ->whereNotNull('frozen_at')
            ->whereNull('storefront_order_id')
            ->get(['id', 'lines']);

        foreach ($attempts as $attempt) {
            foreach ($attempt->lines as $line) {
                $definitionId = (int) $line['definition_id'];

                if ($line['unit_id'] === null && in_array($definitionId, $definitionIds, true)) {
                    $started[$definitionId] = ($started[$definitionId] ?? 0) + (int) $line['quantity'];
                }
            }
        }

        return $started;
    }

    /**
     * Unlocked check when a counter cart is reviewed: devices held for another order, and the
     * linked order's state and contents. Returns the error to show, or null. Quantities are
     * checked against held stock with the balance in ReviewCheckout.
     *
     * @param  list<array<string, mixed>>  $lines
     */
    public static function reviewProblem(int $storeId, array $lines, ?int $storefrontOrderId): ?string
    {
        $order = null;

        if ($storefrontOrderId !== null) {
            $order = StorefrontOrder::query()->with('lines')->find($storefrontOrderId);
            $problem = $order === null ? __('storefront.orders.errors.link_invalid') : self::linkProblem($order, $storeId, $lines, null);

            if ($problem !== null) {
                return $problem;
            }
        }

        $unitIds = collect($lines)->pluck('unit_id')->filter()->map(fn ($id): int => (int) $id)->values()->all();
        $heldForOthers = self::unitHolds($unitIds)->first(fn (StorefrontOrderLine $line): bool => $line->storefront_order_id !== $order?->id);

        return $heldForOthers === null ? null : __('storefront.orders.errors.device_held');
    }

    /**
     * The order's agreed unit prices in minor units, keyed like counter cart lines: "d{definition}"
     * for quantity stock and "u{device}" for a device. A collecting checkout charges these.
     *
     * @return array<string, int>
     */
    public static function agreedPrices(StorefrontOrder $order): array
    {
        return $order->lines->mapWithKeys(fn (StorefrontOrderLine $line): array => [
            ($line->tracksUnits() ? 'u'.$line->inventory_unit_id : 'd'.$line->product_definition_id) => $line->unitPriceMinor(),
        ])->all();
    }

    /**
     * Under the attempt lock, lock the storefront order the checkout collects, before any stock lock.
     */
    public static function lockLinkedOrder(CheckoutAttempt $lockedAttempt): ?StorefrontOrder
    {
        if ($lockedAttempt->storefront_order_id === null) {
            return null;
        }

        return StorefrontOrder::query()->whereKey($lockedAttempt->storefront_order_id)->lockForUpdate()->first()
            ?? throw ValidationException::withMessages(['storefront_order' => __('storefront.orders.errors.link_invalid')]);
    }

    /**
     * Under the definition, balance, device, and reservation locks: refuse stock that confirmed
     * orders hold for others, and check the linked order still matches the checkout. A short
     * branch refuses every order holding the product until counts are restored or holds released.
     *
     * @param  Collection<int, InventoryBalance>  $balances  Locked balances keyed by product definition.
     *
     * @throws ValidationException
     */
    public static function assertSellable(CheckoutAttempt $lockedAttempt, ?StorefrontOrder $lockedOrder, Collection $balances): void
    {
        /** @var list<array<string, mixed>> $lines */
        $lines = $lockedAttempt->lines;

        if ($lockedOrder !== null) {
            $lockedOrder->setRelation('lines', $lockedOrder->lines()->lockForUpdate()->get());
            $problem = self::linkProblem($lockedOrder, $lockedAttempt->store_id, $lines, $lockedAttempt->id);

            if ($problem !== null) {
                throw ValidationException::withMessages(['storefront_order' => $problem]);
            }
        }

        $requested = [];
        $names = [];

        foreach ($lines as $line) {
            if ($line['unit_id'] === null) {
                $requested[(int) $line['definition_id']] = ($requested[(int) $line['definition_id']] ?? 0) + (int) $line['quantity'];
                $names[(int) $line['definition_id']] = $line['name'];
            }
        }

        $held = self::heldQuantities($lockedAttempt->store_id, array_keys($requested), $lockedOrder?->id, lock: true);
        $errors = [];

        foreach ($requested as $definitionId => $quantity) {
            $sellable = (int) ($balances->get($definitionId)?->quantity ?? 0) - ($held[$definitionId] ?? 0);

            if ($sellable < $quantity) {
                $errors[] = $lockedOrder !== null
                    ? __('storefront.orders.errors.stock_short', ['name' => $names[$definitionId]])
                    : __('storefront.orders.errors.quantity_held', ['name' => $names[$definitionId], 'available' => max(0, $sellable)]);
            }
        }

        $unitHeld = self::unitHolds($lockedAttempt->unitIds(), lock: true)
            ->contains(fn (StorefrontOrderLine $line): bool => $line->storefront_order_id !== $lockedOrder?->id);

        if ($unitHeld) {
            $errors[] = __('storefront.orders.errors.device_held');
        }

        if ($errors !== []) {
            throw ValidationException::withMessages(['storefront_order' => $errors]);
        }
    }

    /**
     * When a linked checkout starts payment, claim the order's holds for it. Call after
     * assertSellable() in the same transaction; a repeated start keeps the existing claim.
     */
    public static function claim(StorefrontOrder $lockedOrder, CheckoutAttempt $lockedAttempt, User $actor): void
    {
        $unclaimed = $lockedOrder->lines->filter(fn (StorefrontOrderLine $line): bool => $line->claimed_attempt_id === null);

        if ($unclaimed->isEmpty()) {
            return;
        }

        StorefrontOrderLine::query()->whereKey($unclaimed->modelKeys())->update(['claimed_attempt_id' => $lockedAttempt->id, 'claimed_at' => now()]);

        self::audit($lockedOrder, $actor, 'storefront_order_claimed', "payment started by checkout {$lockedAttempt->key}", ['checkout_attempt_id' => $lockedAttempt->id]);
    }

    /**
     * Release holds claimed by this checkout once its funds are resolved. Call inside that
     * resolution's transaction, after the attempt lock. A deadline that passed meanwhile now
     * releases the stock.
     */
    public static function releaseClaim(CheckoutAttempt $lockedAttempt, User $actor): void
    {
        if ($lockedAttempt->storefront_order_id === null) {
            return;
        }

        $order = StorefrontOrder::query()->whereKey($lockedAttempt->storefront_order_id)->lockForUpdate()->first();
        $released = StorefrontOrderLine::query()->where('claimed_attempt_id', $lockedAttempt->id)->update(['claimed_attempt_id' => null, 'claimed_at' => null]);

        if ($order !== null && $released > 0) {
            self::audit($order, $actor, 'storefront_order_claim_released', "payment claim of checkout {$lockedAttempt->key} released", ['checkout_attempt_id' => $lockedAttempt->id]);
        }
    }

    /**
     * Close the linked order with the completed sale, consuming its holds, inside the finalize
     * transaction.
     */
    public static function fulfill(StorefrontOrder $lockedOrder, Sale $sale, User $actor): void
    {
        StorefrontOrderLine::query()->where('storefront_order_id', $lockedOrder->id)->update([
            'held_quantity' => 0,
            'active_unit_id' => null,
            'claimed_attempt_id' => null,
            'claimed_at' => null,
        ]);

        $lockedOrder->update([
            'status' => StorefrontOrderStatus::FULFILLED,
            'sale_id' => $sale->id,
            'fulfilled_at' => $sale->completed_at,
        ]);
        $lockedOrder->order()->update(['status' => StorefrontOrderStatus::FULFILLED->orderStatus()]);

        self::audit($lockedOrder, $actor, 'storefront_order_fulfilled', "collected with sale {$sale->number}", ['sale_id' => $sale->id]);
    }

    /**
     * Record one order transition in the storefront audit log.
     *
     * @param  array<string, mixed>  $properties
     */
    public static function audit(StorefrontOrder $order, User $actor, string $event, string $description, array $properties = []): void
    {
        activity('storefront')
            ->performedOn($order)
            ->causedBy($actor)
            ->event($event)
            ->withProperties([
                'store_id' => $order->store_id,
                'order_id' => $order->order_id,
                'status' => $order->status->value,
                ...$properties,
            ])
            ->log("Storefront order #{$order->order_id}: {$description}");
    }

    /**
     * Why a checkout cannot collect this order, or null. The checkout must hold exactly the
     * order's products, quantities, and devices at their agreed prices, without adjustments.
     *
     * @param  list<array<string, mixed>>  $lines
     */
    private static function linkProblem(StorefrontOrder $order, int $storeId, array $lines, ?int $attemptId): ?string
    {
        if ($order->store_id !== $storeId || ! $order->isConfirmed()) {
            return __('storefront.orders.errors.link_invalid');
        }

        if ($order->lines->contains(fn (StorefrontOrderLine $line): bool => $line->claimed_attempt_id !== null && $line->claimed_attempt_id !== $attemptId)) {
            return __('storefront.orders.errors.claimed_elsewhere');
        }

        $claimedHere = $attemptId !== null && $order->lines->contains(fn (StorefrontOrderLine $line): bool => $line->claimed_attempt_id === $attemptId);

        if ($order->isPastPickup() && ! $claimedHere) {
            return __('storefront.orders.errors.expired');
        }

        $expected = [];

        foreach ($order->lines as $line) {
            $key = $line->tracksUnits() ? 'u'.$line->inventory_unit_id : 'd'.$line->product_definition_id;
            $expected[$key] = [
                'quantity' => ($expected[$key]['quantity'] ?? 0) + $line->quantity,
                'price' => $line->unitPriceMinor(),
            ];
        }

        $actual = [];

        foreach ($lines as $line) {
            if (($line['adjustment_reason'] ?? null) !== null || (int) $line['unit_price'] !== (int) $line['list_unit_price']) {
                return __('storefront.orders.errors.cart_mismatch');
            }

            $key = $line['unit_id'] === null ? 'd'.$line['definition_id'] : 'u'.$line['unit_id'];
            $actual[$key] = [
                'quantity' => ($actual[$key]['quantity'] ?? 0) + (int) $line['quantity'],
                'price' => (int) $line['list_unit_price'],
            ];
        }

        ksort($expected);
        ksort($actual);

        return $expected === $actual ? null : __('storefront.orders.errors.cart_mismatch');
    }
}
