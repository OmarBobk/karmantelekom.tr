{{-- Expects: $record (Expense or CashPayout with supplier, unit.productDefinition, unit.identifiers loaded) --}}
@if ($record->supplier || $record->unit)
    <span class="block text-xs text-gray-600" data-expense-links>
        @if ($record->supplier)
            {{ __('pos.expenses.fields.supplier') }}: <span dir="auto" class="text-gray-800">{{ $record->supplier->name }}</span>
        @endif
        @if ($record->supplier && $record->unit)
            ·
        @endif
        @if ($record->unit)
            {{ __('pos.expenses.fields.device') }}: <span dir="auto" class="text-gray-800">{{ $record->unit->productDefinition?->name }}</span>
            @if ($record->unit->primaryIdentifierValue())
                <bdi dir="ltr" class="font-mono">{{ $record->unit->primaryIdentifierValue() }}</bdi>
            @endif
        @endif
    </span>
@endif
