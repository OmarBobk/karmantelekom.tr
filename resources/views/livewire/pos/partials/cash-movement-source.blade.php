{{-- Expects: $movement (CashMovement with its source relations loaded), $store --}}
<span class="block text-xs text-gray-600">{{ $movement->type->label() }}</span>
@if ($movement->salePayment?->sale)
    <a href="{{ route('pos.sales.show', [$store, $movement->salePayment->sale]) }}" dir="ltr" class="font-mono font-semibold text-blue-700 underline">{{ $movement->salePayment->sale->number }}</a>
@elseif ($movement->customerPayment)
    <bdi dir="ltr" class="font-mono">{{ $movement->customerPayment->receipt_number }}</bdi>
    <span class="text-gray-700">{{ $movement->customerPayment->customer?->name }}</span>
@elseif ($movement->expense)
    <span class="text-gray-700">{{ $movement->expense->category->label() }} · <span dir="auto">{{ $movement->expense->description }}</span></span>
@elseif ($movement->supplierPayment)
    <bdi dir="ltr" class="font-mono">{{ $movement->supplierPayment->reference }}</bdi>
    <span class="text-gray-700">{{ $movement->supplierPayment->supplier?->name }}</span>
@elseif ($movement->transfer)
    <span dir="auto" class="text-gray-700">{{ $movement->transfer->reason }}</span>
@endif
