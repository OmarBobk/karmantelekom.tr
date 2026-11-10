@php
    $field = 'mt-1 block min-h-11 w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500';
    $tabClass = 'inline-flex min-h-11 items-center rounded-lg px-4 text-sm font-semibold';
    $statusClasses = [
        'pending' => 'bg-amber-50 text-amber-800 ring-amber-600/30',
        'confirmed' => 'bg-green-50 text-green-800 ring-green-600/20',
        'fulfilled' => 'bg-violet-50 text-violet-800 ring-violet-600/20',
    ];
@endphp
<section dir="{{ $textDirection }}" lang="{{ $locale }}" aria-labelledby="storefront-orders-title" class="space-y-4 text-start">
    <div>
        <h1 id="storefront-orders-title" class="text-xl font-semibold text-gray-900">{{ __('storefront.orders.title') }}</h1>
        <p class="text-sm text-gray-600">{{ __('storefront.orders.help') }}</p>
    </div>

    <div role="tablist" aria-label="{{ __('storefront.orders.title') }}" class="flex gap-2">
        <button type="button" role="tab" aria-selected="{{ $open ? 'true' : 'false' }}" wire:click="$set('tab', 'open')" @class([$tabClass, 'bg-blue-600 text-white' => $open, 'text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50' => ! $open])>{{ __('storefront.orders.open') }}</button>
        <button type="button" role="tab" aria-selected="{{ $open ? 'false' : 'true' }}" wire:click="$set('tab', 'closed')" @class([$tabClass, 'bg-blue-600 text-white' => ! $open, 'text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50' => $open])>{{ __('storefront.orders.closed') }}</button>
    </div>

    @error('order')
        <p role="alert" class="rounded-lg bg-red-50 px-4 py-3 text-sm font-medium text-red-800 ring-1 ring-red-200">{{ $message }}</p>
    @enderror

    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200" wire:loading.class="opacity-60" data-testid="storefront-orders">
        @if ($orders->isEmpty())
            <p class="px-4 py-8 text-center text-sm text-gray-600">{{ $open ? __('storefront.orders.empty_open') : __('storefront.orders.empty_closed') }}</p>
        @else
            <ul class="divide-y divide-gray-100">
                @foreach ($orders as $order)
                    @php
                        $status = $order->displayStatus();
                        $isShort = in_array($order->id, $short, true);
                    @endphp
                    <li wire:key="storefront-order-{{ $order->id }}" class="space-y-2 px-4 py-3">
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold text-gray-900">{{ __('storefront.orders.number', ['number' => $order->order_id]) }}</p>
                                <p class="text-sm text-gray-600">{{ $order->buyer?->name }} · {{ $order->shop?->name }}</p>
                            </div>
                            <bdi dir="ltr" class="font-semibold tabular-nums text-gray-900">{{ \App\Services\Purchasing\LocalizedMoney::format($order->totalMinor()) }} TRY</bdi>
                            <span @class(['rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset', $statusClasses[$status->value] ?? 'bg-gray-100 text-gray-700 ring-gray-500/20'])>{{ $status->label() }}</span>
                            @if ($isShort)
                                <span class="rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-semibold text-red-800 ring-1 ring-inset ring-red-600/30" data-testid="shortage-flag">{{ __('storefront.orders.shortage') }}</span>
                            @endif
                            @if ($order->awaitsPriceAcceptance())
                                <span class="rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-800 ring-1 ring-inset ring-amber-600/30">{{ __('storefront.orders.awaiting_buyer') }}</span>
                            @endif
                        </div>

                        <ul class="space-y-1 text-sm text-gray-700">
                            @foreach ($order->lines as $line)
                                <li class="flex flex-wrap justify-between gap-x-4">
                                    <span>
                                        {{ $line->productDefinition->name }}
                                        @if ($line->productDefinition->model)<bdi class="text-gray-500">{{ $line->productDefinition->model }}</bdi>@endif
                                        × {{ $line->quantity }}
                                        @if ($line->unit)
                                            · <bdi dir="ltr" class="font-mono">{{ $line->unit->primaryIdentifierValue() }}</bdi>
                                        @endif
                                    </span>
                                    <bdi dir="ltr" class="tabular-nums">{{ \App\Services\Purchasing\LocalizedMoney::format($line->unitPriceMinor()) }}</bdi>
                                </li>
                            @endforeach
                        </ul>

                        <dl class="flex flex-wrap gap-x-6 gap-y-1 text-sm text-gray-600">
                            <div><dt class="inline">{{ __('storefront.orders.submitted_at') }}</dt> <dd class="inline"><time datetime="{{ $order->created_at->toIso8601String() }}">{{ $order->created_at->format('d.m.Y H:i') }}</time></dd></div>
                            @if ($order->expires_at)
                                <div><dt class="inline">{{ __('storefront.orders.pickup_until') }}</dt> <dd class="inline"><time datetime="{{ $order->expires_at->toIso8601String() }}">{{ $order->expires_at->format('d.m.Y H:i') }}</time></dd></div>
                            @endif
                            @if ($order->sale)
                                <div><dt class="inline">{{ __('storefront.orders.sale') }}</dt> <dd class="inline"><bdi dir="ltr" class="font-mono">{{ $order->sale->number }}</bdi></dd></div>
                            @endif
                            @if ($order->notes)
                                <div><dt class="inline">{{ __('storefront.orders.notes') }}</dt> <dd class="inline" dir="auto">{{ $order->notes }}</dd></div>
                            @endif
                            @if ($order->cancellation_reason)
                                <div><dt class="inline">{{ __('storefront.orders.cancellation_reason') }}</dt> <dd class="inline" dir="auto">{{ $order->cancellation_reason }}</dd></div>
                            @endif
                        </dl>

                        <div class="flex flex-wrap gap-2">
                            @if ($canManage && $status === \App\Enums\StorefrontOrderStatus::PENDING && ! $order->awaitsPriceAcceptance() && $confirmingId !== $order->id)
                                <button type="button" wire:click="openConfirm({{ $order->id }})" class="inline-flex min-h-11 items-center rounded-lg bg-blue-600 px-4 text-sm font-semibold text-white hover:bg-blue-700">{{ __('storefront.orders.review_confirm') }}</button>
                            @endif
                            @if ($status === \App\Enums\StorefrontOrderStatus::CONFIRMED && ! $isShort)
                                <a href="{{ route('pos.counter', ['store' => $store, 'order' => $order->id]) }}" class="inline-flex min-h-11 items-center rounded-lg bg-emerald-600 px-4 text-sm font-semibold text-white hover:bg-emerald-700">{{ __('storefront.orders.collect') }}</a>
                            @endif
                            @if ($canManage && in_array($status, [\App\Enums\StorefrontOrderStatus::PENDING, \App\Enums\StorefrontOrderStatus::CONFIRMED], true) && $cancellingId !== $order->id)
                                <button type="button" wire:click="openCancel({{ $order->id }})" class="inline-flex min-h-11 items-center rounded-lg px-4 text-sm font-semibold text-red-700 ring-1 ring-red-200 hover:bg-red-50">{{ __('storefront.orders.cancel') }}</button>
                            @endif
                        </div>

                        @if ($confirmingId === $order->id)
                            <form wire:submit="confirm" class="space-y-3 rounded-lg bg-gray-50 p-3 ring-1 ring-gray-200" data-testid="confirm-form">
                                @foreach ($order->lines->filter(fn ($line) => $line->tracksUnits()) as $line)
                                    <div>
                                        <label for="device-{{ $line->id }}" class="block text-sm font-medium text-gray-800">{{ __('storefront.orders.device_for', ['name' => $line->productDefinition->name, 'price' => \App\Services\Purchasing\LocalizedMoney::format($line->unitPriceMinor())]) }}</label>
                                        <select id="device-{{ $line->id }}" wire:model="deviceChoices.{{ $line->id }}" class="{{ $field }}">
                                            <option value="">{{ __('storefront.orders.choose_device') }}</option>
                                            @foreach ($eligibleDevices[$line->id] ?? [] as $unit)
                                                <option value="{{ $unit->id }}">{{ $unit->primaryIdentifierValue() }} · {{ $unit->condition->label() }} · {{ \App\Services\Purchasing\LocalizedMoney::format(\App\Actions\Pos\SellingPrice::listPriceMinor($line->productDefinition, $unit) ?? 0) }}</option>
                                            @endforeach
                                        </select>
                                        @error('lines.'.$line->id) <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                                    </div>
                                @endforeach
                                @foreach ($order->lines->reject(fn ($line) => $line->tracksUnits()) as $line)
                                    @error('lines.'.$line->id) <p class="text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                                @endforeach
                                @error('devices') <p class="text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                                <div class="max-w-56">
                                    <label for="pickup-hours" class="block text-sm font-medium text-gray-800">{{ __('storefront.orders.pickup_hours') }}</label>
                                    <input id="pickup-hours" type="number" inputmode="numeric" min="1" max="{{ \App\Models\StorefrontOrder::MAX_PICKUP_HOURS }}" step="1" wire:model="pickupHours" required class="{{ $field }}">
                                    @error('pickup_hours') <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                                </div>
                                <p class="text-xs text-gray-600">{{ __('storefront.orders.confirm_help') }}</p>
                                <div class="flex flex-wrap gap-2">
                                    <button type="submit" wire:loading.attr="disabled" wire:target="confirm" class="inline-flex min-h-11 items-center rounded-lg bg-blue-600 px-4 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">{{ __('storefront.orders.confirm') }}</button>
                                    <button type="button" wire:click="requestPrices" wire:loading.attr="disabled" wire:target="requestPrices" class="inline-flex min-h-11 items-center rounded-lg px-4 text-sm font-semibold text-amber-800 ring-1 ring-amber-300 hover:bg-amber-50 disabled:opacity-60">{{ __('storefront.orders.request_prices') }}</button>
                                    <button type="button" wire:click="closeForms" class="inline-flex min-h-11 items-center rounded-lg px-4 text-sm font-semibold text-gray-700 ring-1 ring-gray-300 hover:bg-white">{{ __('storefront.orders.close') }}</button>
                                </div>
                            </form>
                        @endif

                        @if ($cancellingId === $order->id)
                            <form wire:submit="cancel" class="space-y-2 rounded-lg bg-gray-50 p-3 ring-1 ring-gray-200">
                                <label for="cancel-reason" class="block text-sm font-medium text-gray-800">{{ __('storefront.orders.cancel_reason') }}</label>
                                <textarea id="cancel-reason" wire:model="reason" rows="2" maxlength="{{ \App\Models\StorefrontOrder::MAX_NOTE_LENGTH }}" required @error('reason') aria-invalid="true" aria-describedby="cancel-reason-error" @enderror class="{{ $field }}" x-init="$el.focus()"></textarea>
                                @error('reason') <p id="cancel-reason-error" class="text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                                <div class="flex gap-2">
                                    <button type="submit" class="inline-flex min-h-11 items-center rounded-lg bg-red-600 px-4 text-sm font-semibold text-white hover:bg-red-700">{{ __('storefront.orders.cancel') }}</button>
                                    <button type="button" wire:click="closeForms" class="inline-flex min-h-11 items-center rounded-lg px-4 text-sm font-semibold text-gray-700 ring-1 ring-gray-300 hover:bg-white">{{ __('storefront.orders.keep') }}</button>
                                </div>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    {{ $orders->links() }}
</section>
