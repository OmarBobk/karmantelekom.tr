@php
    use App\Models\Sale;
    $card = 'overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200';
    $link = 'inline-flex min-h-11 items-center rounded-lg px-3 text-sm font-medium text-blue-700 hover:bg-blue-50';
    $field = 'mt-1 block min-h-11 w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500';
@endphp
<div dir="{{ $textDirection }}" lang="{{ $locale }}" class="mx-auto max-w-6xl space-y-6 text-start">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('reports.audit.title') }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ __('reports.audit.subtitle') }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($canViewDashboard)
                <a href="{{ route('subdomain.operations.dashboard', ['from' => $from, 'to' => $to]) }}" class="{{ $link }} ring-1 ring-blue-200">{{ __('reports.review.open_dashboard') }}</a>
            @endif
            @if ($canViewFinance)
                <a href="{{ route('subdomain.operations.finance', ['from' => $from, 'to' => $to]) }}" class="{{ $link }} ring-1 ring-blue-200">{{ __('reports.open_finance') }}</a>
            @endif
            @if ($canViewReview)
                <a href="{{ route('subdomain.operations.review', ['from' => $from, 'to' => $to]) }}" class="{{ $link }} ring-1 ring-blue-200">{{ __('reports.review.navigation') }}</a>
            @endif
        </div>
    </div>

    <div class="rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-950 ring-1 ring-amber-200" role="note">
        <p>{{ __('reports.audit.partial_note') }}</p>
        <ul class="mt-2 list-disc space-y-1 ps-5">
            @if ($canViewSales)
                <li><a href="{{ route('subdomain.operations.dashboard', ['from' => $from, 'to' => $to]) }}" class="font-medium text-blue-800 underline">{{ __('reports.audit.history_sales') }}</a></li>
            @endif
            @if ($canViewExpenses)
                <li><a href="{{ route('subdomain.operations.finance', ['from' => $from, 'to' => $to]) }}" class="font-medium text-blue-800 underline">{{ __('reports.audit.history_finance') }}</a></li>
            @endif
            @if ($canViewInventory)
                <li><a href="{{ route('subdomain.inventory') }}" class="font-medium text-blue-800 underline">{{ __('reports.audit.history_inventory') }}</a></li>
            @endif
        </ul>
        <p class="mt-2 text-xs">{{ __('reports.audit.legacy_note') }}</p>
    </div>

    <form wire:submit="applyFilters" class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
        <fieldset>
            <legend class="text-sm font-semibold text-gray-900">{{ __('reports.audit.filters') }}</legend>
            <div class="mt-2 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label for="audit-from" class="block text-sm font-medium text-gray-800">{{ __('reports.range.from') }}</label>
                    <input id="audit-from" type="date" wire:model="from" dir="ltr" required class="{{ $field }}">
                </div>
                <div>
                    <label for="audit-to" class="block text-sm font-medium text-gray-800">{{ __('reports.range.to') }}</label>
                    <input id="audit-to" type="date" wire:model="to" dir="ltr" required class="{{ $field }}">
                </div>
                <div>
                    <label for="audit-store" class="block text-sm font-medium text-gray-800">{{ __('reports.sales.branch') }}</label>
                    <select id="audit-store" wire:model.live="storeId" class="{{ $field }}">
                        <option value="">{{ __('reports.audit.all_branches') }}</option>
                        @foreach ($visibleStores as $store)
                            <option value="{{ $store->id }}">{{ $store->code }} — {{ $store->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="audit-area" class="block text-sm font-medium text-gray-800">{{ __('reports.audit.area') }}</label>
                    <select id="audit-area" wire:model.live="area" class="{{ $field }}">
                        <option value="">{{ __('reports.audit.all_areas') }}</option>
                        @foreach ($areas as $areaName)
                            <option value="{{ $areaName }}">{{ __('reports.audit.areas.'.$areaName) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="audit-event" class="block text-sm font-medium text-gray-800">{{ __('reports.audit.event') }}</label>
                    <select id="audit-event" wire:model.live="event" class="{{ $field }}">
                        <option value="">{{ __('reports.audit.all_events') }}</option>
                        @foreach ($eventChoices as $choice)
                            <option value="{{ $choice['event'] }}">{{ $choice['summary'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="audit-actor" class="block text-sm font-medium text-gray-800">{{ __('reports.audit.actor') }}</label>
                    <select id="audit-actor" wire:model.live="actorId" class="{{ $field }}">
                        <option value="">{{ __('reports.audit.all_actors') }}</option>
                        @foreach ($actors as $actor)
                            <option value="{{ $actor->id }}">{{ $actor->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="mt-3 flex flex-wrap gap-2">
                <button type="submit" wire:loading.attr="disabled" class="inline-flex min-h-11 items-center rounded-lg bg-blue-600 px-4 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">{{ __('reports.range.apply') }}</button>
                <button type="button" wire:click="showToday" wire:loading.attr="disabled" class="inline-flex min-h-11 items-center rounded-lg px-4 text-sm font-semibold text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50 disabled:opacity-60">{{ __('reports.range.today') }}</button>
            </div>
            @if ($rangeError)
                <p role="alert" class="mt-2 text-sm font-medium text-red-700">{{ __('reports.range.'.$rangeError) }}</p>
            @elseif ($filterError)
                <p role="alert" class="mt-2 text-sm font-medium text-red-700">{{ __('reports.audit.errors.'.$filterError) }}</p>
            @endif
        </fieldset>
    </form>

    <div class="{{ $card }}" wire:loading.class="opacity-60">
        @if ($rangeError || $filterError)
            <p class="px-4 py-8 text-center text-sm text-gray-600">{{ __('reports.finance.unavailable') }}</p>
        @elseif ($rows->isEmpty())
            <p class="px-4 py-8 text-center text-sm text-gray-600">{{ __('reports.audit.empty') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-xs text-gray-600">
                        <tr>
                            <th scope="col" class="px-4 py-2 text-start font-medium">{{ __('reports.audit.when') }}</th>
                            <th scope="col" class="px-4 py-2 text-start font-medium">{{ __('reports.audit.actor') }}</th>
                            <th scope="col" class="px-4 py-2 text-start font-medium">{{ __('reports.sales.branch') }}</th>
                            <th scope="col" class="px-4 py-2 text-start font-medium">{{ __('reports.audit.area') }}</th>
                            <th scope="col" class="px-4 py-2 text-start font-medium">{{ __('reports.audit.summary') }}</th>
                            <th scope="col" class="px-4 py-2 text-start font-medium"><span class="sr-only">{{ __('reports.audit.source') }}</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($rows as $row)
                            <tr wire:key="audit-{{ $row['id'] }}">
                                <td class="px-4 py-3 whitespace-nowrap"><time datetime="{{ $row['created_at']?->toIso8601String() }}" dir="ltr">{{ Sale::displayTime($row['created_at']) }}</time></td>
                                <td class="px-4 py-3">{{ $row['actor'] }}</td>
                                <td class="px-4 py-3">
                                    @if ($row['branch'] === null)
                                        <span class="text-gray-500">{{ __('reports.audit.global') }}</span>
                                    @else
                                        <span dir="ltr" class="font-mono">{{ $row['branch'] }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">{{ __('reports.audit.areas.'.$row['area']) }}</td>
                                <td class="px-4 py-3">{{ $row['summary'] }}</td>
                                <td class="px-4 py-3 text-end">
                                    @if ($row['link_url'])
                                        <a href="{{ $row['link_url'] }}" class="{{ $link }}">{{ $row['link_label'] }}</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-100 px-4 py-3">{{ $rows->links() }}</div>
        @endif
    </div>
</div>
