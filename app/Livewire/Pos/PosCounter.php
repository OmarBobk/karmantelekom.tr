<?php

declare(strict_types=1);

namespace App\Livewire\Pos;

use App\Actions\Pos\CancelCheckoutAttempt;
use App\Actions\Pos\CheckCheckoutStatus;
use App\Actions\Pos\FinalizeCheckout;
use App\Actions\Pos\RecordCardApproval;
use App\Actions\Pos\ResolveCounterCode;
use App\Actions\Pos\ResolveTerminalPayment;
use App\Actions\Pos\ResolveVoidedApproval;
use App\Actions\Pos\ReviewCheckout;
use App\Actions\Pos\SellingPrice;
use App\Actions\Pos\StartTerminalPayment;
use App\Enums\CheckoutAttemptStatus;
use App\Enums\TrackingMode;
use App\Exceptions\Inventory\IdempotencyConflictException;
use App\Exceptions\Purchasing\InvalidAmountException;
use App\Livewire\Pos\Concerns\UsesPosLocale;
use App\Models\CheckoutAttempt;
use App\Models\InventoryUnit;
use App\Models\ProductDefinition;
use App\Models\Sale;
use App\Models\Store;
use App\Services\Purchasing\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The sales counter for one store.
 *
 * The cart and the active attempt key are working state kept in the session per cashier and
 * store. The checkout attempt in the database is the durable record: it is created on review,
 * before payment, and unresolved attempts are listed for recovery even without the session.
 * Every action re-authorizes use_pos and store membership.
 */
class PosCounter extends Component
{
    use UsesPosLocale;

    public const CHOOSER_DIALOG = 'pos-choose';

    public const ADJUST_DIALOG = 'pos-adjust';

    public const RESOLVE_DIALOG = 'pos-resolve';

    public const RESOLVE_TERMINAL_DIALOG = 'pos-resolve-terminal';

    #[Locked]
    public int $storeId;

    /**
     * @var array<string, array{definition_id: int, unit_id: ?int, quantity: int, name: string, model: ?string, identifier: ?string, condition: ?string, adjusted_price: string, adjustment_reason: string}>
     */
    #[Locked]
    public array $cart = [];

    #[Locked]
    public ?string $attemptKey = null;

    #[Locked]
    public ?int $attemptRevision = null;

    /** Cart and payment, or the completed sale. */
    #[Locked]
    public string $step = 'cart';

    #[Locked]
    public ?int $lastSaleId = null;

    /**
     * @var list<array<string, mixed>>
     */
    #[Locked]
    public array $choices = [];

    #[Locked]
    public string $choicesFor = '';

    /**
     * @var list<array<string, mixed>>
     */
    #[Locked]
    public array $searchResults = [];

    #[Locked]
    public string $searchTerm = '';

    public ?int $cardTerminalId = null;

    public string $cardAmount = '';

    public string $cardReference = '';

    public string $cashTendered = '';

    #[Locked]
    public string $adjustLineKey = '';

    public string $adjustedPrice = '';

    public string $adjustmentReason = '';

    #[Locked]
    public string $resolveAttemptKey = '';

    public string $voidReference = '';

    public string $voidNote = '';

    public string $terminalOutcome = '';

    /**
     * Request key of the next terminal payment start, issued before the request is sent so a
     * retry from the same page replays instead of starting again. Renewed after each confirmed start.
     */
    #[Locked]
    public string $startRequestKey = '';

    /** The terminal payment start the approval form shows and is bound to. */
    #[Locked]
    public ?string $displayedPaymentKey = null;

    /** The one start this page confirmed as new; only then may the cashier be told to charge. */
    #[Locked]
    public ?string $chargeInstructedFor = null;

    /** The terminal payment start the owner's resolution dialog shows and is bound to. */
    #[Locked]
    public ?string $resolvePaymentKey = null;

    #[Locked]
    public string $resolvePaymentSummary = '';

    public function mount(Store $store): void
    {
        $this->storeId = $store->id;
        $this->startRequestKey = (string) Str::uuid();
        $this->authorizeCounter();
        $this->restoreWorkingState();
        $this->syncActiveAttempt();

        if ($this->attemptKey === null && $this->cart === [] && $this->lastSaleId !== null) {
            $this->step = 'done';
        }
    }

    public function dehydrate(): void
    {
        session()->put($this->sessionKey(), [
            'cart' => $this->cart,
            'attempt_key' => $this->attemptKey,
            'last_sale_id' => $this->lastSaleId,
        ]);
    }

    public function scan(ResolveCounterCode $resolver, string $code): void
    {
        $store = $this->authorizeCounter();
        $code = mb_substr(Str::trim($code), 0, 81);

        if ($code === '') {
            return;
        }

        $matches = $resolver(auth()->user(), $store, $code);
        $this->searchResults = [];
        $this->searchTerm = '';

        if ($matches === []) {
            $this->notify('error', __('pos.scan.not_found', ['code' => $code]));

            return;
        }

        if (count($matches) === 1 && $matches[0]['available'] && $matches[0]['kind'] === 'definition') {
            $this->addTarget($matches[0]);

            return;
        }

        if (count($matches) === 1 && $matches[0]['available'] && $matches[0]['kind'] === 'unit' && in_array($matches[0]['match'], ['imei', 'serial'], true)) {
            $this->addTarget($matches[0]);

            return;
        }

        if (count($matches) === 1 && ! $matches[0]['available']) {
            $this->notify('error', self::unavailableMessage($matches[0]));

            return;
        }

        $this->choices = $matches;
        $this->choicesFor = $code;
        $this->dispatch('open-dialog', id: self::CHOOSER_DIALOG);
    }

    public function search(ResolveCounterCode $resolver, string $term): void
    {
        $store = $this->authorizeCounter();
        $this->searchTerm = mb_substr(Str::trim($term), 0, 100);
        $this->searchResults = $resolver->search(auth()->user(), $store, $this->searchTerm);
    }

    public function choose(ResolveCounterCode $resolver, int $definitionId, ?int $unitId = null): void
    {
        $store = $this->authorizeCounter();
        $target = $resolver->target(auth()->user(), $store, $definitionId, $unitId);

        if ($target === null || ! $target['available']) {
            $this->notify('error', $target === null ? __('pos.errors.item_unavailable') : self::unavailableMessage($target));

            return;
        }

        $this->addTarget($target);
        $this->choices = [];
        $this->searchResults = [];
        $this->searchTerm = '';
        $this->dispatch('close-dialog', id: self::CHOOSER_DIALOG);
    }

    public function setQuantity(string $key, int $quantity): void
    {
        $store = $this->authorizeCounter();

        if (! isset($this->cart[$key]) || $this->cart[$key]['unit_id'] !== null || ! $this->ensureCartEditable()) {
            return;
        }

        if ($quantity < 1) {
            $this->removeLine($key);

            return;
        }

        $available = (int) ($store->inventoryBalances()->where('product_definition_id', $this->cart[$key]['definition_id'])->value('quantity') ?? 0);

        if ($quantity > $available || $quantity > ReviewCheckout::MAX_QUANTITY) {
            $this->notify('error', __('pos.errors.insufficient_stock', ['available' => $available]));

            return;
        }

        $this->cart[$key]['quantity'] = $quantity;
        $this->cartChanged();
    }

    public function removeLine(string $key): void
    {
        $this->authorizeCounter();

        if (! isset($this->cart[$key]) || ! $this->ensureCartEditable()) {
            return;
        }

        unset($this->cart[$key]);
        $this->cartChanged();
    }

    public function openAdjustment(string $key): void
    {
        $store = $this->authorizeCounter();
        $this->authorize('adjustSalePrices', $store);

        if (! isset($this->cart[$key]) || ! $this->ensureCartEditable()) {
            return;
        }

        $this->resetValidation();
        $this->adjustLineKey = $key;
        $this->adjustedPrice = $this->cart[$key]['adjusted_price'];
        $this->adjustmentReason = $this->cart[$key]['adjustment_reason'];
        $this->dispatch('open-dialog', id: self::ADJUST_DIALOG);
    }

    public function saveAdjustment(): void
    {
        $store = $this->authorizeCounter();
        $this->authorize('adjustSalePrices', $store);

        if (! isset($this->cart[$this->adjustLineKey]) || ! $this->ensureCartEditable()) {
            return;
        }

        $this->resetValidation(['adjustedPrice', 'adjustmentReason']);
        $price = Str::trim($this->adjustedPrice);
        $reason = Str::trim($this->adjustmentReason);

        if ($price !== '') {
            try {
                Money::parse($price, Money::MAX_UNIT_AMOUNT);
            } catch (InvalidAmountException) {
                throw ValidationException::withMessages(['adjustedPrice' => __('pos.errors.price_invalid', ['max' => Money::format(Money::MAX_UNIT_AMOUNT)])]);
            }

            if ($reason === '' || mb_strlen($reason) > ReviewCheckout::MAX_REASON_LENGTH) {
                throw ValidationException::withMessages(['adjustmentReason' => __('pos.errors.adjustment_reason_required', ['max' => ReviewCheckout::MAX_REASON_LENGTH])]);
            }
        }

        $this->cart[$this->adjustLineKey]['adjusted_price'] = $price;
        $this->cart[$this->adjustLineKey]['adjustment_reason'] = $price === '' ? '' : $reason;
        $this->cartChanged();
        $this->dispatch('close-dialog', id: self::ADJUST_DIALOG);
    }

    public function review(ReviewCheckout $review): void
    {
        $store = $this->authorizeCounter();

        try {
            $attempt = $review(auth()->user(), $store, $this->cartInput(), $this->attemptKey, $this->attemptKey === null ? null : $this->attemptRevision);
        } catch (ModelNotFoundException) {
            $this->forgetAttempt();
            $this->notify('error', __('pos.errors.attempt_missing'));

            return;
        }

        $this->attemptKey = $attempt->key;
        $this->attemptRevision = $attempt->revision;
        $this->step = 'payment';
        $this->resetValidation();
    }

    public function backToCart(): void
    {
        $this->authorizeCounter();

        $attempt = $this->activeAttempt();

        if ($attempt?->isFrozen()) {
            $this->notify('warning', __('pos.errors.attempt_frozen'));

            return;
        }

        $this->step = 'cart';
    }

    /**
     * Persist the terminal and amount before the cashier is told to charge the card.
     */
    public function startTerminalPayment(StartTerminalPayment $start): void
    {
        $store = $this->authorizeCounter();

        if ($this->attemptKey === null || $this->attemptRevision === null) {
            return;
        }

        try {
            $result = $start(auth()->user(), $store, $this->attemptKey, $this->attemptRevision, $this->startRequestKey, (int) $this->cardTerminalId, $this->cardAmount);
        } catch (ValidationException $exception) {
            $this->syncActiveAttempt();

            throw $exception;
        }

        $this->resetValidation();
        $this->startRequestKey = (string) Str::uuid();
        $this->cart = self::cartFromAttempt($result->attempt);
        $this->displayedPaymentKey = $result->paymentKey();

        if ($result->isNewStart) {
            $this->chargeInstructedFor = $result->paymentKey();
            $this->notify('info', __('pos.payment.terminal_started', ['amount' => Money::format((int) $result->attempt->terminalPaymentAmountMinor())]));

            return;
        }

        $this->chargeInstructedFor = null;
        $this->notify('warning', __('pos.payment.terminal_start_replayed'));
    }

    public function recordCardApproval(RecordCardApproval $record): void
    {
        $store = $this->authorizeCounter();
        $attempt = $this->activeAttempt();

        if ($attempt === null || $this->attemptRevision === null) {
            return;
        }

        if (! $attempt->hasStartedTerminalPayment() || $this->displayedPaymentKey === null) {
            $this->syncActiveAttempt();
            $this->notify('error', __('pos.errors.terminal_payment_not_started'));

            return;
        }

        try {
            $record(
                auth()->user(), $store, $attempt->key, $this->attemptRevision, $this->displayedPaymentKey,
                (int) $attempt->terminal_payment_terminal_id, $this->cardReference, (string) $attempt->terminal_payment_amount,
            );
        } catch (ValidationException $exception) {
            $this->syncActiveAttempt();

            throw $exception;
        }

        $this->resetValidation();
        $this->notify('success', __('pos.payment.card_recorded'));
    }

    public function finalize(FinalizeCheckout $finalize): void
    {
        $store = $this->authorizeCounter();

        if ($this->attemptKey === null || $this->attemptRevision === null) {
            return;
        }

        try {
            $sale = $finalize(auth()->user(), $store, $this->attemptKey, $this->attemptRevision, $this->cashTendered);
        } catch (IdempotencyConflictException) {
            $this->syncActiveAttempt();
            $this->notify('error', __('pos.errors.finalize_conflict'));

            return;
        }

        $this->completed($sale);
    }

    public function checkStatus(CheckCheckoutStatus $check): void
    {
        $store = $this->authorizeCounter();

        if ($this->attemptKey === null) {
            return;
        }

        $attempt = $check(auth()->user(), $store, $this->attemptKey);

        match ($attempt->status) {
            CheckoutAttemptStatus::COMPLETED => $this->completed($attempt->sale),
            CheckoutAttemptStatus::OPEN => $this->notify('warning', match (true) {
                $attempt->cardApproval !== null => __('pos.recovery.open_with_card', ['amount' => Money::format($attempt->cardApproval->amountMinor()), 'reference' => $attempt->cardApproval->reference]),
                $attempt->hasStartedTerminalPayment() => __('pos.recovery.open_terminal_pending', [
                    'amount' => Money::format((int) $attempt->terminalPaymentAmountMinor()),
                    'terminal' => $attempt->terminalPaymentTerminal?->displayName(),
                ]),
                default => __('pos.recovery.open_without_payment'),
            }),
            default => $this->forgetAttempt(),
        };

        if ($attempt->status === CheckoutAttemptStatus::OPEN) {
            $this->attemptRevision = $attempt->revision;
            $this->displayedPaymentKey = $attempt->terminal_payment_key;
            $this->chargeInstructedFor = null;
            $this->step = 'payment';
        }
    }

    public function cancelAttempt(CancelCheckoutAttempt $cancel): void
    {
        $store = $this->authorizeCounter();

        if ($this->attemptKey === null) {
            return;
        }

        $cancel(auth()->user(), $store, $this->attemptKey);
        $this->forgetAttempt();
        $this->notify('info', __('pos.payment.cancelled'));
    }

    public function newSale(CancelCheckoutAttempt $cancel): void
    {
        $store = $this->authorizeCounter();
        $attempt = $this->activeAttempt();

        if ($attempt?->isOpen()) {
            try {
                $cancel(auth()->user(), $store, $attempt->key);
            } catch (ValidationException) {
                $this->syncActiveAttempt();
                $this->notify('error', __('pos.errors.new_sale_blocked'));

                return;
            }
        }

        $this->cart = [];
        $this->lastSaleId = null;
        $this->forgetAttempt();
        $this->resetPaymentFields();
    }

    public function resumeAttempt(string $key): void
    {
        $store = $this->authorizeCounter();
        $attempt = $store->checkoutAttempts()->where('key', $key)->first();

        if ($attempt === null || ! $attempt->isOpen()) {
            $this->notify('error', __('pos.errors.attempt_missing'));

            return;
        }

        $this->authorize('operate', $attempt);

        $active = $this->activeAttempt();

        if ($active !== null && $active->id !== $attempt->id && $active->isOpen() && ($active->hasStartedTerminalPayment() || $active->cardApproval()->exists())) {
            $this->notify('error', __('pos.errors.new_sale_blocked'));

            return;
        }

        $this->cart = self::cartFromAttempt($attempt);
        $this->attemptKey = $attempt->key;
        $this->attemptRevision = $attempt->revision;
        $this->displayedPaymentKey = $attempt->terminal_payment_key;
        $this->chargeInstructedFor = null;
        $this->lastSaleId = null;
        $this->step = 'payment';
        $this->resetPaymentFields();
    }

    public function openResolve(string $key): void
    {
        $store = $this->authorizeCounter();
        $attempt = $store->checkoutAttempts()->where('key', $key)->firstOrFail();
        $this->authorize('resolve', $attempt);

        $this->resetValidation();
        $this->resolveAttemptKey = $attempt->key;
        $this->voidReference = '';
        $this->voidNote = '';
        $this->dispatch('open-dialog', id: self::RESOLVE_DIALOG);
    }

    public function resolveVoid(ResolveVoidedApproval $resolve): void
    {
        $store = $this->authorizeCounter();

        $resolve(auth()->user(), $store, $this->resolveAttemptKey, $this->voidReference, $this->voidNote);

        if ($this->resolveAttemptKey === $this->attemptKey) {
            $this->forgetAttempt();
        }

        $this->resolveAttemptKey = '';
        $this->dispatch('close-dialog', id: self::RESOLVE_DIALOG);
        $this->notify('success', __('pos.recovery.voided'));
    }

    public function openResolveTerminal(string $key): void
    {
        $store = $this->authorizeCounter();
        $attempt = $store->checkoutAttempts()->where('key', $key)->firstOrFail();
        $this->authorize('resolve', $attempt);

        if (! $attempt->hasPendingTerminalPayment()) {
            $this->notify('error', __('pos.errors.no_terminal_payment'));

            return;
        }

        $this->resetValidation();
        $this->resolveAttemptKey = $attempt->key;
        $this->resolvePaymentKey = $attempt->terminal_payment_key;
        $this->resolvePaymentSummary = __('pos.recovery.resolve_terminal_payment', [
            'amount' => Money::format((int) $attempt->terminalPaymentAmountMinor()),
            'terminal' => $attempt->terminalPaymentTerminal?->displayName(),
            'time' => Sale::displayTime($attempt->terminal_payment_started_at),
        ]);
        $this->terminalOutcome = '';
        $this->voidReference = '';
        $this->voidNote = '';
        $this->dispatch('open-dialog', id: self::RESOLVE_TERMINAL_DIALOG);
    }

    public function resolveTerminalPayment(ResolveTerminalPayment $resolve): void
    {
        $store = $this->authorizeCounter();

        if ($this->resolvePaymentKey === null) {
            return;
        }

        $attempt = $resolve(auth()->user(), $store, $this->resolveAttemptKey, $this->resolvePaymentKey, $this->terminalOutcome, $this->voidReference, $this->voidNote);

        if ($attempt->key === $this->attemptKey) {
            $this->attemptRevision = $attempt->revision;
            $this->displayedPaymentKey = $attempt->terminal_payment_key;
            $this->chargeInstructedFor = null;
        }

        $this->resolveAttemptKey = '';
        $this->resolvePaymentKey = null;
        $this->resolvePaymentSummary = '';
        $this->dispatch('close-dialog', id: self::RESOLVE_TERMINAL_DIALOG);
        $this->notify('success', __('pos.recovery.terminal_cleared'));
    }

    public function render(): View
    {
        $store = $this->authorizeCounter();
        $user = auth()->user();
        $attempt = $this->activeAttempt()?->load(['cardApproval.terminal', 'terminalPaymentTerminal']);
        $cartLines = $this->cartLinesWithCurrentPrices();
        $terminalPending = $attempt !== null && $attempt->isOpen() && $attempt->hasPendingTerminalPayment();

        return view('livewire.pos.pos-counter', [
            'store' => $store,
            'attempt' => $attempt,
            'terminalPending' => $terminalPending,
            'chargeNow' => $terminalPending
                && $this->chargeInstructedFor !== null
                && $this->chargeInstructedFor === $attempt->terminal_payment_key
                && $this->displayedPaymentKey === $attempt->terminal_payment_key,
            'cartLines' => $cartLines,
            'cartTotal' => array_sum(array_column($cartLines, 'line_total')),
            'cartEditable' => ! ($attempt?->isOpen() && $attempt->isFrozen()),
            'payment' => $attempt?->isOpen() ? $this->paymentPreview($attempt) : null,
            'terminals' => $store->cardTerminals()->active()->orderBy('label')->get(),
            'recoverable' => $this->recoverableAttempts($store),
            'lastSale' => $this->lastSaleId === null ? null : Sale::query()->visibleTo($user, $store)->find($this->lastSaleId),
            'canAdjust' => $user->can('adjustSalePrices', $store),
            'canResolve' => $user->can('resolveCheckoutAttempts', $store),
            ...$this->localeViewData(),
        ])->layout('layouts.pos', ['posStore' => $store])->title(__('pos.counter.title', ['store' => $store->code]));
    }

    public static function formatMoney(?int $minor): string
    {
        return $minor === null ? '—' : Money::format($minor);
    }

    /**
     * @param  array<string, mixed>  $match
     */
    public static function unavailableMessage(array $match): string
    {
        if ($match['status'] === 'inactive') {
            return __('pos.errors.item_unavailable_named', ['name' => $match['name']]);
        }

        if ($match['kind'] === 'unit') {
            return __('pos.errors.unit_unavailable_named', [
                'name' => $match['name'],
                'status' => __('inventory.unit_statuses.'.$match['status']),
            ]);
        }

        return __('pos.errors.out_of_stock_named', ['name' => $match['name']]);
    }

    /**
     * @param  array<string, mixed>  $match
     */
    private function addTarget(array $match): void
    {
        if (! $this->ensureCartEditable()) {
            return;
        }

        if (! $match['available']) {
            $this->notify('error', self::unavailableMessage($match));

            return;
        }

        if ($match['kind'] === 'unit') {
            $key = 'u'.$match['unit_id'];

            if (isset($this->cart[$key])) {
                $this->notify('warning', __('pos.scan.already_in_cart', ['name' => $match['name']]));

                return;
            }

            if (count($this->cart) >= ReviewCheckout::MAX_LINES) {
                $this->notify('error', __('pos.errors.too_many_lines', ['max' => ReviewCheckout::MAX_LINES]));

                return;
            }

            $this->cart[$key] = [
                'definition_id' => $match['definition_id'],
                'unit_id' => $match['unit_id'],
                'quantity' => 1,
                'name' => $match['name'],
                'model' => $match['model'],
                'identifier' => $match['identifier'],
                'condition' => $match['condition'],
                'adjusted_price' => '',
                'adjustment_reason' => '',
            ];
        } else {
            $key = 'd'.$match['definition_id'];
            $quantity = ($this->cart[$key]['quantity'] ?? 0) + 1;

            if ($quantity > (int) $match['stock']) {
                $this->notify('error', __('pos.errors.insufficient_stock', ['available' => (int) $match['stock']]));

                return;
            }

            if (! isset($this->cart[$key]) && count($this->cart) >= ReviewCheckout::MAX_LINES) {
                $this->notify('error', __('pos.errors.too_many_lines', ['max' => ReviewCheckout::MAX_LINES]));

                return;
            }

            $this->cart[$key] = [
                'definition_id' => $match['definition_id'],
                'unit_id' => null,
                'quantity' => $quantity,
                'name' => $match['name'],
                'model' => $match['model'],
                'identifier' => null,
                'condition' => null,
                'adjusted_price' => $this->cart[$key]['adjusted_price'] ?? '',
                'adjustment_reason' => $this->cart[$key]['adjustment_reason'] ?? '',
            ];
        }

        if ($match['price_minor'] === null) {
            $this->notify('warning', __('pos.scan.added_unpriced', ['name' => $match['name']]));
        } else {
            $this->notify('success', __('pos.scan.added', ['name' => $match['name']]));
        }

        $this->cartChanged();
    }

    private function completed(?Sale $sale): void
    {
        if ($sale === null) {
            return;
        }

        $this->cart = [];
        $this->attemptKey = null;
        $this->attemptRevision = null;
        $this->displayedPaymentKey = null;
        $this->chargeInstructedFor = null;
        $this->lastSaleId = $sale->id;
        $this->step = 'done';
        $this->resetPaymentFields();
        $this->resetValidation();
        $this->dispatch('pos-sale-completed', url: route('pos.sales.show', [$this->storeId, $sale->id]));
    }

    private function ensureCartEditable(): bool
    {
        $attempt = $this->activeAttempt();

        if ($attempt?->isOpen() && $attempt->isFrozen()) {
            $this->notify('warning', __('pos.errors.attempt_frozen'));

            return false;
        }

        return true;
    }

    private function cartChanged(): void
    {
        $this->lastSaleId = null;

        if ($this->step !== 'cart') {
            $this->step = 'cart';
        }
    }

    private function forgetAttempt(): void
    {
        $this->attemptKey = null;
        $this->attemptRevision = null;
        $this->displayedPaymentKey = null;
        $this->chargeInstructedFor = null;
        $this->step = 'cart';
    }

    private function resetPaymentFields(): void
    {
        $this->cardTerminalId = null;
        $this->cardAmount = '';
        $this->cardReference = '';
        $this->cashTendered = '';
    }

    private function activeAttempt(): ?CheckoutAttempt
    {
        if ($this->attemptKey === null) {
            return null;
        }

        return CheckoutAttempt::query()->where('store_id', $this->storeId)->where('key', $this->attemptKey)->first();
    }

    /**
     * After a reload or a lost response, follow the attempt's committed state.
     */
    private function syncActiveAttempt(): void
    {
        $attempt = $this->activeAttempt();

        if ($attempt === null || auth()->user()->cannot('view', $attempt)) {
            $this->forgetAttempt();

            return;
        }

        if ($attempt->status === CheckoutAttemptStatus::COMPLETED) {
            $this->completed($attempt->sale()->first());

            return;
        }

        if (! $attempt->isOpen()) {
            $this->forgetAttempt();

            return;
        }

        $this->attemptRevision = $attempt->revision;
        $this->step = 'payment';
        $this->displayedPaymentKey = $attempt->terminal_payment_key;

        if ($this->chargeInstructedFor !== $attempt->terminal_payment_key) {
            $this->chargeInstructedFor = null;
        }

        if ($attempt->isFrozen() || $this->cart === []) {
            $this->cart = self::cartFromAttempt($attempt);
        }
    }

    private function restoreWorkingState(): void
    {
        $state = session($this->sessionKey());

        if (! is_array($state)) {
            return;
        }

        $this->cart = is_array($state['cart'] ?? null) ? $state['cart'] : [];
        $this->attemptKey = is_string($state['attempt_key'] ?? null) ? $state['attempt_key'] : null;
        $this->lastSaleId = is_int($state['last_sale_id'] ?? null) ? $state['last_sale_id'] : null;
    }

    private function sessionKey(): string
    {
        return 'pos.working.'.auth()->id().'.'.$this->storeId;
    }

    private function authorizeCounter(): Store
    {
        $store = Store::query()->findOrFail($this->storeId);
        $this->authorize('usePos', $store);

        return $store;
    }

    /**
     * @return array<string, array{definition_id: int, unit_id: ?int, quantity: int, name: string, model: ?string, identifier: ?string, condition: ?string, adjusted_price: string, adjustment_reason: string}>
     */
    private static function cartFromAttempt(CheckoutAttempt $attempt): array
    {
        $cart = [];

        foreach ($attempt->lines as $line) {
            $cart[$line['key']] = [
                'definition_id' => (int) $line['definition_id'],
                'unit_id' => $line['unit_id'] === null ? null : (int) $line['unit_id'],
                'quantity' => (int) $line['quantity'],
                'name' => $line['name'],
                'model' => $line['model'],
                'identifier' => $line['unit_identifier'],
                'condition' => $line['unit_condition'],
                'adjusted_price' => $line['adjustment_reason'] === null ? '' : Money::input((int) $line['unit_price']),
                'adjustment_reason' => (string) $line['adjustment_reason'],
            ];
        }

        return $cart;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function cartInput(): array
    {
        return collect($this->cart)->map(fn (array $line): array => [
            'definition_id' => $line['definition_id'],
            'unit_id' => $line['unit_id'],
            'quantity' => $line['quantity'],
            'adjusted_price' => $line['adjusted_price'],
            'adjustment_reason' => $line['adjustment_reason'],
        ])->all();
    }

    /**
     * Cart lines with today's list prices, for display only; review prices them authoritatively.
     *
     * @return list<array<string, mixed>>
     */
    private function cartLinesWithCurrentPrices(): array
    {
        if ($this->cart === []) {
            return [];
        }

        $definitions = ProductDefinition::query()->whereKey(array_column($this->cart, 'definition_id'))->get()->keyBy('id');
        $units = InventoryUnit::query()->where('store_id', $this->storeId)->whereKey(array_filter(array_column($this->cart, 'unit_id')))->get()->keyBy('id');
        $lines = [];

        foreach ($this->cart as $key => $line) {
            $definition = $definitions->get($line['definition_id']);
            $unit = $line['unit_id'] === null ? null : $units->get($line['unit_id']);
            $listPrice = $definition === null ? null : SellingPrice::listPriceMinor($definition, $unit);
            $adjusted = null;

            if ($line['adjusted_price'] !== '') {
                try {
                    $adjusted = Money::parse($line['adjusted_price'], Money::MAX_UNIT_AMOUNT);
                } catch (InvalidAmountException) {
                    $adjusted = null;
                }
            }

            $unitPrice = $adjusted ?? $listPrice;

            $lines[] = [
                ...$line,
                'key' => (string) $key,
                'tracking_mode' => $definition?->tracking_mode ?? TrackingMode::QUANTITY,
                'list_price' => $listPrice,
                'unit_price' => $unitPrice,
                'line_total' => $unitPrice === null ? 0 : $unitPrice * $line['quantity'],
            ];
        }

        return $lines;
    }

    /**
     * @return array{total: int, card: int, cash_due: int, change: ?int}
     */
    private function paymentPreview(CheckoutAttempt $attempt): array
    {
        $total = $attempt->totalMinor();
        $card = $attempt->hasPendingTerminalPayment() ? (int) $attempt->terminalPaymentAmountMinor() : $attempt->approvedCardMinor();
        $cashDue = max(0, $total - $card);
        $change = null;

        if ($cashDue > 0 && Str::trim($this->cashTendered) !== '') {
            try {
                $tendered = Money::parse($this->cashTendered, Money::MAX_TOTAL_AMOUNT, allowZero: true);
                $change = $tendered >= $cashDue ? $tendered - $cashDue : null;
            } catch (InvalidAmountException) {
                $change = null;
            }
        }

        return ['total' => $total, 'card' => $card, 'cash_due' => $cashDue, 'change' => $change];
    }

    /**
     * Unresolved attempts in this store other than the active one: the cashier's own, or all for the owner.
     *
     * @return Collection<int, CheckoutAttempt>
     */
    private function recoverableAttempts(Store $store): Collection
    {
        $user = auth()->user();

        return $store->checkoutAttempts()
            ->unresolved()
            ->when(! $user->can('viewAllSales', $store), fn ($query) => $query->where('user_id', $user->id))
            ->when($this->attemptKey !== null, fn ($query) => $query->where('key', '!=', $this->attemptKey))
            ->with(['cardApproval.terminal', 'terminalPaymentTerminal', 'cashier:id,name'])
            ->latest('updated_at')
            ->limit(20)
            ->get();
    }

    private function notify(string $type, string $message): void
    {
        $this->dispatch('notify', ['type' => $type, 'message' => $message]);
    }
}
