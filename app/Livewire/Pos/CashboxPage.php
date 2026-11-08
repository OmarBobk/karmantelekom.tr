<?php

declare(strict_types=1);

namespace App\Livewire\Pos;

use App\Actions\Cashbox\CloseCashboxSession;
use App\Actions\Cashbox\ConfirmCashboxTransfer;
use App\Actions\Cashbox\OpenCashboxSession;
use App\Actions\Cashbox\ReleaseCashboxTransfer;
use App\Actions\Cashbox\ReleaseCashIntake;
use App\Actions\Cashbox\StartCashboxTransfer;
use App\Enums\CashboxTransferKind;
use App\Enums\CashPayoutResolution;
use App\Exceptions\Inventory\IdempotencyConflictException;
use App\Exceptions\Purchasing\InvalidAmountException;
use App\Livewire\Pos\Concerns\UsesPosLocale;
use App\Models\CashboxSession;
use App\Models\CashboxTransfer;
use App\Models\CashIntake;
use App\Models\CashMovement;
use App\Models\Store;
use App\Services\Purchasing\LocalizedMoney;
use App\Services\Purchasing\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The store's cashbox: open and close sessions, see the cash received in the open session, record
 * owner deposits and withdrawals, and release cash intakes and transfers that cannot complete.
 * Owner only.
 *
 * The expected amount is taken when the page loads or the owner refreshes, and the close is
 * checked against that figure, so cash recorded while the owner was counting refuses the close
 * instead of silently changing what the count is compared with.
 */
class CashboxPage extends Component
{
    use UsesPosLocale;
    use WithPagination;

    public const RELEASE_DIALOG = 'cashbox-release';

    public const TRANSFER_RELEASE_DIALOG = 'cashbox-transfer-release';

    #[Locked]
    public int $storeId;

    /** The expected amount shown to the owner, in minor units; null without an open session. */
    #[Locked]
    public ?int $expectedShown = null;

    /** The session the expected amount belongs to; the close names both, so a stale form never closes a newer session. */
    #[Locked]
    public ?int $sessionShown = null;

    #[Locked]
    public ?string $expectedShownAt = null;

    public string $openingCounted = '';

    public string $openingNote = '';

    public string $countedClosing = '';

    public string $closingNote = '';

    #[Locked]
    public ?int $releaseIntakeId = null;

    public string $releaseNote = '';

    /** The deposit or withdrawal form's request key; renewed only after a submission is recorded. */
    #[Locked]
    public string $transferKey = '';

    public string $transferKind = 'deposit';

    public string $transferAmount = '';

    public string $transferReason = '';

    /** The transfer this screen just recorded and told the owner to move cash for. */
    #[Locked]
    public ?int $instructedTransferId = null;

    #[Locked]
    public ?int $releaseTransferId = null;

    public string $transferResolution = '';

    public string $transferReleaseNote = '';

    public function mount(Store $store): void
    {
        $this->storeId = $store->id;
        $this->transferKey = (string) Str::uuid();
        $this->snapshotExpected($this->authorizeCashbox());
    }

    public function startTransfer(StartCashboxTransfer $start): void
    {
        $store = $this->authorizeCashbox();

        try {
            $transfer = $start(auth()->user(), $store, $this->sessionShown, [
                'kind' => $this->transferKind,
                'amount' => self::canonical($this->transferAmount, 'amount'),
                'reason' => $this->transferReason,
            ], $this->transferKey);
        } catch (IdempotencyConflictException) {
            $this->transferKey = (string) Str::uuid();

            throw ValidationException::withMessages(['amount' => __('pos.expenses.errors.request_conflict')]);
        } catch (ValidationException $exception) {
            if (array_key_exists('cashbox', $exception->errors())) {
                $this->snapshotExpected($store);
            }

            throw $exception;
        }

        $this->transferKey = (string) Str::uuid();
        $this->reset('transferAmount', 'transferReason');
        $this->resetValidation();

        $amount = LocalizedMoney::format($transfer->amountMinor());
        $kind = $transfer->kind->label();

        if ($transfer->wasRecentlyCreated) {
            $this->instructedTransferId = $transfer->id;
            $this->notify('warning', __($transfer->kind === CashboxTransferKind::DEPOSIT ? 'pos.cashbox.transfers.move_deposit' : 'pos.cashbox.transfers.move_withdrawal', ['amount' => $amount]));

            return;
        }

        $this->instructedTransferId = null;
        $this->notify('info', __('pos.cashbox.transfers.replay_'.$transfer->status->value, ['kind' => $kind]));
    }

    /**
     * Confirm the displayed transfer's cash was moved, naming the session this page shows.
     */
    public function confirmTransfer(ConfirmCashboxTransfer $confirm, int $transferId): void
    {
        $store = $this->authorizeCashbox();

        try {
            $movement = $confirm(auth()->user(), $store, $transferId, $this->sessionShown);
        } finally {
            $this->snapshotExpected($store);
        }

        if ($this->instructedTransferId === $transferId) {
            $this->instructedTransferId = null;
        }

        $this->resetValidation();
        $this->notify('success', __('pos.cashbox.transfers.confirmed', [
            'kind' => $movement->transfer->kind->label(),
            'amount' => LocalizedMoney::format($movement->amountMinor()),
        ]));
    }

    public function openTransferRelease(int $transferId): void
    {
        $store = $this->authorizeCashbox();
        $transfer = CashboxTransfer::query()->where('store_id', $store->id)->pending()->find($transferId);

        if ($transfer === null) {
            $this->notify('error', __('pos.cashbox.transfers.errors.not_pending'));

            return;
        }

        $this->resetValidation();
        $this->releaseTransferId = $transfer->id;
        $this->reset('transferResolution', 'transferReleaseNote');
        $this->dispatch('open-dialog', id: self::TRANSFER_RELEASE_DIALOG);
    }

    public function releaseTransfer(ReleaseCashboxTransfer $release): void
    {
        $store = $this->authorizeCashbox();

        if ($this->releaseTransferId === null) {
            return;
        }

        try {
            $release(auth()->user(), $store, $this->releaseTransferId, $this->transferResolution ?: null, $this->transferReleaseNote);
        } catch (ValidationException $exception) {
            $map = ['resolution' => 'transferResolution', 'release_note' => 'transferReleaseNote'];

            throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(fn ($messages, $key) => [$map[$key] ?? $key => $messages])->all());
        }

        if ($this->instructedTransferId === $this->releaseTransferId) {
            $this->instructedTransferId = null;
        }

        $this->releaseTransferId = null;
        $this->reset('transferResolution', 'transferReleaseNote');
        $this->snapshotExpected($store);
        $this->dispatch('close-dialog', id: self::TRANSFER_RELEASE_DIALOG);
        $this->notify('success', __('pos.cashbox.transfers.released'));
    }

    public function refreshExpected(): void
    {
        $this->snapshotExpected($this->authorizeCashbox());
        $this->resetValidation();
    }

    public function open(OpenCashboxSession $open): void
    {
        $store = $this->authorizeCashbox();
        $session = $open(auth()->user(), $store, self::canonical($this->openingCounted, 'opening_counted'), $this->openingNote);

        $this->reset('openingCounted', 'openingNote');
        $this->resetValidation();
        $this->snapshotExpected($store);
        $this->notify('success', __('pos.cashbox.opened', ['amount' => LocalizedMoney::format($session->openingCountedMinor())]));
    }

    public function close(CloseCashboxSession $close): void
    {
        $store = $this->authorizeCashbox();

        if ($this->sessionShown === null || $this->expectedShown === null) {
            $this->snapshotExpected($store);

            return;
        }

        try {
            $closed = $close(auth()->user(), $store, $this->sessionShown, Money::toDecimal($this->expectedShown), self::canonical($this->countedClosing, 'counted_closing'), $this->closingNote);
        } catch (ValidationException $exception) {
            $session = CashboxSession::openFor($store);

            if ($session?->id !== $this->sessionShown || $session->expectedMinor() !== $this->expectedShown) {
                $this->snapshotExpected($store);
            }

            throw $exception;
        }

        $this->reset('countedClosing', 'closingNote');
        $this->resetValidation();
        $this->resetPage('history');
        $this->snapshotExpected($store);
        $this->notify('success', __('pos.cashbox.closed', ['difference' => LocalizedMoney::format(Money::fromDecimal($closed->closing_difference))]));
    }

    public function openRelease(int $intakeId): void
    {
        $store = $this->authorizeCashbox();
        $intake = CashIntake::query()->where('store_id', $store->id)->pending()->find($intakeId);

        if ($intake === null) {
            $this->notify('error', __('pos.cashbox.errors.intake_not_pending'));

            return;
        }

        $this->resetValidation();
        $this->releaseIntakeId = $intake->id;
        $this->releaseNote = '';
        $this->dispatch('open-dialog', id: self::RELEASE_DIALOG);
    }

    public function release(ReleaseCashIntake $release): void
    {
        $store = $this->authorizeCashbox();

        if ($this->releaseIntakeId === null) {
            return;
        }

        $release(auth()->user(), $store, $this->releaseIntakeId, $this->releaseNote);

        $this->releaseIntakeId = null;
        $this->releaseNote = '';
        $this->dispatch('close-dialog', id: self::RELEASE_DIALOG);
        $this->notify('success', __('pos.cashbox.released'));
    }

    public function render(): View
    {
        $store = $this->authorizeCashbox();
        $session = CashboxSession::openFor($store)?->load('opener:id,name');

        return view('livewire.pos.cashbox-page', [
            'store' => $store,
            'session' => $session,
            'tracked' => $session !== null || CashboxSession::trackingStarted($store),
            'previous' => $session === null ? CashboxSession::query()->where('store_id', $store->id)->closed()->latest('closed_at')->latest('id')->first() : null,
            'cashIn' => $session?->cashInMinor() ?? 0,
            'cashOut' => $session?->cashOutMinor() ?? 0,
            'pendingPayoutCount' => $session?->pendingPayoutCount() ?? 0,
            'available' => $session?->availableForPayoutMinor(),
            'pendingTransfers' => $session === null ? collect() : $session->transfers()->pending()->with('starter:id,name')->oldest('started_at')->get(),
            'transferKinds' => CashboxTransferKind::cases(),
            'resolutions' => CashPayoutResolution::cases(),
            'movements' => $session === null ? collect() : $session->movements()
                ->with(CashMovement::DISPLAY_RELATIONS)
                ->latest('occurred_at')
                ->latest('id')
                ->limit(100)
                ->get(),
            'pendingIntakes' => $session === null ? collect() : $session->intakes()
                ->pending()
                ->with(['starter:id,name', 'customer:id,name'])
                ->oldest('started_at')
                ->get(),
            'history' => CashboxSession::query()
                ->where('store_id', $store->id)
                ->closed()
                ->with(['opener:id,name', 'closer:id,name'])
                ->latest('closed_at')
                ->latest('id')
                ->paginate(20, pageName: 'history'),
            ...$this->localeViewData(),
        ])->layout('layouts.pos', ['posStore' => $store])->title(__('pos.cashbox.title', ['store' => $store->code]));
    }

    private function snapshotExpected(Store $store): void
    {
        $session = CashboxSession::openFor($store);
        $this->sessionShown = $session?->id;
        $this->expectedShown = $session?->expectedMinor();
        $this->expectedShownAt = $session === null ? null : Carbon::now()->toIso8601String();
    }

    /**
     * @throws ValidationException
     */
    private static function canonical(string $input, string $field): string
    {
        try {
            return LocalizedMoney::toCanonical($input);
        } catch (InvalidAmountException) {
            throw ValidationException::withMessages([$field => __('pos.errors.amount_format', ['example' => LocalizedMoney::EXAMPLE])]);
        }
    }

    private function authorizeCashbox(): Store
    {
        $store = Store::query()->findOrFail($this->storeId);
        $this->authorize('manageCashbox', $store);

        return $store;
    }

    private function notify(string $type, string $message): void
    {
        $this->dispatch('notify', ['type' => $type, 'message' => $message]);
    }
}
