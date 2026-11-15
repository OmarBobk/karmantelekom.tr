<?php

declare(strict_types=1);

namespace App\Livewire\Pos;

use App\Actions\Network\ApproveReservation;
use App\Actions\Network\CancelReservation;
use App\Actions\Network\DispatchReservation;
use App\Actions\Network\ReceiveReservation;
use App\Actions\Network\RejectReservation;
use App\Actions\Network\SetReservationHold;
use App\Enums\ReservationStatus;
use App\Livewire\Pos\Concerns\UsesPosLocale;
use App\Models\NetworkReservation;
use App\Models\Store;
use App\Services\Reporting\LocalDateRange;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Store Network reservations of one branch: incoming requests for its devices and its own
 * outgoing requests, with the actions each side may take. Every action re-authorizes and
 * rechecks the reservation on the server; the rows are rebuilt on each request.
 */
class NetworkReservations extends Component
{
    use UsesPosLocale;
    use WithPagination;

    public const PER_PAGE = 20;

    #[Locked]
    public int $storeId;

    #[Url(except: 'incoming')]
    public string $tab = 'incoming';

    #[Url(except: '')]
    public string $state = '';

    #[Url(except: '')]
    public string $event = '';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    /** Reservation awaiting a reason before it is rejected or cancelled. */
    #[Locked]
    public ?int $reasonFor = null;

    #[Locked]
    public string $reasonAction = '';

    public string $reason = '';

    public string $holdMinutes = '';

    public function mount(Store $store): void
    {
        $this->storeId = $store->id;
        $this->authorizePage();
        $this->tab = in_array($this->tab, ['incoming', 'outgoing'], true) ? $this->tab : 'incoming';
        $this->state = Str::trim($this->state);
        $this->event = Str::trim($this->event);
        $this->from = Str::trim($this->from);
        $this->to = Str::trim($this->to);
        $this->holdMinutes = $store->reservation_minutes === null ? '' : (string) $store->reservation_minutes;
    }

    public function updatedTab(): void
    {
        $this->tab = in_array($this->tab, ['incoming', 'outgoing'], true) ? $this->tab : 'incoming';
        $this->closeReason();
        $this->resetPage();
    }

    public function updatedState(): void
    {
        $this->state = Str::trim($this->state);
        $this->resetPage();
    }

    public function updatedEvent(): void
    {
        $this->event = Str::trim($this->event);
        $this->resetPage();
    }

    public function clearReportFilters(): void
    {
        $this->state = '';
        $this->event = '';
        $this->from = '';
        $this->to = '';
        $this->resetPage();
    }

    public function approve(ApproveReservation $approve, int $reservationId): void
    {
        $approve(auth()->user(), $this->authorizePage(), $reservationId);
        $this->done(__('network.reservation.approved_message'));
    }

    public function dispatchDevice(DispatchReservation $dispatch, int $reservationId): void
    {
        $dispatch(auth()->user(), $this->authorizePage(), $reservationId);
        $this->done(__('network.reservation.dispatched_message'));
    }

    public function receive(ReceiveReservation $receive, int $reservationId): void
    {
        $reservation = $receive(auth()->user(), $this->authorizePage(), $reservationId);
        $this->done($reservation->status === ReservationStatus::EXPIRED ? __('network.reservation.received_released_message') : __('network.reservation.received_message'));
    }

    public function openReason(int $reservationId, string $action): void
    {
        $this->authorizePage();
        $this->reasonFor = $reservationId;
        $this->reasonAction = $action === 'reject' ? 'reject' : 'cancel';
        $this->reason = '';
        $this->resetErrorBag();
    }

    public function closeReason(): void
    {
        $this->reasonFor = null;
        $this->reasonAction = '';
        $this->reason = '';
    }

    public function submitReason(RejectReservation $reject, CancelReservation $cancel): void
    {
        $store = $this->authorizePage();

        if ($this->reasonFor === null) {
            return;
        }

        if ($this->reasonAction === 'reject') {
            $reject(auth()->user(), $store, $this->reasonFor, $this->reason);
            $this->done(__('network.reservation.rejected_message'));
        } else {
            $cancel(auth()->user(), $store, $this->reasonFor, $this->reason);
            $this->done(__('network.reservation.cancelled_message'));
        }
    }

    public function saveHold(SetReservationHold $setHold): void
    {
        $store = $setHold(auth()->user(), $this->authorizePage(), $this->holdMinutes);
        $this->holdMinutes = (string) $store->reservation_minutes;
        $this->resetErrorBag();
        $this->notify('success', __('network.hold.saved_message'));
    }

    public function render(): View
    {
        $store = $this->authorizePage();
        $incoming = $this->tab === 'incoming';
        $asOf = now();
        $filterError = $this->filterError();

        $reservations = NetworkReservation::query()
            ->where($incoming ? 'owning_store_id' : 'requesting_store_id', $store->id)
            ->when($filterError === null && $this->state !== '', fn ($query) => $query->inDisplayState($this->state, $asOf))
            ->when($filterError === null && $this->event !== '', function ($query) use ($asOf) {
                $from = LocalDateRange::date($this->from);
                $to = LocalDateRange::date($this->to);

                return $query->forPeriodEvent($this->event, $from, $to, $asOf);
            })
            ->when($filterError !== null && ($this->state !== '' || $this->event !== ''), fn ($query) => $query->whereRaw('0 = 1'))
            ->with([
                'productDefinition:id,name,model',
                'unit:id,store_id,condition,status',
                'owningStore:id,name,code',
                'requestingStore:id,name,code',
                'requester:id,name',
                'sale:id,store_id,user_id,number',
            ])
            ->orderByRaw('case when status in (?, ?) then 0 else 1 end', [ReservationStatus::PENDING->value, ReservationStatus::APPROVED->value])
            ->orderByDesc('requested_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE);

        return view('livewire.pos.network-reservations', [
            'store' => $store,
            'incoming' => $incoming,
            'reservations' => $reservations,
            'filterError' => $filterError,
            'hasReportFilters' => $this->state !== '' || $this->event !== '',
            'canManage' => auth()->user()->can('manageReservations', $store),
            ...$this->localeViewData(),
        ])->layout('layouts.pos', ['posStore' => $store])->title(__('network.reservations.title'));
    }

    private function filterError(): ?string
    {
        if ($this->state !== '' && ! in_array($this->state, NetworkReservation::DISPLAY_STATES, true)) {
            return 'invalid_state';
        }

        if ($this->event !== '') {
            if (! in_array($this->event, NetworkReservation::PERIOD_EVENTS, true)) {
                return 'invalid_event';
            }

            return LocalDateRange::error($this->from, $this->to);
        }

        return null;
    }

    private function done(string $message): void
    {
        $this->closeReason();
        $this->resetErrorBag();
        $this->notify('success', $message);
    }

    private function notify(string $type, string $message): void
    {
        $this->dispatch('notify', ['type' => $type, 'message' => $message]);
    }

    private function authorizePage(): Store
    {
        $store = Store::query()->findOrFail($this->storeId);
        $this->authorize('requestReservations', $store);

        return $store;
    }
}
