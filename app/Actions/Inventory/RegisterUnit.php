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

class RegisterUnit
{
    public const MAX_SERIAL_LENGTH = UnitIdentity::MAX_SERIAL_LENGTH;

    public function __construct(private readonly InventoryLedger $ledger) {}

    /**
     * Register one serialized or used-device unit as available stock in a store.
     *
     * A unit registered outside a purchase receipt is recorded as an opening-balance movement of +1
     * with no recorded cost.
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
        ?string $note,
        string $idempotencyKey,
    ): InventoryMovement {
        Gate::forUser($actor)->authorize('adjustInventory', $store);

        $imei = UnitIdentity::normalize($imei);
        $secondaryImei = UnitIdentity::normalize($secondaryImei);
        $serial = UnitIdentity::normalize($serial);
        $note = InventoryLedger::prepareNote($note);

        self::throwIfAny(UnitIdentity::formatErrors($imei, $secondaryImei, $serial));

        $payload = [
            'condition' => $condition->value,
            'imei' => $imei,
            'secondary_imei' => $secondaryImei,
            'serial' => $serial,
            'note' => $note,
        ];

        try {
            return $this->ledger->record(
                $actor,
                $store,
                $definition->id,
                'register_unit',
                null,
                $idempotencyKey,
                $payload,
                function (ProductDefinition $locked) use ($store, $condition, $imei, $secondaryImei, $serial, $note): array {
                    self::throwIfAny(UnitIdentity::modeErrors($locked, $condition));
                    self::throwIfAny(UnitIdentity::takenErrors($locked->id, $imei, $secondaryImei, $serial));

                    $unit = UnitIdentity::create($store, $locked, $condition, $imei, $secondaryImei, $serial);

                    return [
                        'type' => MovementType::OPENING_BALANCE,
                        'reason' => AdjustmentReason::OPENING_BALANCE,
                        'note' => $note,
                        'inventory_unit_id' => $unit->id,
                        'quantity_change' => 1,
                        'unit_status_after' => UnitStatus::AVAILABLE,
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
