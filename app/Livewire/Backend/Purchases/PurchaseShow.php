<?php

declare(strict_types=1);

namespace App\Livewire\Backend\Purchases;

use App\Actions\Purchasing\CopyPurchaseAsCorrection;
use App\Actions\Purchasing\RecordSupplierPayment;
use App\Actions\Purchasing\ReversePurchase;
use App\Actions\Purchasing\ReverseSupplierPayment;
use App\Enums\SupplierPaymentMethod;
use App\Exceptions\Inventory\IdempotencyConflictException;
use App\Livewire\Backend\Concerns\SubmitsInventoryChanges;
use App\Livewire\Backend\Concerns\UsesOperationalLocale;
use App\Models\Purchase;
use App\Models\SupplierPayment;
use App\Services\Purchasing\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * A supplier invoice after drafting: totals, receipt state, payments, and reversals.
 */
class PurchaseShow extends Component
{
    use SubmitsInventoryChanges;
    use UsesOperationalLocale;

    #[Locked]
    public int $purchaseId;

    public string $paymentAmount = '';

    public string $paymentDate = '';

    public string $paymentMethod = 'bank_transfer';

    public string $paymentReference = '';

    public string $paymentNote = '';

    public string $reversingPaymentId = '';

    public string $paymentReversalReason = '';

    public string $reverseReason = '';

    public function mount(Purchase $purchase): void
    {
        $this->authorize('view', $purchase);

        $this->purchaseId = $purchase->id;
        $this->paymentDate = now()->toDateString();

        if ($purchase->isDraft() && Gate::allows('update', $purchase)) {
            $this->redirectRoute('subdomain.purchases.edit', $purchase, navigate: true);

            return;
        }

        if ($notification = session('purchases.notify')) {
            $this->dispatch('notify', $notification);
        }
    }

    public function recordPayment(RecordSupplierPayment $recordSupplierPayment): void
    {
        $purchase = $this->purchase();
        $this->authorize('pay', $purchase);
        $this->resetErrorBag();

        try {
            $payment = $recordSupplierPayment(auth()->user(), $purchase, [
                'amount' => $this->paymentAmount,
                'paid_on' => $this->paymentDate,
                'method' => $this->paymentMethod,
                'reference' => $this->paymentReference,
                'note' => $this->paymentNote,
            ], $this->submissionKey);
        } catch (ValidationException $exception) {
            $map = ['amount' => 'paymentAmount', 'paid_on' => 'paymentDate', 'method' => 'paymentMethod', 'reference' => 'paymentReference', 'note' => 'paymentNote'];

            throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(fn ($messages, $key) => [$map[$key] ?? 'paymentAmount' => $messages])->all());
        } catch (IdempotencyConflictException) {
            $this->renewSubmissionKey();
            $this->addError('paymentReference', __('purchases.errors.submission_conflict'));

            return;
        }

        $this->renewSubmissionKey();
        $this->reset('paymentAmount', 'paymentReference', 'paymentNote');
        $this->dispatch('close-dialog', id: 'record-payment');

        $outstanding = $purchase->refresh()->outstandingMinor();
        $this->dispatch('notify', [
            'type' => $payment->wasRecentlyCreated ? 'success' : 'info',
            'message' => match (true) {
                $payment->wasRecentlyCreated => __('purchases.payments.recorded', ['outstanding' => Money::format($outstanding).' '.$purchase->currency->value]),
                $payment->isReversed() => __('purchases.payments.already_recorded_reversed'),
                default => __('purchases.payments.already_recorded'),
            },
        ]);
    }

    public function reversePayment(ReverseSupplierPayment $reverseSupplierPayment): void
    {
        $purchase = $this->purchase();
        $this->authorize('pay', $purchase);
        $this->resetErrorBag();

        try {
            $result = $reverseSupplierPayment(auth()->user(), $purchase, (int) $this->reversingPaymentId, $this->paymentReversalReason);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(['paymentReversalReason' => collect($exception->errors())->flatten()->all()]);
        } catch (ModelNotFoundException) {
            $this->addError('paymentReversalReason', __('purchases.errors.payment_not_found'));

            return;
        }

        $this->reset('reversingPaymentId', 'paymentReversalReason');
        $this->dispatch('close-dialog', id: 'reverse-payment');
        $this->dispatch('notify', [
            'type' => $result->applied ? 'success' : 'info',
            'message' => $result->applied ? __('purchases.payments.reversed') : __('purchases.payments.already_reversed'),
        ]);
    }

    public function reversePurchase(ReversePurchase $reversePurchase): void
    {
        $purchase = $this->purchase();
        $this->authorize('update', $purchase);
        $this->resetErrorBag();

        try {
            $result = $reversePurchase(auth()->user(), $purchase, $this->reverseReason);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(['reverseReason' => collect($exception->errors())->flatten()->all()]);
        }

        $this->reset('reverseReason');
        $this->dispatch('close-dialog', id: 'reverse-purchase');
        $this->dispatch('notify', [
            'type' => $result->applied ? 'success' : 'info',
            'message' => $result->applied ? __('purchases.reverse.reversed') : __('purchases.reverse.already_reversed'),
        ]);
    }

    public function copyAsCorrection(CopyPurchaseAsCorrection $copyPurchaseAsCorrection): void
    {
        $purchase = $this->purchase();
        $this->authorize('update', $purchase);

        try {
            $result = $copyPurchaseAsCorrection(auth()->user(), $purchase);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(['reverseReason' => collect($exception->errors())->flatten()->all()]);
        }

        session()->flash('purchases.notify', ['type' => $result->applied ? 'success' : 'info', 'message' => $result->applied ? __('purchases.copy.copied') : __('purchases.copy.existing')]);
        $this->redirectRoute('subdomain.purchases.edit', $result->model, navigate: true);
    }

    #[Layout('layouts.backend')]
    public function render(): View
    {
        $purchase = $this->purchase()->load([
            'store:id,code,name',
            'supplier:id,name',
            'lines',
            'receipt.receiver:id,name',
            'payments' => fn ($query) => $query->with(['payer:id,name', 'reverser:id,name'])->orderBy('id'),
            'ledgerEntries' => fn ($query) => $query->orderBy('occurred_at')->orderBy('id'),
            'poster:id,name',
            'reverser:id,name',
            'canceller:id,name',
            'correctionOf:id,supplier_invoice_number,status',
            'corrections:id,correction_of_purchase_id,status,supplier_invoice_number',
        ]);

        $this->authorize('view', $purchase);

        $activePaid = $purchase->payments->reject(fn (SupplierPayment $payment) => $payment->isReversed())->sum(fn (SupplierPayment $payment) => $payment->amountMinor());

        return view('livewire.backend.purchases.purchase-show', [
            'purchase' => $purchase,
            'activePaid' => $activePaid,
            'isReconciled' => ! $purchase->isPosted() || $purchase->paidTotalMinor() === $activePaid,
            'canUpdate' => Gate::allows('update', $purchase),
            'canReceive' => Gate::allows('receive', $purchase),
            'canPay' => Gate::allows('pay', $purchase),
            'canViewAccount' => Gate::allows('viewAccount', $purchase->supplier),
            'canViewInventory' => Gate::allows('viewInventory', $purchase->store),
            'paymentMethods' => SupplierPaymentMethod::cases(),
            ...$this->localeViewData(),
        ])->title(($purchase->supplier_invoice_number ?? __('purchases.index.draft_label', ['id' => $purchase->id])).' — '.__('purchases.title'));
    }

    private function purchase(): Purchase
    {
        return Purchase::query()->findOrFail($this->purchaseId);
    }
}
