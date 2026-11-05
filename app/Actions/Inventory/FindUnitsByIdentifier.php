<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Enums\IdentifierType;
use App\Models\InventoryUnitIdentifier;
use App\Models\InventoryUnitIdentifierCorrection;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Exact IMEI / serial lookup limited to stores the user may access.
 *
 * Identifiers are compared as exact strings (leading zeros kept, binary collation). Both IMEI
 * slots match. Arabic-Indic digits and IMEI grouping (spaces, hyphens) are normalized for the
 * IMEI candidate only; the typed value is always also tried as an exact serial. Current
 * identifiers are preferred; values that were corrected away are returned separately as
 * historical matches.
 */
final class FindUnitsByIdentifier
{
    public const MAX_INPUT_LENGTH = 80;

    public const MAX_RESULTS = 20;

    private const ARABIC_DIGITS = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩', '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

    private const LATIN_DIGITS = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

    /**
     * @return array{current: list<array<string, mixed>>, historical: list<array<string, mixed>>}
     */
    public function __invoke(User $user, string $input, ?Store $store = null): array
    {
        [$imeis, $serials] = self::candidates($input);

        if ($imeis === [] && $serials === []) {
            return ['current' => [], 'historical' => []];
        }

        $storeIds = $store !== null
            ? [$store->id]
            : Store::query()->accessibleTo($user)->pluck('id')->all();

        if ($storeIds === []) {
            return ['current' => [], 'historical' => []];
        }

        $current = InventoryUnitIdentifier::query()
            ->select(['id', 'inventory_unit_id', 'type', 'position', 'value'])
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $imei) => $imei->where('type', IdentifierType::IMEI)->where('uniqueness_scope', 0)->whereIn('value', $imeis ?: ['']))
                ->orWhere(fn (Builder $serial) => $serial->where('type', IdentifierType::SERIAL)->whereIn('value', $serials ?: [''])))
            ->whereHas('unit', fn (Builder $units) => $units->whereIn('store_id', $storeIds))
            ->with(['unit:id,store_id,product_definition_id,status', 'unit.store:id,code,name', 'unit.productDefinition:id,name,model'])
            ->orderBy('inventory_unit_id')
            ->limit(self::MAX_RESULTS)
            ->get()
            ->unique('inventory_unit_id')
            ->map(fn (InventoryUnitIdentifier $identifier): array => self::row($identifier->unit, $identifier->type, $identifier->position, $identifier->value, null))
            ->values()
            ->all();

        $currentUnitIds = array_column($current, 'unit_id');

        $historical = InventoryUnitIdentifierCorrection::query()
            ->select(['id', 'inventory_unit_id', 'type', 'position', 'old_value', 'new_value', 'corrected_at'])
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $imei) => $imei->where('type', IdentifierType::IMEI)->whereIn('old_value', $imeis ?: ['']))
                ->orWhere(fn (Builder $serial) => $serial->where('type', IdentifierType::SERIAL)->whereIn('old_value', $serials ?: [''])))
            ->whereNotIn('inventory_unit_id', $currentUnitIds ?: [0])
            ->whereHas('unit', fn (Builder $units) => $units->whereIn('store_id', $storeIds))
            ->with(['unit:id,store_id,product_definition_id,status', 'unit.store:id,code,name', 'unit.productDefinition:id,name,model'])
            ->latest('corrected_at')
            ->limit(self::MAX_RESULTS)
            ->get()
            ->unique('inventory_unit_id')
            ->map(fn (InventoryUnitIdentifierCorrection $correction): array => self::row($correction->unit, $correction->type, $correction->position, $correction->old_value, $correction->new_value))
            ->values()
            ->all();

        return ['current' => $current, 'historical' => $historical];
    }

    /**
     * Exact candidates for each identifier type.
     *
     * @return array{0: list<string>, 1: list<string>} IMEI candidates, serial candidates.
     */
    public static function candidates(string $input): array
    {
        $raw = Str::trim($input);

        if ($raw === '' || mb_strlen($raw) > self::MAX_INPUT_LENGTH) {
            return [[], []];
        }

        $latin = str_replace(self::ARABIC_DIGITS, self::LATIN_DIGITS, $raw);
        $serials = array_values(array_unique(array_filter([$raw, $latin], fn (string $value): bool => mb_strlen($value) <= UnitIdentity::MAX_SERIAL_LENGTH)));

        $imeis = [];
        $grouped = preg_replace('/[\s\-]+/u', '', $latin) ?? '';

        if (preg_match('/^\d{15}$/', $grouped) === 1) {
            $imeis[] = $grouped;
        }

        return [$imeis, $serials];
    }

    /**
     * @return array<string, mixed>
     */
    private static function row(mixed $unit, IdentifierType $type, int $position, string $matchedValue, ?string $currentValue): array
    {
        return [
            'unit_id' => $unit->id,
            'store_id' => $unit->store_id,
            'store_code' => $unit->store->code,
            'store_name' => $unit->store->name,
            'product_name' => $unit->productDefinition->name,
            'product_model' => $unit->productDefinition->model,
            'status' => $unit->status->value,
            'type' => $type->value,
            'position' => $position,
            'matched_value' => $matchedValue,
            'current_value' => $currentValue,
        ];
    }
}
