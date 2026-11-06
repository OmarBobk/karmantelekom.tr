@php
    $field = 'mt-1 block min-h-11 w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500';
    $invalid = 'border-red-400 focus:border-red-500 focus:ring-red-500';
    $primaryButton = 'inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 disabled:cursor-wait disabled:opacity-60';
    $secondaryButton = 'inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-white px-4 py-2 text-sm font-medium text-gray-800 ring-1 ring-gray-300 hover:bg-gray-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600';
    $openRepair = $unit->openRepair;
    $acquisition = $unit->acquisition;
    $acquiredOn = $canViewPurchases ? $acquisition?->receiptLine?->receipt?->purchase : null;
@endphp

<div dir="{{ $textDirection }}" lang="{{ $locale }}" class="mx-auto max-w-6xl space-y-6 text-start" x-data="{ reversing: {}, correcting: {} }">
    @include('livewire.backend.inventory.partials.breadcrumbs', ['items' => [
        [__('inventory.title'), route('subdomain.inventory')],
        [$store->code, route('subdomain.inventory.store', $store)],
        [$unit->productDefinition->name, route('subdomain.inventory.product', [$store, $unit->productDefinition])],
        [$unit->primaryIdentifierValue() ?? '#'.$unit->id, null],
    ]])

    @if ($staleNotice || $isStale)
        <div role="alert" class="flex flex-wrap items-start gap-3 rounded-xl bg-amber-50 px-4 py-3 ring-1 ring-amber-300">
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-amber-700" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495ZM10 6a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 6Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/></svg>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-amber-900">{{ __('devices.stale.title') }}</p>
                <p class="mt-0.5 text-sm text-amber-900">{{ $staleNotice ?? __('devices.stale.page', ['status' => $unit->status->label()]) }}</p>
            </div>
            @if ($isStale)
                <button type="button" wire:click="refreshState" wire:loading.attr="disabled" wire:target="refreshState" class="min-h-11 rounded-lg bg-white px-3 text-sm font-medium text-amber-900 ring-1 ring-amber-300 hover:bg-amber-100">{{ __('devices.stale.refresh') }}</button>
            @else
                <button type="button" wire:click="dismissStaleNotice" class="min-h-11 rounded-lg px-3 text-sm font-medium text-amber-900 hover:bg-amber-100">{{ __('devices.stale.dismiss') }}</button>
            @endif
        </div>
    @endif

    {{-- Identity: everything the operator needs to confirm this is the right device before acting. --}}
    <section aria-labelledby="unit-title" class="rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
        <div class="flex flex-wrap items-start justify-between gap-4 border-b border-gray-100 p-5">
            <div class="min-w-0">
                <h1 id="unit-title" class="text-xl font-bold text-gray-900">
                    {{ $unit->productDefinition->name }}
                    @if ($unit->productDefinition->model)
                        <bdi dir="ltr" class="ms-1 text-base font-medium text-gray-600">{{ $unit->productDefinition->model }}</bdi>
                    @endif
                </h1>
                <p class="mt-0.5 text-sm text-gray-600">{{ $unit->productDefinition->tracking_mode->label() }}</p>
            </div>
            <span class="shrink-0 rounded-full px-3 py-1 text-sm font-semibold ring-1 ring-inset {{ $unit->status->badgeClasses() }}">{{ $unit->status->label() }}</span>
        </div>

        <div class="grid gap-6 p-5 md:grid-cols-2">
            <dl class="space-y-3">
                @foreach ($unit->identifiers as $identifier)
                    <div wire:key="identifier-{{ $identifier->id }}" class="flex flex-wrap items-end justify-between gap-2">
                        <div class="min-w-0">
                            <dt class="text-xs font-medium text-gray-600">
                                {{ $identifier->type->label() }}@if ($identifier->type === \App\Enums\IdentifierType::IMEI && $unit->identifiers->where('type', \App\Enums\IdentifierType::IMEI)->count() > 1) {{ $identifier->position }}@endif
                            </dt>
                            <dd dir="ltr" class="text-start font-mono text-lg tracking-wide text-gray-900 break-all">{{ $identifier->value }}</dd>
                        </div>
                        @if ($canCorrectIdentity)
                            <button type="button"
                                x-on:click="correcting = { type: @js($identifier->type->label()), value: @js($identifier->value), imei: @js($identifier->type === \App\Enums\IdentifierType::IMEI) }; $wire.correctingIdentifierId = '{{ $identifier->id }}'; $wire.correctionExpectedValue = @js($identifier->value); $dispatch('open-dialog', { id: 'correct-identifier' })"
                                class="min-h-11 rounded-lg px-3 text-sm font-medium text-blue-700 hover:bg-blue-50">
                                {{ __('devices.correction.action') }}<span class="sr-only"> {{ $identifier->type->label() }}</span>
                            </button>
                        @endif
                    </div>
                @endforeach
            </dl>

            <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                <div>
                    <dt class="text-xs font-medium text-gray-600">{{ __('devices.fields.branch') }}</dt>
                    <dd class="font-medium text-gray-900"><bdi dir="ltr" class="font-mono">{{ $store->code }}</bdi> <span class="text-gray-600">{{ $store->name }}</span></dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-600">{{ __('devices.fields.location') }}</dt>
                    <dd class="font-medium text-gray-900">
                        @if ($unit->status === \App\Enums\UnitStatus::REMOVED)
                            {{ __('devices.location.removed') }}
                        @elseif ($unit->status === \App\Enums\UnitStatus::SOLD)
                            {{ __('pos.unit.location_sold') }}
                        @elseif ($openRepair?->isExternal())
                            {!! __('devices.location.external', ['name' => '<bdi>'.e($openRepair->repairer_name).'</bdi>']) !!}
                        @elseif ($openRepair)
                            {{ __('devices.location.in_house') }}
                        @else
                            {{ __('devices.location.in_store') }}
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-600">{{ __('inventory.fields.condition') }}</dt>
                    <dd class="font-medium text-gray-900">{{ $unit->condition->label() }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-600">{{ __('devices.fields.availability') }}</dt>
                    <dd class="font-medium text-gray-900">{{ $unit->status === \App\Enums\UnitStatus::AVAILABLE ? __('devices.availability.available') : __('devices.availability.unavailable') }}</dd>
                </div>
                <div class="col-span-2">
                    <dt class="text-xs font-medium text-gray-600">{{ __('devices.fields.acquisition') }}</dt>
                    <dd class="text-gray-900">
                        @if ($acquisition)
                            @if ($acquiredOn)
                                <a href="{{ route('subdomain.purchases.show', $acquiredOn) }}" class="font-medium text-blue-700 hover:text-blue-900">{!! __('devices.acquisition.invoice', ['number' => '<bdi dir="ltr" class="font-mono">'.e($acquiredOn->supplier_invoice_number).'</bdi>']) !!}</a>
                                @if ($canManageCosts)
                                    <span class="text-gray-600">·</span>
                                    @include('livewire.backend.purchases.partials.money', ['minor' => $acquisition->unitCostMinor(), 'currency' => $acquisition->currency, 'class' => 'font-semibold'])
                                @endif
                            @else
                                {{ __('devices.acquisition.receipt') }}
                            @endif
                        @else
                            <span class="font-medium text-amber-800">{{ __('devices.acquisition.unknown') }}</span>
                        @endif
                    </dd>
                </div>
                @if ($unit->removed_at)
                    <div class="col-span-2">
                        <dt class="text-xs font-medium text-gray-600">{{ __('inventory.fields.removed_at') }}</dt>
                        <dd dir="ltr" class="text-start text-gray-900">{{ $unit->removed_at->format('Y-m-d H:i') }}</dd>
                    </div>
                @endif
                @if ($soldOn)
                    <div class="col-span-2">
                        <dt class="text-xs font-medium text-gray-600">{{ __('pos.unit.sold_on') }}</dt>
                        <dd class="text-gray-900">
                            @php($soldLabel = __('pos.history.sale_reference', ['number' => '<bdi dir="ltr" class="font-mono">'.e($soldOn->number).'</bdi>']))
                            @can('view', $soldOn)
                                <a href="{{ route('pos.sales.show', [$soldOn->store_id, $soldOn->id]) }}" class="font-medium text-blue-700 hover:text-blue-900">{!! $soldLabel !!}</a>
                            @else
                                {!! $soldLabel !!}
                            @endcan
                            · <time dir="ltr" datetime="{{ $soldOn->completed_at->toIso8601String() }}">{{ \App\Models\Sale::displayTime($soldOn->completed_at) }}</time>
                        </dd>
                    </div>
                @endif
                @if ($isUsedDevice)
                    <div class="col-span-2">
                        <dt class="text-xs font-medium text-gray-600">{{ __('pos.pricing.unit_price') }}</dt>
                        <dd class="text-gray-900">
                            @if ($unit->sellingPriceMinor() === null)
                                <span class="inline-flex rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-800 ring-1 ring-amber-200">{{ __('pos.pricing.not_priced') }}</span>
                            @else
                                <span dir="ltr" class="font-mono font-semibold tabular-nums">{{ \App\Services\Purchasing\Money::format($unit->sellingPriceMinor()) }} TRY</span>
                            @endif
                            @if ($canSetUnitPrice)
                                <button type="button" x-on:click="$dispatch('open-dialog', { id: 'unit-price' })" class="ms-2 min-h-11 rounded-lg px-3 text-sm font-medium text-blue-700 hover:bg-blue-50">{{ __('pos.pricing.set_unit_price') }}</button>
                            @endif
                        </dd>
                    </div>
                @endif
            </dl>
        </div>

        @if ($unit->status === \App\Enums\UnitStatus::REMOVED)
            <p class="border-t border-gray-100 bg-gray-50 px-5 py-3 text-sm text-gray-700">{{ __('inventory.remove.already_removed') }}</p>
        @endif
        @if ($canInspect || $canStartRepair || $canCloseRepair || $canRecordCost || $canRemove)
            <div class="flex flex-wrap gap-2 border-t border-gray-100 bg-gray-50/60 px-5 py-4" role="group" aria-label="{{ __('devices.actions.label') }}">
                @if ($canCloseRepair)
                    <button type="button" x-on:click="$dispatch('open-dialog', { id: 'complete-repair' })" class="{{ $primaryButton }}">{{ __('devices.complete.action') }}</button>
                    <button type="button" x-on:click="$dispatch('open-dialog', { id: 'cancel-repair' })" class="{{ $secondaryButton }}">{{ __('devices.cancel.action') }}</button>
                @endif
                @if ($canInspect)
                    <button type="button" x-on:click="$dispatch('open-dialog', { id: 'record-inspection' })" class="{{ $canStartRepair ? $secondaryButton : $primaryButton }}">{{ __('devices.inspection.action') }}</button>
                @endif
                @if ($canStartRepair)
                    <button type="button" x-on:click="$dispatch('open-dialog', { id: 'start-repair' })" class="{{ $primaryButton }}">{{ __('devices.repair.action') }}</button>
                @endif
                @if ($canRecordCost)
                    <button type="button" x-on:click="$dispatch('open-dialog', { id: 'record-cost' })" class="{{ $secondaryButton }}">{{ __('devices.cost.action') }}</button>
                @endif
                @if ($canRemove)
                    <button type="button" x-on:click="$dispatch('open-dialog', { id: 'remove-unit' })" class="ms-auto inline-flex min-h-11 items-center rounded-lg px-4 py-2 text-sm font-medium text-red-700 ring-1 ring-red-200 hover:bg-red-50">{{ __('inventory.remove.title') }}</button>
                @endif
            </div>
        @endif
    </section>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            @if ($isUsedDevice)
                <section aria-labelledby="inspections-title" class="rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                    <h2 id="inspections-title" class="border-b border-gray-100 px-4 py-3 text-base font-semibold text-gray-900">{{ __('devices.inspection.history_title') }}</h2>
                    @forelse ($inspections as $inspection)
                        <article wire:key="inspection-{{ $inspection->id }}" class="border-b border-gray-100 px-4 py-3 last:border-b-0">
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                <span @class(['rounded-full px-2.5 py-0.5 text-xs font-semibold', 'bg-green-50 text-green-800' => $inspection->outcome === \App\Enums\InspectionOutcome::READY, 'bg-amber-50 text-amber-800' => $inspection->outcome === \App\Enums\InspectionOutcome::NEEDS_REPAIR])>{{ $inspection->outcome->label() }}</span>
                                <span class="text-sm text-gray-700">{{ __('inventory.fields.condition') }}: {{ $inspection->condition_before->label() }} <span aria-hidden="true">{{ $textDirection === 'rtl' ? '←' : '→' }}</span><span class="sr-only">{{ __('devices.to') }}</span> {{ $inspection->condition_after->label() }}</span>
                            </div>
                            @if ($inspection->findings)
                                <p class="mt-1 whitespace-pre-line text-sm text-gray-900"><bdi>{{ $inspection->findings }}</bdi></p>
                            @endif
                            <p class="mt-1 text-xs text-gray-600">{{ __('devices.recorded_by', ['name' => $inspection->inspector?->name]) }} · <time dir="ltr" datetime="{{ $inspection->inspected_at->toIso8601String() }}">{{ $inspection->inspected_at->format('Y-m-d H:i') }}</time></p>
                        </article>
                    @empty
                        <p class="px-4 py-6 text-center text-sm text-gray-600">{{ __('devices.inspection.empty') }}</p>
                    @endforelse
                </section>

                <section aria-labelledby="repairs-title" class="rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                    <h2 id="repairs-title" class="border-b border-gray-100 px-4 py-3 text-base font-semibold text-gray-900">{{ __('devices.repair.history_title') }}</h2>
                    @forelse ($repairs as $repair)
                        <article wire:key="repair-{{ $repair->id }}" class="space-y-1 border-b border-gray-100 px-4 py-3 last:border-b-0">
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                <span @class(['rounded-full px-2.5 py-0.5 text-xs font-semibold', 'bg-sky-50 text-sky-800' => $repair->status === \App\Enums\RepairStatus::OPEN, 'bg-green-50 text-green-800' => $repair->status === \App\Enums\RepairStatus::COMPLETED, 'bg-gray-100 text-gray-700' => $repair->status === \App\Enums\RepairStatus::CANCELLED])>{{ $repair->status->label() }}</span>
                                <span class="text-sm font-medium text-gray-900">{{ $repair->repairer_type->label() }}@if ($repair->repairer_name) · <bdi>{{ $repair->repairer_name }}</bdi>@endif</span>
                                <span class="text-xs text-gray-500">#{{ $repair->id }}</span>
                                @if ($repair->status === \App\Enums\RepairStatus::COMPLETED && $repair->active_cost_entries_count === 0)
                                    <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-900">{{ __('devices.repair.costing_pending') }}</span>
                                @endif
                            </div>
                            <p class="text-sm text-gray-900"><span class="font-medium text-gray-600">{{ __('devices.fields.problem') }}:</span> <bdi class="whitespace-pre-line">{{ $repair->problem }}</bdi></p>
                            @if ($repair->work_performed)
                                <p class="text-sm text-gray-900"><span class="font-medium text-gray-600">{{ __('devices.fields.work_performed') }}:</span> <bdi class="whitespace-pre-line">{{ $repair->work_performed }}</bdi></p>
                            @endif
                            @if ($repair->close_reason)
                                <p class="text-sm text-gray-900"><span class="font-medium text-gray-600">{{ __('devices.fields.close_reason') }}:</span> <bdi class="whitespace-pre-line">{{ $repair->close_reason }}</bdi></p>
                            @endif
                            <p class="text-xs text-gray-600">
                                {{ __('devices.repair.started', ['name' => $repair->starter?->name]) }} <time dir="ltr" datetime="{{ $repair->started_at->toIso8601String() }}">{{ $repair->started_at->format('Y-m-d H:i') }}</time>
                                @if ($repair->returned_at)
                                    · {{ __('devices.repair.returned_confirmed') }} <time dir="ltr" datetime="{{ $repair->returned_at->toIso8601String() }}">{{ $repair->returned_at->format('Y-m-d H:i') }}</time>
                                @endif
                                @if ($repair->closed_at)
                                    · {{ __('devices.repair.closed', ['name' => $repair->closer?->name]) }} <time dir="ltr" datetime="{{ $repair->closed_at->toIso8601String() }}">{{ $repair->closed_at->format('Y-m-d H:i') }}</time>
                                @endif
                            </p>
                        </article>
                    @empty
                        <p class="px-4 py-6 text-center text-sm text-gray-600">{{ __('devices.repair.empty') }}</p>
                    @endforelse
                </section>
            @endif

            @include('livewire.backend.inventory.partials.unit-timeline', ['timeline' => $timeline, 'canViewPurchases' => $canViewPurchases, 'canManageCosts' => $canManageCosts])
        </div>

        <aside class="space-y-6">
            @if ($costEntries !== null)
                @include('livewire.backend.inventory.partials.unit-costs', ['costEntries' => $costEntries, 'acquisition' => $acquisition, 'canViewPurchases' => $canViewPurchases, 'canRecordCost' => $canRecordCost])
            @endif
        </aside>
    </div>

    @include('livewire.backend.inventory.partials.unit-dialogs')
</div>
