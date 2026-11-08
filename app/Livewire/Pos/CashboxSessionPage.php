<?php

declare(strict_types=1);

namespace App\Livewire\Pos;

use App\Enums\CashMovementDirection;
use App\Livewire\Pos\Concerns\UsesPosLocale;
use App\Models\CashboxSession;
use App\Models\CashMovement;
use App\Models\Store;
use App\Services\Purchasing\Money;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Read-only reconciliation of one closed cashbox session in a branch. Owner only.
 *
 * The figures are the session's stored opening and closing snapshot, never recomputed, so the
 * page shows exactly what the owner counted against when closing. Movements are listed with
 * their direction, recorder, and source.
 */
class CashboxSessionPage extends Component
{
    use UsesPosLocale;
    use WithPagination;

    #[Locked]
    public int $storeId;

    #[Locked]
    public int $sessionId;

    public function mount(Store $store, int $session): void
    {
        $this->storeId = $store->id;
        $this->sessionId = $session;
        $this->closedSession($this->authorizeCashbox());
    }

    public function render(): View
    {
        $store = $this->authorizeCashbox();
        $session = $this->closedSession($store)->load(['opener:id,name', 'closer:id,name']);

        return view('livewire.pos.cashbox-session-page', [
            'store' => $store,
            'session' => $session,
            'openingExpected' => $session->opening_expected === null ? null : Money::fromDecimal($session->opening_expected),
            'openingDifference' => $session->opening_difference === null ? null : Money::fromDecimal($session->opening_difference),
            'expectedClosing' => Money::fromDecimal($session->expected_closing),
            'countedClosing' => (int) $session->countedClosingMinor(),
            'closingDifference' => Money::fromDecimal($session->closing_difference),
            'movementCounts' => $session->movements()->toBase()->selectRaw('direction, count(*) as total')->groupBy('direction')->pluck('total', 'direction'),
            'inValue' => CashMovementDirection::IN->value,
            'movements' => $session->movements()
                ->with(CashMovement::DISPLAY_RELATIONS)
                ->oldest('occurred_at')
                ->oldest('id')
                ->paginate(25, pageName: 'movements'),
            ...$this->localeViewData(),
        ])->layout('layouts.pos', ['posStore' => $store])->title(__('pos.cashbox.reconciliation.title', ['store' => $store->code]));
    }

    private function closedSession(Store $store): CashboxSession
    {
        return CashboxSession::query()->where('store_id', $store->id)->closed()->findOrFail($this->sessionId);
    }

    private function authorizeCashbox(): Store
    {
        $store = Store::query()->findOrFail($this->storeId);
        $this->authorize('manageCashbox', $store);

        return $store;
    }
}
