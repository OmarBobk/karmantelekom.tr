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
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AdjustQuantity
{
    public const MAX_QUANTITY = 1_000_000;

    private const MAX_BALANCE = 4_294_967_295;

    public function __construct(private readonly InventoryLedger $ledger) {}

    /**
     * Increase or decrease a quantity-tracked balance with an explicit reason.
     *
     * The resulting quantity is computed from the locked balance row and may never go below zero.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     * @throws IdempotencyConflictException
     */
    public function __invoke(
        User $actor,
        Store $store,
        ProductDefinition $definition,
        MovementType $type,
        AdjustmentReason $reason,
        int $quantity,
        ?string $note,
        string $idempotencyKey,
    ): InventoryMovement {
        Gate::forUser($actor)->authorize('adjustInventory', $store);

        if ($type === MovementType::OPENING_BALANCE) {
            throw ValidationException::withMessages(['type' => __('inventory.errors.invalid_adjustment_type')]);
        }

        if (! $type->allows($reason)) {
            throw ValidationException::withMessages(['reason' => __('inventory.errors.reason_mismatch')]);
        }

        if ($quantity < 1 || $quantity > self::MAX_QUANTITY) {
            throw ValidationException::withMessages([
                'quantity' => __('inventory.errors.quantity_range', ['max' => self::MAX_QUANTITY]),
            ]);
        }

        $note = InventoryLedger::prepareNote($note, $type->requiresNote());

        $change = $type === MovementType::ADJUSTMENT_DECREASE ? -$quantity : $quantity;

        return $this->ledger->record(
            $actor,
            $store,
            $definition->id,
            'adjust_quantity',
            null,
            $idempotencyKey,
            ['type' => $type->value, 'reason' => $reason->value, 'quantity' => $quantity, 'note' => $note],
            function (ProductDefinition $locked) use ($store, $type, $reason, $change, $note): array {
                if ($locked->tracking_mode !== TrackingMode::QUANTITY) {
                    throw ValidationException::withMessages([
                        'quantity' => __('inventory.errors.requires_quantity_tracking'),
                    ]);
                }

                $balance = $this->ledger->lockBalance($store, $locked);
                $available = (int) $balance->quantity;
                $after = $available + $change;

                if ($after < 0) {
                    throw ValidationException::withMessages([
                        'quantity' => __('inventory.errors.insufficient_stock', ['available' => $available]),
                    ]);
                }

                if ($after > self::MAX_BALANCE) {
                    throw ValidationException::withMessages([
                        'quantity' => __('inventory.errors.quantity_range', ['max' => self::MAX_QUANTITY]),
                    ]);
                }

                $balance->update(['quantity' => $after]);

                return [
                    'type' => $type,
                    'reason' => $reason,
                    'note' => $note,
                    'quantity_change' => $change,
                    'quantity_after' => $after,
                ];
            },
        );
    }
}
