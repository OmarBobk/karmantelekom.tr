@php
    use App\Enums\UnitStatus;
    use App\Models\NetworkReservation;
    use App\Models\Sale;
    use App\Services\Purchasing\LocalizedMoney;
    $card = 'overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200';
    $th = 'px-3 py-2 text-start font-medium';
    $thEnd = 'px-3 py-2 text-end font-medium';
    $link = 'inline-flex min-h-11 items-center rounded-lg px-2 text-sm font-medium text-blue-700 hover:bg-blue-50';
    $period = '<bdi dir="ltr">'.e($from).'</bdi>';
    $periodTo = '<bdi dir="ltr">'.e($to).'</bdi>';
    $currentStates = ['pending', 'approved', 'payment_started', 'in_transit', 'received', 'expired'];
    $periodEvents = NetworkReservation::PERIOD_EVENTS;
    $statusKeys = [
        UnitStatus::AVAILABLE->value,
        UnitStatus::INSPECTION->value,
        UnitStatus::IN_REPAIR->value,
        UnitStatus::REVIEW->value,
        UnitStatus::IN_TRANSIT->value,
    ];
    $reservationUrl = function ($store, string $tab, array $extra = []) use ($from, $to): string {
        return route('pos.reservations', array_filter([
            'store' => $store,
            'tab' => $tab,
            'from' => $extra['from'] ?? null,
            'to' => $extra['to'] ?? null,
            'state' => $extra['state'] ?? null,
            'event' => $extra['event'] ?? null,
        ], fn ($value) => $value !== null && $value !== ''));
    };
@endphp
<div dir="{{ $textDirection }}" lang="{{ $locale }}" class="mx-auto max-w-6xl space-y-6 text-start">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('reports.review.title') }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ __('reports.review.subtitle') }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($canViewDashboard)
                <a href="{{ route('subdomain.operations.dashboard', ['from' => $from, 'to' => $to]) }}" class="{{ $link }} ring-1 ring-blue-200">{{ __('reports.review.open_dashboard') }}</a>
            @endif
            @if ($canViewFinance)
                <a href="{{ route('subdomain.operations.finance', ['from' => $from, 'to' => $to]) }}" class="{{ $link }} ring-1 ring-blue-200">{{ __('reports.open_finance') }}</a>
            @endif
            @if ($canViewAudit)
                <a href="{{ route('subdomain.operations.audit', ['from' => $from, 'to' => $to]) }}" class="{{ $link }} ring-1 ring-blue-200">{{ __('reports.audit.navigation') }}</a>
            @endif
        </div>
    </div>

    <form wire:submit="applyRange" class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
        <fieldset>
            <legend class="text-sm font-semibold text-gray-900">{{ __('reports.review.range_legend') }}</legend>
            <div class="mt-2 flex flex-wrap items-end gap-3">
                <div>
                    <label for="review-from" class="block text-sm font-medium text-gray-800">{{ __('reports.range.from') }}</label>
                    <input id="review-from" type="date" wire:model="from" dir="ltr" required @if ($rangeError) aria-invalid="true" aria-describedby="review-range-error" @endif class="mt-1 block min-h-11 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div>
                    <label for="review-to" class="block text-sm font-medium text-gray-800">{{ __('reports.range.to') }}</label>
                    <input id="review-to" type="date" wire:model="to" dir="ltr" required @if ($rangeError) aria-invalid="true" aria-describedby="review-range-error" @endif class="mt-1 block min-h-11 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <button type="submit" wire:loading.attr="disabled" class="inline-flex min-h-11 items-center rounded-lg bg-blue-600 px-4 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">{{ __('reports.range.apply') }}</button>
                <button type="button" wire:click="showToday" wire:loading.attr="disabled" class="inline-flex min-h-11 items-center rounded-lg px-4 text-sm font-semibold text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50 disabled:opacity-60">{{ __('reports.range.today') }}</button>
                <span wire:loading class="text-sm text-gray-500" role="status">{{ __('reports.range.loading') }}</span>
            </div>
            @if ($rangeError)
                <p id="review-range-error" role="alert" class="mt-2 text-sm font-medium text-red-700">{{ __('reports.range.'.$rangeError) }}</p>
            @endif
            <p class="mt-2 text-xs text-gray-500">{{ __('reports.review.range_note') }}</p>
        </fieldset>
    </form>

    @if (! $network && ! $devices)
        <div class="rounded-xl border-2 border-dashed border-gray-300 bg-white px-6 py-12 text-center">
            <h2 class="text-base font-semibold text-gray-900">{{ __('reports.review.empty_title') }}</h2>
            <p class="mt-1 text-sm text-gray-500">{{ __('reports.review.empty_body') }}</p>
        </div>
    @endif

    @if ($network)
        <section aria-labelledby="review-network-title" class="space-y-4">
            <div>
                <h2 id="review-network-title" class="text-base font-semibold text-gray-900">{{ __('reports.review.network.title') }}</h2>
                <p class="text-sm text-gray-600">{{ __('reports.review.network.note') }}</p>
                <p class="mt-1 text-xs text-gray-500">{{ __('reports.review.network.expiry_note') }}</p>
            </div>

            @foreach (['incoming' => $network['incoming'], 'outgoing' => $network['outgoing']] as $direction => $rows)
                <div class="{{ $card }}">
                    <div class="border-b border-gray-100 px-4 py-3">
                        <h3 class="text-sm font-semibold text-gray-900">{{ __('reports.review.network.'.$direction) }}</h3>
                        @if ($direction === 'incoming')
                            <p class="mt-1 text-xs text-gray-500">{{ __('reports.review.network.incoming_total_note') }}</p>
                        @endif
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-xs text-gray-600">
                                <tr>
                                    <th scope="col" class="{{ $th }}">{{ __('reports.sales.branch') }}</th>
                                    @foreach ($currentStates as $state)
                                        <th scope="col" class="{{ $thEnd }}"><span class="inline-flex items-center gap-1">{{ __('reports.review.network.states.'.$state) }} <span class="rounded bg-gray-100 px-1 font-normal text-gray-500">{{ __('reports.finance.current') }}</span></span></th>
                                    @endforeach
                                    @unless ($rangeError)
                                        @foreach ($periodEvents as $event)
                                            <th scope="col" class="{{ $thEnd }}">{{ __('reports.review.network.events.'.$event) }}</th>
                                        @endforeach
                                    @endunless
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($rows as $row)
                                    <tr wire:key="network-{{ $direction }}-{{ $row['store']->id }}">
                                        <th scope="row" class="px-3 py-3 text-start font-medium text-gray-900">{{ $row['store']->name }} <span dir="ltr" class="ms-1 font-mono text-xs text-gray-500">{{ $row['store']->code }}</span></th>
                                        @foreach ($currentStates as $state)
                                            <td class="px-3 py-3 text-end tabular-nums">
                                                <a href="{{ $reservationUrl($row['store'], $direction, ['state' => $state]) }}" class="{{ $link }}" aria-label="{{ __('reports.review.network.view_state', ['state' => __('reports.review.network.states.'.$state), 'branch' => $row['store']->code]) }}">{{ $row['current'][$state] }}</a>
                                            </td>
                                        @endforeach
                                        @unless ($rangeError)
                                            @foreach ($periodEvents as $event)
                                                <td class="px-3 py-3 text-end tabular-nums">
                                                    <a href="{{ $reservationUrl($row['store'], $direction, ['event' => $event, 'from' => $from, 'to' => $to]) }}" class="{{ $link }}" aria-label="{{ __('reports.review.network.view_event', ['event' => __('reports.review.network.events.'.$event), 'branch' => $row['store']->code]) }}">{{ $row['period'][$event] }}</a>
                                                </td>
                                            @endforeach
                                        @endunless
                                    </tr>
                                    @if (! $rangeError && ($row['cancelled_by_owning'] + $row['cancelled_by_requesting']) > 0)
                                        <tr class="bg-gray-50/70">
                                            <td colspan="{{ 1 + count($currentStates) + count($periodEvents) }}" class="px-3 py-2 text-xs text-gray-600">
                                                {{ __('reports.review.network.cancellation_detail', [
                                                    'owning' => $row['cancelled_by_owning'],
                                                    'requesting' => $row['cancelled_by_requesting'],
                                                ]) }}
                                            </td>
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                            @if ($direction === 'incoming')
                                <tfoot class="border-t-2 border-gray-200 bg-gray-50">
                                    <tr>
                                        <th scope="row" class="px-3 py-3 text-start font-semibold text-gray-900">{{ __('reports.all_branches') }}</th>
                                        @foreach ($currentStates as $state)
                                            <td class="px-3 py-3 text-end font-semibold tabular-nums">{{ $network['totals'][$state] ?? 0 }}</td>
                                        @endforeach
                                        @unless ($rangeError)
                                            @foreach ($periodEvents as $event)
                                                <td class="px-3 py-3 text-end font-semibold tabular-nums">{{ $network['period_totals'][$event] ?? 0 }}</td>
                                            @endforeach
                                        @endunless
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                    @if ($rangeError)
                        <p class="border-t border-gray-100 px-4 py-3 text-sm text-gray-600">{{ __('reports.finance.unavailable') }}</p>
                    @endif
                </div>
            @endforeach
        </section>
    @endif

    @if ($devices)
        <section aria-labelledby="review-devices-title" class="space-y-4">
            <div>
                <h2 id="review-devices-title" class="text-base font-semibold text-gray-900">{{ __('reports.review.devices.title') }}</h2>
                <p class="text-sm text-gray-600">{{ __('reports.review.devices.note') }}</p>
            </div>

            <div class="{{ $card }}">
                <div class="border-b border-gray-100 px-4 py-3">
                    <h3 class="text-sm font-semibold text-gray-900">{{ __('reports.review.devices.status_title') }} <span class="ms-1 rounded-md bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">{{ __('reports.finance.current') }}</span></h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-xs text-gray-600">
                            <tr>
                                <th scope="col" class="{{ $th }}">{{ __('reports.sales.branch') }}</th>
                                @foreach ($statusKeys as $status)
                                    <th scope="col" class="{{ $thEnd }}">{{ UnitStatus::from($status)->label() }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($devices['status_rows'] as $row)
                                <tr wire:key="device-status-{{ $row['store']->id }}">
                                    <th scope="row" class="px-3 py-3 text-start font-medium text-gray-900">{{ $row['store']->name }} <span dir="ltr" class="ms-1 font-mono text-xs text-gray-500">{{ $row['store']->code }}</span></th>
                                    @foreach ($statusKeys as $status)
                                        <td class="px-3 py-3 text-end tabular-nums">
                                            <a href="{{ route('subdomain.inventory.store', [$row['store'], 'units' => $status === UnitStatus::AVAILABLE->value ? 'available' : ($status === UnitStatus::REVIEW->value ? 'review' : ($status === UnitStatus::IN_TRANSIT->value ? 'in_transit' : 'in_service'))]) }}" class="{{ $link }}">{{ $row['counts'][$status] }}</a>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t-2 border-gray-200 bg-gray-50">
                            <tr>
                                <th scope="row" class="px-3 py-3 text-start font-semibold">{{ __('reports.all_branches') }}</th>
                                @foreach ($statusKeys as $status)
                                    <td class="px-3 py-3 text-end font-semibold tabular-nums">{{ $devices['status_totals'][$status] ?? 0 }}</td>
                                @endforeach
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="{{ $card }}">
                <div class="border-b border-gray-100 px-4 py-3">
                    <h3 class="text-sm font-semibold text-gray-900">{{ __('reports.review.devices.open_repairs') }} <span class="ms-1 rounded-md bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">{{ __('reports.finance.current') }}</span></h3>
                </div>
                @if ($devices['open_repairs']->isEmpty())
                    <p class="px-4 py-8 text-center text-sm text-gray-600">{{ __('reports.review.devices.open_empty') }}</p>
                @else
                    <ul class="divide-y divide-gray-100">
                        @foreach ($devices['open_repairs'] as $item)
                            @php($repair = $item['repair'])
                            <li wire:key="open-repair-{{ $repair->id }}" class="px-4 py-3 text-sm">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <div>
                                        <a href="{{ route('subdomain.inventory.unit', [$repair->store, $repair->unit]) }}" class="font-medium text-blue-700 hover:underline">{{ $repair->unit->productDefinition->name }}</a>
                                        <p class="text-gray-600">{{ $repair->store->code }} · {{ $repair->repairer_type->label() }}@if ($repair->repairer_name) · {{ $repair->repairer_name }}@endif</p>
                                        <p class="text-xs text-gray-500">{{ __('reports.review.devices.started_by', ['name' => $repair->starter?->name ?? '—', 'time' => Sale::displayTime($repair->started_at)]) }} · {{ __('reports.review.devices.age_hours', ['hours' => $item['age_hours']]) }}</p>
                                    </div>
                                    @if ($item['costs'] !== null)
                                        <div class="text-end text-xs text-gray-600">
                                            @forelse ($item['costs']['known'] as $currency => $minor)
                                                <p dir="ltr">{{ LocalizedMoney::format($minor) }} {{ $currency }}</p>
                                            @empty
                                                @unless ($item['costs']['has_entries'])
                                                    <p>{{ __('reports.review.devices.costing_pending') }}</p>
                                                @endunless
                                            @endforelse
                                            @if ($item['costs']['unknown'] > 0)
                                                <p>{{ __('reports.review.devices.unknown_amounts', ['count' => $item['costs']['unknown']]) }}</p>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                    <div class="border-t border-gray-100 px-4 py-3">{{ $devices['open_repairs']->links() }}</div>
                @endif
            </div>

            <div class="{{ $card }}">
                <div class="border-b border-gray-100 px-4 py-3">
                    <h3 class="text-sm font-semibold text-gray-900">{{ __('reports.review.devices.closed_repairs') }}</h3>
                    @unless ($rangeError)
                        <p class="mt-1 text-sm text-gray-600">{!! __('reports.review.devices.closed_period', ['from' => $period, 'to' => $periodTo]) !!}</p>
                        <p class="mt-1 text-xs text-gray-500">{{ __('reports.review.devices.costs_as_of_now') }}</p>
                    @endunless
                </div>
                @if ($rangeError)
                    <p class="px-4 py-8 text-center text-sm text-gray-600">{{ __('reports.finance.unavailable') }}</p>
                @else
                    <div class="grid gap-3 border-b border-gray-100 px-4 py-3 sm:grid-cols-2">
                        @foreach ($devices['status_rows'] as $row)
                            <p class="text-sm text-gray-700">
                                <span class="font-medium">{{ $row['store']->code }}</span>:
                                {{ __('reports.review.devices.closed_counts', [
                                    'completed' => $devices['closed_counts'][$row['store']->id]['completed'] ?? 0,
                                    'cancelled' => $devices['closed_counts'][$row['store']->id]['cancelled'] ?? 0,
                                ]) }}
                            </p>
                        @endforeach
                    </div>
                    @if ($devices['closed_repairs']->isEmpty())
                        <p class="px-4 py-8 text-center text-sm text-gray-600">{{ __('reports.review.devices.closed_empty') }}</p>
                    @else
                        <ul class="divide-y divide-gray-100">
                            @foreach ($devices['closed_repairs'] as $item)
                                @php($repair = $item['repair'])
                                <li wire:key="closed-repair-{{ $repair->id }}" class="px-4 py-3 text-sm">
                                    <div class="flex flex-wrap items-start justify-between gap-2">
                                        <div>
                                            <a href="{{ route('subdomain.inventory.unit', [$repair->store, $repair->unit]) }}" class="font-medium text-blue-700 hover:underline">{{ $repair->unit->productDefinition->name }}</a>
                                            <p class="text-gray-600">{{ $repair->store->code }} · {{ $repair->status->label() }} · {{ Sale::displayTime($repair->closed_at) }}</p>
                                        </div>
                                        @if ($item['costs'] !== null)
                                            <div class="text-end text-xs text-gray-600">
                                                @forelse ($item['costs']['known'] as $currency => $minor)
                                                    <p dir="ltr">{{ LocalizedMoney::format($minor) }} {{ $currency }}</p>
                                                @empty
                                                    @unless ($item['costs']['has_entries'])
                                                        <p>{{ __('reports.review.devices.costing_pending') }}</p>
                                                    @endunless
                                                @endforelse
                                                @if ($item['costs']['unknown'] > 0)
                                                    <p>{{ __('reports.review.devices.unknown_amounts', ['count' => $item['costs']['unknown']]) }}</p>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                        <div class="border-t border-gray-100 px-4 py-3">{{ $devices['closed_repairs']->links() }}</div>
                    @endif
                @endif
            </div>

            @if ($devices['costs'])
                <div class="{{ $card }}">
                    <div class="border-b border-gray-100 px-4 py-3">
                        <h3 class="text-sm font-semibold text-gray-900">{{ __('reports.review.devices.recorded_costs') }} <span class="ms-1 rounded-md bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">{{ __('reports.finance.current') }}</span></h3>
                        <p class="mt-1 text-xs text-gray-500">{{ __('reports.review.devices.recorded_costs_note') }}</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-xs text-gray-600">
                                <tr>
                                    <th scope="col" class="{{ $th }}">{{ __('reports.sales.branch') }}</th>
                                    <th scope="col" class="{{ $thEnd }}">{{ __('reports.review.devices.on_hand') }}</th>
                                    <th scope="col" class="{{ $thEnd }}">{{ __('reports.review.devices.unknown_acquisition') }}</th>
                                    <th scope="col" class="{{ $thEnd }}">{{ __('reports.review.devices.acquisition_costs') }}</th>
                                    <th scope="col" class="{{ $thEnd }}">{{ __('reports.review.devices.additional_costs') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($devices['costs']['rows'] as $row)
                                    <tr wire:key="recorded-costs-{{ $row['store']->id }}">
                                        <th scope="row" class="px-3 py-3 text-start font-medium">{{ $row['store']->code }}</th>
                                        <td class="px-3 py-3 text-end tabular-nums">{{ $row['devices'] }}</td>
                                        <td class="px-3 py-3 text-end tabular-nums">{{ $row['unknown_acquisition'] }}</td>
                                        <td class="px-3 py-3 text-end text-xs">
                                            @forelse ($row['acquisition'] as $currency => $minor)
                                                <p dir="ltr">{{ LocalizedMoney::format($minor) }} {{ $currency }}</p>
                                            @empty
                                                <span class="text-gray-500">—</span>
                                            @endforelse
                                        </td>
                                        <td class="px-3 py-3 text-end text-xs">
                                            @foreach ($row['additional'] as $currency => $minor)
                                                <p dir="ltr">{{ LocalizedMoney::format($minor) }} {{ $currency }}</p>
                                            @endforeach
                                            @if ($row['additional_unknown'] > 0)
                                                <p>{{ __('reports.review.devices.unknown_amounts', ['count' => $row['additional_unknown']]) }}</p>
                                            @elseif ($row['additional'] === [])
                                                <span class="text-gray-500">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </section>
    @endif
</div>
