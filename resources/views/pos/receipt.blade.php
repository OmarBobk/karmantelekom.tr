@php
    use App\Models\Sale;
    use App\Services\Purchasing\LocalizedMoney;
    use App\Services\Purchasing\Money;
    $money = fn (string|int|null $value) => LocalizedMoney::format(is_int($value) ? $value : Money::fromDecimal($value));
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $textDirection }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>{{ __('pos.receipt.title', ['number' => $sale->number]) }}</title>
        @vite(['resources/css/app.css'])
        <style>
            @page { size: 80mm auto; margin: 3mm; }
            @media print {
                html, body { background: #fff !important; }
                .slip { width: 74mm !important; max-width: none !important; box-shadow: none !important; margin: 0 !important; padding: 0 !important; border: 0 !important; }
            }
        </style>
    </head>
    <body class="bg-gray-100 text-gray-900">
        <nav class="mx-auto flex max-w-sm flex-wrap items-center gap-2 p-3 print:hidden" aria-label="{{ __('pos.receipt.actions') }}">
            <a href="{{ route('pos.sales', $store) }}" class="inline-flex min-h-11 items-center rounded-lg px-3 text-sm font-semibold text-gray-700 ring-1 ring-gray-300 hover:bg-white">{{ __('pos.receipt.back_to_history') }}</a>
            <a href="{{ route('pos.counter', $store) }}" class="inline-flex min-h-11 items-center rounded-lg px-3 text-sm font-semibold text-gray-700 ring-1 ring-gray-300 hover:bg-white">{{ __('pos.nav.counter') }}</a>
            <button type="button" onclick="window.print()" class="ms-auto inline-flex min-h-11 items-center rounded-lg bg-blue-600 px-4 text-sm font-semibold text-white hover:bg-blue-700">{{ __('pos.receipt.print') }}</button>
        </nav>

        <article class="slip mx-auto mb-6 max-w-sm bg-white p-4 text-[12px] leading-snug tabular-nums shadow-sm ring-1 ring-gray-200" aria-label="{{ __('pos.receipt.title', ['number' => $sale->number]) }}">
            <header class="border-b border-dashed border-gray-400 pb-2 text-center">
                <p class="text-sm font-bold">{{ config('app.name') }}</p>
                <p>{{ $store->name }} (<bdi dir="ltr">{{ $store->code }}</bdi>)</p>
                <p class="mt-1 font-semibold">{{ __('pos.receipt.internal_slip') }}</p>
            </header>

            <dl class="grid grid-cols-2 gap-x-2 border-b border-dashed border-gray-400 py-2">
                <dt>{{ __('pos.fields.sale_number') }}</dt>
                <dd dir="ltr" class="text-end font-semibold">{{ $sale->number }}</dd>
                <dt>{{ __('pos.fields.date') }}</dt>
                <dd dir="ltr" class="text-end">{{ Sale::displayTime($sale->completed_at) }}</dd>
                <dt>{{ __('pos.fields.cashier') }}</dt>
                <dd class="text-end">{{ $sale->cashier?->name }}</dd>
            </dl>

            <ul class="divide-y divide-dotted divide-gray-300 border-b border-dashed border-gray-400">
                @foreach ($sale->lines as $line)
                    <li class="py-1.5">
                        <p class="font-semibold">{{ $line->product_name }}@if ($line->product_model) · {{ $line->product_model }}@endif</p>
                        @if ($line->unit_identifier)
                            <p><bdi dir="ltr">{{ $line->unit_identifier }}</bdi>@if ($line->unit_condition) · {{ __('inventory.conditions.'.$line->unit_condition) }}@endif</p>
                        @endif
                        <p class="flex justify-between gap-2">
                            <span dir="ltr">{{ $line->quantity }} × {{ $money($line->unit_price) }}</span>
                            <span dir="ltr" class="font-semibold">{{ $money($line->line_total) }}</span>
                        </p>
                        @if (Money::fromDecimal($line->discount_amount) > 0)
                            <p class="text-gray-700">{{ __('pos.receipt.list_price') }} <span dir="ltr">{{ $money($line->list_unit_price) }}</span> · {{ __('pos.fields.discount') }} <span dir="ltr">-{{ $money($line->discount_amount) }}</span></p>
                        @endif
                    </li>
                @endforeach
            </ul>

            <dl class="grid grid-cols-2 gap-x-2 border-b border-dashed border-gray-400 py-2">
                @if (Money::fromDecimal($sale->discount_total) > 0)
                    <dt>{{ __('pos.fields.subtotal') }}</dt>
                    <dd dir="ltr" class="text-end">{{ $money($sale->subtotal) }}</dd>
                    <dt>{{ __('pos.fields.discount') }}</dt>
                    <dd dir="ltr" class="text-end">-{{ $money($sale->discount_total) }}</dd>
                @endif
                <dt class="text-sm font-bold">{{ __('pos.fields.total') }}</dt>
                <dd dir="ltr" class="text-end text-sm font-bold">{{ $money($sale->total) }} TRY</dd>
            </dl>

            <dl class="grid grid-cols-2 gap-x-2 py-2">
                @foreach ($sale->payments as $payment)
                    <dt>{{ $payment->method->label() }}</dt>
                    <dd dir="ltr" class="text-end">{{ $money($payment->amount) }}</dd>
                    @if ($payment->method === \App\Enums\SalePaymentMethod::CARD)
                        <dt class="text-gray-700">{{ __('pos.fields.card_reference') }}</dt>
                        <dd dir="ltr" class="text-end text-gray-700">{{ $payment->terminal_reference }}</dd>
                    @else
                        <dt class="text-gray-700">{{ __('pos.fields.cash_tendered') }}</dt>
                        <dd dir="ltr" class="text-end text-gray-700">{{ $money($payment->cash_tendered) }}</dd>
                        <dt class="text-gray-700">{{ __('pos.fields.change') }}</dt>
                        <dd dir="ltr" class="text-end text-gray-700">{{ $money($payment->change_given) }}</dd>
                    @endif
                @endforeach
            </dl>

            <footer class="border-t border-dashed border-gray-400 pt-2 text-center">
                <p>{{ __('pos.receipt.not_fiscal') }}</p>
                <p class="mt-1">{{ __('pos.receipt.thanks') }}</p>
            </footer>
        </article>

        @if ($discountAudit->isNotEmpty())
            <section class="mx-auto mb-6 max-w-sm rounded-lg bg-white p-4 text-sm shadow-sm ring-1 ring-gray-200 print:hidden" aria-labelledby="discount-audit-title">
                <h2 id="discount-audit-title" class="font-semibold text-gray-900">{{ __('pos.discount.audit_title') }}</h2>
                <p class="text-xs text-gray-600">{{ __('pos.discount.audit_help') }}</p>
                <ul class="mt-2 divide-y divide-gray-100">
                    @foreach ($discountAudit as $line)
                        <li class="py-2">
                            <p class="font-medium text-gray-900">{{ $line->product_name }}@if ($line->unit_identifier) · <bdi dir="ltr">{{ $line->unit_identifier }}</bdi>@endif</p>
                            <dl class="mt-1 grid grid-cols-2 gap-x-2 gap-y-0.5 text-gray-700">
                                <dt>{{ __('pos.discount.original_price') }}</dt>
                                <dd dir="ltr" class="text-end tabular-nums">{{ $money($line->list_unit_price) }}</dd>
                                <dt>{{ __('pos.discount.amount') }}</dt>
                                <dd dir="ltr" class="text-end tabular-nums">-{{ $money($line->unit_discount) }}</dd>
                                <dt>{{ __('pos.discount.final_price') }}</dt>
                                <dd dir="ltr" class="text-end font-semibold tabular-nums text-gray-900">{{ $money($line->unit_price) }}</dd>
                                @if ($line->discount_lowest_unit_price !== null)
                                    <dt>{{ __('pos.discount.lowest_price') }}</dt>
                                    <dd dir="ltr" class="text-end tabular-nums">{{ $money($line->discount_lowest_unit_price) }}</dd>
                                @endif
                                <dt>{{ __('pos.discount.limit') }}</dt>
                                <dd class="text-end">{{ __('pos.discount.limit_'.$line->discount_limit, ['percent' => \App\Models\PosDiscountGrant::formatBasisPoints((int) $line->discount_basis_points)]) }}</dd>
                                <dt>{{ __('pos.discount.given_by') }}</dt>
                                <dd class="text-end">{{ $line->discountedBy?->name }}</dd>
                            </dl>
                            <p class="mt-1 text-gray-800">{{ __('pos.fields.adjustment_reason') }}: {{ $line->adjustment_reason }}</p>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($autoPrint)
            <script>window.addEventListener('load', () => window.print());</script>
        @endif
    </body>
</html>
