<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Enums\AdjustmentReason;
use App\Enums\IdentifierType;
use App\Enums\MovementType;
use App\Enums\UnitCondition;
use App\Enums\UnitStatus;
use App\Exceptions\Inventory\IdempotencyConflictException;
use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\InventoryUnitIdentifier;
use App\Models\ProductDefinition;
use App\Models\Store;
use App\Models\User;
use App\Rules\ValidImei;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RegisterUnit
{
    public const MAX_SERIAL_LENGTH = 64;

    public function __construct(private readonly InventoryLedger $ledger) {}

    /**
     * Register one serialized or used-device unit as available stock in a store.
     *
     * Until purchasing exists, a registered unit is recorded as an opening-balance movement of +1.
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

        $imei = self::normalize($imei);
        $secondaryImei = self::normalize($secondaryImei);
        $serial = self::normalize($serial);
        $note = InventoryLedger::prepareNote($note);

        $this->validateIdentifiers($imei, $secondaryImei, $serial);

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
                    $mode = $locked->tracking_mode;

                    if (! $mode->tracksIndividualItems()) {
                        throw ValidationException::withMessages(['condition' => __('inventory.errors.requires_unit_tracking')]);
                    }

                    if (! in_array($condition, UnitCondition::allowedFor($mode), true)) {
                        throw ValidationException::withMessages(['condition' => __('inventory.errors.condition_not_allowed')]);
                    }

                    $this->ensureIdentifiersAreUnused($locked, $imei, $secondaryImei, $serial);

                    $unit = InventoryUnit::query()->create([
                        'store_id' => $store->id,
                        'product_definition_id' => $locked->id,
                        'status' => UnitStatus::AVAILABLE,
                        'condition' => $condition,
                    ]);

                    $identifiers = array_filter([
                        [IdentifierType::IMEI, 1, $imei],
                        [IdentifierType::IMEI, 2, $secondaryImei],
                        [IdentifierType::SERIAL, 1, $serial],
                    ], fn (array $identifier): bool => $identifier[2] !== null);

                    foreach ($identifiers as [$type, $position, $value]) {
                        InventoryUnitIdentifier::query()->create([
                            'inventory_unit_id' => $unit->id,
                            'product_definition_id' => $locked->id,
                            'type' => $type,
                            'position' => $position,
                            'value' => $value,
                        ]);
                    }

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
            $this->ensureIdentifiersAreUnused($definition, $imei, $secondaryImei, $serial);

            throw $exception;
        }
    }

    private static function normalize(?string $value): ?string
    {
        $value = Str::trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @throws ValidationException
     */
    private function validateIdentifiers(?string $imei, ?string $secondaryImei, ?string $serial): void
    {
        $errors = [];

        if ($imei === null && $secondaryImei === null && $serial === null) {
            $errors['imei'] = __('inventory.errors.identifier_required');
        }

        if ($imei === null && $secondaryImei !== null) {
            $errors['imei'] = __('inventory.errors.primary_imei_required');
        }

        if ($imei !== null && ! ValidImei::isValid($imei)) {
            $errors['imei'] = __('inventory.errors.invalid_imei');
        }

        if ($secondaryImei !== null && ! ValidImei::isValid($secondaryImei)) {
            $errors['secondary_imei'] = __('inventory.errors.invalid_imei');
        } elseif ($secondaryImei !== null && $secondaryImei === $imei) {
            $errors['secondary_imei'] = __('inventory.errors.duplicate_imei_pair');
        }

        if ($serial !== null && mb_strlen($serial) > self::MAX_SERIAL_LENGTH) {
            $errors['serial'] = __('operations.validation.max', ['attribute' => __('inventory.fields.serial'), 'max' => self::MAX_SERIAL_LENGTH]);
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * IMEIs are unique system-wide and serials per definition, including removed units.
     *
     * @throws ValidationException
     */
    private function ensureIdentifiersAreUnused(ProductDefinition $definition, ?string $imei, ?string $secondaryImei, ?string $serial): void
    {
        $errors = [];

        foreach (['imei' => $imei, 'secondary_imei' => $secondaryImei] as $field => $value) {
            if ($value !== null && InventoryUnitIdentifier::query()->where('type', IdentifierType::IMEI)->where('value', $value)->exists()) {
                $errors[$field] = __('inventory.errors.imei_taken');
            }
        }

        if ($serial !== null && InventoryUnitIdentifier::query()
            ->where('type', IdentifierType::SERIAL)
            ->where('product_definition_id', $definition->id)
            ->where('value', $serial)
            ->exists()) {
            $errors['serial'] = __('inventory.errors.serial_taken');
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
