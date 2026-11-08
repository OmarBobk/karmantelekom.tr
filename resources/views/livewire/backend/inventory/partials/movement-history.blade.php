{{-- Expects: $movements (iterable of InventoryMovement), $showUnit (bool); optional $canViewPurchases (bool) --}}
@php($isPaginated = $movements instanceof \Illuminate\Contracts\Pagination\Paginator)
<section aria-labelledby="movement-history-title" class="rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
    <div class="border-b border-gray-100 px-4 py-3">
        <h2 id="movement-history-title" class="text-base font-semibold text-gray-900">{{ __('inventory.history.title') }}</h2>
        <p class="text-xs text-gray-500">{{ __('inventory.history.subtitle') }}</p>
    </div>

    @if (collect($isPaginated ? $movements->items() : $movements)->isEmpty())
        <p class="px-4 py-10 text-center text-sm text-gray-500">{{ __('inventory.history.empty') }}</p>
    @else
        <ol class="divide-y divide-gray-100">
            @foreach ($movements as $movement)
                <li wire:key="movement-{{ $movement->id }}" class="flex flex-wrap items-start gap-x-4 gap-y-1 px-4 py-3">
                    <div @class([
                        'w-16 shrink-0 rounded-md px-2 py-1 text-center font-mono text-sm font-semibold tabular-nums',
                        'bg-green-50 text-green-700' => $movement->quantity_change > 0,
                        'bg-red-50 text-red-700' => $movement->quantity_change < 0,
                    ])>
                        <span dir="ltr">{{ $movement->quantity_change > 0 ? '+' : '−' }}{{ abs($movement->quantity_change) }}</span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-gray-900">
                            {{ $movement->type->label() }}
                            @if ($movement->reason->label() !== $movement->type->label())
                                <span class="text-gray-400">·</span>
                                <span class="font-normal text-gray-700">{{ $movement->reason->label() }}</span>
                            @endif
                        </p>
                        <p class="mt-0.5 flex flex-wrap gap-x-3 text-xs text-gray-500">
                            <time datetime="{{ $movement->occurred_at->toIso8601String() }}" dir="ltr">{{ $movement->occurred_at->format('Y-m-d H:i') }}</time>
                            <span><bdi>{{ $movement->actor?->name }}</bdi></span>
                            @if ($movement->quantity_after !== null && $movement->reason === \App\Enums\AdjustmentReason::PHYSICAL_COUNT)
                                <span>{{ __('inventory.history.previous_balance') }}: <span dir="ltr" class="font-mono">{{ $movement->quantity_after - $movement->quantity_change }}</span></span>
                                <span>{{ __('inventory.history.counted_total') }}: <span dir="ltr" class="font-mono">{{ $movement->quantity_after }}</span></span>
                            @elseif ($movement->quantity_after !== null)
                                <span>{{ __('inventory.history.balance_after') }}: <span dir="ltr" class="font-mono">{{ $movement->quantity_after }}</span></span>
                            @endif
                            @if ($movement->unit_status_after)
                                <span>
                                    {{ __('inventory.history.status_change') }}:
                                    {{ $movement->unit_status_before?->label() ?? '—' }}
                                    <span aria-hidden="true">{{ $textDirection === 'rtl' ? '←' : '→' }}</span>
                                    {{ $movement->unit_status_after->label() }}
                                </span>
                            @endif
                            @if ($movement->reference instanceof \App\Models\PurchaseReceiptLine && $movement->reference->receipt?->purchase)
                                @php($receiptPurchase = $movement->reference->receipt->purchase)
                                @php($receiptLabel = __('purchases.history.receipt_reference', ['number' => '<bdi dir="ltr" class="font-mono">'.e($receiptPurchase->supplier_invoice_number).'</bdi>']))
                                @if ($canViewPurchases ?? false)
                                    <a href="{{ route('subdomain.purchases.show', $receiptPurchase) }}" class="font-medium text-blue-600 hover:text-blue-800">{!! $receiptLabel !!}</a>
                                @else
                                    <span>{!! $receiptLabel !!}</span>
                                @endif
                            @endif
                            @include('livewire.backend.inventory.partials.sale-reference', ['movement' => $movement])
                            @if ($showUnit && $movement->unit)
                                <a href="{{ route('subdomain.inventory.unit', [$movement->store_id, $movement->unit]) }}" class="font-medium text-blue-600 hover:text-blue-800">
                                    <span dir="ltr" class="font-mono">{{ $movement->unit->primaryIdentifierValue() ?? '#'.$movement->unit->id }}</span>
                                </a>
                            @endif
                        </p>
                        @if ($movement->note)
                            <p class="mt-1 whitespace-pre-line text-sm text-gray-700"><bdi>{{ $movement->note }}</bdi></p>
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>
        @if ($isPaginated && $movements->hasPages())
            <div class="border-t border-gray-100 px-4 py-3">{{ $movements->links() }}</div>
        @endif
    @endif
</section>
