@php
    use App\Models\Sale;
    use App\Services\Purchasing\LocalizedMoney;
    use App\Services\Purchasing\Money;
    $signed = fn (int $minor): string => ($minor > 0 ? '+' : '').LocalizedMoney::format($minor);
    $pendingEntry = fn (array $pending): string => trans_choice('reports.finance.pending_entry', $pending['count'], ['count' => $pending['count'], 'amount' => LocalizedMoney::format($pending['amount'])]);
    $card = 'overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200';
    $th = 'px-4 py-2 text-start font-medium';
    $thEnd = 'px-4 py-2 text-end font-medium';
    $money = 'px-4 py-3 text-end tabular-nums text-gray-900';
    $link = 'inline-flex min-h-11 items-center rounded-lg px-3 text-sm font-medium text-blue-700 hover:bg-blue-50';
    $period = '<bdi dir="ltr">'.e($from).'</bdi>';
    $periodTo = '<bdi dir="ltr">'.e($to).'</bdi>';
@endphp
<div dir="{{ $textDirection }}" lang="{{ $locale }}" class="mx-auto max-w-6xl space-y-6 text-start">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('reports.finance.title') }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ __('reports.finance.subtitle') }}</p>
        </div>
        @if ($canViewDashboard)
            <a href="{{ route('subdomain.operations.dashboard', ['from' => $from, 'to' => $to]) }}" class="{{ $link }} ring-1 ring-blue-200">{{ __('reports.finance.open_dashboard') }}</a>
        @endif
    </div>

    @if ($expenses || $cashbox)
        <form wire:submit="applyRange" class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
            <fieldset>
                <legend class="text-sm font-semibold text-gray-900">{{ __('reports.finance.range_legend') }}</legend>
                <div class="mt-2 flex flex-wrap items-end gap-3">
                    <div>
                        <label for="finance-from" class="block text-sm font-medium text-gray-800">{{ __('reports.range.from') }}</label>
                        <input id="finance-from" type="date" wire:model="from" dir="ltr" required @if ($rangeError) aria-invalid="true" aria-describedby="finance-range-error" @endif class="mt-1 block min-h-11 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label for="finance-to" class="block text-sm font-medium text-gray-800">{{ __('reports.range.to') }}</label>
                        <input id="finance-to" type="date" wire:model="to" dir="ltr" required @if ($rangeError) aria-invalid="true" aria-describedby="finance-range-error" @endif class="mt-1 block min-h-11 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <button type="submit" wire:loading.attr="disabled" class="inline-flex min-h-11 items-center rounded-lg bg-blue-600 px-4 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">{{ __('reports.range.apply') }}</button>
                    <button type="button" wire:click="showToday" wire:loading.attr="disabled" class="inline-flex min-h-11 items-center rounded-lg px-4 text-sm font-semibold text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50 disabled:opacity-60">{{ __('reports.range.today') }}</button>
                    <span wire:loading class="text-sm text-gray-500" role="status">{{ __('reports.range.loading') }}</span>
                </div>
                @if ($rangeError)
                    <p id="finance-range-error" role="alert" class="mt-2 text-sm font-medium text-red-700">{{ __('reports.range.'.$rangeError) }}</p>
                @endif
                <p class="mt-2 text-xs text-gray-500">{{ __('reports.finance.range_note') }}</p>
            </fieldset>
        </form>
    @endif

    @if ($expenses)
        <section aria-labelledby="finance-expenses-title" class="{{ $card }}" wire:loading.class="opacity-60">
            <div class="border-b border-gray-100 px-4 py-3">
                <h2 id="finance-expenses-title" class="text-base font-semibold text-gray-900">{{ __('reports.finance.expenses.title') }}</h2>
                @unless ($rangeError)
                    <p class="mt-1 text-sm text-gray-600">{!! __('reports.finance.expenses.period', ['from' => $period, 'to' => $periodTo]) !!}</p>
                @endunless
                <p class="mt-1 text-xs text-gray-500">{{ __('reports.finance.expenses.note') }}</p>
            </div>
            @if ($rangeError)
                <p class="px-4 py-8 text-center text-sm text-gray-600">{{ __('reports.finance.unavailable') }}</p>
            @else
                @if ($expenses['categories'] === [])
                    <p class="px-4 pt-4 text-sm text-gray-600">{{ __('reports.finance.expenses.empty') }}</p>
                @endif
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-xs text-gray-600">
                            <tr>
                                <th scope="col" class="{{ $th }}">{{ __('reports.sales.branch') }}</th>
                                @foreach ($expenses['categories'] as $category)
                                    <th scope="col" class="{{ $thEnd }}">{{ $category->label() }}</th>
                                @endforeach
                                <th scope="col" class="{{ $thEnd }}">{{ __('reports.finance.expenses.total') }}</th>
                                <th scope="col" class="px-4 py-2"><span class="sr-only">{{ __('reports.finance.expenses.view') }}</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($expenses['rows'] as $row)
                                <tr wire:key="finance-expenses-{{ $row['store']->id }}">
                                    <th scope="row" class="px-4 py-3 text-start font-medium text-gray-900">{{ $row['store']->name }} <span dir="ltr" class="ms-1 font-mono text-xs text-gray-500">{{ $row['store']->code }}</span></th>
                                    @foreach ($expenses['categories'] as $category)
                                        <td dir="ltr" class="{{ $money }}">{{ LocalizedMoney::format($row['categories'][$category->value] ?? 0) }}</td>
                                    @endforeach
                                    <td class="{{ $money }} font-semibold"><span dir="ltr">{{ LocalizedMoney::format($row['total']) }} TRY</span></td>
                                    <td class="px-4 py-3 text-end">
                                        <a href="{{ route('pos.expenses', [$row['store'], 'from' => $from, 'to' => $to]) }}" aria-label="{{ __('reports.finance.expenses.view_label', ['branch' => $row['store']->code]) }}" class="{{ $link }}">{{ __('reports.finance.expenses.view') }}</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t-2 border-gray-200 bg-gray-50">
                            <tr>
                                <th scope="row" class="px-4 py-3 text-start font-semibold text-gray-900">{{ __('reports.all_branches') }}</th>
                                @foreach ($expenses['categories'] as $category)
                                    <td dir="ltr" class="{{ $money }} font-semibold">{{ LocalizedMoney::format($expenses['category_totals'][$category->value]) }}</td>
                                @endforeach
                                <td class="{{ $money }} font-bold"><span dir="ltr">{{ LocalizedMoney::format($expenses['total']) }} TRY</span></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </section>
    @endif

    @if ($cashbox)
        <section aria-labelledby="finance-cashbox-title" class="space-y-3">
            <div>
                <h2 id="finance-cashbox-title" class="text-base font-semibold text-gray-900">{{ __('reports.finance.cashbox.title') }} <span class="ms-1 rounded-md bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">{{ __('reports.finance.current') }}</span></h2>
                <p class="text-sm text-gray-600">{{ __('reports.finance.cashbox.note') }}</p>
            </div>
            <ul class="grid gap-4 md:grid-cols-2">
                @foreach ($cashbox['live'] as $live)
                    <li wire:key="finance-cashbox-{{ $live['store']->id }}" class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                        <div class="flex items-start justify-between gap-3">
                            <p class="font-semibold text-gray-900">{{ $live['store']->name }}</p>
                            <span dir="ltr" class="rounded-md bg-gray-100 px-2 py-0.5 font-mono text-xs text-gray-700">{{ $live['store']->code }}</span>
                        </div>
                        @if ($live['state'] === 'open')
                            <p class="mt-1 text-sm text-emerald-800">{{ __('reports.finance.cashbox.state_open', ['time' => Sale::displayTime($live['session']->opened_at)]) }}</p>
                            <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                                <dt class="font-semibold text-gray-900">{{ __('reports.finance.cashbox.expected') }}</dt>
                                <dd dir="ltr" class="text-end text-lg font-bold tabular-nums text-gray-900">{{ LocalizedMoney::format($live['expected']) }} TRY</dd>
                                <dt class="text-gray-700">{{ __('reports.finance.cashbox.available') }}</dt>
                                <dd dir="ltr" class="text-end font-semibold tabular-nums text-gray-900">{{ LocalizedMoney::format($live['available']) }} TRY</dd>
                            </dl>
                            <p class="mt-3 text-xs font-medium text-gray-700">{{ __('reports.finance.cashbox.pending_title') }}</p>
                            <dl class="mt-1 grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                                @foreach ($live['pending'] as $kind => $pending)
                                    <dt class="text-gray-600">{{ __('reports.finance.cashbox.pending.'.$kind) }}</dt>
                                    <dd class="text-end tabular-nums text-gray-800">{{ $pendingEntry($pending) }}</dd>
                                @endforeach
                            </dl>
                            <p class="mt-2 text-xs text-gray-500">{{ __('reports.finance.cashbox.pending_note') }}</p>
                        @else
                            <p class="mt-2 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-950 ring-1 ring-amber-300">{{ __('reports.finance.cashbox.state_'.$live['state']) }}</p>
                            @if ($live['previous'])
                                <p class="mt-2 text-sm text-gray-700">{{ __('reports.finance.cashbox.last_closed', ['time' => Sale::displayTime($live['previous']->closed_at), 'amount' => LocalizedMoney::format($live['previous']->countedClosingMinor())]) }}</p>
                            @endif
                        @endif
                        <a href="{{ route('pos.cashbox', $live['store']) }}" aria-label="{{ __('reports.finance.cashbox.open_label', ['branch' => $live['store']->code]) }}" class="{{ $link }} mt-2 -ms-3">{{ __('reports.finance.cashbox.open') }}</a>
                    </li>
                @endforeach
            </ul>
        </section>

        <section aria-labelledby="finance-differences-title" class="{{ $card }}" wire:loading.class="opacity-60">
            <div class="border-b border-gray-100 px-4 py-3">
                <h2 id="finance-differences-title" class="text-base font-semibold text-gray-900">{{ __('reports.finance.differences.title') }}</h2>
                @unless ($rangeError)
                    <p class="mt-1 text-sm text-gray-600">{!! __('reports.finance.differences.period', ['from' => $period, 'to' => $periodTo]) !!}</p>
                @endunless
                <p class="mt-1 text-xs text-gray-500">{{ __('reports.finance.differences.note') }}</p>
            </div>
            @if ($rangeError)
                <p class="px-4 py-8 text-center text-sm text-gray-600">{{ __('reports.finance.unavailable') }}</p>
            @else
                @php($differences = $cashbox['differences'])
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-xs text-gray-600">
                            <tr>
                                <th scope="col" class="{{ $th }}">{{ __('reports.sales.branch') }}</th>
                                <th scope="col" class="{{ $thEnd }}">{{ __('reports.finance.differences.sessions') }}</th>
                                <th scope="col" class="{{ $thEnd }}">{{ __('reports.finance.differences.with_difference') }}</th>
                                <th scope="col" class="{{ $thEnd }}">{{ __('reports.finance.differences.overage') }}</th>
                                <th scope="col" class="{{ $thEnd }}">{{ __('reports.finance.differences.shortage') }}</th>
                                <th scope="col" class="{{ $thEnd }}">{{ __('reports.finance.differences.net') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($differences['rows'] as $row)
                                <tr wire:key="finance-differences-{{ $row['store']->id }}">
                                    <th scope="row" class="px-4 py-3 text-start font-medium text-gray-900">{{ $row['store']->name }} <span dir="ltr" class="ms-1 font-mono text-xs text-gray-500">{{ $row['store']->code }}</span></th>
                                    <td dir="ltr" class="{{ $money }}">{{ $row['sessions'] }}</td>
                                    <td dir="ltr" class="{{ $money }}">{{ $row['with_difference'] }}</td>
                                    <td dir="ltr" class="{{ $money }}">{{ LocalizedMoney::format($row['overage']) }}</td>
                                    <td dir="ltr" class="{{ $money }}">{{ LocalizedMoney::format($row['shortage']) }}</td>
                                    <td dir="ltr" class="{{ $money }} font-semibold">{{ $signed($row['overage'] - $row['shortage']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t-2 border-gray-200 bg-gray-50">
                            <tr>
                                <th scope="row" class="px-4 py-3 text-start font-semibold text-gray-900">{{ __('reports.all_branches') }}</th>
                                <td dir="ltr" class="{{ $money }} font-semibold">{{ $differences['sessions'] }}</td>
                                <td dir="ltr" class="{{ $money }} font-semibold">{{ $differences['with_difference'] }}</td>
                                <td dir="ltr" class="{{ $money }} font-semibold">{{ LocalizedMoney::format($differences['overage']) }} TRY</td>
                                <td dir="ltr" class="{{ $money }} font-semibold">{{ LocalizedMoney::format($differences['shortage']) }} TRY</td>
                                <td dir="ltr" class="{{ $money }} font-bold">{{ $signed($differences['net']) }} TRY</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="border-t border-gray-100 px-4 py-3">
                    <h3 class="text-sm font-semibold text-gray-900">{{ __('reports.finance.differences.list_title') }}</h3>
                </div>
                @if ($cashbox['sessions']->isEmpty())
                    <p class="px-4 pb-6 text-center text-sm text-gray-600">{{ __('reports.finance.differences.empty') }}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-xs text-gray-600">
                                <tr>
                                    <th scope="col" class="{{ $th }}">{{ __('reports.finance.differences.closed_at') }}</th>
                                    <th scope="col" class="{{ $th }}">{{ __('reports.sales.branch') }}</th>
                                    <th scope="col" class="{{ $thEnd }}">{{ __('reports.finance.differences.expected') }}</th>
                                    <th scope="col" class="{{ $thEnd }}">{{ __('reports.finance.differences.counted') }}</th>
                                    <th scope="col" class="{{ $thEnd }}">{{ __('reports.finance.differences.closing_difference') }}</th>
                                    <th scope="col" class="{{ $thEnd }}">{{ __('reports.finance.differences.opening_difference') }}</th>
                                    <th scope="col" class="px-4 py-2"><span class="sr-only">{{ __('reports.finance.differences.view') }}</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($cashbox['sessions'] as $session)
                                    <tr wire:key="finance-session-{{ $session->id }}">
                                        <td class="px-4 py-3"><time dir="ltr" datetime="{{ $session->closed_at->toIso8601String() }}">{{ Sale::displayTime($session->closed_at) }}</time></td>
                                        <td dir="ltr" class="px-4 py-3 font-mono text-xs text-gray-700">{{ $session->store->code }}</td>
                                        <td dir="ltr" class="{{ $money }}">{{ LocalizedMoney::format(Money::fromDecimal($session->expected_closing)) }}</td>
                                        <td dir="ltr" class="{{ $money }}">{{ LocalizedMoney::format($session->countedClosingMinor()) }}</td>
                                        <td dir="ltr" class="{{ $money }} font-semibold">{{ $signed(Money::fromDecimal($session->closing_difference)) }}</td>
                                        <td dir="ltr" class="px-4 py-3 text-end tabular-nums text-gray-500">{{ $session->opening_difference === null ? '—' : $signed(Money::fromDecimal($session->opening_difference)) }}</td>
                                        <td class="px-4 py-3 text-end">
                                            <a href="{{ route('pos.cashbox.session', [$session->store, $session]) }}" aria-label="{{ __('reports.finance.differences.view_label', ['branch' => $session->store->code]) }}" class="{{ $link }}">{{ __('reports.finance.differences.view') }}</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="px-4 py-3">{{ $cashbox['sessions']->links() }}</div>
                @endif
            @endif
        </section>
    @endif

    @if ($suppliers)
        <section aria-labelledby="finance-suppliers-title" class="{{ $card }}">
            <div class="border-b border-gray-100 px-4 py-3">
                <h2 id="finance-suppliers-title" class="text-base font-semibold text-gray-900">{{ __('reports.finance.suppliers.title') }} <span class="ms-1 rounded-md bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">{{ __('reports.finance.current') }}</span></h2>
                <p class="mt-1 text-xs text-gray-500">{{ __('reports.finance.suppliers.note') }}</p>
            </div>
            @if ($suppliers['totals'] === [] && $suppliers['pending'] === [])
                <p class="px-4 py-8 text-center text-sm text-gray-600">{{ __('reports.finance.suppliers.empty') }}</p>
            @else
                <div class="grid gap-4 p-4 md:grid-cols-2">
                    @foreach ($suppliers['totals'] as $currency => $totals)
                        <div wire:key="finance-supplier-total-{{ $currency }}" class="rounded-lg p-4 ring-1 {{ $totals['balance'] === $totals['open_invoices'] ? 'ring-gray-200' : 'ring-red-300' }}">
                            <p class="text-xs text-gray-600">{{ __('reports.finance.suppliers.owed', ['currency' => $currency]) }}</p>
                            <p dir="ltr" class="text-start text-xl font-bold tabular-nums text-gray-900">{{ LocalizedMoney::format($totals['balance']) }} {{ $currency }}</p>
                            <p class="mt-1 text-sm {{ $totals['balance'] === $totals['open_invoices'] ? 'text-emerald-800' : 'font-medium text-red-700' }}">
                                {{ $totals['balance'] === $totals['open_invoices'] ? __('purchases.account.reconciled') : __('purchases.account.not_reconciled') }}
                            </p>
                            <dl class="mt-2 grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                                @foreach ($totals['stores'] as $storeId => $store)
                                    <dt class="text-gray-600"><span dir="ltr" class="font-mono text-xs">{{ $suppliers['stores'][$storeId]->code ?? $storeId }}</span></dt>
                                    <dd dir="ltr" class="text-end tabular-nums text-gray-800">{{ LocalizedMoney::format($store['balance']) }}</dd>
                                @endforeach
                            </dl>
                        </div>
                    @endforeach
                </div>

                @if ($suppliers['pending'] !== [])
                    <div class="px-4 pb-4">
                        <p class="text-sm font-medium text-gray-800">{{ __('reports.finance.suppliers.pending_title') }}</p>
                        <p class="text-xs text-gray-500">{{ __('reports.finance.suppliers.pending_note') }}</p>
                        <ul class="mt-1 space-y-1 text-sm">
                            @foreach ($suppliers['pending'] as $storeId => $pending)
                                <li wire:key="finance-supplier-pending-{{ $storeId }}"><span dir="ltr" class="font-mono text-xs text-gray-600">{{ $suppliers['stores'][$storeId]->code ?? $storeId }}</span> <span class="tabular-nums">{{ $pendingEntry($pending) }}</span></li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if ($suppliers['suppliers']->isNotEmpty())
                    <div class="overflow-x-auto border-t border-gray-100">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-xs text-gray-600">
                                <tr>
                                    <th scope="col" class="{{ $th }}">{{ __('reports.finance.suppliers.supplier') }}</th>
                                    <th scope="col" class="{{ $thEnd }}">{{ __('reports.finance.suppliers.balance') }}</th>
                                    <th scope="col" class="{{ $th }}">{{ __('reports.finance.suppliers.check') }}</th>
                                    <th scope="col" class="px-4 py-2"><span class="sr-only">{{ __('reports.finance.suppliers.view') }}</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($suppliers['suppliers'] as $row)
                                    <tr wire:key="finance-supplier-{{ $row['supplier_id'] }}-{{ $row['currency'] }}">
                                        <th scope="row" dir="auto" class="px-4 py-3 text-start font-medium text-gray-900">{{ $row['name'] }}</th>
                                        <td class="{{ $money }} font-semibold"><span dir="ltr">{{ LocalizedMoney::format($row['balance']) }} {{ $row['currency'] }}</span></td>
                                        <td class="px-4 py-3 text-sm {{ $row['balance'] === $row['open_invoices'] ? 'text-emerald-800' : 'font-medium text-red-700' }}">{{ $row['balance'] === $row['open_invoices'] ? __('purchases.account.reconciled') : __('purchases.account.not_reconciled') }}</td>
                                        <td class="px-4 py-3 text-end">
                                            <a href="{{ route('subdomain.suppliers.account', [$row['supplier_id'], 'currency' => $row['currency']]) }}" aria-label="{{ __('reports.finance.suppliers.view_label', ['supplier' => $row['name']]) }}" class="{{ $link }}">{{ __('reports.finance.suppliers.view') }}</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="px-4 py-3">{{ $suppliers['suppliers']->links() }}</div>
                @endif
            @endif
        </section>
    @endif

    @if ($debts)
        <section aria-labelledby="finance-debts-title" class="{{ $card }}">
            <div class="border-b border-gray-100 px-4 py-3">
                <h2 id="finance-debts-title" class="text-base font-semibold text-gray-900">{{ __('reports.finance.debts.title') }} <span class="ms-1 rounded-md bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">{{ __('reports.finance.current') }}</span></h2>
                <p class="mt-1 text-xs text-gray-500">{{ __('reports.finance.debts.note') }}</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-xs text-gray-600">
                        <tr>
                            <th scope="col" class="{{ $th }}">{{ __('reports.sales.branch') }}</th>
                            <th scope="col" class="{{ $thEnd }}">{{ __('reports.finance.debts.debtors') }}</th>
                            <th scope="col" class="{{ $thEnd }}">{{ __('reports.finance.debts.due') }}</th>
                            <th scope="col" class="{{ $thEnd }}">{{ __('reports.finance.debts.pending') }}</th>
                            <th scope="col" class="px-4 py-2"><span class="sr-only">{{ __('reports.finance.debts.view') }}</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($debts['rows'] as $row)
                            <tr wire:key="finance-debts-{{ $row['store']->id }}">
                                <th scope="row" class="px-4 py-3 text-start font-medium text-gray-900">{{ $row['store']->name }} <span dir="ltr" class="ms-1 font-mono text-xs text-gray-500">{{ $row['store']->code }}</span></th>
                                <td dir="ltr" class="{{ $money }}">{{ $row['debtors'] }}</td>
                                <td class="{{ $money }} font-semibold"><span dir="ltr">{{ LocalizedMoney::format($row['due']) }} TRY</span></td>
                                <td class="px-4 py-3 text-end tabular-nums text-gray-600">{{ $pendingEntry($row['pending']) }}</td>
                                <td class="px-4 py-3 text-end">
                                    <a href="{{ route('pos.customers', $row['store']) }}" aria-label="{{ __('reports.finance.debts.view_label', ['branch' => $row['store']->code]) }}" class="{{ $link }}">{{ __('reports.finance.debts.view') }}</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="border-t-2 border-gray-200 bg-gray-50">
                        <tr>
                            <th scope="row" class="px-4 py-3 text-start font-semibold text-gray-900">{{ __('reports.all_branches') }}</th>
                            <td dir="ltr" class="{{ $money }} font-semibold">{{ $debts['debtors'] }}</td>
                            <td class="{{ $money }} font-bold"><span dir="ltr">{{ LocalizedMoney::format($debts['due']) }} TRY</span></td>
                            <td></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>
    @endif
</div>
