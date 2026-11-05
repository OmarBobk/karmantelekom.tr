<?php

declare(strict_types=1);

namespace App\Livewire\Backend\Inventory;

use App\Actions\Inventory\CloseRepair;
use App\Actions\Inventory\CorrectUnitIdentifier;
use App\Actions\Inventory\DeviceCostInput;
use App\Actions\Inventory\RecordDeviceCost;
use App\Actions\Inventory\RecordInspection;
use App\Actions\Inventory\RemoveUnit;
use App\Actions\Inventory\ReverseDeviceCost;
use App\Actions\Inventory\StartRepair;
use App\Actions\Inventory\UnitLifecycle;
use App\Enums\AdjustmentReason;
use App\Enums\Currency;
use App\Enums\DeviceCostCategory;
use App\Enums\InspectionOutcome;
use App\Enums\MovementType;
use App\Enums\RepairerType;
use App\Enums\RepairStatus;
use App\Enums\TrackingMode;
use App\Enums\UnitCondition;
use App\Enums\UnitStatus;
use App\Exceptions\Inventory\StaleUnitStateException;
use App\Livewire\Backend\Concerns\SubmitsInventoryChanges;
use App\Livewire\Backend\Concerns\UsesOperationalLocale;
use App\Models\DeviceCostEntry;
use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\Purchase;
use App\Models\PurchaseReceiptLine;
use App\Models\Store;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * One unit's identity, location, service history, costs, and timeline; always loaded through its store.
 *
 * Every lifecycle form submits the status and lifecycle revision this page was showing. When
 * another operator changed the device first, nothing is written, the page shows the current
 * device and repair state, and keeps everything the operator typed. The forms stay bound to
 * the old revision, so resubmitting is rejected again, until the operator explicitly chooses
 * to continue with the current state (refreshState), which also clears return confirmations.
 */
class UnitDetail extends Component
{
    use SubmitsInventoryChanges;
    use UsesOperationalLocale;
    use WithPagination;

    public const TIMELINE_PER_PAGE = 15;

    public const RECENT_SERVICE_RECORDS = 10;

    #[Locked]
    public Store $store;

    #[Locked]
    public int $unitId;

    #[Locked]
    public string $expectedStatus = '';

    #[Locked]
    public int $expectedRevision = 0;

    #[Locked]
    public ?string $staleNotice = null;

    public string $removalReason = '';

    public string $removalNote = '';

    public string $inspectionOutcome = '';

    public string $inspectionCondition = '';

    public string $inspectionFindings = '';

    public string $repairerType = 'external';

    public string $repairerName = '';

    public string $repairProblem = '';

    public string $completionWork = '';

    public bool $completionReturned = false;

    public string $partsMode = 'pending';

    public string $partsAmount = '';

    public string $partsCurrency = 'TRY';

    public string $partsDescription = '';

    public string $laborMode = 'pending';

    public string $laborAmount = '';

    public string $laborCurrency = 'TRY';

    public string $cancelReason = '';

    public bool $cancelReturned = false;

    public string $costCategory = '';

    public string $costDescription = '';

    public string $costMode = 'known';

    public string $costAmount = '';

    public string $costCurrency = 'TRY';

    public string $costRepairId = '';

    public string $reversingEntryId = '';

    public string $reversalReason = '';

    public string $replacementMode = 'none';

    public string $replacementAmount = '';

    public string $replacementCurrency = 'TRY';

    public string $replacementDescription = '';

    public string $correctingIdentifierId = '';

    public string $correctionExpectedValue = '';

    public string $newIdentifierValue = '';

    public string $correctionReason = '';

    public bool $correctionConfirmed = false;

    public function mount(Store $store, InventoryUnit $unit): void
    {
        $this->authorize('viewInventory', $store);

        $this->store = $store;
        $this->unitId = $unit->id;
        $this->adoptState($unit);
    }

    public function remove(RemoveUnit $removeUnit): void
    {
        $this->authorize('adjustInventory', $this->store);
        $this->resetErrorBag();

        $this->validate([
            'removalReason' => ['required', Rule::in(array_map(
                fn (AdjustmentReason $reason) => $reason->value,
                MovementType::ADJUSTMENT_DECREASE->allowedReasons(),
            ))],
            'removalNote' => ['required', 'string', 'max:1000'],
        ], attributes: [
            'removalReason' => __('inventory.fields.reason'),
            'removalNote' => __('inventory.fields.note'),
        ]);

        $this->runLifecycleAction(
            fn () => $removeUnit(
                auth()->user(),
                $this->store,
                $this->unitId,
                AdjustmentReason::from($this->removalReason),
                $this->removalNote,
                $this->submissionKey,
                $this->expectedRevision,
            ),
            ['reason' => 'removalReason', 'note' => 'removalNote'],
            'removalReason',
            'remove-unit',
            ['removalReason', 'removalNote'],
        );
    }

    public function recordInspection(RecordInspection $recordInspection): void
    {
        $this->authorize('serviceDevices', $this->store);
        $this->resetErrorBag();

        $outcome = InspectionOutcome::tryFrom($this->inspectionOutcome);

        if ($outcome === null) {
            throw ValidationException::withMessages(['inspectionOutcome' => __('devices.errors.required', ['attribute' => __('devices.fields.outcome')])]);
        }

        $this->runLifecycleAction(
            fn () => $recordInspection(
                auth()->user(),
                $this->store,
                $this->unitId,
                UnitStatus::from($this->expectedStatus),
                $this->expectedRevision,
                $outcome,
                UnitCondition::tryFrom($this->inspectionCondition),
                $this->inspectionFindings,
                $this->submissionKey,
            ),
            ['condition' => 'inspectionCondition', 'findings' => 'inspectionFindings'],
            'inspectionOutcome',
            'record-inspection',
            ['inspectionOutcome', 'inspectionCondition', 'inspectionFindings'],
        );
    }

    public function startRepair(StartRepair $startRepair): void
    {
        $this->authorize('serviceDevices', $this->store);
        $this->resetErrorBag();

        $type = RepairerType::tryFrom($this->repairerType);

        if ($type === null) {
            throw ValidationException::withMessages(['repairerType' => __('devices.errors.required', ['attribute' => __('devices.fields.repairer_type')])]);
        }

        $this->runLifecycleAction(
            fn () => $startRepair(
                auth()->user(),
                $this->store,
                $this->unitId,
                UnitStatus::from($this->expectedStatus),
                $this->expectedRevision,
                $type,
                $this->repairerName,
                $this->repairProblem,
                $this->submissionKey,
            ),
            ['problem' => 'repairProblem', 'repairer_name' => 'repairerName'],
            'repairProblem',
            'start-repair',
            ['repairerName', 'repairProblem'],
        );
    }

    public function completeRepair(CloseRepair $closeRepair): void
    {
        $this->authorize('serviceDevices', $this->store);
        $this->resetErrorBag();

        $costs = $this->completionCosts();

        $this->runLifecycleAction(
            fn () => $closeRepair(
                auth()->user(),
                $this->store,
                $this->unitId,
                UnitStatus::from($this->expectedStatus),
                $this->expectedRevision,
                RepairStatus::COMPLETED,
                $this->completionWork,
                $this->completionReturned,
                $costs,
                $this->submissionKey,
            ),
            ['work_performed' => 'completionWork', 'returned' => 'completionReturned'],
            'completionWork',
            'complete-repair',
            ['completionWork', 'completionReturned', 'partsMode', 'partsAmount', 'partsDescription', 'laborMode', 'laborAmount'],
        );
    }

    public function cancelRepair(CloseRepair $closeRepair): void
    {
        $this->authorize('serviceDevices', $this->store);
        $this->resetErrorBag();

        $this->runLifecycleAction(
            fn () => $closeRepair(
                auth()->user(),
                $this->store,
                $this->unitId,
                UnitStatus::from($this->expectedStatus),
                $this->expectedRevision,
                RepairStatus::CANCELLED,
                $this->cancelReason,
                $this->cancelReturned,
                [],
                $this->submissionKey,
            ),
            ['close_reason' => 'cancelReason', 'returned' => 'cancelReturned'],
            'cancelReason',
            'cancel-repair',
            ['cancelReason', 'cancelReturned'],
        );
    }

    public function recordCost(RecordDeviceCost $recordDeviceCost): void
    {
        $this->authorize('manageDeviceCosts', $this->store);
        $this->resetErrorBag();

        $cost = $this->parseCost(fn () => DeviceCostInput::fromInput(
            $this->costCategory,
            $this->costDescription,
            $this->costAmount,
            $this->costCurrency,
            $this->costMode === 'unknown',
        ), ['cost_category' => 'costCategory', 'cost_description' => 'costDescription', 'cost_amount' => 'costAmount', 'cost_currency' => 'costCurrency']);

        $this->runLifecycleAction(
            fn () => $recordDeviceCost(
                auth()->user(),
                $this->store,
                $this->unitId,
                $cost,
                $this->costRepairId === '' ? null : (int) $this->costRepairId,
                $this->submissionKey,
            ),
            ['cost_repair' => 'costRepairId'],
            'costCategory',
            'record-cost',
            ['costCategory', 'costDescription', 'costMode', 'costAmount', 'costRepairId'],
        );
    }

    public function reverseCost(ReverseDeviceCost $reverseDeviceCost): void
    {
        $this->authorize('manageDeviceCosts', $this->store);
        $this->resetErrorBag();

        $entry = $this->unitQuery()->firstOrFail()->costEntries()->whereKey((int) $this->reversingEntryId)->first();

        if ($entry === null) {
            throw ValidationException::withMessages(['reversalReason' => __('devices.errors.cost_not_found')]);
        }

        $replacement = $this->replacementMode === 'none' ? null : $this->parseCost(fn () => DeviceCostInput::fromInput(
            $entry->category->value,
            $this->replacementDescription,
            $this->replacementAmount,
            $this->replacementCurrency,
            $this->replacementMode === 'unknown',
            'replacement_',
        ), ['replacement_description' => 'replacementDescription', 'replacement_amount' => 'replacementAmount', 'replacement_currency' => 'replacementCurrency']);

        $this->runLifecycleAction(
            fn () => $reverseDeviceCost(
                auth()->user(),
                $this->store,
                $this->unitId,
                $entry->id,
                $this->reversalReason,
                $replacement,
                $this->submissionKey,
            ),
            ['reversal_reason' => 'reversalReason'],
            'reversalReason',
            'reverse-cost',
            ['reversingEntryId', 'reversalReason', 'replacementMode', 'replacementAmount', 'replacementDescription'],
        );
    }

    public function correctIdentifier(CorrectUnitIdentifier $correctUnitIdentifier): void
    {
        $this->authorize('correctUnitIdentity', $this->store);
        $this->resetErrorBag();

        if (! $this->correctionConfirmed) {
            throw ValidationException::withMessages(['correctionConfirmed' => __('devices.errors.correction_confirmation_required')]);
        }

        $this->runLifecycleAction(
            fn () => $correctUnitIdentifier(
                auth()->user(),
                $this->store,
                $this->unitId,
                (int) $this->correctingIdentifierId,
                $this->correctionExpectedValue,
                $this->newIdentifierValue,
                $this->correctionReason,
                $this->submissionKey,
            ),
            ['new_value' => 'newIdentifierValue', 'correction_reason' => 'correctionReason'],
            'newIdentifierValue',
            'correct-identifier',
            ['correctingIdentifierId', 'correctionExpectedValue', 'newIdentifierValue', 'correctionReason', 'correctionConfirmed'],
        );
    }

    public function dismissStaleNotice(): void
    {
        $this->authorize('viewInventory', $this->store);
        $this->staleNotice = null;
    }

    /**
     * Rebind the forms to the device's current state after the operator reviewed it.
     *
     * Typed text is kept. A physical-return confirmation was given for the repair the form was
     * opened for, so it is cleared and must be confirmed again for the current repair.
     */
    public function refreshState(): void
    {
        $this->authorize('viewInventory', $this->store);
        $this->adoptState($this->unitQuery()->firstOrFail());
        $this->reset('completionReturned', 'cancelReturned');
        $this->staleNotice = null;
        $this->resetErrorBag();
    }

    #[Layout('layouts.backend')]
    public function render(): View
    {
        $this->authorize('viewInventory', $this->store);

        $canViewPurchases = Gate::allows('viewAny', Purchase::class);
        $canManageCosts = Gate::allows('manageDeviceCosts', $this->store);
        $canService = Gate::allows('serviceDevices', $this->store);

        $unit = $this->unitQuery()
            ->with([
                'identifiers:id,inventory_unit_id,type,position,value',
                'productDefinition:id,name,model,tracking_mode',
                'openRepair',
                'acquisition:id,inventory_unit_id,purchase_receipt_line_id,currency,unit_cost',
            ])
            ->when($canViewPurchases, fn (Builder $query) => $query->with('acquisition.receiptLine:id,purchase_receipt_id', 'acquisition.receiptLine.receipt:id,purchase_id', 'acquisition.receiptLine.receipt.purchase:id,supplier_invoice_number'))
            ->firstOrFail();

        $isUsedDevice = $unit->productDefinition->tracking_mode === TrackingMode::USED_DEVICE;

        return view('livewire.backend.inventory.unit-detail', [
            'unit' => $unit,
            'isUsedDevice' => $isUsedDevice,
            'isStale' => $unit->lifecycle_revision !== $this->expectedRevision || $unit->status->value !== $this->expectedStatus,
            'inspections' => $isUsedDevice
                ? $unit->inspections()->with('inspector:id,name')->latest('inspected_at')->latest('id')->limit(self::RECENT_SERVICE_RECORDS)->get()
                : new Collection,
            'repairs' => $isUsedDevice
                ? $unit->repairs()
                    ->with(['starter:id,name', 'closer:id,name'])
                    ->withCount(['costEntries as active_cost_entries_count' => fn (Builder $entries) => $entries->whereNull('reversed_at')])
                    ->latest('started_at')
                    ->latest('id')
                    ->limit(self::RECENT_SERVICE_RECORDS)
                    ->get()
                : new Collection,
            'costEntries' => $canManageCosts && $isUsedDevice ? $this->costEntries($unit) : null,
            'timeline' => $this->timeline($unit, $canManageCosts),
            'canViewPurchases' => $canViewPurchases,
            'canManageCosts' => $canManageCosts,
            'canInspect' => $canService && $isUsedDevice && UnitLifecycle::allows($unit->status, UnitLifecycle::INSPECT),
            'canStartRepair' => $canService && $isUsedDevice && UnitLifecycle::allows($unit->status, UnitLifecycle::START_REPAIR),
            'canCloseRepair' => $canService && $isUsedDevice && $unit->openRepair !== null && UnitLifecycle::allows($unit->status, UnitLifecycle::CLOSE_REPAIR),
            'canRecordCost' => $canManageCosts && $isUsedDevice,
            'canCorrectIdentity' => Gate::allows('correctUnitIdentity', $this->store),
            'canRemove' => $unit->status->allowsRemoval() && Gate::allows('adjustInventory', $this->store),
            'removalReasons' => MovementType::ADJUSTMENT_DECREASE->allowedReasons(),
            'usedConditions' => UnitCondition::allowedFor(TrackingMode::USED_DEVICE),
            'currencies' => Currency::cases(),
            'costCategories' => DeviceCostCategory::cases(),
            ...$this->localeViewData(),
        ])->title(($unit->primaryIdentifierValue() ?? '#'.$unit->id).' — '.$this->store->code);
    }

    /**
     * @return Builder<InventoryUnit>
     */
    private function unitQuery(): Builder
    {
        return $this->store->units()->whereKey($this->unitId)->getQuery();
    }

    private function adoptState(InventoryUnit $unit): void
    {
        $this->expectedStatus = $unit->status->value;
        $this->expectedRevision = $unit->lifecycle_revision;
    }

    /**
     * Run one recorded action with this form's key, mapping errors onto its fields.
     *
     * On success the form is cleared, its dialog closed, and the page adopts the new state.
     * On a stale form nothing was written: the page explains the change and keeps the
     * operator's input, but the expected state is not adopted here, so the same form cannot
     * silently apply to whatever the device became (for example a newer repair).
     *
     * @param  Closure(): InventoryMovement  $action
     * @param  array<string, string>  $errorMap
     * @param  list<string>  $resetOnSuccess
     */
    private function runLifecycleAction(Closure $action, array $errorMap, string $fallbackErrorKey, string $dialog, array $resetOnSuccess): void
    {
        try {
            $movement = $this->submitInventoryChange($action, ['unit' => $fallbackErrorKey, ...$errorMap], $fallbackErrorKey);
        } catch (StaleUnitStateException $exception) {
            $this->staleNotice = __('devices.stale.body', ['status' => $exception->currentStatus->label()]);
            $this->addError($fallbackErrorKey, __('devices.stale.field'));

            return;
        }

        if ($movement === null) {
            return;
        }

        $this->reset($resetOnSuccess);
        $this->staleNotice = null;
        $this->adoptState($this->unitQuery()->firstOrFail());
        $this->resetPage('timeline');
        $this->dispatch('close-dialog', id: $dialog);
    }

    /**
     * @param  Closure(): DeviceCostInput  $parse
     * @param  array<string, string>  $errorMap
     */
    private function parseCost(Closure $parse, array $errorMap): DeviceCostInput
    {
        try {
            return $parse();
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())
                ->mapWithKeys(fn (array $messages, string $key) => [$errorMap[$key] ?? array_values($errorMap)[0] => $messages])
                ->all());
        }
    }

    /**
     * Costs entered with a repair completion. Users without cost access never send any.
     *
     * @return list<DeviceCostInput>
     */
    private function completionCosts(): array
    {
        if (! Gate::allows('manageDeviceCosts', $this->store)) {
            return [];
        }

        $costs = [];
        $errors = [];
        $errorMap = [
            'parts_category' => 'partsMode',
            'parts_description' => 'partsDescription',
            'parts_amount' => 'partsAmount',
            'parts_currency' => 'partsCurrency',
            'labor_category' => 'laborMode',
            'labor_description' => 'laborMode',
            'labor_amount' => 'laborAmount',
            'labor_currency' => 'laborCurrency',
        ];

        foreach ([
            'parts' => [DeviceCostCategory::REPAIR_PARTS, $this->partsMode, $this->partsAmount, $this->partsCurrency, $this->partsDescription],
            'labor' => [DeviceCostCategory::REPAIR_LABOR, $this->laborMode, $this->laborAmount, $this->laborCurrency, null],
        ] as $prefix => [$category, $mode, $amount, $currency, $description]) {
            if ($mode === 'pending') {
                continue;
            }

            try {
                $costs[] = DeviceCostInput::fromInput($category->value, $description, $amount, $currency, $mode === 'unknown', $prefix.'_');
            } catch (ValidationException $exception) {
                foreach ($exception->errors() as $key => $messages) {
                    $errors[$errorMap[$key] ?? $prefix.'Mode'] = $messages;
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $costs;
    }

    /**
     * @return Collection<int, DeviceCostEntry>
     */
    private function costEntries(InventoryUnit $unit): Collection
    {
        return $unit->costEntries()
            ->with(['recorder:id,name', 'reverser:id,name'])
            ->orderBy('recorded_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * The unit's full history, newest first, paginated on the server with a fixed set of eager loads.
     * Cost events are only listed for users allowed to see device costs.
     */
    private function timeline(InventoryUnit $unit, bool $canManageCosts): mixed
    {
        return $unit->movements()
            ->select(['id', 'store_id', 'product_definition_id', 'inventory_unit_id', 'type', 'reason', 'note', 'quantity_change', 'quantity_after', 'unit_status_before', 'unit_status_after', 'reference_type', 'reference_id', 'user_id', 'occurred_at'])
            ->unless($canManageCosts, fn (Builder $query) => $query->whereNotIn('type', MovementType::costEvents()))
            ->with([
                'actor:id,name',
                'inspection',
                'startedRepair',
                'closedRepair',
                'identifierCorrection',
                'reference' => fn (MorphTo $reference) => $reference->morphWith([
                    PurchaseReceiptLine::class => ['receipt:id,purchase_id', 'receipt.purchase:id,supplier_invoice_number'],
                ]),
            ])
            ->when($canManageCosts, fn (Builder $query) => $query->with(['costEntries', 'reversedCostEntries']))
            ->latest('occurred_at')
            ->latest('id')
            ->paginate(self::TIMELINE_PER_PAGE, pageName: 'timeline');
    }
}
