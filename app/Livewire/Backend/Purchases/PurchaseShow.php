<?php

declare(strict_types=1);

namespace App\Livewire\Backend\Purchases;

use App\Actions\Purchasing\ConfirmSupplierCashPayout;
use App\Actions\Purchasing\CopyPurchaseAsCorrection;
use App\Actions\Purchasing\RecordSupplierPayment;
use App\Actions\Purchasing\ReleaseSupplierCashPayout;
use App\Actions\Purchasing\ReversePurchase;
use App\Actions\Purchasing\ReverseSupplierPayment;
use App\Enums\CashPayoutResolution;
use App\Enums\Currency;
use App\Enums\SupplierPaymentMethod;
use App\Exceptions\Inventory\IdempotencyConflictException;
use App\Livewire\Backend\Concerns\SubmitsInventoryChanges;
use App\Livewire\Backend\Concerns\UsesOperationalLocale;
use App\Models\CashboxSession;
use App\Models\Purchase;
use App\Models\SupplierCashPayout;
use App\Models\SupplierPayment;
use App\Services\Purchasing\LocalizedMoney;
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

    /**
     * The open cashbox session TRY cash would be reserved on, as shown with the payment form.
     */
    #[Locked]
    public ?int $sessionShown = null;

    /**
     * The payout this screen just created and told the operator to hand out.
     */
    #[Locked]
    public ?int $instructedPayoutId = null;

    #[Locked]
    public ?int $releasePayoutId = null;

    public string $releaseResolution = '';

    public string $releaseNote = '';

    public function mount(Purchase $purchase): void
    {
        $this->authorize('view', $purchase);

        $this->purchaseId = $purchase->id;
        $this->paymentDate = now()->toDateString();
        $this->snapshotSession($purchase);

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
            ], $this->submissionKey, $this->sessionShown);
        } catch (ValidationException $exception) {
            $this->snapshotSession($purchase);
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

        if ($payment instanceof SupplierCashPayout) {
            $this->instructedPayoutId = $payment->wasRecentlyCreated ? $payment->id : null;
            $amount = LocalizedMoney::format($payment->amountMinor());
            $this->dispatch('notify', [
                'type' => $payment->wasRecentlyCreated ? 'warning' : 'info',
                'message' => match (true) {
                    $payment->wasRecentlyCreated => __('purchases.cash_payouts.hand_out', ['amount' => $amount]),
                    $payment->isPending() => __('purchases.cash_payouts.replay_pending', ['amount' => $amount]),
                    default => __('purchases.cash_payouts.replay_released'),
                },
            ]);

            return;
        }

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

    /**
     * Confirm the displayed payout's cash was handed to the supplier, naming the session it was shown with.
     */
    public function confirmPayout(ConfirmSupplierCashPayout $confirmSupplierCashPayout, int $payoutId, int $sessionId): void
    {
        $purchase = $this->purchase();
        $this->authorize('pay', $purchase);
        $this->resetErrorBag();

        try {
            $payment = $confirmSupplierCashPayout(auth()->user(), $purchase, $payoutId, $sessionId);
        } catch (ModelNotFoundException) {
            $this->addError('cashbox', __('purchases.cash_payouts.errors.payout_not_pending'));

            return;
        } finally {
            $this->snapshotSession($purchase);
        }

        if ($this->instructedPayoutId === $payoutId) {
            $this->instructedPayoutId = null;
        }

        $this->dispatch('notify', [
            'type' => $payment->wasRecentlyCreated ? 'success' : 'info',
            'message' => $payment->wasRecentlyCreated
                ? __('purchases.cash_payouts.confirmed', ['outstanding' => Money::format($purchase->refresh()->outstandingMinor()).' '.$purchase->currency->value])
                : __('purchases.cash_payouts.already_confirmed'),
        ]);
    }

    public function openRelease(int $payoutId): void
    {
        $purchase = $this->purchase();
        $this->authorize('manageCashbox', $purchase->store);
        $payout = $purchase->cashPayouts()->pending()->find($payoutId);

        if ($payout === null) {
            $this->addError('cashbox', __('purchases.cash_payouts.errors.payout_not_pending'));

            return;
        }

        $this->resetErrorBag();
        $this->releasePayoutId = $payout->id;
        $this->reset('releaseResolution', 'releaseNote');
        $this->dispatch('open-dialog', id: 'release-payout');
    }

    public function releasePayout(ReleaseSupplierCashPayout $releaseSupplierCashPayout): void
    {
        $purchase = $this->purchase();

        if ($this->releasePayoutId === null) {
            return;
        }

        try {
            $releaseSupplierCashPayout(auth()->user(), $purchase, $this->releasePayoutId, $this->releaseResolution ?: null, $this->releaseNote);
        } catch (ValidationException $exception) {
            $map = ['resolution' => 'releaseResolution', 'release_note' => 'releaseNote'];

            throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(fn ($messages, $key) => [$map[$key] ?? 'releaseNote' => $messages])->all());
        }

        if ($this->instructedPayoutId === $this->releasePayoutId) {
            $this->instructedPayoutId = null;
        }

        $this->releasePayoutId = null;
        $this->reset('releaseResolution', 'releaseNote');
        $this->snapshotSession($purchase);
        $this->dispatch('close-dialog', id: 'release-payout');
        $this->dispatch('notify', ['type' => 'success', 'message' => __('purchases.cash_payouts.released')]);
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
        $tracksCash = $purchase->currency === Currency::TRY && CashboxSession::trackingStarted($purchase->store);
        $session = $tracksCash ? CashboxSession::openFor($purchase->store) : null;

        return view('livewire.backend.purchases.purchase-show', [
            'purchase' => $purchase,
            'pendingPayouts' => $purchase->cashPayouts()->pending()->with('starter:id,name')->orderBy('id')->get(),
            'payable' => $purchase->payableMinor(),
            'tracksCash' => $tracksCash,
            'cashAvailable' => $session !== null && $session->id === $this->sessionShown ? $session->availableForPayoutMinor() : null,
            'canRelease' => Gate::allows('manageCashbox', $purchase->store),
            'resolutions' => CashPayoutResolution::cases(),
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

    private function snapshotSession(Purchase $purchase): void
    {
        $this->sessionShown = $purchase->currency === Currency::TRY ? CashboxSession::openFor($purchase->store)?->id : null;
    }
}
