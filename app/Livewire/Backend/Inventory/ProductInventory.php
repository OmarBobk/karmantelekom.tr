<?php

declare(strict_types=1);

namespace App\Livewire\Backend\Inventory;

use App\Actions\Inventory\FindUnitsByIdentifier;
use App\Actions\Inventory\InventoryLedger;
use App\Actions\Inventory\LocateDeviceForReconciliation;
use App\Actions\Inventory\MarkUnitFound;
use App\Actions\Inventory\RecordFoundUnit;
use App\Actions\Inventory\RecordOpeningBalance;
use App\Actions\Inventory\RecordStockCount;
use App\Actions\Inventory\RegisterOpeningUnit;
use App\Actions\Inventory\RemoveUnit;
use App\Actions\Storefront\SetStorefrontQuantity;
use App\Enums\AdjustmentReason;
use App\Enums\UnitCondition;
use App\Enums\UnitStatus;
use App\Exceptions\Inventory\StaleUnitStateException;
use App\Livewire\Backend\Concerns\SubmitsInventoryChanges;
use App\Livewire\Backend\Concerns\UsesOperationalLocale;
use App\Models\InventoryMovement;
use App\Models\ProductDefinition;
use App\Models\Purchase;
use App\Models\PurchaseReceiptLine;
use App\Models\SaleLine;
use App\Models\Store;
use App\Models\StorefrontAllocation;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * One product definition's stock in one store: quantity or units, and movement history.
 */
class ProductInventory extends Component
{
    use SubmitsInventoryChanges;
    use UsesOperationalLocale;
    use WithPagination;

    #[Locked]
    public Store $store;

    #[Locked]
    public ProductDefinition $definition;

    public string $openingQuantity = '';

    public string $openingNote = '';

    public bool $countInProgress = false;

    /**
     * Latest movement for this store and product when the count started; null when there was none.
     */
    #[Locked]
    public ?int $countStartedAfterMovementId = null;

    public string $countedQuantity = '';

    public string $countReason = '';

    public string $unitCondition = '';

    public string $imei = '';

    public string $secondaryImei = '';

    public string $serial = '';

    public string $unitNote = '';

    public string $unitStatusFilter = 'available';

    public string $reconcileCode = '';

    /**
     * Result of the last reconciliation lookup; one of the LocateDeviceForReconciliation outcomes.
     */
    #[Locked]
    public ?string $reconcileOutcome = null;

    #[Locked]
    public ?int $reconcileUnitId = null;

    /**
     * Lifecycle revision of the looked-up device; a change before submission makes the action stale.
     */
    #[Locked]
    public ?int $reconcileRevision = null;

    /**
     * This branch's devices of the product that matched an ambiguous lookup; one must be chosen.
     *
     * @var list<int>
     */
    #[Locked]
    public array $reconcileCandidateIds = [];

    public string $reconcileReason = '';

    public string $foundCondition = '';

    public string $foundImei = '';

    public string $foundSecondaryImei = '';

    public string $foundSerial = '';

    /**
     * Online quantity this branch offers on the storefront; a ceiling against on-hand stock.
     */
    public string $storefrontQuantity = '0';

    public function mount(Store $store, ProductDefinition $productDefinition): void
    {
        $this->authorize('viewInventory', $store);

        $this->store = $store;
        $this->definition = $productDefinition;
        $this->unitCondition = UnitCondition::allowedFor($productDefinition->tracking_mode)[0]->value ?? '';
        $this->foundCondition = $this->unitCondition;
        $this->storefrontQuantity = (string) $this->storefrontAllocation();
    }

    public function saveStorefrontQuantity(SetStorefrontQuantity $setStorefrontQuantity): void
    {
        $this->authorize('allocateStorefrontStock', $this->store);

        $this->validate(
            ['storefrontQuantity' => ['required', 'integer', 'min:0', 'max:'.StorefrontAllocation::MAX_QUANTITY]],
            attributes: ['storefrontQuantity' => __('storefront.allocation.quantity')],
        );

        $setStorefrontQuantity(auth()->user(), $this->store, $this->definition->id, (int) $this->storefrontQuantity);

        $this->storefrontQuantity = (string) $this->storefrontAllocation();
        $this->dispatch('notify', ['type' => 'success', 'message' => __('storefront.allocation.quantity_saved')]);
    }

    private function storefrontAllocation(): int
    {
        return (int) StorefrontAllocation::query()
            ->where('store_id', $this->store->id)
            ->where('product_definition_id', $this->definition->id)
            ->whereNull('inventory_unit_id')
            ->value('quantity');
    }

    public function updatedUnitStatusFilter(): void
    {
        $this->resetPage('units');
    }

    public function recordOpeningBalance(RecordOpeningBalance $recordOpeningBalance): void
    {
        $this->authorize('adjustInventory', $this->store);

        $this->validate(
            ['openingQuantity' => ['required', 'integer', 'min:1', 'max:'.RecordOpeningBalance::MAX_QUANTITY]],
            attributes: ['openingQuantity' => __('inventory.fields.quantity')],
        );

        $movement = $this->submitInventoryChange(
            fn () => $recordOpeningBalance(
                auth()->user(),
                $this->store,
                $this->definition,
                (int) $this->openingQuantity,
                $this->openingNote,
                $this->submissionKey,
            ),
            ['quantity' => 'openingQuantity', 'note' => 'openingNote'],
            'openingQuantity',
        );

        if ($movement !== null) {
            $this->reset('openingQuantity', 'openingNote');
        }
    }

    /**
     * Tie a new physical count to the current stock state. Restarting discards a stale count.
     */
    public function startCount(): void
    {
        $this->authorize('adjustInventory', $this->store);

        $this->countStartedAfterMovementId = RecordStockCount::latestMovementId($this->store, $this->definition);
        $this->countInProgress = true;
        $this->reset('countedQuantity');
        $this->resetErrorBag();
        $this->renewSubmissionKey();
    }

    public function cancelCount(): void
    {
        $this->reset('countInProgress', 'countStartedAfterMovementId', 'countedQuantity', 'countReason');
        $this->resetErrorBag();
    }

    public function recordCount(RecordStockCount $recordStockCount): void
    {
        $this->authorize('adjustInventory', $this->store);

        if (! $this->countInProgress) {
            return;
        }

        $this->validate([
            'countedQuantity' => ['required', 'integer', 'min:0', 'max:'.RecordStockCount::MAX_QUANTITY],
            'countReason' => ['required', 'string', 'max:'.InventoryLedger::MAX_NOTE_LENGTH],
        ], attributes: [
            'countedQuantity' => __('inventory.fields.counted_quantity'),
            'countReason' => __('inventory.fields.reason'),
        ]);

        $this->submitInventoryChange(
            fn () => $recordStockCount(
                auth()->user(),
                $this->store,
                $this->definition,
                (int) $this->countedQuantity,
                $this->countStartedAfterMovementId,
                $this->countReason,
                $this->submissionKey,
            ),
            ['counted_quantity' => 'countedQuantity', 'note' => 'countReason', 'count' => 'count'],
            'countedQuantity',
            __('inventory.messages.count_matches'),
        );

        if ($this->getErrorBag()->isEmpty()) {
            $this->cancelCount();
        }
    }

    public function registerOpeningUnit(RegisterOpeningUnit $registerOpeningUnit): void
    {
        $this->authorize('adjustInventory', $this->store);

        $condition = UnitCondition::tryFrom($this->unitCondition);

        if ($condition === null) {
            $this->addError('unitCondition', __('inventory.errors.condition_not_allowed'));

            return;
        }

        $movement = $this->submitInventoryChange(
            fn () => $registerOpeningUnit(
                auth()->user(),
                $this->store,
                $this->definition,
                $condition,
                $this->imei,
                $this->secondaryImei,
                $this->serial,
                $this->unitNote,
                $this->submissionKey,
            ),
            ['imei' => 'imei', 'secondary_imei' => 'secondaryImei', 'serial' => 'serial', 'condition' => 'unitCondition', 'note' => 'unitNote'],
            'imei',
        );

        if ($movement !== null) {
            $this->reset('imei', 'secondaryImei', 'serial', 'unitNote');
        }
    }

    /**
     * Look up the exact device a counted IMEI or serial refers to and remember its revision.
     */
    public function findDevice(LocateDeviceForReconciliation $locate): void
    {
        $this->authorize('adjustInventory', $this->store);

        $this->validate(
            ['reconcileCode' => ['required', 'string', 'max:'.FindUnitsByIdentifier::MAX_INPUT_LENGTH]],
            attributes: ['reconcileCode' => __('inventory.reconcile.code')],
        );

        $result = $locate($this->store, $this->definition, $this->reconcileCode);

        $this->resetErrorBag();
        $this->reset('reconcileReason', 'foundSecondaryImei');
        $this->reconcileOutcome = $result['outcome'];
        $this->reconcileUnitId = $result['unit']?->id;
        $this->reconcileRevision = $result['unit']?->lifecycle_revision;
        $this->reconcileCandidateIds = $result['candidates'];
        $this->foundImei = $result['imei'] ?? '';
        $this->foundSerial = $result['serial'] ?? '';
        $this->renewSubmissionKey();
    }

    /**
     * Pick the exact device after an ambiguous lookup; only the offered candidates are accepted.
     */
    public function chooseDevice(int $unitId): void
    {
        $this->authorize('adjustInventory', $this->store);

        if ($this->reconcileOutcome !== LocateDeviceForReconciliation::AMBIGUOUS || ! in_array($unitId, $this->reconcileCandidateIds, true)) {
            return;
        }

        $unit = $this->store->units()->where('product_definition_id', $this->definition->id)->find($unitId);

        if ($unit === null) {
            $this->clearReconciliation();

            return;
        }

        $this->resetErrorBag();
        $this->reset('reconcileReason', 'reconcileCandidateIds');
        $this->reconcileOutcome = LocateDeviceForReconciliation::UNIT;
        $this->reconcileUnitId = $unit->id;
        $this->reconcileRevision = $unit->lifecycle_revision;
        $this->renewSubmissionKey();
    }

    public function clearReconciliation(): void
    {
        $this->reset('reconcileCode', 'reconcileOutcome', 'reconcileUnitId', 'reconcileRevision', 'reconcileCandidateIds', 'reconcileReason', 'foundImei', 'foundSecondaryImei', 'foundSerial');
        $this->foundCondition = $this->unitCondition;
        $this->resetErrorBag();
    }

    /**
     * The looked-up available or in-inspection device is not on the shelf: remove it as lost.
     */
    public function markMissing(RemoveUnit $removeUnit): void
    {
        $this->authorize('adjustInventory', $this->store);
        $this->reconcileUnit(fn (): InventoryMovement => $removeUnit(
            auth()->user(),
            $this->store,
            $this->reconcileUnitId,
            AdjustmentReason::LOST,
            $this->reconcileReason,
            $this->submissionKey,
            $this->reconcileRevision,
        ));
    }

    /**
     * The looked-up removed device is on the shelf: return it to stock awaiting review.
     */
    public function markFound(MarkUnitFound $markUnitFound): void
    {
        $this->authorize('adjustInventory', $this->store);
        $this->reconcileUnit(fn (): InventoryMovement => $markUnitFound(
            auth()->user(),
            $this->store,
            $this->reconcileUnitId,
            $this->reconcileRevision,
            $this->reconcileReason,
            $this->submissionKey,
        ));
    }

    /**
     * A device with no record anywhere is on the shelf: record it awaiting review.
     */
    public function recordFoundDevice(RecordFoundUnit $recordFoundUnit): void
    {
        $this->authorize('adjustInventory', $this->store);

        if ($this->reconcileOutcome !== LocateDeviceForReconciliation::NOT_FOUND) {
            return;
        }

        $condition = UnitCondition::tryFrom($this->foundCondition);

        if ($condition === null) {
            $this->addError('foundCondition', __('inventory.errors.condition_not_allowed'));

            return;
        }

        $movement = $this->submitInventoryChange(
            fn () => $recordFoundUnit(
                auth()->user(),
                $this->store,
                $this->definition,
                $condition,
                $this->foundImei,
                $this->foundSecondaryImei,
                $this->foundSerial,
                $this->reconcileReason,
                $this->submissionKey,
            ),
            ['imei' => 'foundImei', 'secondary_imei' => 'foundSecondaryImei', 'serial' => 'foundSerial', 'condition' => 'foundCondition', 'note' => 'reconcileReason'],
            'reconcileReason',
        );

        if ($movement !== null) {
            $this->clearReconciliation();
        }
    }

    /**
     * @param  Closure(): InventoryMovement  $action
     */
    private function reconcileUnit(Closure $action): void
    {
        if ($this->reconcileOutcome !== LocateDeviceForReconciliation::UNIT || $this->reconcileUnitId === null || $this->reconcileRevision === null) {
            return;
        }

        try {
            $movement = $this->submitInventoryChange($action, ['note' => 'reconcileReason', 'reason' => 'reconcileReason', 'unit' => 'reconcileReason'], 'reconcileReason');
        } catch (StaleUnitStateException $exception) {
            $this->addError('reconcileReason', __('inventory.reconcile.stale', ['status' => $exception->currentStatus->label()]));

            return;
        }

        if ($movement !== null) {
            $this->clearReconciliation();
        }
    }

    #[Layout('layouts.backend')]
    public function render(): View
    {
        $this->authorize('viewInventory', $this->store);

        $definition = $this->definition->refresh()->load(['brand:id,name', 'category:id,name']);
        $tracksUnits = $definition->tracking_mode->tracksIndividualItems();

        $canViewPurchases = Gate::allows('viewAny', Purchase::class);

        $movements = $this->store->inventoryMovements()
            ->where('product_definition_id', $definition->id)
            ->where('quantity_change', '<>', 0)
            ->with(['actor:id,name', 'unit.identifiers', 'reference' => fn (MorphTo $reference) => $reference->morphWith([
                PurchaseReceiptLine::class => ['receipt.purchase:id,supplier_invoice_number'],
                SaleLine::class => ['sale:id,store_id,user_id,number'],
            ])])
            ->latest('occurred_at')
            ->latest('id')
            ->paginate(20, pageName: 'history');

        $units = $tracksUnits
            ? $this->store->units()
                ->where('product_definition_id', $definition->id)
                ->when($this->unitStatusFilter !== 'all', fn ($query) => $query->whereIn('status', $this->unitStatusesForFilter()))
                ->with('identifiers')
                ->latest('id')
                ->paginate(25, pageName: 'units')
            : null;

        return view('livewire.backend.inventory.product-inventory', [
            'product' => $definition,
            'tracksUnits' => $tracksUnits,
            'quantity' => (int) ($this->store->inventoryBalances()->where('product_definition_id', $definition->id)->value('quantity') ?? 0),
            'availableUnits' => $tracksUnits
                ? $this->store->units()->where('product_definition_id', $definition->id)->where('status', UnitStatus::AVAILABLE)->count()
                : 0,
            'hasStoreHistory' => $movements->total() > 0,
            'movements' => $movements,
            'units' => $units,
            'canAdjust' => Gate::allows('adjustInventory', $this->store),
            'canAllocateStorefront' => ! $tracksUnits && Gate::allows('allocateStorefrontStock', $this->store),
            'storefrontAllocated' => $tracksUnits ? 0 : $this->storefrontAllocation(),
            'setupOpen' => $this->store->refresh()->inventorySetupIsOpen(),
            'reconcileUnit' => $tracksUnits && $this->reconcileUnitId !== null
                ? $this->store->units()->with(['identifiers', 'productDefinition:id,name,model'])->find($this->reconcileUnitId)
                : null,
            'reconcileCandidates' => $tracksUnits && $this->reconcileCandidateIds !== []
                ? $this->store->units()->where('product_definition_id', $definition->id)->with('identifiers')->whereKey($this->reconcileCandidateIds)->orderBy('id')->get()
                : collect(),
            'conditions' => UnitCondition::allowedFor($definition->tracking_mode),
            'canViewPurchases' => $canViewPurchases,
            'lastPurchase' => $canViewPurchases ? $this->lastPurchaseLine($definition) : null,
            ...$this->localeViewData(),
        ])->title($definition->name.' — '.$this->store->code);
    }

    /**
     * @return list<UnitStatus>
     */
    private function unitStatusesForFilter(): array
    {
        return match ($this->unitStatusFilter) {
            'in_service' => [UnitStatus::INSPECTION, UnitStatus::IN_REPAIR],
            'review' => [UnitStatus::REVIEW],
            'in_transit' => [UnitStatus::IN_TRANSIT],
            'removed' => [UnitStatus::REMOVED],
            'sold' => [UnitStatus::SOLD],
            default => [UnitStatus::AVAILABLE],
        };
    }

    /**
     * The most recent receipt line for this product into this store, with its invoice currency.
     */
    private function lastPurchaseLine(ProductDefinition $definition): ?PurchaseReceiptLine
    {
        return PurchaseReceiptLine::query()
            ->where('product_definition_id', $definition->id)
            ->whereHas('receipt', fn (Builder $receipt) => $receipt->where('store_id', $this->store->id))
            ->with('receipt.purchase:id,supplier_invoice_number,currency')
            ->latest('id')
            ->first();
    }
}
