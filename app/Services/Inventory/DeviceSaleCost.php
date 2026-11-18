<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\Currency;
use App\Enums\DeviceCostCategory;
use App\Enums\SaleLineCostStatus;
use App\Models\DeviceCostEntry;
use App\Models\InventoryUnit;
use App\Models\PurchaseReceiptUnit;
use App\Models\SaleLine;
use App\Models\SaleLineCostFact;
use App\Services\Purchasing\Money;
use Illuminate\Support\Collection;

/**
 * Builds the frozen recorded-cost basis for one individually tracked unit at sale time.
 *
 * Included: TRY acquisition + non-reversed additional acquisition, repair parts, and repair labor.
 * “Other” is excluded with a warning. Missing acquisition or any unknown/incompatible included
 * amount makes the line unknown. Amounts are snapshotted; later reversals do not rewrite them.
 */
final class DeviceSaleCost
{
    public const BASIS = 'device_recorded';

    public const BASIS_VERSION = QuantityCostPool::BASIS_VERSION;

    /**
     * @return array{
     *     status: SaleLineCostStatus,
     *     currency: ?string,
     *     amount_minor: ?int,
     *     unknown_reason: ?string,
     *     facts: list<array<string, mixed>>
     * }
     */
    public static function snapshot(InventoryUnit $lockedUnit): array
    {
        $facts = [];
        $includedMinor = 0;
        $unknownReason = null;

        $acquisition = PurchaseReceiptUnit::query()
            ->where('inventory_unit_id', $lockedUnit->id)
            ->orderBy('id')
            ->first();

        if ($acquisition === null) {
            $facts[] = [
                'fact_type' => 'acquisition',
                'inclusion' => 'unknown',
                'category' => null,
                'currency' => null,
                'amount' => null,
                'purchase_receipt_unit_id' => null,
                'device_cost_entry_id' => null,
                'entry_reversed_at_sale' => false,
                'note' => 'missing_acquisition',
            ];
            $unknownReason = 'missing_acquisition';
        } elseif ($acquisition->currency !== Currency::TRY) {
            $facts[] = [
                'fact_type' => 'acquisition',
                'inclusion' => 'unknown',
                'category' => null,
                'currency' => $acquisition->currency->value,
                'amount' => $acquisition->unit_cost,
                'purchase_receipt_unit_id' => $acquisition->id,
                'device_cost_entry_id' => null,
                'entry_reversed_at_sale' => false,
                'note' => 'incompatible_currency',
            ];
            $unknownReason = 'incompatible_currency';
        } else {
            $facts[] = [
                'fact_type' => 'acquisition',
                'inclusion' => 'included',
                'category' => null,
                'currency' => Currency::TRY->value,
                'amount' => $acquisition->unit_cost,
                'purchase_receipt_unit_id' => $acquisition->id,
                'device_cost_entry_id' => null,
                'entry_reversed_at_sale' => false,
                'note' => null,
            ];
            $includedMinor += $acquisition->unitCostMinor();
        }

        /** @var Collection<int, DeviceCostEntry> $entries */
        $entries = DeviceCostEntry::query()
            ->where('inventory_unit_id', $lockedUnit->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        // Highest entry id seen at sale — laterActivity uses this instead of timestamps.
        $entryCursorId = $entries->max(fn (DeviceCostEntry $entry): int => (int) $entry->id);

        foreach ($entries as $entry) {
            if ($entry->isReversed()) {
                continue;
            }

            $category = $entry->category;
            $isOther = $category === DeviceCostCategory::OTHER;
            $includedCategory = in_array($category, [
                DeviceCostCategory::ADDITIONAL_ACQUISITION,
                DeviceCostCategory::REPAIR_PARTS,
                DeviceCostCategory::REPAIR_LABOR,
            ], true);

            if ($isOther) {
                $facts[] = [
                    'fact_type' => 'device_cost',
                    'inclusion' => 'excluded',
                    'category' => $category->value,
                    'currency' => $entry->currency?->value,
                    'amount' => $entry->amount,
                    'purchase_receipt_unit_id' => null,
                    'device_cost_entry_id' => $entry->id,
                    'entry_reversed_at_sale' => false,
                    'note' => 'other_excluded',
                ];

                continue;
            }

            if (! $includedCategory) {
                continue;
            }

            if ($entry->isUnknown()) {
                $facts[] = [
                    'fact_type' => 'device_cost',
                    'inclusion' => 'unknown',
                    'category' => $category->value,
                    'currency' => null,
                    'amount' => null,
                    'purchase_receipt_unit_id' => null,
                    'device_cost_entry_id' => $entry->id,
                    'entry_reversed_at_sale' => false,
                    'note' => 'unknown_amount',
                ];
                $unknownReason ??= 'unknown_included_amount';

                continue;
            }

            if ($entry->currency !== Currency::TRY) {
                $facts[] = [
                    'fact_type' => 'device_cost',
                    'inclusion' => 'unknown',
                    'category' => $category->value,
                    'currency' => $entry->currency?->value,
                    'amount' => $entry->amount,
                    'purchase_receipt_unit_id' => null,
                    'device_cost_entry_id' => $entry->id,
                    'entry_reversed_at_sale' => false,
                    'note' => 'incompatible_currency',
                ];
                $unknownReason ??= 'incompatible_currency';

                continue;
            }

            $facts[] = [
                'fact_type' => 'device_cost',
                'inclusion' => 'included',
                'category' => $category->value,
                'currency' => Currency::TRY->value,
                'amount' => $entry->amount,
                'purchase_receipt_unit_id' => null,
                'device_cost_entry_id' => $entry->id,
                'entry_reversed_at_sale' => false,
                'note' => null,
            ];
            $includedMinor += Money::fromDecimal($entry->amount);
        }

        if ($entryCursorId !== null) {
            $facts[] = [
                'fact_type' => 'entry_cursor',
                'inclusion' => 'excluded',
                'category' => null,
                'currency' => null,
                'amount' => null,
                'purchase_receipt_unit_id' => null,
                'device_cost_entry_id' => (int) $entryCursorId,
                'entry_reversed_at_sale' => false,
                'note' => 'entry_cursor',
            ];
        }

        if ($unknownReason !== null) {
            return [
                'status' => SaleLineCostStatus::UNKNOWN,
                'currency' => null,
                'amount_minor' => null,
                'unknown_reason' => $unknownReason,
                'facts' => $facts,
            ];
        }

        return [
            'status' => SaleLineCostStatus::KNOWN,
            'currency' => Currency::TRY->value,
            'amount_minor' => $includedMinor,
            'unknown_reason' => null,
            'facts' => $facts,
        ];
    }

    /**
     * Compare frozen sale facts with current device-cost rows for owner drill-down.
     *
     * Uses the frozen entry_cursor / snapshotted entry ids — not wall-clock equality — so
     * same-second events stay ordered. Pre-sale reversals are not labeled as post-sale costs.
     *
     * @param  Collection<int, SaleLineCostFact>|null  $facts
     * @param  Collection<int, DeviceCostEntry>|null  $entries
     * @return array{
     *     snapshot: list<array<string, mixed>>,
     *     later: list<array<string, mixed>>
     * }
     */
    public static function laterActivity(
        SaleLine $saleLine,
        ?Collection $facts = null,
        ?Collection $entries = null,
    ): array {
        $factRows = $facts ?? $saleLine->costFacts()->orderBy('id')->get();

        $snapshot = [];
        $snapshottedEntryIds = [];
        $cursorId = 0;

        foreach ($factRows as $fact) {
            if ($fact->fact_type === 'entry_cursor') {
                $cursorId = max($cursorId, (int) ($fact->device_cost_entry_id ?? 0));

                continue;
            }

            if ($fact->device_cost_entry_id !== null) {
                $snapshottedEntryIds[] = (int) $fact->device_cost_entry_id;
            }

            $snapshot[] = [
                'kind' => 'snapshot',
                'inclusion' => $fact->inclusion,
                'fact_type' => $fact->fact_type,
                'category' => $fact->category instanceof DeviceCostCategory
                    ? $fact->category->value
                    : ($fact->category !== null ? (string) $fact->category : null),
                'amount' => $fact->amount,
                'currency' => $fact->currency?->value,
                'device_cost_entry_id' => $fact->device_cost_entry_id,
                'purchase_receipt_unit_id' => $fact->purchase_receipt_unit_id,
                'note' => $fact->note,
            ];
        }

        $unitId = $saleLine->inventory_unit_id;

        if ($unitId === null) {
            return ['snapshot' => $snapshot, 'later' => []];
        }

        if ($cursorId === 0 && $snapshottedEntryIds !== []) {
            $cursorId = max($snapshottedEntryIds);
        }

        $currentEntries = $entries ?? DeviceCostEntry::query()
            ->where('inventory_unit_id', $unitId)
            ->orderBy('id')
            ->get();

        $later = [];

        foreach ($currentEntries as $entry) {
            $wasSnapshotted = in_array($entry->id, $snapshottedEntryIds, true);

            if ($wasSnapshotted) {
                if ($entry->isReversed()) {
                    $later[] = [
                        'kind' => 'reversed_after_sale',
                        'device_cost_entry_id' => $entry->id,
                        'category' => $entry->category->value,
                        'amount' => $entry->amount,
                        'currency' => $entry->currency?->value,
                    ];
                }

                continue;
            }

            // Ids above the frozen cursor did not exist at sale completion.
            if ((int) $entry->id <= $cursorId) {
                continue;
            }

            $later[] = [
                'kind' => $entry->isReversed() ? 'recorded_after_sale_reversed' : 'recorded_after_sale',
                'device_cost_entry_id' => $entry->id,
                'category' => $entry->category->value,
                'amount' => $entry->amount,
                'currency' => $entry->currency?->value,
            ];
        }

        return ['snapshot' => $snapshot, 'later' => $later];
    }
}
