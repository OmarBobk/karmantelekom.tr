<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Enums\AdjustmentReason;
use App\Enums\MovementType;
use App\Enums\TrackingMode;
use App\Exceptions\Inventory\CountMatchesBalanceException;
use App\Exceptions\Inventory\IdempotencyConflictException;
use App\Models\InventoryMovement;
use App\Models\ProductDefinition;
use App\Models\Store;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RecordStockCount
{
    public const MAX_QUANTITY = 1_000_000;

    public function __construct(private readonly InventoryLedger $ledger) {}

    /**
     * Set a quantity-tracked balance to the physically counted total.
     *
     * The count is tied to the latest movement for the store and definition when counting
     * started. Under the definition lock, any later movement makes the count stale, even one
     * that returned the balance to the same number. The movement stores the counted total as
     * quantity_after and the signed difference as quantity_change; their difference is the
     * prior balance.
     *
     * @return InventoryMovement|null Null when the count matches the balance and nothing changed.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     * @throws IdempotencyConflictException
     */
    public function __invoke(
        User $actor,
        Store $store,
        ProductDefinition $definition,
        int $countedQuantity,
        ?int $countStartedAfterMovementId,
        ?string $reason,
        string $idempotencyKey,
    ): ?InventoryMovement {
        Gate::forUser($actor)->authorize('adjustInventory', $store);

        if ($countedQuantity < 0 || $countedQuantity > self::MAX_QUANTITY) {
            throw ValidationException::withMessages([
                'counted_quantity' => __('inventory.errors.count_range', ['max' => self::MAX_QUANTITY]),
            ]);
        }

        $note = InventoryLedger::prepareNote($reason);

        if ($note === null) {
            throw ValidationException::withMessages(['note' => __('inventory.errors.count_reason_required')]);
        }

        try {
            return $this->ledger->record(
                $actor,
                $store,
                $definition->id,
                'stock_count',
                null,
                $idempotencyKey,
                ['counted_quantity' => $countedQuantity, 'count_started_after' => $countStartedAfterMovementId, 'note' => $note],
                function (ProductDefinition $locked) use ($store, $countedQuantity, $countStartedAfterMovementId, $note): array {
                    if ($locked->tracking_mode !== TrackingMode::QUANTITY) {
                        throw ValidationException::withMessages([
                            'counted_quantity' => __('inventory.errors.requires_quantity_tracking'),
                        ]);
                    }

                    if (self::latestMovementId($store, $locked) !== $countStartedAfterMovementId) {
                        throw ValidationException::withMessages(['count' => __('inventory.errors.count_stale')]);
                    }

                    $balance = $this->ledger->lockBalance($store, $locked);
                    $prior = (int) $balance->quantity;

                    if ($prior === $countedQuantity) {
                        throw new CountMatchesBalanceException($prior);
                    }

                    $balance->update(['quantity' => $countedQuantity]);
                    $difference = $countedQuantity - $prior;

                    return [
                        'type' => $difference > 0 ? MovementType::ADJUSTMENT_INCREASE : MovementType::ADJUSTMENT_DECREASE,
                        'reason' => AdjustmentReason::COUNT_CORRECTION,
                        'note' => $note,
                        'quantity_change' => $difference,
                        'quantity_after' => $countedQuantity,
                    ];
                },
            );
        } catch (CountMatchesBalanceException) {
            return null;
        }
    }

    /**
     * The movement a new count is tied to: the latest one for this store and definition.
     */
    public static function latestMovementId(Store $store, ProductDefinition $definition): ?int
    {
        $id = InventoryMovement::query()
            ->where('store_id', $store->id)
            ->where('product_definition_id', $definition->id)
            ->max('id');

        return $id === null ? null : (int) $id;
    }
}
