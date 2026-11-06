<?php

declare(strict_types=1);

namespace App\Actions\Pos;

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
    public function __invoke(User $actor, Store $store, array $cart, ?string $attemptKey = null, ?int $expectedRevision = null): CheckoutAttempt
    {
        Gate::forUser($actor)->authorize('usePos', $store);

        $existing = $attemptKey === null ? null : self::findAttempt($store, $attemptKey);

        if ($existing !== null) {
            Gate::forUser($actor)->authorize('operate', $existing);
        }

        [$lines, $totals] = $this->priceCart($actor, $store, $cart);
        $payloadHash = self::payloadHash($store, $lines);

        if ($existing === null) {
            return DB::transaction(function () use ($actor, $store, $lines, $totals, $payloadHash): CheckoutAttempt {
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
                    'status' => CheckoutAttemptStatus::OPEN,
                    'revision' => 1,
                    'currency' => Currency::TRY,
                    'lines' => $lines,
                    ...$totals,
                    'payload_hash' => $payloadHash,
                ]);
            });
        }

        return DB::transaction(function () use ($existing, $expectedRevision, $lines, $totals, $payloadHash): CheckoutAttempt {
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
                'lines' => $lines,
                ...$totals,
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
     * Fingerprint of everything the cashier reviewed: items, revisions, quantities, and prices.
     *
     * @param  list<array<string, mixed>>  $lines
     */
    public static function payloadHash(Store $store, array $lines): string
    {
        return Idempotency::fingerprint([
            'store_id' => $store->id,
            'lines' => array_map(fn (array $line): array => [
                $line['key'], $line['definition_id'], $line['unit_id'], $line['unit_revision'],
                $line['quantity'], $line['list_unit_price'], $line['unit_price'], $line['adjustment_reason'],
            ], $lines),
        ]);
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
        $canAdjust = Gate::forUser($actor)->allows('adjustSalePrices', $store);

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

            [$unitPrice, $reason, $lineErrors] = $this->adjustment($input, $listPrice, $canAdjust, $field);

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
     * An owner-only lower price for one line, which always needs a reason.
     *
     * @param  array<string, mixed>  $input
     * @return array{0: int, 1: ?string, 2: array<string, string>}
     */
    private function adjustment(array $input, int $listPrice, bool $canAdjust, string $field): array
    {
        $rawPrice = is_scalar($input['adjusted_price'] ?? null) ? Str::trim((string) $input['adjusted_price']) : '';
        $reason = is_scalar($input['adjustment_reason'] ?? null) ? Str::trim((string) $input['adjustment_reason']) : '';

        if ($rawPrice === '') {
            return [$listPrice, null, []];
        }

        if (! $canAdjust) {
            return [$listPrice, null, ["{$field}.adjusted_price" => __('pos.errors.adjustment_not_allowed')]];
        }

        try {
            $price = Money::parse($rawPrice, Money::MAX_UNIT_AMOUNT);
        } catch (InvalidAmountException) {
            return [$listPrice, null, ["{$field}.adjusted_price" => __('pos.errors.price_invalid', ['max' => Money::format(Money::MAX_UNIT_AMOUNT)])]];
        }

        if ($price === $listPrice) {
            return [$listPrice, null, []];
        }

        if ($price > $listPrice) {
            return [$listPrice, null, ["{$field}.adjusted_price" => __('pos.errors.adjustment_above_list')]];
        }

        if ($reason === '' || mb_strlen($reason) > self::MAX_REASON_LENGTH) {
            return [$listPrice, null, ["{$field}.adjustment_reason" => __('pos.errors.adjustment_reason_required', ['max' => self::MAX_REASON_LENGTH])]];
        }

        return [$price, $reason, []];
    }
}
