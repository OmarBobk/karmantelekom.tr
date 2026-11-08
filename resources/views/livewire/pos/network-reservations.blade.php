@php
    $field = 'mt-1 block min-h-11 w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500';
    $tabClass = 'inline-flex min-h-11 items-center rounded-lg px-4 text-sm font-semibold';
    $stateClasses = [
        'pending' => 'bg-amber-50 text-amber-800 ring-amber-600/30',
        'approved' => 'bg-green-50 text-green-800 ring-green-600/20',
        'received' => 'bg-green-50 text-green-800 ring-green-600/20',
        'in_transit' => 'bg-indigo-50 text-indigo-800 ring-indigo-600/30',
        'fulfilled' => 'bg-violet-50 text-violet-800 ring-violet-600/20',
    ];
@endphp
<section dir="{{ $textDirection }}" lang="{{ $locale }}" aria-labelledby="reservations-title" class="space-y-4 text-start">
    <div>
        <h1 id="reservations-title" class="text-xl font-semibold text-gray-900">{{ __('network.reservations.title') }}</h1>
        <p class="text-sm text-gray-600">{{ $incoming ? __('network.reservations.incoming_help') : __('network.reservations.outgoing_help') }}</p>
    </div>

    <div role="tablist" aria-label="{{ __('network.reservations.title') }}" class="flex gap-2">
        <button type="button" role="tab" aria-selected="{{ $incoming ? 'true' : 'false' }}" wire:click="$set('tab', 'incoming')" @class([$tabClass, 'bg-blue-600 text-white' => $incoming, 'text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50' => ! $incoming])>{{ __('network.reservations.incoming') }}</button>
        <button type="button" role="tab" aria-selected="{{ $incoming ? 'false' : 'true' }}" wire:click="$set('tab', 'outgoing')" @class([$tabClass, 'bg-blue-600 text-white' => ! $incoming, 'text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50' => $incoming])>{{ __('network.reservations.outgoing') }}</button>
    </div>

    @if ($incoming && $canManage)
        <form wire:submit="saveHold" class="flex flex-wrap items-end gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
            <div class="basis-56">
                <label for="hold-minutes" class="block text-sm font-medium text-gray-800">{{ __('network.hold.label') }}</label>
                <input id="hold-minutes" type="number" inputmode="numeric" min="{{ \App\Models\Store::MIN_RESERVATION_MINUTES }}" max="{{ \App\Models\Store::MAX_RESERVATION_MINUTES }}" step="1" wire:model="holdMinutes" aria-describedby="hold-help" class="{{ $field }}">
            </div>
            <button type="submit" class="inline-flex min-h-11 items-center rounded-lg bg-blue-600 px-4 text-sm font-semibold text-white hover:bg-blue-700">{{ __('network.hold.save') }}</button>
            <p id="hold-help" class="basis-full text-xs text-gray-600">{{ $store->reservation_minutes === null ? __('network.hold.not_set') : __('network.hold.help') }}</p>
            @error('reservation_minutes') <p class="basis-full text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
        </form>
    @endif

    @error('reservation')
        <p role="alert" class="rounded-lg bg-red-50 px-4 py-3 text-sm font-medium text-red-800 ring-1 ring-red-200">{{ $message }}</p>
    @enderror

    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200" wire:loading.class="opacity-60" data-testid="network-reservations">
        @if ($reservations->isEmpty())
            <p class="px-4 py-8 text-center text-sm text-gray-600">{{ $incoming ? __('network.reservations.empty_incoming') : __('network.reservations.empty_outgoing') }}</p>
        @else
            <ul class="divide-y divide-gray-100">
                @foreach ($reservations as $reservation)
                    @php
                        $state = $reservation->displayState();
                        $otherStore = $incoming ? $reservation->requestingStore : $reservation->owningStore;
                        $heldHere = $reservation->unit?->store_id === $store->id;
                        $sellable = $reservation->isActive() && ! $reservation->isInTransit() && $heldHere && ($incoming ? $reservation->dispatched_at === null : $reservation->received_at !== null);
                    @endphp
                    <li wire:key="reservation-{{ $reservation->id }}" class="space-y-2 px-4 py-3">
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold text-gray-900">{{ $reservation->productDefinition->name }} <span class="text-sm font-normal text-gray-500">#{{ $reservation->id }}</span></p>
                                <p class="text-sm text-gray-600">
                                    @if ($reservation->productDefinition->model)<bdi>{{ $reservation->productDefinition->model }}</bdi> · @endif
                                    {{ $reservation->unit?->condition->label() }}
                                </p>
                            </div>
                            <span class="text-sm text-gray-700">
                                {{ $incoming ? __('network.reservations.for_branch') : __('network.reservations.from_branch') }}
                                {{ $otherStore->name }} <bdi dir="ltr" class="font-mono text-gray-500">{{ $otherStore->code }}</bdi>
                            </span>
                            <span @class(['rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset', $stateClasses[$state] ?? 'bg-gray-100 text-gray-700 ring-gray-500/20'])>{{ __('network.reservation_states.'.$state) }}</span>
                        </div>

                        <dl class="flex flex-wrap gap-x-6 gap-y-1 text-sm text-gray-600">
                            <div><dt class="inline">{{ __('network.reservations.requested_by') }}</dt> <dd class="inline">{{ $reservation->requester?->name }}, <time datetime="{{ $reservation->requested_at->toIso8601String() }}">{{ $reservation->requested_at->format('d.m.Y H:i') }}</time></dd></div>
                            @if ($reservation->expires_at)
                                <div><dt class="inline">{{ __('network.reservations.held_until') }}</dt> <dd class="inline"><time datetime="{{ $reservation->expires_at->toIso8601String() }}">{{ $reservation->expires_at->format('d.m.Y H:i') }}</time></dd></div>
                            @endif
                            @if ($reservation->dispatched_at)
                                <div><dt class="inline">{{ __('network.reservations.dispatched_at') }}</dt> <dd class="inline">{{ $reservation->dispatched_at->format('d.m.Y H:i') }}</dd></div>
                            @endif
                            @if ($reservation->received_at)
                                <div><dt class="inline">{{ __('network.reservations.received_at') }}</dt> <dd class="inline">{{ $reservation->received_at->format('d.m.Y H:i') }}</dd></div>
                            @endif
                            @if ($reservation->sale)
                                <div><dt class="inline">{{ __('network.reservations.sale') }}</dt> <dd class="inline"><bdi dir="ltr" class="font-mono">{{ $reservation->sale->number }}</bdi></dd></div>
                            @endif
                            @if ($reservation->decision_note)
                                <div><dt class="inline">{{ __('network.reservations.rejection_reason') }}</dt> <dd class="inline" dir="auto">{{ $reservation->decision_note }}</dd></div>
                            @endif
                            @if ($reservation->cancellation_reason)
                                <div><dt class="inline">{{ __('network.reservations.cancellation_reason') }}</dt> <dd class="inline" dir="auto">{{ $reservation->cancellation_reason }}</dd></div>
                            @endif
                        </dl>

                        <div class="flex flex-wrap gap-2">
                            @if ($incoming && $canManage && $state === 'pending')
                                <button type="button" wire:click="approve({{ $reservation->id }})" wire:loading.attr="disabled" wire:target="approve" class="inline-flex min-h-11 items-center rounded-lg bg-blue-600 px-4 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">{{ __('network.reservation.approve') }}</button>
                                <button type="button" wire:click="openReason({{ $reservation->id }}, 'reject')" class="inline-flex min-h-11 items-center rounded-lg px-4 text-sm font-semibold text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50">{{ __('network.reservation.reject') }}</button>
                            @endif
                            @if ($incoming && $canManage && $state === 'approved' && $reservation->dispatched_at === null)
                                <button type="button" wire:click="dispatchDevice({{ $reservation->id }})" wire:confirm="{{ __('network.reservation.dispatch_confirm') }}" wire:loading.attr="disabled" wire:target="dispatchDevice" class="inline-flex min-h-11 items-center rounded-lg bg-indigo-600 px-4 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-60">{{ __('network.reservation.dispatch') }}</button>
                            @endif
                            @if (! $incoming && $state === 'in_transit')
                                <button type="button" wire:click="receive({{ $reservation->id }})" wire:loading.attr="disabled" wire:target="receive" class="inline-flex min-h-11 items-center rounded-lg bg-indigo-600 px-4 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-60">{{ __('network.reservation.receive') }}</button>
                            @endif
                            @if ($sellable)
                                <a href="{{ route('pos.counter', ['store' => $store, 'reservation' => $reservation->id]) }}" class="inline-flex min-h-11 items-center rounded-lg bg-emerald-600 px-4 text-sm font-semibold text-white hover:bg-emerald-700">{{ __('network.reservation.sell') }}</a>
                            @endif
                            @if ($reservation->isCancellable() && ($incoming ? $canManage : true))
                                <button type="button" wire:click="openReason({{ $reservation->id }}, 'cancel')" class="inline-flex min-h-11 items-center rounded-lg px-4 text-sm font-semibold text-red-700 ring-1 ring-red-200 hover:bg-red-50">{{ __('network.reservation.cancel') }}</button>
                            @endif
                        </div>

                        @if ($reasonFor === $reservation->id)
                            <form wire:submit="submitReason" class="space-y-2 rounded-lg bg-gray-50 p-3 ring-1 ring-gray-200">
                                @php $reasonField = $reasonAction === 'reject' ? 'decision_note' : 'cancellation_reason'; @endphp
                                <label for="reservation-reason" class="block text-sm font-medium text-gray-800">{{ $reasonAction === 'reject' ? __('network.reservation.reject_reason') : __('network.reservation.cancel_reason') }}</label>
                                <textarea id="reservation-reason" wire:model="reason" rows="2" maxlength="{{ \App\Models\NetworkReservation::MAX_NOTE_LENGTH }}" required @error($reasonField) aria-invalid="true" aria-describedby="reservation-reason-error" @enderror class="{{ $field }}" x-init="$el.focus()"></textarea>
                                @error($reasonField) <p id="reservation-reason-error" class="text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                                <div class="flex gap-2">
                                    <button type="submit" class="inline-flex min-h-11 items-center rounded-lg bg-red-600 px-4 text-sm font-semibold text-white hover:bg-red-700">{{ $reasonAction === 'reject' ? __('network.reservation.reject') : __('network.reservation.cancel') }}</button>
                                    <button type="button" wire:click="closeReason" class="inline-flex min-h-11 items-center rounded-lg px-4 text-sm font-semibold text-gray-700 ring-1 ring-gray-300 hover:bg-white">{{ __('network.reservation.keep') }}</button>
                                </div>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    {{ $reservations->links() }}
</section>
