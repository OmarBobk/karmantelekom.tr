<?php

declare(strict_types=1);

namespace App\Actions\Pos;

use App\Actions\Inventory\InventoryLedger;
use App\Actions\Inventory\UnitLifecycle;
use App\Enums\AdjustmentReason;
use App\Enums\CardApprovalStatus;
use App\Enums\CheckoutAttemptStatus;
use App\Enums\Currency;
use App\Enums\MovementType;
use App\Enums\SalePaymentMethod;
use App\Enums\TrackingMode;
use App\Enums\UnitStatus;
use App\Exceptions\Inventory\IdempotencyConflictException;
use App\Exceptions\Pos\CheckoutItemsChangedException;
use App\Exceptions\Purchasing\InvalidAmountException;
use App\Models\CardApproval;
use App\Models\CheckoutAttempt;
use App\Models\InventoryBalance;
use App\Models\InventoryUnit;
use App\Models\ProductDefinition;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\SaleNumberSequence;
use App\Models\SalePayment;
use App\Models\Store;
use App\Models\User;
use App\Services\Idempotency;
use App\Services\Purchasing\LocalizedMoney;
use App\Services\Purchasing\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Complete a reviewed checkout attempt as one sale, in one transaction.
 *
 * Lock order: the attempt, its card approval, product definitions (ascending), the store's
 * existing balance rows, then units (ascending), then the store's sale number sequence.
 * Everything reviewed is checked again under those locks; the sale, its lines, its final
 * payments, the stock movements, and sold unit states are written together or not at all.
 *
 * A started terminal payment without a recorded approval blocks completion: the card may have
 * been charged, so the sale cannot be taken as cash only.
 *
 * An identical retry of a completed attempt returns its sale. When the items changed or the
 * transaction failed, the attempt stays open with the failure recorded, so an approved card
 * payment remains visible and is never taken again.
 */
class FinalizeCheckout
{
    /** Counter transactions are all-or-nothing, so a deadlock victim is safely retried. */
    public const DEADLOCK_ATTEMPTS = 3;

    public function __construct(private readonly InventoryLedger $inventoryLedger) {}

    /**
     * @throws AuthorizationException
     * @throws IdempotencyConflictException
     * @throws ValidationException
     */
    public function __invoke(User $actor, Store $store, string $attemptKey, int $expectedRevision, ?string $cashTendered): Sale
    {
        Gate::forUser($actor)->authorize('usePos', $store);

        $attempt = ReviewCheckout::findAttempt($store, $attemptKey);
        Gate::forUser($actor)->authorize('operate', $attempt);

        $tenderedMinor = self::parseTendered($cashTendered);

        $fingerprint = Idempotency::fingerprint([
            'operation' => 'finalize_checkout',
            'attempt' => $attempt->key,
            'revision' => $expectedRevision,
            'cash_tendered' => $tenderedMinor,
        ]);

        try {
            return DB::transaction(fn (): Sale => $this->complete($actor, $store, $attempt, $expectedRevision, $tenderedMinor, $fingerprint), self::DEADLOCK_ATTEMPTS);
        } catch (CheckoutItemsChangedException $exception) {
            $this->recordFailure($attempt, $exception->failureCode, (string) collect($exception->errors())->flatten()->first());

            throw $exception;
        } catch (ValidationException|IdempotencyConflictException|AuthorizationException $exception) {
            throw $exception;
        } catch (UniqueConstraintViolationException $exception) {
            $completed = CheckoutAttempt::query()->whereKey($attempt->id)->first();

            if ($completed?->status === CheckoutAttemptStatus::COMPLETED) {
                return self::replay($completed, $actor, $fingerprint);
            }

            $this->recordSystemFailure($attempt, $exception);

            throw $exception;
        } catch (Throwable $exception) {
            $this->recordSystemFailure($attempt, $exception);

            throw $exception;
        }
    }

    /**
     * @throws ValidationException
     * @throws IdempotencyConflictException
     */
    private function complete(User $actor, Store $store, CheckoutAttempt $attempt, int $expectedRevision, ?int $tenderedMinor, string $fingerprint): Sale
    {
        $locked = CheckoutAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();

        if ($locked->status === CheckoutAttemptStatus::COMPLETED) {
            return self::replay($locked, $actor, $fingerprint);
        }

        ReviewCheckout::assertOpen($locked);

        if ($locked->revision !== $expectedRevision) {
            throw ValidationException::withMessages(['attempt' => __('pos.errors.attempt_changed')]);
        }

        $approval = $locked->lockCardApproval();

        if ($approval === null && $locked->hasStartedTerminalPayment()) {
            throw ValidationException::withMessages(['attempt' => __('pos.errors.terminal_payment_pending')]);
        }

        $cardMinor = $approval !== null && $approval->status === CardApprovalStatus::APPROVED ? $approval->amountMinor() : 0;
        $totalMinor = $locked->totalMinor();
        [$cashDue, $changeMinor] = self::cashSplit($totalMinor, $cardMinor, $tenderedMinor);

        /** @var list<array<string, mixed>> $lines */
        $lines = $locked->lines;
        $definitions = $this->inventoryLedger->lockDefinitions(array_map(fn (array $line): int => (int) $line['definition_id'], $lines));
        $balances = $this->lockBalances($store, $lines, $definitions);
        $units = $this->lockUnits($store, $lines);

        $this->revalidate($lines, $definitions, $balances, $units, $locked->isFrozen() ? null : DiscountAuthority::for($actor, $store));

        $now = now();
        $sequence = $this->nextSequence($store);

        $sale = Sale::query()->create([
            'store_id' => $store->id,
            'user_id' => $actor->id,
            'customer_id' => $locked->customer_id,
            'checkout_attempt_id' => $locked->id,
            'number' => Sale::formatNumber($store, $sequence),
            'sequence' => $sequence,
            'currency' => Currency::TRY,
            'subtotal' => $locked->subtotal,
            'discount_total' => $locked->discount_total,
            'total' => $locked->total,
            'cash_tendered' => $cashDue > 0 ? Money::toDecimal((int) $tenderedMinor) : null,
            'change_given' => Money::toDecimal($changeMinor),
            'completed_at' => $now,
        ]);

        $ordered = collect($lines)->sortBy([['definition_id', 'asc'], ['key', 'asc']])->values();

        foreach ($ordered as $index => $line) {
            $definition = $definitions->get((int) $line['definition_id']);
            $unit = $line['unit_id'] === null ? null : $units->get((int) $line['unit_id']);
            $isDiscounted = (int) $line['unit_price'] < (int) $line['list_unit_price'];

            $saleLine = SaleLine::query()->create([
                'sale_id' => $sale->id,
                'line_number' => $index + 1,
                'product_definition_id' => $definition->id,
                'inventory_unit_id' => $unit?->id,
                'tracking_mode' => $definition->tracking_mode,
                'product_name' => $line['name'],
                'product_model' => $line['model'],
                'sku' => $line['sku'],
                'barcode' => $line['barcode'],
                'unit_identifier' => $unit?->primaryIdentifierValue(),
                'unit_condition' => $unit?->condition->value,
                'quantity' => $line['quantity'],
                'list_unit_price' => Money::toDecimal($line['list_unit_price']),
                'unit_price' => Money::toDecimal($line['unit_price']),
                'unit_discount' => Money::toDecimal((int) $line['list_unit_price'] - (int) $line['unit_price']),
                'discount_amount' => Money::toDecimal($line['discount']),
                'line_total' => Money::toDecimal($line['line_total']),
                'adjustment_reason' => $line['adjustment_reason'],
                'discount_limit' => $isDiscounted ? ($line['discount_limit'] ?? DiscountAuthority::OWNER) : null,
                'discount_basis_points' => $isDiscounted ? ($line['discount_basis_points'] ?? null) : null,
                'discount_lowest_unit_price' => $isDiscounted && isset($line['lowest_unit_price']) ? Money::toDecimal((int) $line['lowest_unit_price']) : null,
                'discounted_by' => $isDiscounted ? ($line['discounted_by'] ?? $locked->user_id) : null,
            ]);

            $movement = [
                'type' => MovementType::SALE,
                'reason' => AdjustmentReason::SALE,
                'reference_type' => SaleLine::MORPH_ALIAS,
                'reference_id' => $saleLine->id,
                'occurred_at' => $now,
            ];

            if ($unit === null) {
                $balance = $balances->get($definition->id);
                $balance->decrement('quantity', (int) $line['quantity']);

                $this->inventoryLedger->apply($actor, $store, $definition, "sale:{$sale->id}:{$saleLine->id}", $fingerprint, [
                    ...$movement,
                    'quantity_change' => -(int) $line['quantity'],
                    'quantity_after' => $balance->quantity,
                ]);

                continue;
            }

            UnitLifecycle::advance($unit, ['status' => UnitStatus::SOLD]);

            $this->inventoryLedger->apply($actor, $store, $definition, "sale:{$sale->id}:{$saleLine->id}", $fingerprint, [
                ...$movement,
                'inventory_unit_id' => $unit->id,
                'quantity_change' => -1,
                'unit_status_before' => UnitStatus::AVAILABLE,
                'unit_status_after' => UnitStatus::SOLD,
            ]);
        }

        $this->writePayments($actor, $store, $sale, $approval, $cardMinor, $cashDue, $tenderedMinor, $changeMinor, $now);

        $approval?->update(['status' => CardApprovalStatus::CAPTURED]);

        $locked->update([
            'status' => CheckoutAttemptStatus::COMPLETED,
            'finalize_fingerprint' => $fingerprint,
            'completed_at' => $now,
            'failure_code' => null,
            'failure_message' => null,
            'failed_at' => null,
        ]);

        return $sale;
    }

    /**
     * Cash due after the approved card amount, and change against cash tendered only.
     *
     * @return array{0: int, 1: int}
     *
     * @throws ValidationException
     */
    public static function cashSplit(int $totalMinor, int $cardMinor, ?int $tenderedMinor): array
    {
        $cashDue = $totalMinor - $cardMinor;

        if ($cashDue < 0) {
            throw ValidationException::withMessages(['card_amount' => __('pos.errors.card_amount_above_total')]);
        }

        if ($cashDue === 0) {
            if ($tenderedMinor !== null && $tenderedMinor > 0) {
                throw ValidationException::withMessages(['cash_tendered' => __('pos.errors.no_cash_due')]);
            }

            return [0, 0];
        }

        if ($tenderedMinor === null || $tenderedMinor < $cashDue) {
            throw ValidationException::withMessages(['cash_tendered' => __('pos.errors.cash_short', ['due' => LocalizedMoney::format($cashDue)])]);
        }

        return [$cashDue, $tenderedMinor - $cashDue];
    }

    /**
     * @throws ValidationException
     */
    private static function parseTendered(?string $cashTendered): ?int
    {
        if (Str::trim((string) $cashTendered) === '') {
            return null;
        }

        try {
            return Money::parse((string) $cashTendered, Money::MAX_TOTAL_AMOUNT, allowZero: true);
        } catch (InvalidAmountException) {
            throw ValidationException::withMessages(['cash_tendered' => __('pos.errors.cash_invalid')]);
        }
    }

    /**
     * @throws IdempotencyConflictException
     */
    private static function replay(CheckoutAttempt $completed, User $actor, string $fingerprint): Sale
    {
        Idempotency::assertReplay($completed->user_id, (string) $completed->finalize_fingerprint, $actor, $fingerprint);

        return Sale::query()->where('checkout_attempt_id', $completed->id)->firstOrFail();
    }

    /**
     * Lock existing balance rows only. A missing row means no stock; it is never created here.
     *
     * @param  list<array<string, mixed>>  $lines
     * @param  Collection<int, ProductDefinition>  $definitions
     * @return Collection<int, InventoryBalance>
     */
    private function lockBalances(Store $store, array $lines, Collection $definitions): Collection
    {
        $ids = collect($lines)
            ->filter(fn (array $line): bool => $definitions->get((int) $line['definition_id'])?->tracking_mode === TrackingMode::QUANTITY)
            ->map(fn (array $line): int => (int) $line['definition_id'])
            ->unique()->sort()->values()->all();

        if ($ids === []) {
            return new Collection;
        }

        return InventoryBalance::query()
            ->where('store_id', $store->id)
            ->whereIn('product_definition_id', $ids)
            ->orderBy('product_definition_id')
            ->lockForUpdate()
            ->get()
            ->keyBy('product_definition_id');
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return Collection<int, InventoryUnit>
     */
    private function lockUnits(Store $store, array $lines): Collection
    {
        $ids = collect($lines)->pluck('unit_id')->filter()->map(fn ($id): int => (int) $id)->unique()->sort()->values()->all();

        if ($ids === []) {
            return new Collection;
        }

        return $store->units()->whereKey($ids)->orderBy('id')->lockForUpdate()->with('identifiers')->get()->keyBy('id');
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @param  Collection<int, ProductDefinition>  $definitions
     * @param  Collection<int, InventoryBalance>  $balances
     * @param  Collection<int, InventoryUnit>  $units
     * @param  DiscountAuthority|null  $authority  The finalizing cashier's current discount limits; null once a
     *                                             terminal payment started, because the charged total is final.
     *
     * @throws CheckoutItemsChangedException
     */
    private function revalidate(array $lines, Collection $definitions, Collection $balances, Collection $units, ?DiscountAuthority $authority): void
    {
        $unavailable = [];
        $priceChanged = [];
        $discountChanged = [];
        $requested = [];

        foreach ($lines as $line) {
            $field = "lines.{$line['key']}";
            $definition = $definitions->get((int) $line['definition_id']);

            if ($definition === null || ! $definition->is_active || $definition->tracking_mode->value !== $line['tracking_mode']) {
                $unavailable["{$field}.item"] = __('pos.errors.item_unavailable_named', ['name' => $line['name']]);

                continue;
            }

            $unit = null;

            if ($definition->tracking_mode === TrackingMode::QUANTITY) {
                $requested[$definition->id] = ($requested[$definition->id] ?? 0) + (int) $line['quantity'];
                $onHand = (int) ($balances->get($definition->id)?->quantity ?? 0);

                if ($onHand < $requested[$definition->id]) {
                    $unavailable["{$field}.quantity"] = __('pos.errors.insufficient_stock_named', ['name' => $line['name'], 'available' => $onHand]);

                    continue;
                }
            } else {
                $unit = $units->get((int) $line['unit_id']);

                if ($unit === null || $unit->product_definition_id !== $definition->id || $unit->status !== UnitStatus::AVAILABLE || $unit->lifecycle_revision !== (int) $line['unit_revision']) {
                    $unavailable["{$field}.item"] = __('pos.errors.unit_changed', [
                        'name' => $line['name'],
                        'status' => $unit?->status->label() ?? __('pos.errors.unit_missing'),
                    ]);

                    continue;
                }
            }

            if (SellingPrice::listPriceMinor($definition, $unit) !== (int) $line['list_unit_price']) {
                $priceChanged["{$field}.price"] = __('pos.errors.price_changed', ['name' => $line['name']]);

                continue;
            }

            if ($authority !== null && (int) $line['unit_price'] < (int) $line['list_unit_price']
                && $authority->refusal((int) $line['list_unit_price'], (int) $line['unit_price'], $unit) !== null) {
                $discountChanged["{$field}.adjusted_price"] = __('pos.errors.discount_changed', ['name' => $line['name']]);
            }
        }

        if ($unavailable !== []) {
            throw CheckoutItemsChangedException::because(CheckoutItemsChangedException::UNAVAILABLE, $unavailable);
        }

        if ($priceChanged !== []) {
            throw CheckoutItemsChangedException::because(CheckoutItemsChangedException::PRICE_CHANGED, $priceChanged);
        }

        if ($discountChanged !== []) {
            throw CheckoutItemsChangedException::because(CheckoutItemsChangedException::DISCOUNT_CHANGED, $discountChanged);
        }
    }

    private function nextSequence(Store $store): int
    {
        SaleNumberSequence::query()->insertOrIgnore([
            'store_id' => $store->id,
            'last_number' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sequence = SaleNumberSequence::query()->whereKey($store->id)->lockForUpdate()->firstOrFail();
        $sequence->last_number++;
        $sequence->save();

        return $sequence->last_number;
    }

    private function writePayments(User $actor, Store $store, Sale $sale, ?CardApproval $approval, int $cardMinor, int $cashDue, ?int $tenderedMinor, int $changeMinor, mixed $now): void
    {
        $base = [
            'sale_id' => $sale->id,
            'store_id' => $store->id,
            'user_id' => $actor->id,
            'currency' => Currency::TRY,
            'occurred_at' => $now,
        ];

        if ($approval !== null && $cardMinor > 0) {
            SalePayment::query()->create([
                ...$base,
                'method' => SalePaymentMethod::CARD,
                'amount' => Money::toDecimal($cardMinor),
                'card_approval_id' => $approval->id,
                'card_terminal_id' => $approval->card_terminal_id,
                'terminal_reference' => $approval->reference,
            ]);
        }

        if ($cashDue > 0) {
            SalePayment::query()->create([
                ...$base,
                'method' => SalePaymentMethod::CASH,
                'amount' => Money::toDecimal($cashDue),
                'cash_tendered' => Money::toDecimal((int) $tenderedMinor),
                'change_given' => Money::toDecimal($changeMinor),
            ]);
        }
    }

    private function recordFailure(CheckoutAttempt $attempt, string $code, string $message): void
    {
        DB::transaction(function () use ($attempt, $code, $message): void {
            $locked = CheckoutAttempt::query()->whereKey($attempt->id)->lockForUpdate()->first();

            if ($locked?->isOpen()) {
                $locked->update([
                    'failure_code' => $code,
                    'failure_message' => Str::limit($message, 500, ''),
                    'failed_at' => now(),
                ]);
            }
        });
    }

    private function recordSystemFailure(CheckoutAttempt $attempt, Throwable $exception): void
    {
        Log::error('POS checkout finalization failed', [
            'checkout_attempt_id' => $attempt->id,
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
        ]);

        try {
            $this->recordFailure($attempt, 'system_error', __('pos.errors.system_error'));
        } catch (Throwable) {
            // The original exception is more useful than a failure to record it.
        }
    }
}
