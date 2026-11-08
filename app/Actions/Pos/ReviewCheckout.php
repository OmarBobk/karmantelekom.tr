<?php

declare(strict_types=1);

namespace App\Actions\Pos;

use App\Actions\Network\ReservationGuard;
use App\Enums\CheckoutAttemptStatus;
use App\Enums\Currency;
use App\Enums\TrackingMode;
use App\Enums\UnitStatus;
use App\Exceptions\Purchasing\InvalidAmountException;
use App\Models\CheckoutAttempt;
use App\Models\InventoryUnit;
use App\Models\ProductDefinition;
use App\Models\Store;
use App\Models\User;
use App\Services\Idempotency;
use App\Services\Purchasing\LocalizedMoney;
use App\Services\Purchasing\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Price and check a cart on the server and persist it as a checkout attempt before payment.
 *
 * A new attempt gets a stable key, and is refused while the cashier has a terminal payment in
 * this store awaiting verification. Reviewing an existing open attempt replaces its cart and
 * advances its revision, but only until a terminal payment is started; after that the
 * reviewed payload is frozen. Availability is read without locks here and checked again under
 * locks when the sale is finalized.
 *
 * The optional customer is part of the reviewed payload: it must be one of the store's counter
 * customers, choosing another one advances the revision, and it is frozen with the cart.
 *
 * So is the credit amount: the exact part of the total the customer will owe. A positive credit
 * needs a customer and the reviewer's sell_on_credit authority, cannot exceed the total, and
 * records the reviewer as its authorizer.
 */
class ReviewCheckout
{
    public const MAX_LINES = 100;

    public const MAX_QUANTITY = 9999;

    public const MAX_REASON_LENGTH = 500;

    /**
     * @param  array<string, array{definition_id?: mixed, unit_id?: mixed, quantity?: mixed, adjusted_price?: mixed, adjustment_reason?: mixed}>  $cart  Keyed by cart line key.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $actor, Store $store, array $cart, ?string $attemptKey = null, ?int $expectedRevision = null, ?int $customerId = null, ?string $creditAmount = null, ?int $reservationId = null): CheckoutAttempt
    {
        Gate::forUser($actor)->authorize('usePos', $store);

        $existing = $attemptKey === null ? null : self::findAttempt($store, $attemptKey);

        if ($existing !== null) {
            Gate::forUser($actor)->authorize('operate', $existing);
        }

        if ($customerId !== null && ! $store->customers()->whereKey($customerId)->exists()) {
            throw ValidationException::withMessages(['customer' => __('pos.customer.errors.unavailable')]);
        }

        $creditMinor = self::parseCredit($creditAmount);

        if ($creditMinor > 0) {
            self::assertCreditAllowed($actor, $store, $customerId);
        }

        [$lines, $totals] = $this->priceCart($actor, $store, $cart);

        if ($creditMinor > Money::fromDecimal($totals['total'])) {
            throw ValidationException::withMessages(['credit_amount' => __('pos.credit.errors.above_total', ['total' => LocalizedMoney::format(Money::fromDecimal($totals['total']))])]);
        }

        $reservationProblem = ReservationGuard::reviewProblem($store, collect($lines)->pluck('unit_id')->filter()->map(fn ($id): int => (int) $id)->unique()->values()->all(), $reservationId);

        if ($reservationProblem !== null) {
            throw ValidationException::withMessages(['reservation' => $reservationProblem]);
        }

        $payloadHash = self::payloadHash($store, $lines, $customerId, $creditMinor, $reservationId);
        $credit = [
            'credit_amount' => Money::toDecimal($creditMinor),
            'credit_authorized_by' => $creditMinor > 0 ? $actor->id : null,
        ];

        if ($existing === null) {
            return DB::transaction(function () use ($actor, $store, $lines, $totals, $credit, $payloadHash, $customerId, $reservationId): CheckoutAttempt {
                $hasPendingTerminalPayment = $store->checkoutAttempts()
                    ->where('user_id', $actor->id)
                    ->pendingTerminalPayment()
                    ->lockForUpdate()
                    ->exists();

                if ($hasPendingTerminalPayment) {
                    throw ValidationException::withMessages(['cart' => __('pos.errors.terminal_payment_pending_elsewhere')]);
                }

                return CheckoutAttempt::query()->create([
                    'key' => (string) Str::uuid(),
                    'store_id' => $store->id,
                    'user_id' => $actor->id,
                    'customer_id' => $customerId,
                    'network_reservation_id' => $reservationId,
                    'status' => CheckoutAttemptStatus::OPEN,
                    'revision' => 1,
                    'currency' => Currency::TRY,
                    'lines' => $lines,
                    ...$totals,
                    ...$credit,
                    'payload_hash' => $payloadHash,
                ]);
            });
        }

        return DB::transaction(function () use ($existing, $expectedRevision, $lines, $totals, $credit, $payloadHash, $customerId, $reservationId): CheckoutAttempt {
            $attempt = CheckoutAttempt::query()->whereKey($existing->id)->lockForUpdate()->firstOrFail();

            self::assertOpen($attempt);

            if ($attempt->isFrozen()) {
                throw ValidationException::withMessages(['attempt' => __('pos.errors.attempt_frozen')]);
            }

            if ($expectedRevision !== null && $attempt->revision !== $expectedRevision) {
                throw ValidationException::withMessages(['attempt' => __('pos.errors.attempt_changed')]);
            }

            if (hash_equals($attempt->payload_hash, $payloadHash)) {
                return $attempt;
            }

            $attempt->update([
                'customer_id' => $customerId,
                'network_reservation_id' => $reservationId,
                'lines' => $lines,
                ...$totals,
                ...$credit,
                'payload_hash' => $payloadHash,
                'revision' => $attempt->revision + 1,
                'failure_code' => null,
                'failure_message' => null,
                'failed_at' => null,
            ]);

            return $attempt;
        });
    }

    public static function findAttempt(Store $store, string $attemptKey): CheckoutAttempt
    {
        return $store->checkoutAttempts()->where('key', Str::trim($attemptKey))->firstOrFail();
    }

    /**
     * @throws ValidationException
     */
    public static function assertOpen(CheckoutAttempt $attempt): void
    {
        if (! $attempt->isOpen()) {
            throw ValidationException::withMessages(['attempt' => __('pos.errors.attempt_closed', ['status' => $attempt->status->label()])]);
        }
    }

    /**
     * A positive credit needs a store customer and the reviewer's current credit authority.
     *
     * @throws ValidationException
     */
    public static function assertCreditAllowed(User $actor, Store $store, ?int $customerId): void
    {
        if ($customerId === null || ! $store->customers()->whereKey($customerId)->exists()) {
            throw ValidationException::withMessages(['credit_amount' => __('pos.credit.errors.customer_required')]);
        }

        if (Gate::forUser($actor)->denies('sellOnCredit', $store)) {
            throw ValidationException::withMessages(['credit_amount' => __('pos.credit.errors.not_allowed')]);
        }
    }

    /**
     * Canonical decimal credit to minor units; blank means no credit.
     *
     * @throws ValidationException
     */
    private static function parseCredit(?string $creditAmount): int
    {
        if (Str::trim((string) $creditAmount) === '') {
            return 0;
        }

        try {
            return Money::parse((string) $creditAmount, Money::MAX_TOTAL_AMOUNT, allowZero: true);
        } catch (InvalidAmountException) {
            throw ValidationException::withMessages(['credit_amount' => __('pos.credit.errors.invalid')]);
        }
    }

    /**
     * Fingerprint of everything the cashier reviewed: items, revisions, quantities, prices, and
     * the discount limit each discounted line was checked against, the customer when one was
     * chosen, and the credit when there is one. A walk-in or fully paid payload hashes exactly as
     * it did before customers and credit existed.
     *
     * @param  list<array<string, mixed>>  $lines
     */
    public static function payloadHash(Store $store, array $lines, ?int $customerId = null, int $creditMinor = 0, ?int $reservationId = null): string
    {
        $payload = [
            'store_id' => $store->id,
            'lines' => array_map(fn (array $line): array => [
                $line['key'], $line['definition_id'], $line['unit_id'], $line['unit_revision'],
                $line['quantity'], $line['list_unit_price'], $line['unit_price'], $line['adjustment_reason'],
                $line['discount_limit'] ?? null, $line['discount_basis_points'] ?? null,
                $line['lowest_unit_price'] ?? null, $line['discounted_by'] ?? null,
            ], $lines),
        ];

        if ($customerId !== null) {
            $payload['customer_id'] = $customerId;
        }

        if ($creditMinor > 0) {
            $payload['credit_minor'] = $creditMinor;
        }

        if ($reservationId !== null) {
            $payload['network_reservation_id'] = $reservationId;
        }

        return Idempotency::fingerprint($payload);
    }

    /**
     * @param  array<string, mixed>  $cart
     * @return array{0: list<array<string, mixed>>, 1: array{subtotal: string, discount_total: string, total: string}}
     *
     * @throws ValidationException
     */
    private function priceCart(User $actor, Store $store, array $cart): array
    {
        if ($cart === []) {
            throw ValidationException::withMessages(['cart' => __('pos.errors.cart_empty')]);
        }

        if (count($cart) > self::MAX_LINES) {
            throw ValidationException::withMessages(['cart' => __('pos.errors.too_many_lines', ['max' => self::MAX_LINES])]);
        }

        ksort($cart, SORT_STRING);
        $authority = DiscountAuthority::for($actor, $store);

        $definitionIds = collect($cart)->pluck('definition_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        $unitIds = collect($cart)->pluck('unit_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();

        /** @var Collection<int, ProductDefinition> $definitions */
        $definitions = ProductDefinition::query()->whereKey($definitionIds)->get()->keyBy('id');
        /** @var Collection<int, InventoryUnit> $units */
        $units = $store->units()->whereKey($unitIds)->with('identifiers')->get()->keyBy('id');
        $stock = $store->inventoryBalances()->whereIn('product_definition_id', $definitionIds)->pluck('quantity', 'product_definition_id');

        $errors = [];
        $lines = [];

        foreach ($cart as $key => $input) {
            $key = (string) $key;
            $field = "lines.{$key}";
            $definition = $definitions->get((int) ($input['definition_id'] ?? 0));

            if ($definition === null || ! $definition->is_active) {
                $errors["{$field}.item"] = __('pos.errors.item_unavailable');

                continue;
            }

            $unit = null;
            $quantity = 1;

            if ($definition->tracking_mode === TrackingMode::QUANTITY) {
                $quantity = filter_var($input['quantity'] ?? null, FILTER_VALIDATE_INT);

                if ($quantity === false || $quantity < 1 || $quantity > self::MAX_QUANTITY || ($input['unit_id'] ?? null) !== null) {
                    $errors["{$field}.quantity"] = __('pos.errors.quantity_invalid', ['max' => self::MAX_QUANTITY]);

                    continue;
                }

                if ((int) ($stock[$definition->id] ?? 0) < $quantity) {
                    $errors["{$field}.quantity"] = __('pos.errors.insufficient_stock', ['available' => (int) ($stock[$definition->id] ?? 0)]);

                    continue;
                }
            } else {
                $unit = $units->get((int) ($input['unit_id'] ?? 0));

                if ($unit === null || $unit->product_definition_id !== $definition->id) {
                    $errors["{$field}.item"] = __('pos.errors.unit_not_in_store');

                    continue;
                }

                if ($unit->status !== UnitStatus::AVAILABLE) {
                    $errors["{$field}.item"] = __('pos.errors.unit_unavailable', ['status' => $unit->status->label()]);

                    continue;
                }
            }

            $listPrice = SellingPrice::listPriceMinor($definition, $unit);

            if ($listPrice === null) {
                $errors["{$field}.price"] = __('pos.errors.not_priced');

                continue;
            }

            [$unitPrice, $reason, $discount, $lineErrors] = $this->discount($input, $listPrice, $unit, $authority, $field);

            if ($lineErrors !== []) {
                $errors += $lineErrors;

                continue;
            }

            try {
                $gross = Money::multiply($quantity, $listPrice);
                $lineTotal = Money::multiply($quantity, $unitPrice);
            } catch (InvalidAmountException) {
                $errors["{$field}.quantity"] = __('pos.errors.amount_too_large');

                continue;
            }

            $lines[] = [
                'key' => $key,
                'definition_id' => $definition->id,
                'unit_id' => $unit?->id,
                'unit_revision' => $unit?->lifecycle_revision,
                'tracking_mode' => $definition->tracking_mode->value,
                'name' => $definition->name,
                'model' => $definition->model,
                'sku' => $definition->sku,
                'barcode' => $definition->barcode,
                'unit_identifier' => $unit?->primaryIdentifierValue(),
                'unit_condition' => $unit?->condition->value,
                'quantity' => $quantity,
                'list_unit_price' => $listPrice,
                'unit_price' => $unitPrice,
                'gross' => $gross,
                'discount' => $gross - $lineTotal,
                'line_total' => $lineTotal,
                'adjustment_reason' => $reason,
                'discount_limit' => $discount['limit'] ?? null,
                'discount_basis_points' => $discount['basis_points'] ?? null,
                'lowest_unit_price' => $discount['lowest'] ?? null,
                'discounted_by' => $discount === null ? null : $actor->id,
            ];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        try {
            $subtotal = Money::sum(array_column($lines, 'gross'));
            $discount = Money::sum(array_column($lines, 'discount'));
        } catch (InvalidAmountException) {
            throw ValidationException::withMessages(['cart' => __('pos.errors.amount_too_large')]);
        }

        return [$lines, [
            'subtotal' => Money::toDecimal($subtotal),
            'discount_total' => Money::toDecimal($discount),
            'total' => Money::toDecimal($subtotal - $discount),
        ]];
    }

    /**
     * A lower final unit price for one line within the actor's discount limit, which always
     * needs a reason. The limit is snapshotted so finalization can check it is still current.
     *
     * @param  array<string, mixed>  $input
     * @return array{0: int, 1: ?string, 2: array{limit: string, lowest: int, basis_points: int|null}|null, 3: array<string, string>}
     */
    private function discount(array $input, int $listPrice, ?InventoryUnit $unit, DiscountAuthority $authority, string $field): array
    {
        $rawPrice = is_scalar($input['adjusted_price'] ?? null) ? Str::trim((string) $input['adjusted_price']) : '';
        $reason = is_scalar($input['adjustment_reason'] ?? null) ? Str::trim((string) $input['adjustment_reason']) : '';

        if ($rawPrice === '') {
            return [$listPrice, null, null, []];
        }

        if (! $authority->canDiscount()) {
            return [$listPrice, null, null, ["{$field}.adjusted_price" => __('pos.errors.discount_not_allowed')]];
        }

        try {
            $price = Money::parse($rawPrice, Money::MAX_UNIT_AMOUNT);
        } catch (InvalidAmountException) {
            return [$listPrice, null, null, ["{$field}.adjusted_price" => __('pos.errors.price_invalid', ['max' => LocalizedMoney::format(Money::MAX_UNIT_AMOUNT)])]];
        }

        if ($price === $listPrice) {
            return [$listPrice, null, null, []];
        }

        $refusal = $authority->refusal($listPrice, $price, $unit);

        if ($refusal !== null) {
            return [$listPrice, null, null, ["{$field}.adjusted_price" => $refusal]];
        }

        if ($reason === '' || mb_strlen($reason) > self::MAX_REASON_LENGTH) {
            return [$listPrice, null, null, ["{$field}.adjustment_reason" => __('pos.errors.adjustment_reason_required', ['max' => self::MAX_REASON_LENGTH])]];
        }

        return [$price, $reason, $authority->limitFor($listPrice, $unit), []];
    }
}
