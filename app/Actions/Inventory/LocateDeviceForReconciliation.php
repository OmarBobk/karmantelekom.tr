<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Enums\IdentifierType;
use App\Models\InventoryUnit;
use App\Models\InventoryUnitIdentifier;
use App\Models\ProductDefinition;
use App\Models\Store;
use App\Rules\ValidImei;
use Illuminate\Database\Eloquent\Builder;

/**
 * Finds the exact device a counted IMEI or serial refers to, from one branch's point of view.
 *
 * IMEIs are matched system-wide and serials within the product definition, the same scopes as
 * identity uniqueness, so "not found" means the identifier can be recorded. A device held by
 * another branch is reported without any of its details. When several of this branch's devices
 * of the product match, none is chosen: the caller must pick one of the candidates explicitly.
 */
final class LocateDeviceForReconciliation
{
    public const UNIT = 'unit';

    public const AMBIGUOUS = 'ambiguous';

    public const OTHER_PRODUCT = 'other_product';

    public const OTHER_BRANCH = 'other_branch';

    public const NOT_FOUND = 'not_found';

    public const INVALID = 'invalid';

    /**
     * @return array{outcome: string, unit: InventoryUnit|null, candidates: list<int>, imei: string|null, serial: string|null}
     */
    public function __invoke(Store $store, ProductDefinition $definition, string $code): array
    {
        [$imeis, $serials] = FindUnitsByIdentifier::candidates($code);

        if ($imeis === [] && $serials === []) {
            return ['outcome' => self::INVALID, 'unit' => null, 'candidates' => [], 'imei' => null, 'serial' => null];
        }

        $units = InventoryUnit::query()
            ->whereIn('id', InventoryUnitIdentifier::query()
                ->select('inventory_unit_id')
                ->where(fn (Builder $query) => $query
                    ->where(fn (Builder $imei) => $imei->where('type', IdentifierType::IMEI)->whereIn('value', $imeis ?: ['']))
                    ->orWhere(fn (Builder $serial) => $serial->where('type', IdentifierType::SERIAL)->where('product_definition_id', $definition->id)->whereIn('value', $serials ?: ['']))))
            ->get(['id', 'store_id', 'product_definition_id', 'status', 'lifecycle_revision']);

        $inBranch = $units->where('store_id', $store->id);
        $matches = $inBranch->where('product_definition_id', $definition->id)->sortBy('id')->values();

        if ($matches->count() > 1) {
            return ['outcome' => self::AMBIGUOUS, 'unit' => null, 'candidates' => $matches->pluck('id')->all(), 'imei' => null, 'serial' => null];
        }

        if ($matches->isNotEmpty()) {
            return ['outcome' => self::UNIT, 'unit' => $matches->first()->load('identifiers'), 'candidates' => [], 'imei' => null, 'serial' => null];
        }

        if ($inBranch->isNotEmpty()) {
            return ['outcome' => self::OTHER_PRODUCT, 'unit' => $inBranch->first()->load('identifiers', 'productDefinition:id,name,model'), 'candidates' => [], 'imei' => null, 'serial' => null];
        }

        if ($units->isNotEmpty()) {
            return ['outcome' => self::OTHER_BRANCH, 'unit' => null, 'candidates' => [], 'imei' => null, 'serial' => null];
        }

        $imei = $imeis !== [] && ValidImei::isValid($imeis[0]) ? $imeis[0] : null;

        return ['outcome' => self::NOT_FOUND, 'unit' => null, 'candidates' => [], 'imei' => $imei, 'serial' => $imei === null ? ($serials[0] ?? null) : null];
    }
}
