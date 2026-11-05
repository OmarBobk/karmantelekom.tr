{{-- Expects: $purchase (Purchase with status, received_at, total, paid_total). Each state uses its own colour and label. --}}
@php
    $statusStyles = [
        \App\Enums\PurchaseStatus::DRAFT->value => 'bg-gray-100 text-gray-700 ring-gray-300',
        \App\Enums\PurchaseStatus::POSTED->value => 'bg-blue-50 text-blue-800 ring-blue-200',
        \App\Enums\PurchaseStatus::CANCELLED->value => 'bg-white text-gray-500 ring-gray-300 line-through',
        \App\Enums\PurchaseStatus::REVERSED->value => 'bg-rose-50 text-rose-800 ring-rose-200',
    ];
    $paid = $purchase->paidTotalMinor();
    $paymentState = $paid === 0 ? 'unpaid' : ($paid >= $purchase->totalMinor() ? 'paid' : 'partial');
@endphp
<span class="inline-flex flex-wrap items-center gap-1.5">
    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset {{ $statusStyles[$purchase->status->value] }}">{{ $purchase->status->label() }}</span>
    @if ($purchase->status === \App\Enums\PurchaseStatus::POSTED)
        @if ($purchase->received_at)
            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-800 ring-1 ring-inset ring-emerald-200">
                <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.6l7.3-7.3a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>
                {{ __('purchases.receipt_states.received') }}
            </span>
        @else
            <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-900 ring-1 ring-inset ring-amber-300">
                <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm.75-12.25a.75.75 0 0 0-1.5 0V10c0 .2.08.39.22.53l2.5 2.5a.75.75 0 1 0 1.06-1.06l-2.28-2.28V5.75Z" clip-rule="evenodd"/></svg>
                {{ __('purchases.receipt_states.pending') }}
            </span>
        @endif
        <span @class([
            'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset',
            'bg-red-50 text-red-800 ring-red-200' => $paymentState === 'unpaid',
            'bg-violet-50 text-violet-800 ring-violet-200' => $paymentState === 'partial',
            'bg-emerald-600 text-white ring-emerald-600' => $paymentState === 'paid',
        ])>{{ __('purchases.payment_states.'.$paymentState) }}</span>
    @endif
</span>
