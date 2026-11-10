<?php

declare(strict_types=1);

namespace App\Actions\Pos;

use App\Actions\Inventory\FindUnitsByIdentifier;
use App\Actions\Storefront\BranchOrderHolds;
use App\Enums\CatalogIdentifierType;
use App\Enums\TrackingMode;
use App\Enums\UnitStatus;
use App\Models\InventoryUnit;
use App\Models\ProductDefinition;
use App\Models\Store;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Resolve one scanned or typed counter code inside a single store.
 *
 * Every identifier type is checked: definition barcode and SKU, and the store's units by
 * either IMEI or serial. Nothing is picked when several targets match, and a serialized or
 * used-device product scanned by barcode or SKU always needs an explicit unit. Units held
 * by other stores are never returned.
 */
final class ResolveCounterCode
{
    public const MAX_UNITS_PER_PRODUCT = 20;

    private const ARABIC_DIGITS = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩', '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

    private const LATIN_DIGITS = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

    public function __construct(private readonly FindUnitsByIdentifier $findUnits) {}

    /**
     * @return list<array{kind: string, match: string, definition_id: int, unit_id: ?int, name: string, model: ?string, identifier: ?string, condition: ?string, status: ?string, available: bool, price_minor: ?int, stock: ?int}>
     *
     * @throws AuthorizationException
     */
    public function __invoke(User $actor, Store $store, string $input): array
    {
        Gate::forUser($actor)->authorize('usePos', $store);

        $raw = Str::trim($input);

        if ($raw === '' || mb_strlen($raw) > FindUnitsByIdentifier::MAX_INPUT_LENGTH) {
            return [];
        }

        $candidates = array_values(array_unique([$raw, str_replace(self::ARABIC_DIGITS, self::LATIN_DIGITS, $raw)]));
        $matches = [];
        $seenUnits = [];

        foreach ([CatalogIdentifierType::BARCODE, CatalogIdentifierType::SKU] as $type) {
            $definitions = ProductDefinition::query()->whereIn($type->column(), $candidates)->get();

            foreach ($definitions as $definition) {
                if ($definition->tracking_mode === TrackingMode::QUANTITY) {
                    $matches[] = $this->definitionMatch($store, $definition, $type->value);

                    continue;
                }

                foreach ($this->availableUnits($store, $definition) as $unit) {
                    $seenUnits[$unit->id] = true;
                    $matches[] = $this->unitMatch($definition, $unit, $type->value);
                }

                if (! $definition->is_active || ! $this->hasAvailableUnits($store, $definition)) {
                    $matches[] = [...$this->definitionMatch($store, $definition, $type->value), 'available' => false];
                }
            }
        }

        $found = ($this->findUnits)($actor, $raw, $store);

        foreach ($found['current'] as $row) {
            if (isset($seenUnits[$row['unit_id']])) {
                continue;
            }

            $unit = $store->units()->with(['productDefinition', 'identifiers'])->whereKey($row['unit_id'])->first();

            if ($unit !== null) {
                $seenUnits[$unit->id] = true;
                $matches[] = $this->unitMatch($unit->productDefinition, $unit, $row['type']);
            }
        }

        return $matches;
    }

    /**
     * Active products whose name or model contains the term, as sellable targets in this store.
     *
     * @return list<array{kind: string, match: string, definition_id: int, unit_id: ?int, name: string, model: ?string, identifier: ?string, condition: ?string, status: ?string, available: bool, price_minor: ?int, stock: ?int}>
     *
     * @throws AuthorizationException
     */
    public function search(User $actor, Store $store, string $term, int $limit = 8): array
    {
        Gate::forUser($actor)->authorize('usePos', $store);

        $term = Str::trim($term);

        if (mb_strlen($term) < 2 || mb_strlen($term) > 100) {
            return [];
        }

        $like = '%'.addcslashes($term, '%_\\').'%';
        $definitions = ProductDefinition::query()
            ->where('is_active', true)
            ->where(fn ($query) => $query->where('name', 'like', $like)->orWhere('model', 'like', $like))
            ->orderBy('name')
            ->limit($limit)
            ->get();

        $matches = [];

        foreach ($definitions as $definition) {
            if ($definition->tracking_mode === TrackingMode::QUANTITY) {
                $matches[] = $this->definitionMatch($store, $definition, 'name');

                continue;
            }

            $units = $this->availableUnits($store, $definition);

            foreach ($units as $unit) {
                $matches[] = $this->unitMatch($definition, $unit, 'name');
            }

            if ($units->isEmpty()) {
                $matches[] = [...$this->definitionMatch($store, $definition, 'name'), 'available' => false];
            }
        }

        return $matches;
    }

    /**
     * The sellable target for an explicit choice, re-read on the server.
     *
     * @return array{kind: string, match: string, definition_id: int, unit_id: ?int, name: string, model: ?string, identifier: ?string, condition: ?string, status: ?string, available: bool, price_minor: ?int, stock: ?int}|null
     *
     * @throws AuthorizationException
     */
    public function target(User $actor, Store $store, int $definitionId, ?int $unitId): ?array
    {
        Gate::forUser($actor)->authorize('usePos', $store);

        $definition = ProductDefinition::query()->find($definitionId);

        if ($definition === null) {
            return null;
        }

        if ($definition->tracking_mode === TrackingMode::QUANTITY) {
            return $unitId === null ? $this->definitionMatch($store, $definition, 'choice') : null;
        }

        $unit = $unitId === null ? null : $store->units()->with('identifiers')->whereKey($unitId)->where('product_definition_id', $definition->id)->first();

        return $unit === null ? null : $this->unitMatch($definition, $unit, 'choice');
    }

    /**
     * @return array{kind: string, match: string, definition_id: int, unit_id: ?int, name: string, model: ?string, identifier: ?string, condition: ?string, status: ?string, available: bool, price_minor: ?int, stock: ?int}
     */
    private function definitionMatch(Store $store, ProductDefinition $definition, string $match): array
    {
        $stock = $definition->tracking_mode === TrackingMode::QUANTITY
            ? max(0, (int) ($store->inventoryBalances()->where('product_definition_id', $definition->id)->value('quantity') ?? 0)
                - (BranchOrderHolds::heldQuantities($store->id, [$definition->id])[$definition->id] ?? 0))
            : null;

        return [
            'kind' => 'definition',
            'match' => $match,
            'definition_id' => $definition->id,
            'unit_id' => null,
            'name' => $definition->name,
            'model' => $definition->model,
            'identifier' => null,
            'condition' => null,
            'status' => $definition->is_active ? null : 'inactive',
            'available' => $definition->is_active && ($stock === null || $stock > 0),
            'price_minor' => SellingPrice::listPriceMinor($definition, null),
            'stock' => $stock,
        ];
    }

    /**
     * @return array{kind: string, match: string, definition_id: int, unit_id: ?int, name: string, model: ?string, identifier: ?string, condition: ?string, status: ?string, available: bool, price_minor: ?int, stock: ?int}
     */
    private function unitMatch(ProductDefinition $definition, InventoryUnit $unit, string $match): array
    {
        return [
            'kind' => 'unit',
            'match' => $match,
            'definition_id' => $definition->id,
            'unit_id' => $unit->id,
            'name' => $definition->name,
            'model' => $definition->model,
            'identifier' => $unit->primaryIdentifierValue(),
            'condition' => $unit->condition->value,
            'status' => $unit->status->value,
            'available' => $definition->is_active && $unit->status === UnitStatus::AVAILABLE,
            'price_minor' => SellingPrice::listPriceMinor($definition, $unit),
            'stock' => null,
        ];
    }

    /**
     * @return Collection<int, InventoryUnit>
     */
    private function availableUnits(Store $store, ProductDefinition $definition): Collection
    {
        if (! $definition->is_active) {
            return new Collection;
        }

        return $store->units()
            ->where('product_definition_id', $definition->id)
            ->where('status', UnitStatus::AVAILABLE)
            ->with('identifiers')
            ->orderBy('id')
            ->limit(self::MAX_UNITS_PER_PRODUCT)
            ->get();
    }

    private function hasAvailableUnits(Store $store, ProductDefinition $definition): bool
    {
        return $store->units()->where('product_definition_id', $definition->id)->where('status', UnitStatus::AVAILABLE)->exists();
    }
}
