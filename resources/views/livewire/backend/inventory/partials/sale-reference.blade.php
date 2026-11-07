@if ($movement->reference instanceof \App\Models\SaleLine && $movement->reference->sale)
    @php($referencedSale = $movement->reference->sale)
    @php($saleLabel = __('pos.history.sale_reference', ['number' => '<bdi dir="ltr" class="font-mono">'.e($referencedSale->number).'</bdi>']))
    @can('view', $referencedSale)
        <a href="{{ route('pos.sales.show', [$referencedSale->store_id, $referencedSale->id]) }}" class="font-medium text-blue-700 hover:text-blue-900">{!! $saleLabel !!}</a>
    @else
        <span>{!! $saleLabel !!}</span>
    @endcan
@endif
