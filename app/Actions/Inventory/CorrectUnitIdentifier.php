<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Enums\AdjustmentReason;
use App\Enums\IdentifierType;
use App\Enums\MovementType;
use App\Exceptions\Inventory\IdempotencyConflictException;
use App\Exceptions\Inventory\StaleUnitStateException;
use App\Models\InventoryMovement;
use App\Models\InventoryUnitIdentifierCorrection;
use App\Models\ProductDefinition;
use App\Models\Store;
use App\Models\User;
use App\Rules\ValidImei;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CorrectUnitIdentifier
{
    public function __construct(private readonly InventoryLedger $ledger) {}

    /**
     * Replace a mistyped IMEI or serial in its existing slot on the same unit.
     *
     * The old value is released and kept in correction history. A value already held by any
     * other unit (including removed units and secondary IMEIs) is rejected; no device is
     * created, merged, or swapped.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     * @throws StaleUnitStateException when the identifier changed after the form was opened.
     * @throws IdempotencyConflictException
     */
    public function __invoke(
        User $actor,
        Store $store,
        int $unitId,
        int $identifierId,
        string $expectedValue,
        ?string $newValue,
        ?string $reason,
        string $idempotencyKey,
    ): InventoryMovement {
        Gate::forUser($actor)->authorize('correctUnitIdentity', $store);

        $unit = $store->units()->whereKey($unitId)->firstOrFail();
        $identifier = $unit->identifiers()->whereKey($identifierId)->firstOrFail();
        $newValue = UnitIdentity::normalize($newValue);
        $reason = UnitLifecycle::text($reason, 'correction_reason', required: true);

        $this->validateFormat($identifier->type, $newValue, $expectedValue);

        $details = [];

        try {
            return $this->ledger->record(
                $actor,
                $store,
                $unit->product_definition_id,
                'correct_identifier',
                $unit->id,
                $idempotencyKey,
                ['identifier_id' => $identifierId, 'expected_value' => $expectedValue, 'new_value' => $newValue, 'reason' => $reason],
                function (ProductDefinition $locked) use ($store, $unitId, $identifierId, $expectedValue, $newValue, &$details): array {
                    $unit = $store->units()->whereKey($unitId)->lockForUpdate()->firstOrFail();
                    $identifier = $unit->identifiers()->whereKey($identifierId)->lockForUpdate()->firstOrFail();

                    if ($identifier->value !== $expectedValue) {
                        throw new StaleUnitStateException($unit->status, $unit->lifecycle_revision);
                    }

                    $this->throwIfTaken($identifier->type, $locked->id, (string) $newValue);

                    $details = [
                        'unit_id' => $unit->id,
                        'identifier' => $identifier,
                        'old_value' => $identifier->value,
                    ];

                    $identifier->update(['value' => $newValue]);

                    return [
                        'type' => MovementType::IDENTIFIER_CORRECTED,
                        'reason' => AdjustmentReason::IDENTITY_CORRECTION,
                        'inventory_unit_id' => $unit->id,
                        'quantity_change' => 0,
                        'unit_status_before' => $unit->status,
                        'unit_status_after' => $unit->status,
                    ];
                },
                function (InventoryMovement $movement) use ($actor, $newValue, $reason, &$details): void {
                    InventoryUnitIdentifierCorrection::query()->create([
                        'inventory_unit_id' => $details['unit_id'],
                        'inventory_unit_identifier_id' => $details['identifier']->id,
                        'type' => $details['identifier']->type,
                        'position' => $details['identifier']->position,
                        'old_value' => $details['old_value'],
                        'new_value' => $newValue,
                        'reason' => $reason,
                        'inventory_movement_id' => $movement->id,
                        'user_id' => $actor->id,
                        'corrected_at' => $movement->occurred_at,
                    ]);
                },
            );
        } catch (UniqueConstraintViolationException $exception) {
            $this->throwIfTaken($identifier->type, $unit->product_definition_id, (string) $newValue);

            throw $exception;
        }
    }

    /**
     * @throws ValidationException
     */
    private function validateFormat(IdentifierType $type, ?string $newValue, string $expectedValue): void
    {
        if ($newValue === null) {
            throw ValidationException::withMessages(['new_value' => __('devices.errors.required', ['attribute' => __('devices.fields.new_value')])]);
        }

        if ($newValue === $expectedValue) {
            throw ValidationException::withMessages(['new_value' => __('devices.errors.identifier_unchanged')]);
        }

        if ($type === IdentifierType::IMEI && ! ValidImei::isValid($newValue)) {
            throw ValidationException::withMessages(['new_value' => __('inventory.errors.invalid_imei')]);
        }

        if ($type === IdentifierType::SERIAL && mb_strlen($newValue) > UnitIdentity::MAX_SERIAL_LENGTH) {
            throw ValidationException::withMessages(['new_value' => __('operations.validation.max', ['attribute' => __('inventory.fields.serial'), 'max' => UnitIdentity::MAX_SERIAL_LENGTH])]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function throwIfTaken(IdentifierType $type, int $definitionId, string $value): void
    {
        $taken = $type === IdentifierType::IMEI
            ? UnitIdentity::takenImeis([$value]) !== []
            : UnitIdentity::takenSerials($definitionId, [$value]) !== [];

        if ($taken) {
            throw ValidationException::withMessages([
                'new_value' => __($type === IdentifierType::IMEI ? 'inventory.errors.imei_taken' : 'inventory.errors.serial_taken'),
            ]);
        }
    }
}
