<?php

declare(strict_types=1);

namespace App\Livewire\Backend\Inventory;

use App\Actions\Inventory\AdjustQuantity;
use App\Actions\Inventory\RecordOpeningBalance;
use App\Actions\Inventory\RegisterUnit;
use App\Enums\AdjustmentReason;
use App\Enums\MovementType;
use App\Enums\UnitCondition;
use App\Enums\UnitStatus;
use App\Livewire\Backend\Concerns\SubmitsInventoryChanges;
use App\Livewire\Backend\Concerns\UsesOperationalLocale;
use App\Models\ProductDefinition;
use App\Models\Purchase;
use App\Models\PurchaseReceiptLine;
use App\Models\SaleLine;
use App\Models\Store;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
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

    public string $adjustmentType = 'adjustment_decrease';

    public string $adjustmentReason = '';

    public string $adjustmentQuantity = '';

    public string $adjustmentNote = '';

    public string $unitCondition = '';

    public string $imei = '';

    public string $secondaryImei = '';

    public string $serial = '';

    public string $unitNote = '';

    public string $unitStatusFilter = 'available';

    public function mount(Store $store, ProductDefinition $productDefinition): void
    {
        $this->authorize('viewInventory', $store);

        $this->store = $store;
        $this->definition = $productDefinition;
        $this->unitCondition = UnitCondition::allowedFor($productDefinition->tracking_mode)[0]->value ?? '';
    }

    public function updatedAdjustmentType(): void
    {
        $this->adjustmentReason = '';
        $this->resetErrorBag('adjustmentReason');
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

    public function adjust(AdjustQuantity $adjustQuantity): void
    {
        $this->authorize('adjustInventory', $this->store);

        $type = MovementType::tryFrom($this->adjustmentType);
        $allowedReasons = $type?->allowedReasons() ?? [];

        $this->validate([
            'adjustmentType' => ['required', Rule::in([MovementType::ADJUSTMENT_INCREASE->value, MovementType::ADJUSTMENT_DECREASE->value])],
            'adjustmentReason' => ['required', Rule::in(array_map(fn (AdjustmentReason $reason) => $reason->value, $allowedReasons))],
            'adjustmentQuantity' => ['required', 'integer', 'min:1', 'max:'.AdjustQuantity::MAX_QUANTITY],
            'adjustmentNote' => [$type?->requiresNote() ? 'required' : 'nullable', 'string', 'max:1000'],
        ], attributes: [
            'adjustmentType' => __('inventory.fields.direction'),
            'adjustmentReason' => __('inventory.fields.reason'),
            'adjustmentQuantity' => __('inventory.fields.quantity'),
            'adjustmentNote' => __('inventory.fields.note'),
        ]);

        $movement = $this->submitInventoryChange(
            fn () => $adjustQuantity(
                auth()->user(),
                $this->store,
                $this->definition,
                $type,
                AdjustmentReason::from($this->adjustmentReason),
                (int) $this->adjustmentQuantity,
                $this->adjustmentNote,
                $this->submissionKey,
            ),
            ['quantity' => 'adjustmentQuantity', 'note' => 'adjustmentNote', 'reason' => 'adjustmentReason', 'type' => 'adjustmentType'],
            'adjustmentQuantity',
        );

        if ($movement !== null) {
            $this->reset('adjustmentReason', 'adjustmentQuantity', 'adjustmentNote');
        }
    }

    public function registerUnit(RegisterUnit $registerUnit): void
    {
        $this->authorize('adjustInventory', $this->store);

        $condition = UnitCondition::tryFrom($this->unitCondition);

        if ($condition === null) {
            $this->addError('unitCondition', __('inventory.errors.condition_not_allowed'));

            return;
        }

        $movement = $this->submitInventoryChange(
            fn () => $registerUnit(
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

        $type = MovementType::tryFrom($this->adjustmentType);

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
            'adjustmentReasons' => $type?->allowedReasons() ?? [],
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
