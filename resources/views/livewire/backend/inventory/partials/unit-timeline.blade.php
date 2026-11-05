{{-- Expects: $timeline (paginator of InventoryMovement), $canViewPurchases (bool), $canManageCosts (bool), $textDirection --}}
@php($arrow = $textDirection === 'rtl' ? '←' : '→')
<section aria-labelledby="unit-timeline-title" class="rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
    <div class="border-b border-gray-100 px-4 py-3">
        <h2 id="unit-timeline-title" class="text-base font-semibold text-gray-900">{{ __('devices.timeline.title') }}</h2>
        <p class="text-xs text-gray-600">{{ __('devices.timeline.subtitle') }}</p>
    </div>

    @if ($timeline->isEmpty())
        <p class="px-4 py-10 text-center text-sm text-gray-600">{{ __('inventory.history.empty') }}</p>
    @else
        <ol class="divide-y divide-gray-100">
            @foreach ($timeline as $movement)
                <li wire:key="timeline-{{ $movement->id }}" class="flex items-start gap-4 px-4 py-3">
                    @if ($movement->quantity_change !== 0)
                        <div @class([
                            'w-14 shrink-0 rounded-md px-2 py-1 text-center font-mono text-sm font-semibold tabular-nums',
                            'bg-green-50 text-green-700' => $movement->quantity_change > 0,
                            'bg-red-50 text-red-700' => $movement->quantity_change < 0,
                        ])>
                            <span dir="ltr">{{ $movement->quantity_change > 0 ? '+' : '−' }}{{ abs($movement->quantity_change) }}</span>
                        </div>
                    @else
                        <div class="flex w-14 shrink-0 justify-center pt-1.5" aria-hidden="true">
                            <span class="h-2.5 w-2.5 rounded-full bg-gray-400"></span>
                        </div>
                    @endif
                    <div class="min-w-0 flex-1 space-y-1">
                        <p class="text-sm font-medium text-gray-900">
                            {{ $movement->type->label() }}
                            @if ($movement->reason->label() !== $movement->type->label() && ! $movement->type->isUnitEvent())
                                <span class="text-gray-400">·</span>
                                <span class="font-normal text-gray-700">{{ $movement->reason->label() }}</span>
                            @endif
                        </p>
                        <p class="flex flex-wrap gap-x-3 text-xs text-gray-600">
                            <span>{{ __('devices.timeline.recorded') }} <time datetime="{{ $movement->occurred_at->toIso8601String() }}" dir="ltr">{{ $movement->occurred_at->format('Y-m-d H:i') }}</time></span>
                            <span><bdi>{{ $movement->actor?->name }}</bdi></span>
                            @if ($movement->unit_status_after && $movement->unit_status_before !== $movement->unit_status_after)
                                <span>
                                    {{ __('inventory.history.status_change') }}:
                                    {{ $movement->unit_status_before?->label() ?? '—' }}
                                    <span aria-hidden="true">{{ $arrow }}</span><span class="sr-only">{{ __('devices.to') }}</span>
                                    {{ $movement->unit_status_after->label() }}
                                </span>
                            @endif
                            @if ($movement->reference instanceof \App\Models\PurchaseReceiptLine && $movement->reference->receipt?->purchase)
                                @php($receiptPurchase = $movement->reference->receipt->purchase)
                                @php($receiptLabel = __('purchases.history.receipt_reference', ['number' => '<bdi dir="ltr" class="font-mono">'.e($receiptPurchase->supplier_invoice_number).'</bdi>']))
                                @if ($canViewPurchases)
                                    <a href="{{ route('subdomain.purchases.show', $receiptPurchase) }}" class="font-medium text-blue-700 hover:text-blue-900">{!! $receiptLabel !!}</a>
                                @else
                                    <span>{!! $receiptLabel !!}</span>
                                @endif
                            @endif
                        </p>

                        @if ($movement->inspection)
                            <p class="text-sm text-gray-800">
                                {{ $movement->inspection->outcome->label() }} ·
                                {{ __('inventory.fields.condition') }}: {{ $movement->inspection->condition_before->label() }} <span aria-hidden="true">{{ $arrow }}</span><span class="sr-only">{{ __('devices.to') }}</span> {{ $movement->inspection->condition_after->label() }}
                            </p>
                            @if ($movement->inspection->findings)
                                <p class="whitespace-pre-line text-sm text-gray-700"><bdi>{{ $movement->inspection->findings }}</bdi></p>
                            @endif
                        @endif

                        @if ($movement->startedRepair)
                            <p class="text-sm text-gray-800">
                                {{ $movement->startedRepair->repairer_type->label() }}@if ($movement->startedRepair->repairer_name) · <bdi>{{ $movement->startedRepair->repairer_name }}</bdi>@endif
                            </p>
                            <p class="whitespace-pre-line text-sm text-gray-700"><bdi>{{ $movement->startedRepair->problem }}</bdi></p>
                        @endif

                        @if ($movement->closedRepair)
                            @if ($movement->closedRepair->returned_at)
                                <p class="text-sm text-gray-800">{{ __('devices.repair.returned_confirmed') }}</p>
                            @endif
                            <p class="whitespace-pre-line text-sm text-gray-700"><bdi>{{ $movement->closedRepair->work_performed ?? $movement->closedRepair->close_reason }}</bdi></p>
                        @endif

                        @if ($movement->identifierCorrection)
                            @php($correction = $movement->identifierCorrection)
                            <p class="text-sm text-gray-800">
                                {{ $correction->type->label() }}:
                                <del dir="ltr" class="font-mono text-gray-600">{{ $correction->old_value }}</del>
                                <span aria-hidden="true">{{ $arrow }}</span><span class="sr-only">{{ __('devices.to') }}</span>
                                <bdi dir="ltr" class="font-mono font-semibold">{{ $correction->new_value }}</bdi>
                            </p>
                            <p class="whitespace-pre-line text-sm text-gray-700"><bdi>{{ $correction->reason }}</bdi></p>
                        @endif

                        @if ($canManageCosts)
                            @foreach ($movement->reversedCostEntries as $entry)
                                <p wire:key="timeline-reversed-{{ $entry->id }}" class="text-sm text-gray-800">
                                    {{ __('devices.timeline.reversed_entry', ['category' => $entry->category->label()]) }}
                                    <del>@include('livewire.backend.inventory.partials.cost-amount', ['entry' => $entry])</del>
                                </p>
                                <p class="whitespace-pre-line text-sm text-gray-700"><bdi>{{ $entry->reversal_reason }}</bdi></p>
                            @endforeach
                            @foreach ($movement->costEntries as $entry)
                                <p wire:key="timeline-cost-{{ $entry->id }}" class="text-sm text-gray-800">
                                    {{ $entry->category->label() }}@if ($entry->description) · <bdi>{{ $entry->description }}</bdi>@endif:
                                    @include('livewire.backend.inventory.partials.cost-amount', ['entry' => $entry])
                                </p>
                            @endforeach
                        @endif

                        @if ($movement->note)
                            <p class="whitespace-pre-line text-sm text-gray-700"><bdi>{{ $movement->note }}</bdi></p>
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>
        @if ($timeline->hasPages())
            <div class="border-t border-gray-100 px-4 py-3">{{ $timeline->links() }}</div>
        @endif
    @endif
</section>
