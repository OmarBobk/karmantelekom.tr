<?php

declare(strict_types=1);

namespace App\Livewire\Backend\Inventory;

use App\Actions\Inventory\RemoveUnit;
use App\Enums\AdjustmentReason;
use App\Enums\MovementType;
use App\Enums\UnitStatus;
use App\Livewire\Backend\Concerns\SubmitsInventoryChanges;
use App\Livewire\Backend\Concerns\UsesOperationalLocale;
use App\Models\InventoryUnit;
use App\Models\Purchase;
use App\Models\PurchaseReceiptLine;
use App\Models\Store;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * One unit's identity, condition, status, and history; the unit is always loaded through its store.
 */
class UnitDetail extends Component
{
    use SubmitsInventoryChanges;
    use UsesOperationalLocale;

    #[Locked]
    public Store $store;

    #[Locked]
    public int $unitId;

    public string $removalReason = '';

    public string $removalNote = '';

    public function mount(Store $store, InventoryUnit $unit): void
    {
        $this->authorize('viewInventory', $store);

        $this->store = $store;
        $this->unitId = $unit->id;
    }

    public function remove(RemoveUnit $removeUnit): void
    {
        $this->authorize('adjustInventory', $this->store);

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

        $movement = $this->submitInventoryChange(
            fn () => $removeUnit(
                auth()->user(),
                $this->store,
                $this->unitId,
                AdjustmentReason::from($this->removalReason),
                $this->removalNote,
                $this->submissionKey,
            ),
            ['reason' => 'removalReason', 'note' => 'removalNote'],
            'removalReason',
        );

        if ($movement !== null) {
            $this->reset('removalReason', 'removalNote');
        }
    }

    #[Layout('layouts.backend')]
    public function render(): View
    {
        $this->authorize('viewInventory', $this->store);

        $canViewPurchases = Gate::allows('viewAny', Purchase::class);

        $unit = $this->store->units()
            ->with(['identifiers', 'productDefinition'])
            ->when($canViewPurchases, fn ($query) => $query->with('acquisition.receiptLine.receipt.purchase:id,supplier_invoice_number'))
            ->findOrFail($this->unitId);

        return view('livewire.backend.inventory.unit-detail', [
            'unit' => $unit,
            'movements' => $unit->movements()
                ->with(['actor:id,name', 'reference' => fn (MorphTo $reference) => $reference->morphWith([
                    PurchaseReceiptLine::class => ['receipt.purchase:id,supplier_invoice_number'],
                ])])
                ->latest('occurred_at')
                ->latest('id')
                ->get(),
            'canViewPurchases' => $canViewPurchases,
            'canRemove' => $unit->status === UnitStatus::AVAILABLE && Gate::allows('adjustInventory', $this->store),
            'removalReasons' => MovementType::ADJUSTMENT_DECREASE->allowedReasons(),
            ...$this->localeViewData(),
        ])->title(($unit->primaryIdentifierValue() ?? '#'.$unit->id).' — '.$this->store->code);
    }
}
