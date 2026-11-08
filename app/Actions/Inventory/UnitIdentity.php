<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Enums\IdentifierType;
use App\Enums\UnitCondition;
use App\Enums\UnitStatus;
use App\Models\InventoryUnit;
use App\Models\InventoryUnitIdentifier;
use App\Models\ProductDefinition;
use App\Models\Store;
use App\Rules\ValidImei;
use Illuminate\Support\Str;

/**
 * Identity rules for individually tracked units, shared by unit registration and purchase receipts.
 *
 * IMEIs are unique system-wide and serials per definition, including removed units.
 * Error arrays are keyed imei, secondary_imei, serial, and condition.
 */
class UnitIdentity
{
    public const MAX_SERIAL_LENGTH = 64;

    public static function normalize(?string $value): ?string
    {
        $value = Str::trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @return array<string, string>
     */
    public static function formatErrors(?string $imei, ?string $secondaryImei, ?string $serial): array
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

        return $errors;
    }

    /**
     * @return array<string, string>
     */
    public static function modeErrors(ProductDefinition $definition, UnitCondition $condition): array
    {
        $mode = $definition->tracking_mode;

        if (! $mode->tracksIndividualItems()) {
            return ['condition' => __('inventory.errors.requires_unit_tracking')];
        }

        if (! in_array($condition, UnitCondition::allowedFor($mode), true)) {
            return ['condition' => __('inventory.errors.condition_not_allowed')];
        }

        return [];
    }

    /**
     * @return array<string, string>
     */
    public static function takenErrors(int $definitionId, ?string $imei, ?string $secondaryImei, ?string $serial): array
    {
        $takenImeis = self::takenImeis(array_filter([$imei, $secondaryImei]));
        $errors = [];

        foreach (['imei' => $imei, 'secondary_imei' => $secondaryImei] as $field => $value) {
            if ($value !== null && in_array($value, $takenImeis, true)) {
                $errors[$field] = __('inventory.errors.imei_taken');
            }
        }

        if ($serial !== null && self::takenSerials($definitionId, [$serial]) !== []) {
            $errors['serial'] = __('inventory.errors.serial_taken');
        }

        return $errors;
    }

    /**
     * @param  array<int, string>  $imeis
     * @return list<string>
     */
    public static function takenImeis(array $imeis): array
    {
        if ($imeis === []) {
            return [];
        }

        return InventoryUnitIdentifier::query()
            ->where('type', IdentifierType::IMEI)
            ->whereIn('value', array_values($imeis))
            ->pluck('value')
            ->all();
    }

    /**
     * @param  array<int, string>  $serials
     * @return list<string>
     */
    public static function takenSerials(int $definitionId, array $serials): array
    {
        if ($serials === []) {
            return [];
        }

        return InventoryUnitIdentifier::query()
            ->where('type', IdentifierType::SERIAL)
            ->where('product_definition_id', $definitionId)
            ->whereIn('value', array_values($serials))
            ->pluck('value')
            ->all();
    }

    /**
     * Create a unit with its identifiers. Call inside a transaction holding the definition lock.
     */
    public static function create(
        Store $store,
        ProductDefinition $definition,
        UnitCondition $condition,
        ?string $imei,
        ?string $secondaryImei,
        ?string $serial,
        UnitStatus $status = UnitStatus::AVAILABLE,
    ): InventoryUnit {
        $unit = InventoryUnit::query()->create([
            'store_id' => $store->id,
            'product_definition_id' => $definition->id,
            'status' => $status,
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
                'product_definition_id' => $definition->id,
                'type' => $type,
                'position' => $position,
                'value' => $value,
            ]);
        }

        return $unit;
    }
}
