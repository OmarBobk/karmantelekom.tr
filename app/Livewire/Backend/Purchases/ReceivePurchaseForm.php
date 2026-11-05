<?php

declare(strict_types=1);

namespace App\Livewire\Backend\Purchases;

use App\Actions\Purchasing\ReceivePurchase;
use App\Enums\TrackingMode;
use App\Enums\UnitCondition;
use App\Exceptions\Inventory\IdempotencyConflictException;
use App\Exceptions\Purchasing\PurchaseAlreadyReceivedException;
use App\Livewire\Backend\Concerns\SubmitsInventoryChanges;
use App\Livewire\Backend\Concerns\UsesOperationalLocale;
use App\Models\Purchase;
use App\Models\PurchaseLine;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Full receipt of a posted invoice: quantities go to stock, each device gets its identity.
 */
class ReceivePurchaseForm extends Component
{
    use SubmitsInventoryChanges;
    use UsesOperationalLocale;

    #[Locked]
    public int $purchaseId;

    /** @var array<int, list<array{condition: string, imei: string, secondary_imei: string, serial: string}>> Keyed by purchase line id. */
    public array $units = [];

    public string $note = '';

    public function mount(Purchase $purchase): void
    {
        $this->authorize('receive', $purchase);

        $this->purchaseId = $purchase->id;

        foreach ($purchase->lines()->get() as $line) {
            if (! $line->tracking_mode->tracksIndividualItems()) {
                continue;
            }

            $defaultCondition = $line->tracking_mode === TrackingMode::SERIALIZED ? UnitCondition::NEW->value : '';
            $this->units[$line->id] = array_fill(0, $line->quantity, ['condition' => $defaultCondition, 'imei' => '', 'secondary_imei' => '', 'serial' => '']);
        }
    }

    public function receive(ReceivePurchase $receivePurchase): void
    {
        $purchase = $this->purchase();
        $this->authorize('receive', $purchase);
        $this->resetErrorBag();

        try {
            $receipt = $receivePurchase(auth()->user(), $purchase, $this->units, $this->note, $this->submissionKey);
        } catch (PurchaseAlreadyReceivedException) {
            $this->renewSubmissionKey();
            $this->addError('receipt', __('purchases.receipt.received_elsewhere'));

            return;
        } catch (IdempotencyConflictException) {
            $this->renewSubmissionKey();
            $this->addError('receipt', __('purchases.errors.submission_conflict'));

            return;
        }

        $this->renewSubmissionKey();
        session()->flash('purchases.notify', [
            'type' => $receipt->wasRecentlyCreated ? 'success' : 'info',
            'message' => $receipt->wasRecentlyCreated ? __('purchases.receipt.received') : __('purchases.receipt.already_received'),
        ]);

        $this->redirectRoute('subdomain.purchases.show', $purchase, navigate: true);
    }

    #[Layout('layouts.backend')]
    public function render(): View
    {
        $purchase = $this->purchase()->load(['store:id,code,name', 'supplier:id,name', 'lines', 'receipt.receiver:id,name']);
        $this->authorize('receive', $purchase);

        return view('livewire.backend.purchases.receive-purchase', [
            'purchase' => $purchase,
            'canReceive' => $purchase->isPosted() && ! $purchase->isReceived(),
            'conditionsByLine' => $purchase->lines->mapWithKeys(fn (PurchaseLine $line) => [$line->id => UnitCondition::allowedFor($line->tracking_mode)]),
            ...$this->localeViewData(),
        ])->title(__('purchases.receipt.page_title'));
    }

    private function purchase(): Purchase
    {
        return Purchase::query()->findOrFail($this->purchaseId);
    }
}
