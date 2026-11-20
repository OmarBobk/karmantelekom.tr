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

    /** Soft cap for the counter's in-memory product index; beyond this, Alpine still asks the server. */
    public const PRODUCT_INDEX_LIMIT = 5000;

    public const SUGGESTION_LIMIT = 8;

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
     * Compact active catalog rows for local counter suggestions (no stock or device rows).
     *
     * @return array{items: list<array{id: int, name: string, model: ?string, sku: ?string, barcode: ?string}>, truncated: bool}
     *
     * @throws AuthorizationException
     */
    public function productIndex(User $actor, Store $store, int $limit = self::PRODUCT_INDEX_LIMIT): array
    {
        Gate::forUser($actor)->authorize('usePos', $store);

        $limit = max(1, $limit);
        $definitions = ProductDefinition::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->orderBy('id')
            ->limit($limit + 1)
            ->get(['id', 'name', 'model', 'sku', 'barcode']);

        $truncated = $definitions->count() > $limit;
        $items = $definitions->take($limit)->map(fn (ProductDefinition $definition): array => $this->suggestionRow($definition))->values()->all();

        return ['items' => $items, 'truncated' => $truncated];
    }

    /**
     * Lightweight product suggestions: identity and match reason only (no stock, price, or units).
     *
     * Exact barcode and SKU matches keep leading zeros and stay case-sensitive for SKU. Name and
     * model use a contains match so Arabic and English typing both work.
     *
     * @return list<array{id: int, name: string, model: ?string, sku: ?string, barcode: ?string, match: string}>
     *
     * @throws AuthorizationException
     */
    public function suggest(User $actor, Store $store, string $term, int $limit = self::SUGGESTION_LIMIT): array
    {
        Gate::forUser($actor)->authorize('usePos', $store);

        $term = Str::trim($term);

        if (mb_strlen($term) < 2 || mb_strlen($term) > 100) {
            return [];
        }

        $limit = max(1, $limit);
        $candidates = array_values(array_unique([$term, str_replace(self::ARABIC_DIGITS, self::LATIN_DIGITS, $term)]));
        $found = [];

        foreach ([CatalogIdentifierType::BARCODE, CatalogIdentifierType::SKU] as $type) {
            foreach (ProductDefinition::query()->where('is_active', true)->whereIn($type->column(), $candidates)->orderBy('name')->orderBy('id')->get(['id', 'name', 'model', 'sku', 'barcode']) as $definition) {
                $found[$definition->id] = [...$this->suggestionRow($definition), 'match' => $type->value];
            }
        }

        if (count($found) < $limit) {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $named = ProductDefinition::query()
                ->where('is_active', true)
                ->where(fn ($query) => $query->where('name', 'like', $like)->orWhere('model', 'like', $like))
                ->orderBy('name')
                ->orderBy('id')
                ->limit($limit)
                ->get(['id', 'name', 'model', 'sku', 'barcode']);

            foreach ($named as $definition) {
                if (isset($found[$definition->id])) {
                    continue;
                }

                $found[$definition->id] = [...$this->suggestionRow($definition), 'match' => 'name'];

                if (count($found) >= $limit) {
                    break;
                }
            }
        }

        return array_values(array_slice($found, 0, $limit));
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
     * Sellable targets for an explicit product suggestion click, re-checked on the server.
     *
     * @return list<array{kind: string, match: string, definition_id: int, unit_id: ?int, name: string, model: ?string, identifier: ?string, condition: ?string, status: ?string, available: bool, price_minor: ?int, stock: ?int}>
     *
     * @throws AuthorizationException
     */
    public function forDefinition(User $actor, Store $store, int $definitionId): array
    {
        Gate::forUser($actor)->authorize('usePos', $store);

        $definition = ProductDefinition::query()->find($definitionId);

        if ($definition === null) {
            return [];
        }

        if ($definition->tracking_mode === TrackingMode::QUANTITY) {
            return [$this->definitionMatch($store, $definition, 'choice')];
        }

        $matches = [];

        foreach ($this->availableUnits($store, $definition) as $unit) {
            $matches[] = $this->unitMatch($definition, $unit, 'choice');
        }

        if ($matches === []) {
            $matches[] = [...$this->definitionMatch($store, $definition, 'choice'), 'available' => false];
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
     * @return array{id: int, name: string, model: ?string, sku: ?string, barcode: ?string}
     */
    private function suggestionRow(ProductDefinition $definition): array
    {
        return [
            'id' => $definition->id,
            'name' => $definition->name,
            'model' => $definition->model,
            'sku' => $definition->sku === null ? null : (string) $definition->sku,
            'barcode' => $definition->barcode === null ? null : (string) $definition->barcode,
        ];
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
