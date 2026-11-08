<?php

declare(strict_types=1);

namespace App\Livewire\Pos;

use App\Actions\Cashbox\CloseCashboxSession;
use App\Actions\Cashbox\OpenCashboxSession;
use App\Actions\Cashbox\ReleaseCashIntake;
use App\Exceptions\Purchasing\InvalidAmountException;
use App\Livewire\Pos\Concerns\UsesPosLocale;
use App\Models\CashboxSession;
use App\Models\CashIntake;
use App\Models\Store;
use App\Services\Purchasing\LocalizedMoney;
use App\Services\Purchasing\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The store's cashbox: open and close sessions, see the cash received in the open session, and
 * release cash intakes that cannot complete. Owner only.
 *
 * The expected amount is taken when the page loads or the owner refreshes, and the close is
 * checked against that figure, so cash recorded while the owner was counting refuses the close
 * instead of silently changing what the count is compared with.
 */
class CashboxPage extends Component
{
    use UsesPosLocale;

    public const RELEASE_DIALOG = 'cashbox-release';

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

    public function mount(Store $store): void
    {
        $this->storeId = $store->id;
        $this->snapshotExpected($this->authorizeCashbox());
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
            'movements' => $session === null ? collect() : $session->movements()
                ->with(['recorder:id,name', 'salePayment.sale:id,number', 'customerPayment:id,receipt_number,customer_id', 'customerPayment.customer:id,name'])
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
                ->limit(20)
                ->get(),
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
