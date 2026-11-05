{{-- Expects: $entry (DeviceCostEntry). Unknown is shown as a word, never as 0. --}}
@if ($entry->isUnknown())
    <span class="rounded bg-amber-50 px-1.5 py-0.5 text-xs font-semibold text-amber-900 ring-1 ring-inset ring-amber-200">{{ __('devices.cost.unknown') }}</span>
@else
    @include('livewire.backend.purchases.partials.money', ['minor' => $entry->amountMinor(), 'currency' => $entry->currency, 'class' => 'font-semibold'])
@endif
