<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Enums\TrackingMode;
use App\Enums\UnitStatus;
use App\Exceptions\Inventory\StaleUnitStateException;
use App\Models\InventoryUnit;
use App\Models\ProductDefinition;
use App\Models\Store;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The used-device transition table and the checks every lifecycle action makes under the unit lock.
 *
 * Every lifecycle or condition change advances the unit's lifecycle revision. A form carries the
 * status and revision it was opened with; any difference means another operator acted first.
 */
final class UnitLifecycle
{
    public const INSPECT = 'inspect';

    public const START_REPAIR = 'start_repair';

    public const CLOSE_REPAIR = 'close_repair';

    public const REMOVE = 'remove';

    public const MARK_FOUND = 'mark_found';

    public const APPROVE_REVIEW = 'approve_review';

    public const MAX_TEXT_LENGTH = 1000;

    /**
     * @var array<string, list<UnitStatus>>
     */
    private const ALLOWED_FROM = [
        self::INSPECT => [UnitStatus::AVAILABLE, UnitStatus::INSPECTION],
        self::START_REPAIR => [UnitStatus::INSPECTION],
        self::CLOSE_REPAIR => [UnitStatus::IN_REPAIR],
        self::REMOVE => [UnitStatus::AVAILABLE, UnitStatus::INSPECTION, UnitStatus::REVIEW],
        self::MARK_FOUND => [UnitStatus::REMOVED],
        self::APPROVE_REVIEW => [UnitStatus::REVIEW],
    ];

    public static function allows(UnitStatus $from, string $action): bool
    {
        return in_array($from, self::ALLOWED_FROM[$action], true);
    }

    /**
     * Lock the store's unit and confirm it is still in the state the form was opened with.
     *
     * Call inside the ledger mutation, after the definition lock.
     *
     * @throws StaleUnitStateException
     */
    public static function lockExpected(Store $store, int $unitId, UnitStatus $expectedStatus, int $expectedRevision): InventoryUnit
    {
        $unit = $store->units()->whereKey($unitId)->lockForUpdate()->firstOrFail();

        if ($unit->lifecycle_revision !== $expectedRevision || $unit->status !== $expectedStatus) {
            throw new StaleUnitStateException($unit->status, $unit->lifecycle_revision);
        }

        return $unit;
    }

    /**
     * @throws ValidationException
     */
    public static function assertAllowed(InventoryUnit $unit, string $action): void
    {
        if (! self::allows($unit->status, $action)) {
            throw ValidationException::withMessages([
                'unit' => __('devices.errors.transition_not_allowed', ['status' => $unit->status->label()]),
            ]);
        }
    }

    /**
     * Inspection, repair, and device costs apply to store-owned used devices only.
     *
     * @throws ValidationException
     */
    public static function assertUsedDevice(ProductDefinition $lockedDefinition): void
    {
        if ($lockedDefinition->tracking_mode !== TrackingMode::USED_DEVICE) {
            throw ValidationException::withMessages(['unit' => __('devices.errors.used_devices_only')]);
        }
    }

    /**
     * Apply a lifecycle or condition change and advance the revision.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function advance(InventoryUnit $unit, array $attributes): void
    {
        $unit->fill($attributes);
        $unit->lifecycle_revision++;
        $unit->save();
    }

    /**
     * Trim free text, treat blank as absent, and validate it under the given error key.
     *
     * @throws ValidationException
     */
    public static function text(?string $value, string $field, bool $required, int $max = self::MAX_TEXT_LENGTH): ?string
    {
        $value = Str::trim((string) $value);

        if ($required && $value === '') {
            throw ValidationException::withMessages([$field => __('devices.errors.required', ['attribute' => __("devices.fields.{$field}")])]);
        }

        if (mb_strlen($value) > $max) {
            throw ValidationException::withMessages([$field => __('operations.validation.max', ['attribute' => __("devices.fields.{$field}"), 'max' => $max])]);
        }

        return $value === '' ? null : $value;
    }
}
