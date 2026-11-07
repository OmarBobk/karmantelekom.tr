@php
    use App\Livewire\Pos\PosCounter;
    use App\Models\Sale;
    use App\Services\Purchasing\LocalizedMoney;
    use App\Services\Purchasing\Money;
    $money = fn (?int $minor) => PosCounter::formatMoney($minor);
    $inputClass = 'block min-h-11 w-full rounded-lg border-gray-300 text-base shadow-sm focus:border-blue-500 focus:ring-blue-500';
    $primaryButton = 'inline-flex min-h-12 items-center justify-center gap-2 rounded-lg bg-blue-600 px-5 text-base font-semibold text-white shadow-sm hover:bg-blue-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 disabled:cursor-not-allowed disabled:opacity-60';
    $secondaryButton = 'inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-white px-4 text-sm font-semibold text-gray-800 shadow-sm ring-1 ring-gray-300 hover:bg-gray-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 disabled:cursor-not-allowed disabled:opacity-60';
@endphp

<div
    dir="{{ $textDirection }}"
    lang="{{ $locale }}"
    class="text-start"
    x-data="{
        code: '',
        timer: null,
        pendingScans: 0,
        uncertain: false,
        init() {
            $wire.$intercept('finalize', ({ onSend, onFailure, onError, onSuccess }) => {
                onSend(() => { this.uncertain = false; });
                onFailure(() => { this.uncertain = true; });
                onError(({ response, preventDefault }) => {
                    if (response.status === 419 || response.status >= 500) {
                        preventDefault();
                        this.uncertain = true;
                    }
                });
            });
            this.$nextTick(() => this.$refs.code?.focus());
        },
        typed() {
            clearTimeout(this.timer);
            const term = this.code;
            if (term.trim().length < 2) { return; }
            this.timer = setTimeout(() => $wire.search(term), 350);
        },
        submit() {
            clearTimeout(this.timer);
            const value = this.code;
            this.code = '';
            if (value.trim() === '') { return; }
            this.pendingScans++;
            $wire.scan(value).finally(() => { this.pendingScans--; });
            this.$refs.code.focus();
        },
        async checkStatus() {
            await $wire.checkStatus();
            this.uncertain = false;
        },
    }"
>
    <h1 class="sr-only">{{ __('pos.counter.title', ['store' => $store->code]) }}</h1>

    <div
        x-show="uncertain"
        x-cloak
        role="alert"
        class="mb-4 rounded-xl bg-amber-50 p-4 text-amber-900 ring-2 ring-amber-400"
    >
        <p class="text-base font-semibold">{{ __('pos.recovery.uncertain_title') }}</p>
        <p class="mt-1 text-sm">{{ __('pos.recovery.uncertain_help') }}</p>
        <button type="button" x-on:click="checkStatus()" class="{{ $primaryButton }} mt-3 bg-amber-700 hover:bg-amber-800">
            {{ __('pos.recovery.check_status') }}
        </button>
    </div>

    <div class="grid gap-4 lg:grid-cols-12">
        {{-- Scan, search, and cart --}}
        <section aria-labelledby="pos-cart-title" class="space-y-4 lg:col-span-7">
            <form role="search" x-on:submit.prevent="submit()" class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                <label for="pos-code" class="block text-sm font-semibold text-gray-900">{{ __('pos.scan.label') }}</label>
                <p id="pos-code-help" class="mt-0.5 text-xs text-gray-600">{{ __('pos.scan.help') }}</p>
                <fieldset class="mt-2 min-w-0" @disabled(! $cartEditable)>
                    <div class="flex gap-2" wire:ignore>
                        <input
                            x-ref="code"
                            x-model="code"
                            x-on:input="typed()"
                            id="pos-code"
                            type="text"
                            inputmode="text"
                            enterkeyhint="go"
                            maxlength="80"
                            autocomplete="off"
                            autocapitalize="off"
                            spellcheck="false"
                            data-scan
                            aria-describedby="pos-code-help"
                            placeholder="{{ __('pos.scan.placeholder') }}"
                            class="block min-h-12 min-w-0 flex-1 rounded-lg border-gray-300 text-lg shadow-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-100"
                        >
                        <button type="submit" class="{{ $primaryButton }}">{{ __('pos.scan.submit') }}</button>
                    </div>
                </fieldset>

                {{-- A search sent before the scan answers first; hide it until the scan replaces it. --}}
                <div x-show="pendingScans === 0">
                @if ($searchResults !== [])
                    <div class="mt-3" aria-live="polite">
                        <p class="text-xs font-medium text-gray-600">{{ __('pos.scan.search_results', ['term' => $searchTerm]) }}</p>
                        <ul class="mt-1 divide-y divide-gray-100 overflow-hidden rounded-lg ring-1 ring-gray-200">
                            @foreach ($searchResults as $result)
                                @include('livewire.pos.partials.target-option', ['option' => $result, 'keyPrefix' => 'search'])
                            @endforeach
                        </ul>
                    </div>
                @elseif ($searchTerm !== '')
                    <p class="mt-3 text-sm text-gray-600" aria-live="polite">{{ __('pos.scan.no_search_results', ['term' => $searchTerm]) }}</p>
                @endif
                </div>
            </form>

            <div class="rounded-xl bg-white px-4 py-3 shadow-sm ring-1 ring-gray-200" aria-labelledby="pos-customer-title" role="group">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p id="pos-customer-title" class="text-sm font-semibold text-gray-900">{{ __('pos.customer.title') }}</p>
                        @if ($selectedCustomer)
                            <p class="mt-0.5 text-base text-gray-900">
                                <span class="font-medium">{{ $selectedCustomer->name }}</span>
                                <bdi dir="ltr" class="ms-1 font-mono text-sm text-gray-600">{{ $selectedCustomer->maskedPhone() }}</bdi>
                            </p>
                        @else
                            <p class="mt-0.5 text-sm text-gray-600">{{ __('pos.customer.walk_in') }}</p>
                        @endif
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" wire:click="openCustomer" @disabled(! $cartEditable) aria-haspopup="dialog" class="{{ $secondaryButton }}">
                            {{ $selectedCustomer ? __('pos.customer.change') : __('pos.customer.choose') }}
                        </button>
                        @if ($selectedCustomer)
                            <button type="button" wire:click="clearCustomer" @disabled(! $cartEditable) class="{{ $secondaryButton }}">{{ __('pos.customer.remove') }}</button>
                        @endif
                    </div>
                </div>
                @error('customer')
                    <p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-3">
                    <h2 id="pos-cart-title" class="text-base font-semibold text-gray-900">{{ __('pos.cart.title') }}</h2>
                    <span class="text-sm text-gray-600">{{ trans_choice('pos.cart.count', count($cartLines), ['count' => count($cartLines)]) }}</span>
                </div>

                @if (! $cartEditable)
                    <p class="border-b border-amber-100 bg-amber-50 px-4 py-2 text-sm text-amber-900">{{ __('pos.errors.attempt_frozen') }}</p>
                @endif

                @error('cart')
                    <p class="px-4 pt-3 text-sm text-red-700" role="alert">{{ $message }}</p>
                @enderror

                @if ($cartLines === [])
                    <p class="px-4 py-8 text-center text-sm text-gray-600">{{ __('pos.cart.empty') }}</p>
                @else
                    <ul class="divide-y divide-gray-100">
                        @foreach ($cartLines as $line)
                            <li wire:key="cart-{{ $line['key'] }}" class="px-4 py-3">
                                <div class="flex flex-wrap items-start gap-3">
                                    <div class="min-w-0 flex-1">
                                        <p class="font-medium text-gray-900">{{ $line['name'] }}@if ($line['model']) <span class="text-gray-600">· {{ $line['model'] }}</span>@endif</p>
                                        @if ($line['identifier'])
                                            <p class="mt-0.5 text-sm text-gray-700">
                                                <bdi dir="ltr" class="font-mono">{{ $line['identifier'] }}</bdi>
                                                @if ($line['condition'])
                                                    · {{ __('inventory.conditions.'.$line['condition']) }}
                                                @endif
                                            </p>
                                        @endif
                                        <p class="mt-0.5 text-sm text-gray-700">
                                            @if ($line['list_price'] === null)
                                                <span class="rounded bg-red-50 px-1.5 py-0.5 text-xs font-semibold text-red-800 ring-1 ring-red-200">{{ __('pos.cart.not_priced') }}</span>
                                            @elseif ($line['adjusted_price'] !== '' && $line['unit_price'] < $line['list_price'])
                                                <s class="text-gray-500" dir="ltr">{{ $money($line['list_price']) }}</s>
                                                <span dir="ltr" class="font-semibold text-gray-900">{{ $money($line['unit_price']) }}</span>
                                                <span class="rounded bg-emerald-50 px-1.5 py-0.5 text-xs font-medium text-emerald-800 ring-1 ring-emerald-200">{{ __('pos.cart.discount_each', ['amount' => $money($line['list_price'] - $line['unit_price'])]) }}</span>
                                                <span class="text-xs text-gray-600">· {{ $line['adjustment_reason'] }}</span>
                                            @else
                                                <span dir="ltr">{{ $money($line['unit_price']) }}</span>
                                            @endif
                                        </p>
                                        @foreach (['item', 'quantity', 'price', 'adjusted_price', 'adjustment_reason'] as $field)
                                            @error('lines.'.$line['key'].'.'.$field)
                                                <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p>
                                            @enderror
                                        @endforeach
                                    </div>

                                    <div class="flex items-center gap-2">
                                        @if ($line['unit_id'] === null)
                                            <div class="flex items-center rounded-lg ring-1 ring-gray-300" role="group" aria-label="{{ __('pos.cart.quantity_for', ['name' => $line['name']]) }}">
                                                <button type="button" wire:click="setQuantity('{{ $line['key'] }}', {{ $line['quantity'] - 1 }})" @disabled(! $cartEditable) class="inline-flex min-h-11 min-w-11 items-center justify-center text-lg text-gray-700 hover:bg-gray-100 disabled:opacity-50" aria-label="{{ __('pos.cart.decrease') }}">−</button>
                                                <span class="min-w-8 text-center font-semibold tabular-nums" aria-live="polite">{{ $line['quantity'] }}</span>
                                                <button type="button" wire:click="setQuantity('{{ $line['key'] }}', {{ $line['quantity'] + 1 }})" @disabled(! $cartEditable) class="inline-flex min-h-11 min-w-11 items-center justify-center text-lg text-gray-700 hover:bg-gray-100 disabled:opacity-50" aria-label="{{ __('pos.cart.increase') }}">+</button>
                                            </div>
                                        @endif
                                        <span dir="ltr" class="min-w-20 text-end font-semibold tabular-nums text-gray-900">{{ $money($line['line_total']) }}</span>
                                    </div>
                                </div>

                                <div class="mt-2 flex flex-wrap gap-2">
                                    @if ($canDiscount && $line['list_price'] !== null)
                                        <button type="button" wire:click="openDiscount('{{ $line['key'] }}')" @disabled(! $cartEditable) aria-haspopup="dialog" class="{{ $secondaryButton }}">{{ __('pos.cart.discount') }}</button>
                                    @endif
                                    @if ($line['adjusted_price'] !== '')
                                        <button type="button" wire:click="removeDiscount('{{ $line['key'] }}')" @disabled(! $cartEditable) class="{{ $secondaryButton }}">{{ __('pos.cart.remove_discount') }}</button>
                                    @endif
                                    <button type="button" wire:click="removeLine('{{ $line['key'] }}')" @disabled(! $cartEditable) class="{{ $secondaryButton }} text-red-700">{{ __('pos.cart.remove') }}</button>
                                </div>
                            </li>
                        @endforeach
                    </ul>

                    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 px-4 py-3">
                        <p class="text-sm text-gray-700">{{ __('pos.cart.estimated_total') }} <span dir="ltr" class="ms-1 text-lg font-semibold tabular-nums text-gray-900">{{ $money($cartTotal) }} TRY</span></p>
                        @if ($step === 'cart')
                            <button type="button" wire:click="review" wire:loading.attr="disabled" wire:target="review" class="{{ $primaryButton }}">
                                <span wire:loading.remove wire:target="review">{{ __('pos.cart.review') }}</span>
                                <span wire:loading wire:target="review">{{ __('pos.cart.reviewing') }}</span>
                            </button>
                        @endif
                    </div>
                @endif
            </div>
        </section>

        {{-- Payment, completion, and recovery --}}
        <section aria-labelledby="pos-payment-title" class="space-y-4 lg:col-span-5">
            @if ($step === 'done' && $lastSale)
                <div class="rounded-xl bg-white p-5 shadow-sm ring-2 ring-emerald-500" role="status">
                    <h2 id="pos-payment-title" class="text-lg font-semibold text-emerald-800">{{ __('pos.done.title') }}</h2>
                    <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                        <dt class="text-gray-600">{{ __('pos.fields.sale_number') }}</dt>
                        <dd dir="ltr" class="text-start font-mono font-semibold text-gray-900">{{ $lastSale->number }}</dd>
                        <dt class="text-gray-600">{{ __('pos.fields.total') }}</dt>
                        <dd dir="ltr" class="text-start font-semibold tabular-nums text-gray-900">{{ $money($lastSale->totalMinor()) }} TRY</dd>
                        @if ($lastSale->cash_tendered !== null)
                            <dt class="text-gray-600">{{ __('pos.fields.change') }}</dt>
                            <dd dir="ltr" class="text-start text-2xl font-bold tabular-nums text-gray-900">{{ $money(Money::fromDecimal($lastSale->change_given)) }} TRY</dd>
                        @endif
                    </dl>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <a href="{{ route('pos.sales.show', [$store, $lastSale]) }}?print=1" target="_blank" rel="noopener" class="{{ $primaryButton }}">{{ __('pos.done.print') }}</a>
                        <button type="button" wire:click="newSale" class="{{ $secondaryButton }} min-h-12">{{ __('pos.done.new_sale') }}</button>
                    </div>
                </div>
            @elseif ($step === 'payment' && $attempt && $payment)
                <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                    <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-3">
                        <h2 id="pos-payment-title" class="text-base font-semibold text-gray-900">{{ __('pos.payment.title') }}</h2>
                        <span class="font-mono text-xs text-gray-500" dir="ltr">#{{ Str::limit($attempt->key, 8, '') }} · r{{ $attempt->revision }}</span>
                    </div>

                    <div class="space-y-4 p-4">
                        @error('attempt')
                            <p class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800 ring-1 ring-red-200" role="alert">{{ $message }}</p>
                        @enderror

                        @if ($attempt->failure_message)
                            <div class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-900 ring-1 ring-red-200" role="alert">
                                <p class="font-semibold">{{ __('pos.payment.not_completed') }}</p>
                                <p>{{ $attempt->failure_message }}</p>
                                @if ($attempt->cardApproval)
                                    <p class="mt-2 font-semibold">{{ __('pos.payment.failed_with_card', ['reference' => $attempt->cardApproval->reference]) }}</p>
                                @endif
                            </div>
                        @endif

                        @if ($attempt->cardApproval)
                            <div class="rounded-lg bg-amber-50 px-3 py-3 text-sm text-amber-950 ring-2 ring-amber-400" role="status">
                                <p class="font-semibold">{{ __('pos.payment.card_approved_title') }}</p>
                                <p class="mt-1">{{ __('pos.payment.card_approved_detail', [
                                    'amount' => $money($attempt->cardApproval->amountMinor()),
                                    'terminal' => $attempt->cardApproval->terminal?->displayName(),
                                    'reference' => $attempt->cardApproval->reference,
                                ]) }}</p>
                                <p class="mt-1 font-semibold">{{ __('pos.payment.do_not_charge_again') }}</p>
                            </div>
                        @endif

                        <dl class="grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                            <dt class="text-gray-600">{{ __('pos.customer.title') }}</dt>
                            <dd class="text-end text-gray-900">
                                @if ($attempt->customer)
                                    {{ $attempt->customer->name }} <bdi dir="ltr" class="font-mono text-gray-600">{{ $attempt->customer->maskedPhone() }}</bdi>
                                @else
                                    {{ __('pos.customer.walk_in') }}
                                @endif
                            </dd>
                            <dt class="text-gray-600">{{ __('pos.fields.subtotal') }}</dt>
                            <dd dir="ltr" class="text-end tabular-nums">{{ $money($attempt->subtotalMinor()) }}</dd>
                            @if ($attempt->discountTotalMinor() > 0)
                                <dt class="text-gray-600">{{ __('pos.fields.discount') }}</dt>
                                <dd dir="ltr" class="text-end tabular-nums">-{{ $money($attempt->discountTotalMinor()) }}</dd>
                            @endif
                            <dt class="font-semibold text-gray-900">{{ __('pos.fields.total') }}</dt>
                            <dd dir="ltr" class="text-end text-xl font-bold tabular-nums text-gray-900">{{ $money($payment['total']) }} TRY</dd>
                            @if ($payment['card'] > 0)
                                <dt class="text-gray-600">{{ __('pos.fields.card') }}</dt>
                                <dd dir="ltr" class="text-end tabular-nums">{{ $money($payment['card']) }}</dd>
                            @endif
                            <dt class="font-semibold text-gray-900">{{ __('pos.fields.cash_due') }}</dt>
                            <dd dir="ltr" class="text-end text-lg font-semibold tabular-nums">{{ $money($payment['cash_due']) }}</dd>
                        </dl>

                        @if ($terminalPending)
                            @php
                                $pendingPayment = [
                                    'amount' => $money($attempt->terminalPaymentAmountMinor()),
                                    'terminal' => $attempt->terminalPaymentTerminal?->displayName(),
                                ];
                            @endphp
                            <div
                                wire:key="terminal-payment-{{ $attempt->terminal_payment_key }}"
                                data-terminal-payment="{{ $chargeNow ? 'charge' : 'verify' }}"
                                @class([
                                    'rounded-lg p-3 text-sm ring-2',
                                    'bg-sky-50 text-sky-950 ring-sky-500' => $chargeNow,
                                    'bg-amber-50 text-amber-950 ring-amber-500' => ! $chargeNow,
                                ])
                                role="status"
                                aria-live="polite"
                            >
                                @if ($chargeNow)
                                    <p class="text-base font-semibold">{{ __('pos.payment.terminal_pending_title') }}</p>
                                    <p class="mt-1">{{ __('pos.payment.terminal_pending_detail', $pendingPayment) }}</p>
                                @else
                                    <p class="text-base font-semibold">{{ __('pos.payment.terminal_verify_title') }}</p>
                                    <p class="mt-1">{{ __('pos.payment.terminal_verify_detail', $pendingPayment) }}</p>
                                    <p class="mt-1 font-semibold">{{ __('pos.payment.do_not_charge_again') }}</p>
                                @endif
                                <p class="mt-1">{{ __('pos.payment.terminal_pending_help') }}</p>
                                <div class="mt-3">
                                    <label for="pos-card-reference" class="block text-sm font-medium text-gray-800">{{ __('pos.fields.card_reference') }}</label>
                                    <input id="pos-card-reference" type="text" dir="ltr" autocomplete="off" maxlength="40" wire:model="cardReference" class="{{ $inputClass }} mt-1 font-mono uppercase">
                                    @error('card_reference') <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                                    @error('card_terminal') <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                                    @error('card_amount') <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                                </div>
                                <button type="button" wire:click="recordCardApproval" wire:loading.attr="disabled" wire:target="recordCardApproval" class="{{ $primaryButton }} mt-3 w-full">{{ __('pos.payment.record_card') }}</button>
                            </div>
                        @elseif (! $attempt->cardApproval)
                            <fieldset class="rounded-lg p-3 ring-1 ring-gray-200">
                                <legend class="px-1 text-sm font-semibold text-gray-900">{{ __('pos.payment.card_title') }}</legend>
                                <p class="text-xs text-gray-600">{{ __('pos.payment.card_help') }}</p>
                                @if ($terminals->isEmpty())
                                    <p class="mt-2 text-sm text-gray-700">{{ __('pos.payment.no_terminals') }}</p>
                                @else
                                    <form wire:submit="startTerminalPayment">
                                        <div class="mt-2 space-y-3">
                                            <div>
                                                <label for="pos-card-terminal" class="block text-sm font-medium text-gray-800">{{ __('pos.fields.terminal') }}</label>
                                                <select id="pos-card-terminal" wire:model="cardTerminalId" class="{{ $inputClass }} mt-1">
                                                    <option value="">{{ __('pos.payment.choose_terminal') }}</option>
                                                    @foreach ($terminals as $terminal)
                                                        <option value="{{ $terminal->id }}">{{ $terminal->displayName() }}</option>
                                                    @endforeach
                                                </select>
                                                @error('card_terminal') <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                                            </div>
                                            <x-operations.money-input
                                                id="pos-card-amount"
                                                wire:model="cardAmount"
                                                error="card_amount"
                                                :label="__('pos.fields.card_charge_amount')"
                                                :placeholder="LocalizedMoney::input($payment['total'])"
                                            />
                                        </div>
                                        <button type="submit" wire:loading.attr="disabled" wire:target="startTerminalPayment" class="{{ $secondaryButton }} mt-3 w-full">{{ __('pos.payment.start_card') }}</button>
                                    </form>
                                @endif
                            </fieldset>
                        @endif

                        @unless ($terminalPending)
                            <form wire:submit="finalize" class="space-y-4">
                                @if ($payment['cash_due'] > 0)
                                    {{-- The change preview is presentation only; finalize checks the tendered cash on the server. --}}
                                    <div x-data="{
                                        due: @js($payment['cash_due']),
                                        get change() {
                                            const tendered = $tryMoney.toMinor($wire.cashTendered);
                                            return tendered === null || tendered < this.due ? null : tendered - this.due;
                                        },
                                    }">
                                        <x-operations.money-input
                                            id="pos-cash-tendered"
                                            wire:model="cashTendered"
                                            error="cash_tendered"
                                            :label="__('pos.fields.cash_tendered')"
                                            label-class="block text-sm font-semibold text-gray-900"
                                            input-class="text-lg"
                                            :placeholder="LocalizedMoney::input($payment['cash_due'])"
                                        />
                                        <p class="mt-2 text-sm text-gray-700" x-show="change !== null" @if ($payment['change'] === null) x-cloak @endif aria-live="polite">
                                            {{ __('pos.fields.change') }}:
                                            <span dir="ltr" class="text-xl font-bold tabular-nums text-gray-900"><span x-text="change === null ? '' : $tryMoney.format(change)">{{ $payment['change'] === null ? '' : $money($payment['change']) }}</span> TRY</span>
                                        </p>
                                    </div>
                                @endif
                                <button type="submit" wire:loading.attr="disabled" wire:target="finalize" class="{{ $primaryButton }} w-full bg-emerald-600 hover:bg-emerald-700">
                                    <span wire:loading.remove wire:target="finalize">{{ __('pos.payment.complete') }}</span>
                                    <span wire:loading wire:target="finalize">{{ __('pos.payment.completing') }}</span>
                                </button>
                            </form>
                        @endunless

                        <div class="flex flex-wrap gap-2">
                            <button type="button" x-on:click="checkStatus()" class="{{ $secondaryButton }}">{{ __('pos.recovery.check_status') }}</button>
                            @if (! $attempt->isFrozen())
                                <button type="button" wire:click="backToCart" class="{{ $secondaryButton }}">{{ __('pos.payment.edit_cart') }}</button>
                                <button type="button" wire:click="cancelAttempt" wire:confirm="{{ __('pos.payment.cancel_confirm') }}" class="{{ $secondaryButton }} text-red-700">{{ __('pos.payment.cancel') }}</button>
                            @elseif ($canResolve && $terminalPending)
                                <button type="button" wire:click="openResolveTerminal('{{ $attempt->key }}')" class="{{ $secondaryButton }} text-red-700">{{ __('pos.recovery.resolve_terminal') }}</button>
                            @elseif ($canResolve && $attempt->cardApproval)
                                <button type="button" wire:click="openResolve('{{ $attempt->key }}')" class="{{ $secondaryButton }} text-red-700">{{ __('pos.recovery.resolve_void') }}</button>
                            @endif
                        </div>
                    </div>
                </div>
            @else
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                    <h2 id="pos-payment-title" class="text-base font-semibold text-gray-900">{{ __('pos.payment.title') }}</h2>
                    <p class="mt-1 text-sm text-gray-600">{{ __('pos.payment.review_first') }}</p>
                    @if ($lastSale)
                        <p class="mt-3 text-sm text-gray-700">
                            {{ __('pos.done.last_sale') }}
                            <a href="{{ route('pos.sales.show', [$store, $lastSale]) }}" class="font-mono font-semibold text-blue-700 underline" dir="ltr">{{ $lastSale->number }}</a>
                        </p>
                    @endif
                </div>
            @endif

            @if ($recoverable->isNotEmpty())
                <div class="rounded-xl bg-white shadow-sm ring-1 ring-amber-300">
                    <h2 class="border-b border-amber-100 px-4 py-3 text-base font-semibold text-gray-900">{{ __('pos.recovery.title') }}</h2>
                    <p class="px-4 pt-2 text-xs text-gray-600">{{ __('pos.recovery.help') }}</p>
                    <ul class="divide-y divide-gray-100">
                        @foreach ($recoverable as $open)
                            <li wire:key="recover-{{ $open->id }}" class="px-4 py-3 text-sm">
                                <p class="text-gray-900">
                                    <span dir="ltr" class="font-semibold tabular-nums">{{ $money($open->totalMinor()) }} TRY</span>
                                    · {{ trans_choice('pos.cart.count', count($open->lines), ['count' => count($open->lines)]) }}
                                    · <time dir="ltr" datetime="{{ $open->updated_at->toIso8601String() }}">{{ Sale::displayTime($open->updated_at) }}</time>
                                    @if ($open->user_id !== auth()->id())
                                        · {{ $open->cashier?->name }}
                                    @endif
                                </p>
                                @if ($open->cardApproval)
                                    <p class="mt-1 font-semibold text-amber-900">{{ __('pos.payment.card_approved_detail', [
                                        'amount' => $money($open->cardApproval->amountMinor()),
                                        'terminal' => $open->cardApproval->terminal?->displayName(),
                                        'reference' => $open->cardApproval->reference,
                                    ]) }} {{ __('pos.payment.do_not_charge_again') }}</p>
                                @elseif ($open->hasPendingTerminalPayment())
                                    <p class="mt-1 font-semibold text-sky-900">{{ __('pos.recovery.open_terminal_pending', [
                                        'amount' => $money($open->terminalPaymentAmountMinor()),
                                        'terminal' => $open->terminalPaymentTerminal?->displayName(),
                                    ]) }}</p>
                                @endif
                                @if ($open->failure_code)
                                    <p class="mt-1 text-red-800">{{ __('pos.recovery.failures.'.$open->failure_code) }}</p>
                                @endif
                                <div class="mt-2 flex flex-wrap gap-2">
                                    @if ($open->user_id === auth()->id())
                                        <button type="button" wire:click="resumeAttempt('{{ $open->key }}')" class="{{ $secondaryButton }}">{{ __('pos.recovery.resume') }}</button>
                                    @endif
                                    @if ($canResolve && $open->cardApproval)
                                        <button type="button" wire:click="openResolve('{{ $open->key }}')" class="{{ $secondaryButton }} text-red-700">{{ __('pos.recovery.resolve_void') }}</button>
                                    @elseif ($canResolve && $open->hasPendingTerminalPayment())
                                        <button type="button" wire:click="openResolveTerminal('{{ $open->key }}')" class="{{ $secondaryButton }} text-red-700">{{ __('pos.recovery.resolve_terminal') }}</button>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </section>
    </div>

    <x-operations.dialog :id="PosCounter::CHOOSER_DIALOG" :title="__('pos.scan.choose_title')" :direction="$textDirection">
        <p class="text-sm text-gray-700">{!! __('pos.scan.choose_help', ['code' => '<bdi dir="ltr" class="font-mono font-semibold">'.e($choicesFor).'</bdi>']) !!}</p>
        <ul class="mt-3 max-h-[60vh] divide-y divide-gray-100 overflow-y-auto rounded-lg ring-1 ring-gray-200">
            @foreach ($choices as $choice)
                @include('livewire.pos.partials.target-option', ['option' => $choice, 'keyPrefix' => 'choice'])
            @endforeach
        </ul>
    </x-operations.dialog>

    <x-operations.dialog :id="PosCounter::CUSTOMER_DIALOG" :title="__('pos.customer.dialog_title')" :direction="$textDirection">
        <form wire:submit="searchCustomers" role="search">
            <label for="pos-customer-search" class="block text-sm font-medium text-gray-800">{{ __('pos.customer.search_label') }}</label>
            <p id="pos-customer-search-help" class="mt-0.5 text-xs text-gray-600">{{ __('pos.customer.search_help') }}</p>
            <div class="mt-1 flex gap-2">
                <input id="pos-customer-search" type="search" dir="auto" autofocus autocomplete="off" maxlength="80" enterkeyhint="search" wire:model="customerSearch" aria-describedby="pos-customer-search-help" class="{{ $inputClass }} min-w-0 flex-1">
                <button type="submit" wire:loading.attr="disabled" wire:target="searchCustomers" class="{{ $secondaryButton }}">{{ __('pos.customer.search') }}</button>
            </div>
        </form>

        <div aria-live="polite">
            @if ($customerResults !== [])
                <ul class="mt-3 max-h-64 divide-y divide-gray-100 overflow-y-auto rounded-lg ring-1 ring-gray-200">
                    @foreach ($customerResults as $result)
                        <li wire:key="customer-{{ $result['id'] }}">
                            <button type="button" wire:click="selectCustomer({{ $result['id'] }})" class="flex min-h-11 w-full items-center justify-between gap-3 px-3 py-2 text-start hover:bg-blue-50 focus-visible:bg-blue-50 focus-visible:outline-none">
                                <span class="font-medium text-gray-900">{{ $result['name'] }}</span>
                                <bdi dir="ltr" class="font-mono text-sm text-gray-600">{{ $result['phone'] }}</bdi>
                            </button>
                        </li>
                    @endforeach
                </ul>
            @elseif ($customerSearchedFor !== '')
                <p class="mt-3 text-sm text-gray-600">{{ __('pos.customer.no_results', ['term' => $customerSearchedFor]) }}</p>
            @endif
        </div>

        <form wire:submit="createCustomer" class="mt-4 space-y-3 border-t border-gray-100 pt-4">
            <p class="text-sm font-semibold text-gray-900">{{ __('pos.customer.new_title') }}</p>
            <div>
                <label for="pos-customer-name" class="block text-sm font-medium text-gray-800">{{ __('pos.customer.name') }} <span class="text-red-700" aria-hidden="true">*</span></label>
                <input id="pos-customer-name" type="text" dir="auto" required autocomplete="off" maxlength="{{ \App\Models\Customer::MAX_NAME_LENGTH }}" wire:model="newCustomerName" @error('customer_name') aria-invalid="true" aria-describedby="pos-customer-name-error" @enderror class="{{ $inputClass }} mt-1">
                @error('customer_name') <p id="pos-customer-name-error" class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="pos-customer-phone" class="block text-sm font-medium text-gray-800">{{ __('pos.customer.phone') }} <span class="text-red-700" aria-hidden="true">*</span></label>
                <input id="pos-customer-phone" type="tel" dir="ltr" inputmode="tel" required autocomplete="off" maxlength="{{ \App\Models\Customer::MAX_PHONE_LENGTH }}" placeholder="0532 123 45 67" wire:model="newCustomerPhone" @error('customer_phone') aria-invalid="true" aria-describedby="pos-customer-phone-error" @enderror class="{{ $inputClass }} mt-1 font-mono">
                @error('customer_phone') <p id="pos-customer-phone-error" class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
            </div>
            <button type="submit" wire:loading.attr="disabled" wire:target="createCustomer" class="{{ $primaryButton }} w-full">{{ __('pos.customer.save') }}</button>
        </form>
    </x-operations.dialog>

    @if ($canDiscount)
        <x-operations.dialog :id="PosCounter::DISCOUNT_DIALOG" :title="__('pos.cart.discount_title')" :direction="$textDirection">
            {{-- The discount preview is presentation only; applyDiscount checks the price on the server. --}}
            <form
                wire:submit="applyDiscount"
                class="space-y-3"
                x-data="{
                    get discountMinor() {
                        const final = $tryMoney.toMinor($wire.discountPrice);
                        const list = $wire.discountListPrice;
                        return final === null || list === null || final > list ? null : list - final;
                    },
                }"
            >
                <p class="font-medium text-gray-900">{{ $cart[$discountLineKey]['name'] ?? '' }}</p>
                <dl class="grid grid-cols-2 gap-x-4 gap-y-2 rounded-lg bg-gray-50 p-3 text-sm">
                    <dt class="text-gray-700">{{ __('pos.discount.original_price') }}</dt>
                    <dd dir="ltr" class="text-end font-mono font-semibold tabular-nums text-gray-900">{{ $money($discountListPrice) }} TRY</dd>
                    <dt class="text-gray-700">{{ __('pos.discount.lowest_price') }}</dt>
                    <dd dir="ltr" class="text-end font-mono font-semibold tabular-nums text-gray-900">{{ $money($discountLowestPrice) }} TRY</dd>
                    <dt class="text-gray-700">{{ __('pos.discount.amount') }}</dt>
                    <dd dir="ltr" class="text-end font-mono tabular-nums text-emerald-800" aria-live="polite" x-text="discountMinor === null ? '—' : '-' + $tryMoney.format(discountMinor) + ' TRY'">—</dd>
                </dl>
                @if ($discountLimit !== '')
                    <p class="text-xs text-gray-600">{{ __('pos.discount.limits.'.$discountLimit) }}</p>
                @endif
                <x-operations.money-input
                    id="pos-discount-price"
                    wire:model="discountPrice"
                    error="discountPrice"
                    :label="__('pos.discount.final_price')"
                />

                <div>
                    <label for="pos-discount-reason" class="block text-sm font-medium text-gray-800">{{ __('pos.discount.reason') }}</label>
                    <textarea id="pos-discount-reason" dir="auto" rows="2" maxlength="500" wire:model="discountReason" @error('discountReason') aria-invalid="true" aria-describedby="pos-discount-reason-error" @enderror class="{{ $inputClass }} mt-1"></textarea>
                    @error('discountReason') <p id="pos-discount-reason-error" class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                </div>
                <button type="submit" wire:loading.attr="disabled" wire:target="applyDiscount" class="{{ $primaryButton }} w-full">{{ __('pos.discount.apply') }}</button>
            </form>
        </x-operations.dialog>
    @endif

    @if ($canResolve)
        <x-operations.dialog :id="PosCounter::RESOLVE_DIALOG" :title="__('pos.recovery.resolve_title')" :direction="$textDirection">
            <form wire:submit="resolveVoid" class="space-y-3">
                <p class="text-sm text-gray-700">{{ __('pos.recovery.resolve_help') }}</p>
                @error('attempt') <p class="text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                <div>
                    <label for="pos-void-reference" class="block text-sm font-medium text-gray-800">{{ __('pos.fields.void_reference') }}</label>
                    <input id="pos-void-reference" type="text" dir="ltr" maxlength="40" autocomplete="off" wire:model="voidReference" class="{{ $inputClass }} mt-1 font-mono uppercase">
                    @error('void_reference') <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="pos-void-note" class="block text-sm font-medium text-gray-800">{{ __('pos.fields.void_note') }}</label>
                    <textarea id="pos-void-note" dir="auto" rows="2" maxlength="500" wire:model="voidNote" class="{{ $inputClass }} mt-1"></textarea>
                    @error('void_note') <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="{{ $primaryButton }} w-full bg-red-700 hover:bg-red-800">{{ __('pos.recovery.confirm_void') }}</button>
            </form>
        </x-operations.dialog>

        <x-operations.dialog :id="PosCounter::RESOLVE_TERMINAL_DIALOG" :title="__('pos.recovery.resolve_terminal_title')" :direction="$textDirection">
            <form wire:submit="resolveTerminalPayment" class="space-y-3" x-data="{ outcome: $wire.entangle('terminalOutcome') }">
                <p class="text-sm text-gray-700">{{ __('pos.recovery.resolve_terminal_help') }}</p>
                @if ($resolvePaymentSummary !== '')
                    <p class="rounded-lg bg-gray-50 px-3 py-2 text-sm font-semibold text-gray-900 ring-1 ring-gray-200">{{ $resolvePaymentSummary }}</p>
                @endif
                @error('attempt') <p class="text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                <fieldset>
                    <legend class="block text-sm font-medium text-gray-800">{{ __('pos.fields.terminal_outcome') }}</legend>
                    <div class="mt-1 space-y-2">
                        @foreach (\App\Enums\TerminalPaymentOutcome::cases() as $outcome)
                            <label wire:key="outcome-{{ $outcome->value }}" class="flex min-h-11 items-center gap-2 rounded-lg px-3 ring-1 ring-gray-200">
                                <input type="radio" name="terminal-outcome" value="{{ $outcome->value }}" x-model="outcome" class="text-blue-600 focus:ring-blue-500">
                                <span class="text-sm text-gray-900">{{ $outcome->label() }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('terminal_outcome') <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                </fieldset>
                <div x-show="outcome === '{{ \App\Enums\TerminalPaymentOutcome::VOIDED->value }}'" x-cloak>
                    <label for="pos-terminal-void-reference" class="block text-sm font-medium text-gray-800">{{ __('pos.fields.void_reference') }}</label>
                    <input id="pos-terminal-void-reference" type="text" dir="ltr" maxlength="40" autocomplete="off" wire:model="voidReference" class="{{ $inputClass }} mt-1 font-mono uppercase">
                    @error('void_reference') <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="pos-terminal-note" class="block text-sm font-medium text-gray-800">{{ __('pos.fields.void_note') }}</label>
                    <textarea id="pos-terminal-note" dir="auto" rows="2" maxlength="500" wire:model="voidNote" class="{{ $inputClass }} mt-1"></textarea>
                    @error('void_note') <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="{{ $primaryButton }} w-full bg-red-700 hover:bg-red-800">{{ __('pos.recovery.confirm_terminal') }}</button>
            </form>
        </x-operations.dialog>
    @endif
</div>
