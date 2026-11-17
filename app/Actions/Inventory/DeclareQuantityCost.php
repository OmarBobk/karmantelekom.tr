<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Enums\Currency;
use App\Enums\QuantityCostStatus;
use App\Enums\TrackingMode;
use App\Exceptions\Inventory\IdempotencyConflictException;
use App\Exceptions\Purchasing\InvalidAmountException;
use App\Models\InventoryCostDeclaration;
use App\Models\ProductDefinition;
use App\Models\Store;
use App\Models\User;
use App\Services\Idempotency;
use App\Services\Inventory\QuantityCostPool;
use App\Services\Purchasing\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Owner declaration of the total TRY recorded cost for the current on-hand quantity.
 *
 * Does not create a stock movement. Rejects when quantity changed since the owner confirmed it.
 */
class DeclareQuantityCost
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException
     * @throws IdempotencyConflictException
     */
    public function __invoke(
        User $actor,
        Store $store,
        ProductDefinition $definition,
        int $expectedQuantity,
        string $declaredTotal,
        ?string $reason,
        string $idempotencyKey,
    ): InventoryCostDeclaration {
        Gate::forUser($actor)->authorize('managePurchases', $store);

        $key = Idempotency::key($idempotencyKey);
        $reason = $this->prepareReason($reason);

        try {
            $totalMinor = Money::parse($declaredTotal, Money::MAX_TOTAL_AMOUNT, allowZero: true);
        } catch (InvalidAmountException) {
            throw ValidationException::withMessages([
                'declared_total' => __('inventory.cost.errors.declared_total_invalid'),
            ]);
        }

        if ($expectedQuantity < 1) {
            throw ValidationException::withMessages([
                'expected_quantity' => __('inventory.cost.errors.zero_quantity'),
            ]);
        }

        $fingerprint = Idempotency::fingerprint([
            'operation' => 'declare_quantity_cost',
            'store_id' => $store->id,
            'product_definition_id' => $definition->id,
            'expected_quantity' => $expectedQuantity,
            'declared_total' => $totalMinor,
            'reason' => $reason,
        ]);

        try {
            return DB::transaction(function () use ($actor, $store, $definition, $expectedQuantity, $totalMinor, $reason, $key, $fingerprint): InventoryCostDeclaration {
                $existing = InventoryCostDeclaration::query()->where('idempotency_key', $key)->first();

                if ($existing !== null) {
                    Idempotency::assertReplay($existing->user_id, $existing->request_fingerprint, $actor, $fingerprint);

                    return $existing;
                }

                ProductDefinition::query()->whereKey($definition->id)->lockForUpdate()->firstOrFail();

                $lockedDefinition = ProductDefinition::query()->findOrFail($definition->id);

                if ($lockedDefinition->tracking_mode !== TrackingMode::QUANTITY) {
                    throw ValidationException::withMessages([
                        'declared_total' => __('inventory.errors.requires_quantity_tracking'),
                    ]);
                }

                $balance = app(InventoryLedger::class)->lockBalance($store, $lockedDefinition);
                $quantity = (int) $balance->quantity;

                if ($quantity === 0) {
                    throw ValidationException::withMessages([
                        'expected_quantity' => __('inventory.cost.errors.zero_quantity'),
                    ]);
                }

                if ($quantity !== $expectedQuantity) {
                    throw ValidationException::withMessages([
                        'expected_quantity' => __('inventory.cost.errors.quantity_changed', ['quantity' => $quantity]),
                    ]);
                }

                $priorStatus = $balance->cost_status instanceof QuantityCostStatus
                    ? $balance->cost_status
                    : QuantityCostStatus::from((string) ($balance->cost_status ?? QuantityCostStatus::UNKNOWN->value));

                $priorCurrency = $balance->cost_currency;
                $priorTotal = $balance->cost_total;

                QuantityCostPool::declareTotal($balance, $expectedQuantity, $totalMinor);
                $balance->refresh();

                return InventoryCostDeclaration::query()->create([
                    'store_id' => $store->id,
                    'product_definition_id' => $lockedDefinition->id,
                    'inventory_balance_id' => $balance->id,
                    'quantity' => $quantity,
                    'currency' => Currency::TRY,
                    'declared_total' => Money::toDecimal($totalMinor),
                    'prior_cost_status' => $priorStatus,
                    'prior_cost_currency' => $priorCurrency,
                    'prior_cost_total' => $priorTotal,
                    'resulting_cost_status' => QuantityCostStatus::KNOWN,
                    'resulting_cost_currency' => Currency::TRY->value,
                    'resulting_cost_total' => Money::toDecimal($totalMinor),
                    'reason' => $reason,
                    'user_id' => $actor->id,
                    'idempotency_key' => $key,
                    'request_fingerprint' => $fingerprint,
                    'declared_at' => now(),
                ]);
            });
        } catch (UniqueConstraintViolationException $exception) {
            $existing = InventoryCostDeclaration::query()->where('idempotency_key', $key)->first();

            if ($existing !== null) {
                Idempotency::assertReplay($existing->user_id, $existing->request_fingerprint, $actor, $fingerprint);

                return $existing;
            }

            throw $exception;
        }
    }

    /**
     * @throws ValidationException
     */
    private function prepareReason(?string $reason): string
    {
        $reason = Str::trim((string) $reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => __('inventory.cost.errors.reason_required'),
            ]);
        }

        if (mb_strlen($reason) > InventoryCostDeclaration::MAX_REASON_LENGTH) {
            throw ValidationException::withMessages([
                'reason' => __('operations.validation.max', [
                    'attribute' => __('inventory.cost.fields.reason'),
                    'max' => InventoryCostDeclaration::MAX_REASON_LENGTH,
                ]),
            ]);
        }

        return $reason;
    }
}
