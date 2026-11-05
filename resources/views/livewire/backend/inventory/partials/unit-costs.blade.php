{{-- Expects: $costEntries (Collection of DeviceCostEntry), $acquisition (?UnitAcquisition), $canViewPurchases, $canRecordCost --}}
@php
    $activeEntries = $costEntries->reject(fn ($entry) => $entry->isReversed());
    $summary = $activeEntries->groupBy(fn ($entry) => $entry->category->value)->map(fn ($entries) => [
        'totals' => $entries->reject(fn ($entry) => $entry->isUnknown())
            ->groupBy(fn ($entry) => $entry->currency->value)
            ->map(fn ($sameCurrency) => $sameCurrency->sum(fn ($entry) => $entry->amountMinor())),
        'unknown' => $entries->filter(fn ($entry) => $entry->isUnknown())->count(),
    ]);
    $grandTotals = $activeEntries->reject(fn ($entry) => $entry->isUnknown())
        ->groupBy(fn ($entry) => $entry->currency->value)
        ->map(fn ($sameCurrency) => $sameCurrency->sum(fn ($entry) => $entry->amountMinor()))
        ->sortKeys();
    $unknownCount = $activeEntries->filter(fn ($entry) => $entry->isUnknown())->count();
@endphp
<section aria-labelledby="unit-costs-title" class="rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
    <div class="flex items-start justify-between gap-3 border-b border-gray-100 px-4 py-3">
        <div>
            <h2 id="unit-costs-title" class="text-base font-semibold text-gray-900">{{ __('devices.costs.title') }}</h2>
            <p class="text-xs text-gray-600">{{ __('devices.costs.subtitle') }}</p>
        </div>
        @if ($canRecordCost)
            <button type="button" x-on:click="$dispatch('open-dialog', { id: 'record-cost' })" class="min-h-11 shrink-0 rounded-lg px-3 text-sm font-medium text-blue-700 hover:bg-blue-50">{{ __('devices.cost.action') }}</button>
        @endif
    </div>

    <dl class="divide-y divide-gray-100 text-sm">
        <div class="flex items-start justify-between gap-3 px-4 py-2.5">
            <dt class="text-gray-700">{{ __('devices.costs.acquisition') }}</dt>
            <dd class="text-end">
                @if (! $acquisition)
                    <span class="rounded bg-amber-50 px-1.5 py-0.5 text-xs font-semibold text-amber-900 ring-1 ring-inset ring-amber-200">{{ __('devices.cost.unknown') }}</span>
                @elseif ($canViewPurchases)
                    @include('livewire.backend.purchases.partials.money', ['minor' => $acquisition->unitCostMinor(), 'currency' => $acquisition->currency, 'class' => 'font-semibold'])
                @else
                    <span class="text-xs text-gray-600">{{ __('devices.costs.acquisition_restricted') }}</span>
                @endif
            </dd>
        </div>
        @foreach (\App\Enums\DeviceCostCategory::cases() as $category)
            @continue(! $summary->has($category->value))
            @php($line = $summary[$category->value])
            <div wire:key="cost-summary-{{ $category->value }}" class="flex items-start justify-between gap-3 px-4 py-2.5">
                <dt class="text-gray-700">{{ $category->label() }}</dt>
                <dd class="flex flex-col items-end gap-0.5">
                    @foreach ($line['totals']->sortKeys() as $currency => $minor)
                        @include('livewire.backend.purchases.partials.money', ['minor' => $minor, 'currency' => $currency, 'class' => 'font-semibold'])
                    @endforeach
                    @if ($line['unknown'] > 0)
                        <span class="text-xs font-medium text-amber-900">{{ trans_choice('devices.costs.unknown_count', $line['unknown'], ['count' => $line['unknown']]) }}</span>
                    @endif
                </dd>
            </div>
        @endforeach
        <div class="flex items-start justify-between gap-3 bg-gray-50 px-4 py-2.5">
            <dt class="font-semibold text-gray-900">{{ __('devices.costs.recorded_total') }}</dt>
            <dd class="flex flex-col items-end gap-0.5">
                @forelse ($grandTotals as $currency => $minor)
                    @include('livewire.backend.purchases.partials.money', ['minor' => $minor, 'currency' => $currency, 'class' => 'font-bold'])
                @empty
                    <span class="text-gray-600">—</span>
                @endforelse
                @if ($unknownCount > 0)
                    <span class="text-xs font-medium text-amber-900">{{ trans_choice('devices.costs.excludes_unknown', $unknownCount, ['count' => $unknownCount]) }}</span>
                @endif
            </dd>
        </div>
    </dl>
    <p class="border-t border-gray-100 px-4 py-2 text-xs text-gray-600">{{ __('devices.costs.currency_note') }}</p>

    <h3 class="border-t border-gray-100 px-4 pt-3 text-sm font-semibold text-gray-900">{{ __('devices.costs.entries') }}</h3>
    @if ($costEntries->isEmpty())
        <p class="px-4 py-4 text-sm text-gray-600">{{ __('devices.costs.empty') }}</p>
    @else
        <ol class="divide-y divide-gray-100">
            @foreach ($costEntries->reverse() as $entry)
                <li wire:key="cost-entry-{{ $entry->id }}" @class(['space-y-1 px-4 py-3', 'bg-gray-50' => $entry->isReversed()])>
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p @class(['text-sm font-medium text-gray-900', 'line-through decoration-gray-400' => $entry->isReversed()])>
                                {{ $entry->category->label() }}@if ($entry->description) · <bdi class="font-normal">{{ $entry->description }}</bdi>@endif
                            </p>
                            <p class="text-xs text-gray-600">
                                {{ __('devices.recorded_by', ['name' => $entry->recorder?->name]) }} · <time dir="ltr" datetime="{{ $entry->recorded_at->toIso8601String() }}">{{ $entry->recorded_at->format('Y-m-d H:i') }}</time>
                                @if ($entry->device_repair_id) · {{ __('devices.costs.for_repair', ['id' => $entry->device_repair_id]) }}@endif
                                @if ($entry->replaces_entry_id) · {{ __('devices.costs.replaces') }}@endif
                            </p>
                        </div>
                        <div @class(['shrink-0 text-end', 'line-through decoration-gray-400 opacity-70' => $entry->isReversed()])>
                            @include('livewire.backend.inventory.partials.cost-amount', ['entry' => $entry])
                        </div>
                    </div>
                    @if ($entry->isReversed())
                        <p class="text-xs text-gray-700">
                            <span class="font-semibold">{{ __('devices.costs.reversed', ['name' => $entry->reverser?->name]) }}</span>
                            <time dir="ltr" datetime="{{ $entry->reversed_at->toIso8601String() }}">{{ $entry->reversed_at->format('Y-m-d H:i') }}</time>:
                            <bdi>{{ $entry->reversal_reason }}</bdi>
                        </p>
                    @elseif ($canRecordCost)
                        <button type="button"
                            x-on:click="reversing = { label: @js($entry->category->label().($entry->description ? ' · '.$entry->description : '')), amount: @js($entry->isUnknown() ? __('devices.cost.unknown') : \App\Services\Purchasing\Money::format($entry->amountMinor()).' '.$entry->currency->value) }; $wire.reversingEntryId = '{{ $entry->id }}'; $wire.replacementCurrency = @js($entry->currency?->value ?? 'TRY'); $wire.replacementDescription = @js($entry->description ?? ''); $dispatch('open-dialog', { id: 'reverse-cost' })"
                            class="min-h-11 rounded-lg px-2 text-sm font-medium text-blue-700 hover:bg-blue-50">
                            {{ __('devices.reversal.action') }}<span class="sr-only"> {{ $entry->category->label() }}</span>
                        </button>
                    @endif
                </li>
            @endforeach
        </ol>
    @endif
</section>
