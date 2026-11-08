<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Enums\AdjustmentReason;
use App\Enums\MovementType;
use App\Enums\UnitCondition;
use App\Enums\UnitStatus;
use App\Exceptions\Inventory\IdempotencyConflictException;
use App\Models\InventoryMovement;
use App\Models\ProductDefinition;
use App\Models\Store;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RecordFoundUnit
{
    public function __construct(private readonly InventoryLedger $ledger) {}

    /**
     * Record a device found during reconciliation whose identifier has no record anywhere.
     *
     * Its source is unverified, so it is created awaiting review: owned by the branch (+1) but not
     * sellable until an owner approves it. Allowed before and after initial setup.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     * @throws IdempotencyConflictException
     */
    public function __invoke(
        User $actor,
        Store $store,
        ProductDefinition $definition,
        UnitCondition $condition,
        ?string $imei,
        ?string $secondaryImei,
        ?string $serial,
        ?string $reason,
        string $idempotencyKey,
    ): InventoryMovement {
        Gate::forUser($actor)->authorize('adjustInventory', $store);

        $imei = UnitIdentity::normalize($imei);
        $secondaryImei = UnitIdentity::normalize($secondaryImei);
        $serial = UnitIdentity::normalize($serial);
        $note = InventoryLedger::prepareNote($reason);

        $errors = UnitIdentity::formatErrors($imei, $secondaryImei, $serial);

        if ($note === null) {
            $errors['note'] = __('inventory.errors.reconcile_reason_required');
        }

        self::throwIfAny($errors);

        try {
            return $this->ledger->record(
                $actor,
                $store,
                $definition->id,
                'record_found_unit',
                null,
                $idempotencyKey,
                ['condition' => $condition->value, 'imei' => $imei, 'secondary_imei' => $secondaryImei, 'serial' => $serial, 'note' => $note],
                function (ProductDefinition $locked) use ($store, $condition, $imei, $secondaryImei, $serial, $note): array {
                    self::throwIfAny(UnitIdentity::modeErrors($locked, $condition));
                    self::throwIfAny(UnitIdentity::takenErrors($locked->id, $imei, $secondaryImei, $serial));

                    $unit = UnitIdentity::create($store, $locked, $condition, $imei, $secondaryImei, $serial, UnitStatus::REVIEW);

                    return [
                        'type' => MovementType::ADJUSTMENT_INCREASE,
                        'reason' => AdjustmentReason::FOUND,
                        'note' => $note,
                        'inventory_unit_id' => $unit->id,
                        'quantity_change' => 1,
                        'unit_status_after' => UnitStatus::REVIEW,
                    ];
                },
            );
        } catch (UniqueConstraintViolationException $exception) {
            self::throwIfAny(UnitIdentity::takenErrors($definition->id, $imei, $secondaryImei, $serial));

            throw $exception;
        }
    }

    /**
     * @param  array<string, string>  $errors
     *
     * @throws ValidationException
     */
    private static function throwIfAny(array $errors): void
    {
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
