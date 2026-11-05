{{-- Expects: $isStale, $unit, $openRepair. Shown inside revision-bound dialogs; the form stays bound to the old state until the operator continues. --}}
@if ($isStale)
    <div role="alert" class="space-y-2 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-950 ring-1 ring-inset ring-amber-300">
        <p class="font-semibold">{{ __('devices.stale.form_title') }}</p>
        <p>{{ __('devices.stale.form_current', ['status' => $unit->status->label()]) }}</p>
        @if ($openRepair)
            <p>
                {{ __('devices.stale.form_repair', ['id' => $openRepair->id]) }}
                {{ $openRepair->repairer_type->label() }}@if ($openRepair->repairer_name) · <bdi>{{ $openRepair->repairer_name }}</bdi>@endif
            </p>
        @endif
        <p>{{ __('devices.stale.form_help') }}</p>
        <button type="button" wire:click="refreshState" wire:loading.attr="disabled" wire:target="refreshState" class="inline-flex min-h-11 items-center rounded-lg bg-white px-3 text-sm font-medium text-amber-950 ring-1 ring-amber-300 hover:bg-amber-100 disabled:cursor-wait disabled:opacity-60">
            {{ __('devices.stale.refresh') }}
        </button>
    </div>
@endif
