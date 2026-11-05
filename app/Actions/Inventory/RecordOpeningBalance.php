<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Enums\AdjustmentReason;
use App\Enums\MovementType;
use App\Enums\TrackingMode;
use App\Exceptions\Inventory\IdempotencyConflictException;
use App\Models\InventoryMovement;
use App\Models\ProductDefinition;
use App\Models\Store;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RecordOpeningBalance
{
    public const MAX_QUANTITY = 1_000_000;

    public function __construct(private readonly InventoryLedger $ledger) {}

    /**
     * Record the single opening balance for a quantity-tracked definition in a store.
     *
     * Allowed only while the store has no stock history for the definition.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     * @throws IdempotencyConflictException
     */
    public function __invoke(
        User $actor,
        Store $store,
        ProductDefinition $definition,
        int $quantity,
        ?string $note,
        string $idempotencyKey,
    ): InventoryMovement {
        Gate::forUser($actor)->authorize('adjustInventory', $store);

        $note = InventoryLedger::prepareNote($note);

        if ($quantity < 1 || $quantity > self::MAX_QUANTITY) {
            throw ValidationException::withMessages([
                'quantity' => __('inventory.errors.quantity_range', ['max' => self::MAX_QUANTITY]),
            ]);
        }

        try {
            return $this->ledger->record(
                $actor,
                $store,
                $definition->id,
                'opening_balance',
                null,
                $idempotencyKey,
                ['quantity' => $quantity, 'note' => $note],
                function (ProductDefinition $locked) use ($store, $quantity, $note): array {
                    if ($locked->tracking_mode !== TrackingMode::QUANTITY) {
                        throw ValidationException::withMessages([
                            'quantity' => __('inventory.errors.requires_quantity_tracking'),
                        ]);
                    }

                    if ($store->inventoryMovements()->where('product_definition_id', $locked->id)->exists()) {
                        throw ValidationException::withMessages([
                            'quantity' => __('inventory.errors.opening_balance_exists'),
                        ]);
                    }

                    $balance = $this->ledger->lockBalance($store, $locked);
                    $balance->update(['quantity' => $quantity]);

                    return [
                        'type' => MovementType::OPENING_BALANCE,
                        'reason' => AdjustmentReason::OPENING_BALANCE,
                        'note' => $note,
                        'quantity_change' => $quantity,
                        'quantity_after' => $quantity,
                    ];
                },
            );
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'quantity' => __('inventory.errors.opening_balance_exists'),
            ]);
        }
    }
}
