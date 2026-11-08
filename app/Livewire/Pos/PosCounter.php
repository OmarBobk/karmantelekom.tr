<?php

declare(strict_types=1);

namespace App\Livewire\Pos;

use App\Actions\Cashbox\StartSaleCashIntake;
use App\Actions\Customers\FindCounterCustomers;
use App\Actions\Customers\SaveCounterCustomer;
use App\Actions\Pos\CancelCheckoutAttempt;
use App\Actions\Pos\CheckCheckoutStatus;
use App\Actions\Pos\DiscountAuthority;
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
use App\Models\CashboxSession;
use App\Models\CheckoutAttempt;
use App\Models\Customer;
use App\Models\InventoryUnit;
use App\Models\NetworkReservation;
use App\Models\ProductDefinition;
use App\Models\Sale;
use App\Models\Store;
use App\Services\Purchasing\LocalizedMoney;
use App\Services\Purchasing\Money;
use Illuminate\Auth\Access\AuthorizationException;
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

    public const DISCOUNT_DIALOG = 'pos-discount';

    public const RESOLVE_DIALOG = 'pos-resolve';

    public const RESOLVE_TERMINAL_DIALOG = 'pos-resolve-terminal';

    public const CUSTOMER_DIALOG = 'pos-customer';

    #[Locked]
    public int $storeId;

    /** The sale's customer, or null for a walk-in. Review checks it belongs to this store. */
    #[Locked]
    public ?int $customerId = null;

    /**
     * The Store Network reservation this sale fulfills, set only from the reservations page link.
     */
    #[Locked]
    public ?int $reservationId = null;

    public string $customerSearch = '';

    /**
     * @var list<array{id: int, name: string, phone: string}>
     */
    #[Locked]
    public array $customerResults = [];

    #[Locked]
    public string $customerSearchedFor = '';

    public string $newCustomerName = '';

    public string $newCustomerPhone = '';

    /** The part of the total the customer will owe, as localized text; blank for a fully paid sale. */
    public string $creditAmount = '';

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
    public string $discountLineKey = '';

    /** The line's list price when the dialog opened, in minor units; display only. */
    #[Locked]
    public ?int $discountListPrice = null;

    /** The lowest unit price the cashier's limit allowed when the dialog opened; display only. */
    #[Locked]
    public ?int $discountLowestPrice = null;

    #[Locked]
    public string $discountLimit = '';

    public string $discountPrice = '';

    public string $discountReason = '';

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

    /**
     * Request key of the next cash intake start; a retry from the same page returns the same
     * intake. Renewed after each confirmed new intake.
     */
    #[Locked]
    public string $cashRequestKey = '';

    /** The one cash intake this page confirmed as new; only then is the cashier told to take the cash. */
    #[Locked]
    public ?int $cashInstructedFor = null;

    /** The pending cash intake this page shows; completing the sale names it, so it is never swapped silently. */
    #[Locked]
    public ?int $displayedCashIntakeId = null;

    /**
     * Mount found an active attempt but no saved cart, so the attempt's reviewed cart is the
     * working cart. A saved empty cart is the cashier's edit and is kept.
     */
    private bool $workingCartMissing = false;

    public function mount(Store $store): void
    {
        $this->storeId = $store->id;
        $this->startRequestKey = (string) Str::uuid();
        $this->cashRequestKey = (string) Str::uuid();
        $this->authorizeCounter();
        $this->restoreWorkingState();
        $this->syncActiveAttempt();

        $requestedReservation = request()->integer('reservation');

        if ($requestedReservation > 0) {
            $this->linkReservation($store, $requestedReservation);
        }

        if ($this->attemptKey === null && $this->cart === [] && $this->lastSaleId !== null) {
            $this->step = 'done';
        }
    }

    public function dehydrate(): void
    {
        session()->put($this->sessionKey(), [
            'cart' => $this->cart,
            'customer_id' => $this->customerId,
            'reservation_id' => $this->reservationId,
            'credit_amount' => $this->creditAmount,
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

    /**
     * Open the discount dialog with the line's current list price and the lowest price the
     * cashier's limit allows. Never shows acquisition cost.
     */
    public function openDiscount(string $key): void
    {
        $store = $this->authorizeCounter();
        $authority = $this->discountAuthority($store);

        if (! isset($this->cart[$key]) || ! $this->ensureCartEditable()) {
            return;
        }

        [$listPrice, $unit] = $this->currentListPrice($store, $this->cart[$key]);

        if ($listPrice === null) {
            $this->notify('error', __('pos.errors.not_priced'));

            return;
        }

        $limit = $authority->limitFor($listPrice, $unit);

        $this->resetValidation();
        $this->discountLineKey = $key;
        $this->discountListPrice = $listPrice;
        $this->discountLowestPrice = $limit['lowest'] ?? null;
        $this->discountLimit = $limit['limit'] ?? '';
        $this->discountPrice = $this->cart[$key]['adjusted_price'] === '' ? '' : LocalizedMoney::input(Money::fromDecimal($this->cart[$key]['adjusted_price']));
        $this->discountReason = $this->cart[$key]['adjustment_reason'];
        $this->dispatch('open-dialog', id: self::DISCOUNT_DIALOG);
    }

    /**
     * Apply the cashier's final unit price at once when it is within their limit; otherwise
     * reject it with the lowest allowed price. Review checks the same limit again.
     */
    public function applyDiscount(): void
    {
        $store = $this->authorizeCounter();
        $authority = $this->discountAuthority($store);
        $key = $this->discountLineKey;

        if (! isset($this->cart[$key]) || ! $this->ensureCartEditable()) {
            return;
        }

        $this->resetValidation(['discountPrice', 'discountReason']);
        $price = Str::trim($this->discountPrice);
        $reason = Str::trim($this->discountReason);

        if ($price === '') {
            $this->setLineDiscount($key, '', '');

            return;
        }

        [$listPrice, $unit] = $this->currentListPrice($store, $this->cart[$key]);

        if ($listPrice === null) {
            throw ValidationException::withMessages(['discountPrice' => __('pos.errors.not_priced')]);
        }

        try {
            $priceMinor = LocalizedMoney::parse($price, Money::MAX_UNIT_AMOUNT);
        } catch (InvalidAmountException $exception) {
            throw ValidationException::withMessages(['discountPrice' => self::amountError($exception, __('pos.errors.price_invalid', ['max' => LocalizedMoney::format(Money::MAX_UNIT_AMOUNT)]))]);
        }

        if ($priceMinor === $listPrice) {
            $this->setLineDiscount($key, '', '');

            return;
        }

        $refusal = $authority->refusal($listPrice, $priceMinor, $unit);

        if ($refusal !== null) {
            throw ValidationException::withMessages(['discountPrice' => $refusal]);
        }

        if ($reason === '' || mb_strlen($reason) > ReviewCheckout::MAX_REASON_LENGTH) {
            throw ValidationException::withMessages(['discountReason' => __('pos.errors.adjustment_reason_required', ['max' => ReviewCheckout::MAX_REASON_LENGTH])]);
        }

        $this->setLineDiscount($key, Money::input($priceMinor), $reason);
        $this->notify('success', __('pos.cart.discount_applied', ['name' => $this->cart[$key]['name'], 'price' => LocalizedMoney::format($priceMinor)]));
    }

    /**
     * Remove a line's discount. Needs no discount permission, so a cashier whose limit was
     * lowered can still bring the cart back within it.
     */
    public function removeDiscount(string $key): void
    {
        $this->authorizeCounter();

        if (! isset($this->cart[$key]) || ! $this->ensureCartEditable()) {
            return;
        }

        $this->setLineDiscount($key, '', '');
    }

    public function openCustomer(): void
    {
        $this->authorizeCounter();

        if (! $this->ensureCartEditable()) {
            return;
        }

        $this->resetValidation(['customerSearch', 'customer_name', 'customer_phone']);
        $this->reset('customerSearch', 'customerResults', 'customerSearchedFor', 'newCustomerName', 'newCustomerPhone');
        $this->dispatch('open-dialog', id: self::CUSTOMER_DIALOG);
    }

    /**
     * A complete phone number finds its customer exactly; text finds customers by name. Both
     * stay within this store. The new-customer form is prefilled from the term.
     */
    public function searchCustomers(FindCounterCustomers $find): void
    {
        $store = $this->authorizeCounter();
        $term = mb_substr(Str::squish($this->customerSearch), 0, 80);

        $this->customerResults = $find(auth()->user(), $store, $term)
            ->map(fn (Customer $customer): array => ['id' => $customer->id, 'name' => $customer->name, 'phone' => $customer->maskedPhone()])
            ->values()
            ->all();
        $this->customerSearchedFor = $term;

        if ($this->customerResults === [] && $term !== '') {
            if (preg_match('/\p{L}/u', $term) === 1) {
                $this->newCustomerName = $term;
            } else {
                $this->newCustomerPhone = $term;
            }
        }
    }

    public function selectCustomer(int $customerId): void
    {
        $store = $this->authorizeCounter();
        $customer = $store->customers()->find($customerId);

        if ($customer === null) {
            $this->notify('error', __('pos.customer.errors.unavailable'));

            return;
        }

        $this->chooseCustomer($customer);
    }

    public function createCustomer(SaveCounterCustomer $save): void
    {
        $store = $this->authorizeCounter();

        if (! $this->ensureCartEditable()) {
            return;
        }

        $this->resetValidation(['customer_name', 'customer_phone']);
        $customer = $save(auth()->user(), $store, $this->newCustomerName, $this->newCustomerPhone);

        $this->chooseCustomer($customer, $customer->wasRecentlyCreated ? 'pos.customer.created' : 'pos.customer.already_exists');
    }

    public function clearCustomer(): void
    {
        $this->authorizeCounter();

        if ($this->customerId === null || ! $this->ensureCartEditable()) {
            return;
        }

        $this->customerId = null;
        $this->creditAmount = '';
        $this->cartChanged();
        $this->notify('info', __('pos.customer.cleared'));
    }

    /**
     * The credit is part of what was reviewed: editing it sends the cashier back to review, and a
     * frozen checkout keeps the credit it was charged with.
     */
    public function updatedCreditAmount(): void
    {
        $this->authorizeCounter();
        $attempt = $this->activeAttempt();

        if ($attempt?->isOpen() && $attempt->isFrozen()) {
            $this->creditAmount = self::creditInput($attempt);
            $this->notify('warning', __('pos.errors.attempt_frozen'));

            return;
        }

        $this->cartChanged();
    }

    public function review(ReviewCheckout $review): void
    {
        $store = $this->authorizeCounter();
        $credit = self::canonicalAmount($this->creditAmount, 'credit_amount');

        try {
            $attempt = $review(auth()->user(), $store, $this->cartInput(), $this->attemptKey, $this->attemptKey === null ? null : $this->attemptRevision, $this->customerId, $credit, $this->reservationId);
        } catch (ModelNotFoundException) {
            $this->forgetAttempt();
            $this->notify('error', __('pos.errors.attempt_missing'));

            return;
        }

        $reviewed = self::cartFromAttempt($attempt);
        $this->cart = array_replace(array_intersect_key($this->cart, $reviewed), $reviewed);
        $this->creditAmount = self::creditInput($attempt);
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

        $this->assertSelectionReviewed();
        $amount = self::canonicalAmount($this->cardAmount, 'card_amount');

        try {
            $result = $start(auth()->user(), $store, $this->attemptKey, $this->attemptRevision, $this->startRequestKey, (int) $this->cardTerminalId, $amount);
        } catch (ValidationException $exception) {
            $this->syncActiveAttempt();

            throw $exception;
        }

        $this->resetValidation();
        $this->startRequestKey = (string) Str::uuid();
        $this->cart = self::cartFromAttempt($result->attempt);
        $this->customerId = $result->attempt->customer_id;
        $this->creditAmount = self::creditInput($result->attempt);
        $this->displayedPaymentKey = $result->paymentKey();

        if ($result->isNewStart) {
            $this->chargeInstructedFor = $result->paymentKey();
            $this->notify('info', __('pos.payment.terminal_started', ['amount' => LocalizedMoney::format((int) $result->attempt->terminalPaymentAmountMinor())]));

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

    /**
     * Bind the cash due to the open cashbox session before the cashier is told to take it.
     */
    public function startCashIntake(StartSaleCashIntake $start): void
    {
        $store = $this->authorizeCounter();

        if ($this->attemptKey === null || $this->attemptRevision === null) {
            return;
        }

        $this->assertSelectionReviewed();

        try {
            $intake = $start(auth()->user(), $store, $this->attemptKey, $this->attemptRevision, $this->cashRequestKey);
        } catch (ValidationException $exception) {
            $this->syncActiveAttempt();

            throw $exception;
        }

        $this->resetValidation();
        $this->cashRequestKey = (string) Str::uuid();
        $this->syncActiveAttempt();

        if ($intake->wasRecentlyCreated) {
            $this->cashInstructedFor = $intake->id;
            $this->notify('info', __('pos.cashbox.intake_title', ['amount' => LocalizedMoney::format($intake->amountMinor())]));
        }
    }

    public function finalize(FinalizeCheckout $finalize): void
    {
        $store = $this->authorizeCounter();

        if ($this->attemptKey === null || $this->attemptRevision === null) {
            return;
        }

        $this->assertSelectionReviewed();
        $cashTendered = self::canonicalAmount($this->cashTendered, 'cash_tendered');

        try {
            $sale = $finalize(auth()->user(), $store, $this->attemptKey, $this->attemptRevision, $cashTendered, $this->displayedCashIntakeId);
        } catch (ValidationException $exception) {
            if (array_key_exists('cashbox', $exception->errors())) {
                $this->syncActiveAttempt();
            }

            throw $exception;
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
                $attempt->hasPendingCashIntake() => __('pos.cashbox.recovery_intake', ['amount' => LocalizedMoney::format($attempt->pendingCashIntake->amountMinor())]),
                $attempt->cardApproval !== null => __('pos.recovery.open_with_card', ['amount' => LocalizedMoney::format($attempt->cardApproval->amountMinor()), 'reference' => $attempt->cardApproval->reference]),
                $attempt->hasStartedTerminalPayment() => __('pos.recovery.open_terminal_pending', [
                    'amount' => LocalizedMoney::format((int) $attempt->terminalPaymentAmountMinor()),
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
            $this->cashInstructedFor = null;
            $this->displayedCashIntakeId = $attempt->pendingCashIntake()->value('id');
            $this->step = $this->unreviewedChange($attempt) === null ? 'payment' : 'cart';
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
        $this->customerId = null;
        $this->reservationId = null;
        $this->creditAmount = '';
        $this->lastSaleId = null;
        $this->forgetAttempt();
        $this->resetPaymentFields();
    }

    public function unlinkReservation(): void
    {
        $this->authorizeCounter();

        if ($this->ensureCartEditable()) {
            $this->reservationId = null;
            $this->cartChanged();
        }
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

        if ($active !== null && $active->id !== $attempt->id && $active->isOpen() && ($active->hasStartedTerminalPayment() || $active->cardApproval()->exists() || $active->hasPendingCashIntake())) {
            $this->notify('error', __('pos.errors.new_sale_blocked'));

            return;
        }

        $this->cart = self::cartFromAttempt($attempt);
        $this->customerId = $attempt->customer_id;
        $this->reservationId = $attempt->network_reservation_id;
        $this->creditAmount = self::creditInput($attempt);
        $this->attemptKey = $attempt->key;
        $this->attemptRevision = $attempt->revision;
        $this->displayedPaymentKey = $attempt->terminal_payment_key;
        $this->chargeInstructedFor = null;
        $this->cashInstructedFor = null;
        $this->displayedCashIntakeId = $attempt->pendingCashIntake()->value('id');
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
            'amount' => LocalizedMoney::format((int) $attempt->terminalPaymentAmountMinor()),
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
        $attempt = $this->activeAttempt()?->load(['cardApproval.terminal', 'terminalPaymentTerminal', 'customer', 'pendingCashIntake']);
        $cartLines = $attempt?->isOpen() && $attempt->isFrozen() ? self::reviewedCartLines($attempt) : $this->cartLinesWithCurrentPrices();
        $terminalPending = $attempt !== null && $attempt->isOpen() && $attempt->hasPendingTerminalPayment();
        $cashIntake = $attempt?->isOpen() ? $attempt->pendingCashIntake : null;
        $cashSessionOpen = CashboxSession::openFor($store) !== null;

        return view('livewire.pos.pos-counter', [
            'store' => $store,
            'attempt' => $attempt,
            'terminalPending' => $terminalPending,
            'cashIntake' => $cashIntake,
            'takeCashNow' => $cashIntake !== null && $this->cashInstructedFor === $cashIntake->id,
            'cashTracked' => $cashSessionOpen || CashboxSession::trackingStarted($store),
            'cashSessionOpen' => $cashSessionOpen,
            'chargeNow' => $terminalPending
                && $this->chargeInstructedFor !== null
                && $this->chargeInstructedFor === $attempt->terminal_payment_key
                && $this->displayedPaymentKey === $attempt->terminal_payment_key,
            'cartLines' => $cartLines,
            'selectedCustomer' => $this->customerId === null ? null : $store->customers()->find($this->customerId),
            'cartTotal' => array_sum(array_column($cartLines, 'line_total')),
            'cartEditable' => ! ($attempt?->isOpen() && $attempt->isFrozen()),
            'payment' => $attempt?->isOpen() ? $this->paymentPreview($attempt) : null,
            'terminals' => $store->cardTerminals()->active()->orderBy('label')->get(),
            'recoverable' => $this->recoverableAttempts($store),
            'lastSale' => $this->lastSaleId === null ? null : Sale::query()->visibleTo($user, $store)->with('debt')->find($this->lastSaleId),
            'canDiscount' => DiscountAuthority::for($user, $store)->canDiscount(),
            'canSellOnCredit' => $user->can('sellOnCredit', $store),
            'canResolve' => $user->can('resolveCheckoutAttempts', $store),
            ...$this->localeViewData(),
        ])->layout('layouts.pos', ['posStore' => $store])->title(__('pos.counter.title', ['store' => $store->code]));
    }

    public static function formatMoney(?int $minor): string
    {
        return $minor === null ? '—' : LocalizedMoney::format($minor);
    }

    /**
     * The POS entry boundary: localized operator text to the canonical decimal the actions accept.
     *
     * @throws ValidationException
     */
    private static function canonicalAmount(string $input, string $field): string
    {
        try {
            return LocalizedMoney::toCanonical($input);
        } catch (InvalidAmountException) {
            throw ValidationException::withMessages([$field => __('pos.errors.amount_format', ['example' => LocalizedMoney::EXAMPLE])]);
        }
    }

    private static function amountError(InvalidAmountException $exception, string $rangeMessage): string
    {
        return in_array($exception->reason, [InvalidAmountException::FORMAT, InvalidAmountException::PRECISION], true)
            ? __('pos.errors.amount_format', ['example' => LocalizedMoney::EXAMPLE])
            : $rangeMessage;
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
        $this->customerId = null;
        $this->reservationId = null;
        $this->creditAmount = '';
        $this->attemptKey = null;
        $this->attemptRevision = null;
        $this->displayedPaymentKey = null;
        $this->chargeInstructedFor = null;
        $this->cashInstructedFor = null;
        $this->displayedCashIntakeId = null;
        $this->lastSaleId = $sale->id;
        $this->step = 'done';
        $this->resetPaymentFields();
        $this->resetValidation();
        $this->dispatch('pos-sale-completed', url: route('pos.sales.show', [$this->storeId, $sale->id]));
    }

    /**
     * Choosing a customer changes what was reviewed, so the cart must be reviewed again.
     */
    private function chooseCustomer(Customer $customer, string $message = 'pos.customer.selected'): void
    {
        if (! $this->ensureCartEditable()) {
            return;
        }

        if ($this->customerId !== $customer->id) {
            $this->customerId = $customer->id;
            $this->cartChanged();
        }

        $this->dispatch('close-dialog', id: self::CUSTOMER_DIALOG);
        $this->notify('success', __($message, ['name' => $customer->name]));
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

    /**
     * Start a sale under a Store Network reservation held for this branch: link it and add its
     * device to the cart. Review and payment check the reservation again on the server.
     */
    private function linkReservation(Store $store, int $reservationId): void
    {
        if (! $this->ensureCartEditable()) {
            return;
        }

        $reservation = NetworkReservation::query()->find($reservationId);

        if ($reservation === null || ! $reservation->isActive() || $reservation->isInTransit()
            || ! $store->units()->whereKey($reservation->inventory_unit_id)->exists()) {
            $this->notify('error', __('network.reservation.errors.link_invalid'));

            return;
        }

        $this->reservationId = $reservation->id;
        $target = app(ResolveCounterCode::class)->target(auth()->user(), $store, $reservation->product_definition_id, $reservation->inventory_unit_id);

        if ($target !== null && ! isset($this->cart['u'.$reservation->inventory_unit_id])) {
            $this->addTarget($target);
        }

        $this->cartChanged();
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
        $this->cashInstructedFor = null;
        $this->displayedCashIntakeId = null;
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
        $this->displayedPaymentKey = $attempt->terminal_payment_key;

        if ($this->chargeInstructedFor !== $attempt->terminal_payment_key) {
            $this->chargeInstructedFor = null;
        }

        $this->displayedCashIntakeId = $attempt->pendingCashIntake()->value('id');

        if ($this->cashInstructedFor !== $this->displayedCashIntakeId) {
            $this->cashInstructedFor = null;
        }

        if ($attempt->isFrozen() || $this->workingCartMissing) {
            $this->reservationId = $attempt->network_reservation_id;
            $this->cart = self::cartFromAttempt($attempt);
            $this->customerId = $attempt->customer_id;
            $this->creditAmount = self::creditInput($attempt);
            $this->workingCartMissing = false;
        }

        $this->step = $this->unreviewedChange($attempt) === null ? 'payment' : 'cart';
    }

    /**
     * What changed in the working cart, customer, or credit since this open attempt was reviewed:
     * the error field to report it on, or null. A frozen attempt is charged as reviewed, so only an
     * editable one can be out of date.
     */
    private function unreviewedChange(CheckoutAttempt $attempt): ?string
    {
        if (! $attempt->isOpen() || $attempt->isFrozen()) {
            return null;
        }

        if (self::reviewedFields($this->cart) !== self::reviewedFields(self::cartFromAttempt($attempt))) {
            return 'cart';
        }

        if ($this->customerId !== $attempt->customer_id) {
            return 'customer';
        }

        return $this->workingCreditMinor() !== $attempt->creditMinor() ? 'credit_amount' : null;
    }

    /**
     * The working credit in minor units: 0 when blank, null when it is not a valid amount.
     */
    private function workingCreditMinor(): ?int
    {
        try {
            return Str::trim($this->creditAmount) === '' ? 0 : LocalizedMoney::parse($this->creditAmount, Money::MAX_TOTAL_AMOUNT, allowZero: true);
        } catch (InvalidAmountException) {
            return null;
        }
    }

    private static function creditInput(CheckoutAttempt $attempt): string
    {
        return $attempt->creditMinor() > 0 ? LocalizedMoney::input($attempt->creditMinor()) : '';
    }

    /**
     * The cart fields review prices and checks, keyed by line, for comparison.
     *
     * @param  array<string, array<string, mixed>>  $cart
     * @return array<string, array{0: int, 1: ?int, 2: int, 3: string, 4: string}>
     */
    private static function reviewedFields(array $cart): array
    {
        $fields = array_map(fn (array $line): array => [
            (int) $line['definition_id'],
            $line['unit_id'] === null ? null : (int) $line['unit_id'],
            (int) $line['quantity'],
            (string) $line['adjusted_price'],
            (string) $line['adjustment_reason'],
        ], $cart);
        ksort($fields, SORT_STRING);

        return $fields;
    }

    /**
     * Payment is taken only for the cart and customer that were reviewed. A frozen attempt keeps
     * its reviewed cart and customer; a completed one is left to finalize, which replays its sale.
     *
     * @throws ValidationException
     */
    private function assertSelectionReviewed(): void
    {
        $attempt = $this->activeAttempt();

        if ($attempt === null || ! $attempt->isOpen()) {
            return;
        }

        if ($attempt->isFrozen()) {
            $this->cart = self::cartFromAttempt($attempt);
            $this->customerId = $attempt->customer_id;
            $this->creditAmount = self::creditInput($attempt);

            return;
        }

        $changed = $this->unreviewedChange($attempt);

        if ($changed !== null) {
            $this->step = 'cart';

            throw ValidationException::withMessages([$changed => match ($changed) {
                'cart' => __('pos.errors.cart_review_again'),
                'customer' => __('pos.customer.errors.review_again'),
                default => __('pos.credit.errors.review_again'),
            }]);
        }
    }

    private function restoreWorkingState(): void
    {
        $state = session($this->sessionKey());

        if (! is_array($state)) {
            return;
        }

        $this->workingCartMissing = ! is_array($state['cart'] ?? null);
        $this->cart = $this->workingCartMissing ? [] : $state['cart'];
        $this->customerId = is_int($state['customer_id'] ?? null) ? $state['customer_id'] : null;
        $this->reservationId = is_int($state['reservation_id'] ?? null) ? $state['reservation_id'] : null;
        $this->creditAmount = is_string($state['credit_amount'] ?? null) ? $state['credit_amount'] : '';
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
     * The reviewed lines of a frozen attempt, exactly as the cashier is charging them; today's
     * prices and limits never reprice a cart after a terminal payment started.
     *
     * @return list<array<string, mixed>>
     */
    private static function reviewedCartLines(CheckoutAttempt $attempt): array
    {
        $cart = self::cartFromAttempt($attempt);

        return array_map(fn (array $line): array => [
            ...$cart[$line['key']],
            'key' => (string) $line['key'],
            'tracking_mode' => TrackingMode::from($line['tracking_mode']),
            'list_price' => (int) $line['list_unit_price'],
            'unit_price' => (int) $line['unit_price'],
            'line_total' => (int) $line['line_total'],
        ], $attempt->lines);
    }

    /**
     * @return array{0: ?int, 1: ?InventoryUnit} The line's current list price and its device, if any.
     */
    private function currentListPrice(Store $store, array $line): array
    {
        $definition = ProductDefinition::query()->find($line['definition_id']);
        $unit = $line['unit_id'] === null ? null : $store->units()->whereKey($line['unit_id'])->first();

        return [$definition === null ? null : SellingPrice::listPriceMinor($definition, $unit), $unit];
    }

    private function discountAuthority(Store $store): DiscountAuthority
    {
        $authority = DiscountAuthority::for(auth()->user(), $store);

        if (! $authority->canDiscount()) {
            throw new AuthorizationException(__('pos.errors.discount_not_allowed'));
        }

        return $authority;
    }

    private function setLineDiscount(string $key, string $price, string $reason): void
    {
        $this->cart[$key]['adjusted_price'] = $price;
        $this->cart[$key]['adjustment_reason'] = $reason;
        $this->cartChanged();
        $this->dispatch('close-dialog', id: self::DISCOUNT_DIALOG);
    }

    /**
     * @return array{total: int, credit: int, payable: int, card: int, cash_due: int, change: ?int}
     */
    private function paymentPreview(CheckoutAttempt $attempt): array
    {
        $total = $attempt->totalMinor();
        $credit = $attempt->creditMinor();
        $payable = $attempt->payableMinor();
        $card = $attempt->hasPendingTerminalPayment() ? (int) $attempt->terminalPaymentAmountMinor() : $attempt->approvedCardMinor();
        $cashDue = max(0, $payable - $card);
        $change = null;

        if ($cashDue > 0 && Str::trim($this->cashTendered) !== '') {
            try {
                $tendered = LocalizedMoney::parse($this->cashTendered, Money::MAX_TOTAL_AMOUNT, allowZero: true);
                $change = $tendered >= $cashDue ? $tendered - $cashDue : null;
            } catch (InvalidAmountException) {
                $change = null;
            }
        }

        return ['total' => $total, 'credit' => $credit, 'payable' => $payable, 'card' => $card, 'cash_due' => $cashDue, 'change' => $change];
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
            ->with(['cardApproval.terminal', 'terminalPaymentTerminal', 'cashier:id,name', 'pendingCashIntake'])
            ->latest('updated_at')
            ->limit(20)
            ->get();
    }

    private function notify(string $type, string $message): void
    {
        $this->dispatch('notify', ['type' => $type, 'message' => $message]);
    }
}
