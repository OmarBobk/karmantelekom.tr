<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Pos\ResolveTerminalPayment;
use App\Enums\CheckoutAttemptStatus;
use App\Enums\InspectionOutcome;
use App\Enums\TrackingMode;
use App\Enums\UnitCondition;
use App\Enums\UnitStatus;
use App\Livewire\Pos\PosCounter;
use App\Livewire\Pos\PosLocale;
use App\Livewire\Pos\SalesHistory;
use App\Models\CheckoutAttempt;
use App\Models\ProductDefinition;
use App\Models\Sale;
use App\Models\User;
use App\Services\Purchasing\LocalizedMoney;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\InteractsWithPos;
use Tests\TestCase;

class PosCounterUiTest extends TestCase
{
    use InteractsWithPos;
    use RefreshDatabase;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCounter();
        $this->useEnglishLocale();
        $this->cashier = $this->createCashier();
    }

    #[Test]
    public function the_acceptance_walkthrough_runs_through_the_counter(): void
    {
        $terminal = $this->createTerminal();

        $counter = $this->counter()
            ->call('scan', $this->cable->barcode)
            ->call('scan', ' '.$this->cable->barcode.' ')
            ->call('scan', $this->phoneSecondImei)
            ->assertSet('cart.d'.$this->cable->id.'.quantity', 2)
            ->assertSet('cart.u'.$this->phone->id.'.identifier', $this->phoneImei)
            ->assertSee('12.599,80')
            ->call('review')
            ->assertHasNoErrors()
            ->assertSet('step', 'payment')
            ->set('cardTerminalId', $terminal->id)
            ->set('cardAmount', '10.000')
            ->call('startTerminalPayment')
            ->assertHasNoErrors()
            ->assertSee(__('pos.payment.terminal_pending_title'))
            ->assertDontSee(__('pos.payment.complete'))
            ->set('cardReference', 'A12345')
            ->call('recordCardApproval')
            ->assertHasNoErrors()
            ->set('cashTendered', '3.000')
            ->call('finalize')
            ->assertHasNoErrors()
            ->assertSet('step', 'done')
            ->assertSet('cart', [])
            ->assertSee('KT01-000001')
            ->assertSee('400,20')
            ->assertDispatched('pos-sale-completed');

        $sale = Sale::query()->sole();
        $this->assertSame($sale->id, $counter->get('lastSaleId'));
        $this->assertSame(8, $this->cableStock());
        $this->assertSame(UnitStatus::SOLD, $this->phone->fresh()->status);
        $this->assertSame(['12599.80', '3000.00', '400.20'], [$sale->total, $sale->cash_tendered, $sale->change_given]);
        $this->assertEqualsCanonicalizing([['card', '10000.00'], ['cash', '2599.80']], $sale->payments->map(fn ($payment): array => [$payment->method->value, $payment->amount])->all());

        $this->actingAs($this->cashier)->get(route('pos.sales.show', [$this->store, $sale]).'?print=1')
            ->assertOk()
            ->assertSee('KT01-000001')
            ->assertSee(['12.599,80 TRY', '10.000,00', '2.599,80', '3.000,00', '400,20'])
            ->assertSee('A12345')
            ->assertSee('window.print', false);

        Livewire::actingAs($this->cashier)->test(SalesHistory::class, ['store' => $this->store])
            ->set('search', $this->phoneSecondImei)
            ->call('applyFilters')
            ->assertSee('KT01-000001')
            ->set('search', 'NOPE-404')
            ->call('applyFilters')
            ->assertDontSee('KT01-000001');
    }

    #[Test]
    public function ambiguous_amount_text_is_rejected_with_an_example_and_nothing_is_charged_or_sold(): void
    {
        $terminal = $this->createTerminal();
        $formatError = __('pos.errors.amount_format', ['example' => '10.000,50']);

        $counter = $this->counter()
            ->call('scan', $this->cable->barcode)
            ->call('review')
            ->assertSee('49,90 TRY')
            ->set('cardTerminalId', $terminal->id)
            ->set('cardAmount', '1,234.56')
            ->call('startTerminalPayment')
            ->assertHasErrors('card_amount')
            ->assertSee($formatError)
            ->assertSet('cardAmount', '1,234.56')
            ->set('cardAmount', '')
            ->set('cashTendered', '49.90')
            ->call('finalize')
            ->assertHasErrors('cash_tendered')
            ->assertSee($formatError)
            ->assertSet('cashTendered', '49.90')
            ->set('cashTendered', '12 50')
            ->call('finalize')
            ->assertHasErrors('cash_tendered')
            ->assertSee($formatError)
            ->assertSet('cashTendered', '12 50')
            ->assertSet('step', 'payment');

        $this->assertNull(CheckoutAttempt::query()->sole()->terminal_payment_key);
        $this->assertSame(0, Sale::query()->count());

        $counter->set('cashTendered', '٥٠')
            ->call('finalize')
            ->assertHasNoErrors()
            ->assertSet('step', 'done');

        $this->assertSame(['50.00', '0.10'], [Sale::query()->sole()->cash_tendered, Sale::query()->sole()->change_given]);
    }

    #[Test]
    public function a_scan_queued_behind_a_name_search_adds_one_unit_and_replaces_the_search_feedback(): void
    {
        $noMatch = __('pos.scan.no_search_results', ['term' => $this->cable->barcode]);

        $this->counter()
            ->call('search', $this->cable->barcode)
            ->assertSee($noMatch)
            ->call('scan', $this->cable->barcode)
            ->assertSet('cart.d'.$this->cable->id.'.quantity', 1)
            ->assertSet('searchTerm', '')
            ->assertDontSee($noMatch)
            ->assertSeeHtml('x-show="pendingScans === 0"')
            ->assertNotDispatched('notify', fn (string $event, array $params): bool => ($params[0]['type'] ?? null) === 'error');
    }

    #[Test]
    public function an_unknown_barcode_scanned_after_a_name_search_still_reports_it_was_not_found(): void
    {
        $this->counter()
            ->call('search', '8690000099999')
            ->call('scan', '8690000099999')
            ->assertSet('cart', [])
            ->assertSet('searchTerm', '')
            ->assertDispatched('notify', fn (string $event, array $params): bool => ($params[0]['message'] ?? '') === __('pos.scan.not_found', ['code' => '8690000099999']));
    }

    #[Test]
    public function a_competing_cashier_cannot_add_or_sell_a_phone_that_was_already_sold(): void
    {
        $rival = $this->createCashier(name: 'Rival');
        $rivalCounter = Livewire::actingAs($rival)->test(PosCounter::class, ['store' => $this->store])
            ->call('scan', $this->phoneImei)
            ->call('review');

        $this->counter()->call('scan', $this->phoneImei)->call('review')->set('cashTendered', '12500')->call('finalize')->assertSet('step', 'done');

        $this->actingAs($rival);
        $rivalCounter->set('cashTendered', '12500')
            ->call('finalize')
            ->assertHasErrors('lines.u'.$this->phone->id.'.item')
            ->assertSet('step', 'payment')
            ->assertSet('cart.u'.$this->phone->id.'.identifier', $this->phoneImei);

        Livewire::actingAs($rival)->test(PosCounter::class, ['store' => $this->store])
            ->call('newSale')
            ->call('scan', $this->phoneImei)
            ->assertDispatched('notify', fn (string $event, array $params): bool => str_contains($params[0]['message'] ?? '', __('inventory.unit_statuses.sold')))
            ->assertSet('cart', []);

        $this->assertSame(1, Sale::query()->count());
    }

    #[Test]
    public function a_barcode_shared_by_several_serialized_units_asks_the_cashier_to_choose(): void
    {
        $headset = ProductDefinition::factory()->priced('1500.00')->create(['name' => 'Headset', 'tracking_mode' => TrackingMode::SERIALIZED, 'barcode' => '8690000000024']);
        $first = $this->registerDevice($this->owner, $this->store, $headset, null, 'HS-001', condition: UnitCondition::NEW);
        $second = $this->registerDevice($this->owner, $this->store, $headset, null, 'HS-002', condition: UnitCondition::NEW);

        $counter = $this->counter()
            ->call('scan', '8690000000024')
            ->assertDispatched('open-dialog', id: PosCounter::CHOOSER_DIALOG)
            ->assertSet('cart', [])
            ->assertSee('HS-001')
            ->assertSee('HS-002');

        $this->assertCount(2, $counter->get('choices'));

        $counter->call('choose', $headset->id, $first->id)
            ->assertSet('cart.u'.$first->id.'.identifier', 'HS-001')
            ->assertSet('choices', []);

        $this->counter()->call('scan', 'HS-002')->assertSet('cart.u'.$second->id.'.identifier', 'HS-002');
    }

    #[Test]
    public function the_cart_survives_a_failed_review_and_unknown_codes(): void
    {
        $counter = $this->counter()
            ->call('scan', $this->cable->barcode)
            ->call('scan', 'UNKNOWN-CODE')
            ->assertDispatched('notify', fn (string $event, array $params): bool => ($params[0]['type'] ?? null) === 'error')
            ->assertSet('cart.d'.$this->cable->id.'.quantity', 1);

        $this->cable->update(['selling_price' => null]);

        $counter->call('review')
            ->assertHasErrors('lines.d'.$this->cable->id.'.price')
            ->assertSet('step', 'cart')
            ->assertSet('cart.d'.$this->cable->id.'.quantity', 1);

        $this->assertSame(0, CheckoutAttempt::query()->count());
    }

    #[Test]
    public function quantity_cannot_exceed_stock_and_only_a_granted_cashier_or_the_owner_can_discount(): void
    {
        $key = 'd'.$this->cable->id;

        $this->counter()
            ->call('scan', $this->cable->barcode)
            ->call('setQuantity', $key, 11)
            ->assertSet("cart.{$key}.quantity", 1)
            ->call('setQuantity', $key, 10)
            ->assertSet("cart.{$key}.quantity", 10)
            ->assertDontSee(__('pos.cart.discount'))
            ->call('openDiscount', $key)
            ->assertForbidden();

        Livewire::actingAs($this->owner)->test(PosCounter::class, ['store' => $this->store])
            ->call('scan', $this->cable->barcode)
            ->call('openDiscount', $key)
            ->assertSet('discountListPrice', 4990)
            ->assertSet('discountLowestPrice', 1)
            ->set('discountPrice', '45')
            ->call('applyDiscount')
            ->assertHasErrors('discountReason')
            ->set('discountReason', 'Bundle')
            ->call('applyDiscount')
            ->assertHasNoErrors()
            ->call('review')
            ->assertSet('step', 'payment')
            ->assertSee('45,00');
    }

    #[Test]
    public function after_a_lost_response_the_reloaded_counter_shows_the_committed_sale(): void
    {
        $counter = $this->counter()->call('scan', $this->cable->barcode)->call('review');
        $attempt = CheckoutAttempt::query()->sole();

        // The server commits but the browser never receives the response or the session update.
        $this->finalize($this->cashier, $attempt, '50');

        $this->counter()
            ->assertSet('step', 'done')
            ->assertSet('attemptKey', null)
            ->assertSee('KT01-000001');

        $counter->set('cashTendered', '50')->call('finalize')->assertSet('step', 'done');
        $this->assertSame(1, Sale::query()->count());
        $this->assertSame(9, $this->cableStock());
    }

    #[Test]
    public function reloading_after_a_completed_sale_shows_it_until_a_new_sale_starts(): void
    {
        $this->counter()->call('scan', $this->cable->barcode)->call('review')->set('cashTendered', '50')->call('finalize');

        $this->counter()
            ->assertSet('step', 'done')
            ->assertSee('KT01-000001')
            ->call('newSale')
            ->assertSet('step', 'cart')
            ->assertSet('lastSaleId', null);

        $this->counter()->assertSet('step', 'cart');
    }

    #[Test]
    public function a_failed_sale_after_a_card_approval_explains_the_locked_cart_and_the_owner_sees_why(): void
    {
        $this->counter()
            ->call('scan', $this->phoneImei)
            ->call('review')
            ->set('cardTerminalId', $this->createTerminal()->id)
            ->set('cardAmount', '12500')
            ->call('startTerminalPayment')
            ->set('cardReference', 'APP-12')
            ->call('recordCardApproval');

        $this->inspectDevice($this->owner, $this->phone, InspectionOutcome::NEEDS_REPAIR);

        $this->counter()
            ->call('finalize')
            ->assertHasErrors('lines.u'.$this->phone->id.'.item')
            ->assertSet('step', 'payment')
            ->assertSee(__('pos.payment.failed_with_card', ['reference' => 'APP-12']))
            ->call('newSale')
            ->assertSet('step', 'payment');

        Livewire::actingAs($this->owner)->test(PosCounter::class, ['store' => $this->store])
            ->assertSee(__('pos.recovery.failures.unavailable'));
    }

    #[Test]
    public function an_uncertain_card_checkout_is_recovered_after_reload_and_never_cleared_by_new_sale(): void
    {
        $terminal = $this->createTerminal();

        $this->counter()
            ->call('scan', $this->phoneImei)
            ->call('review')
            ->set('cardTerminalId', $terminal->id)
            ->set('cardAmount', '12500')
            ->call('startTerminalPayment')
            ->set('cardReference', 'APP-9')
            ->call('recordCardApproval');

        $reloaded = $this->counter()
            ->assertSet('step', 'payment')
            ->assertSee('APP-9')
            ->assertSee(__('pos.payment.do_not_charge_again'))
            ->call('newSale')
            ->assertDispatched('notify', fn (string $event, array $params): bool => ($params[0]['message'] ?? '') === __('pos.errors.new_sale_blocked'))
            ->call('checkStatus')
            ->assertSet('step', 'payment');

        $this->assertNotNull($reloaded->get('attemptKey'));

        $reloaded->call('finalize')->assertSet('step', 'done');
        $this->assertSame(CheckoutAttemptStatus::COMPLETED, CheckoutAttempt::query()->sole()->status);
    }

    #[Test]
    public function an_unresolved_attempt_is_listed_after_a_new_login_and_can_be_resumed(): void
    {
        $terminal = $this->createTerminal();
        $this->counter()
            ->call('scan', $this->phoneImei)
            ->call('review')
            ->set('cardTerminalId', $terminal->id)
            ->set('cardAmount', '12500')
            ->call('startTerminalPayment')
            ->set('cardReference', 'APP-10')
            ->call('recordCardApproval');
        $attempt = CheckoutAttempt::query()->sole();

        session()->flush();

        $this->counter()
            ->assertSet('attemptKey', null)
            ->assertSee(__('pos.recovery.title'))
            ->assertSee('APP-10')
            ->call('resumeAttempt', $attempt->key)
            ->assertSet('attemptKey', $attempt->key)
            ->assertSet('step', 'payment')
            ->assertSet('cart.u'.$this->phone->id.'.identifier', $this->phoneImei)
            ->call('finalize')
            ->assertSet('step', 'done');

        $other = $this->createCashier(name: 'Other');
        $this->assertSame(1, Sale::query()->count());
        Livewire::actingAs($other)->test(PosCounter::class, ['store' => $this->store])->assertDontSee('APP-10');
    }

    #[Test]
    public function the_owner_sees_every_unresolved_attempt_and_resolves_a_voided_approval(): void
    {
        $terminal = $this->createTerminal();
        $this->counter()
            ->call('scan', $this->phoneImei)
            ->call('review')
            ->set('cardTerminalId', $terminal->id)
            ->set('cardAmount', '12500')
            ->call('startTerminalPayment')
            ->set('cardReference', 'APP-11')
            ->call('recordCardApproval');
        $attempt = CheckoutAttempt::query()->sole();

        Livewire::actingAs($this->owner)->test(PosCounter::class, ['store' => $this->store])
            ->assertSee('APP-11')
            ->call('resumeAttempt', $attempt->key)
            ->assertForbidden();

        Livewire::actingAs($this->owner)->test(PosCounter::class, ['store' => $this->store])
            ->call('openResolve', $attempt->key)
            ->assertDispatched('open-dialog', id: PosCounter::RESOLVE_DIALOG)
            ->set('voidReference', 'V-1')
            ->set('voidNote', 'Customer changed mind, voided on terminal')
            ->call('resolveVoid')
            ->assertHasNoErrors();

        $this->assertSame(CheckoutAttemptStatus::VOIDED, $attempt->fresh()->status);
        $this->counter()->assertSet('attemptKey', null)->assertDontSee('APP-11');

        $this->counter()->call('openResolve', $attempt->key)->assertForbidden();
    }

    #[Test]
    public function a_terminal_payment_whose_approval_never_reached_the_app_is_recovered_in_a_fresh_session_and_sold_once(): void
    {
        $terminal = $this->createTerminal();

        $this->counter()
            ->call('scan', $this->cable->barcode)
            ->call('scan', $this->phoneImei)
            ->call('review')
            ->set('cardTerminalId', $terminal->id)
            ->set('cardAmount', '12.549,90')
            ->call('startTerminalPayment')
            ->assertHasNoErrors()
            ->assertSee(__('pos.payment.terminal_pending_title'));

        // The card is charged on the terminal (approval TRM-88), but the request recording the
        // approval never reaches the app and the browser session is lost.
        $attempt = CheckoutAttempt::query()->sole();
        session()->flush();

        $pendingNotice = __('pos.recovery.open_terminal_pending', ['amount' => LocalizedMoney::format(1254990), 'terminal' => $terminal->displayName()]);

        $counter = $this->counter()
            ->assertSet('attemptKey', null)
            ->assertSee($pendingNotice)
            ->call('scan', $this->cable->barcode)
            ->call('review')
            ->assertHasErrors('cart')
            ->assertSet('step', 'cart')
            ->call('resumeAttempt', $attempt->key)
            ->assertSet('attemptKey', $attempt->key)
            ->assertSet('step', 'payment')
            ->assertSet('cart.u'.$this->phone->id.'.identifier', $this->phoneImei)
            ->assertSee(__('pos.payment.terminal_verify_title'))
            ->assertSee(__('pos.payment.do_not_charge_again'))
            ->assertDontSee(__('pos.payment.terminal_pending_title'))
            ->assertDontSee(__('pos.payment.complete'))
            ->call('cancelAttempt')
            ->assertHasErrors('attempt')
            ->call('newSale')
            ->assertDispatched('notify', fn (string $event, array $params): bool => ($params[0]['message'] ?? '') === __('pos.errors.new_sale_blocked'))
            ->assertSet('attemptKey', $attempt->key)
            ->call('removeLine', 'u'.$this->phone->id)
            ->assertSet('cart.u'.$this->phone->id.'.identifier', $this->phoneImei)
            ->set('cashTendered', '12.549,90')
            ->call('finalize')
            ->assertHasErrors('attempt')
            ->assertSet('step', 'payment');

        $this->assertSame(CheckoutAttemptStatus::OPEN, $attempt->fresh()->status);
        $this->assertSame(1, CheckoutAttempt::query()->count());
        $this->assertSame(0, Sale::query()->count());
        $this->assertSame(10, $this->cableStock());
        $this->assertSame(UnitStatus::AVAILABLE, $this->phone->fresh()->status);

        $counter->set('cashTendered', '')
            ->set('cardReference', 'TRM-88')
            ->call('recordCardApproval')
            ->assertHasNoErrors()
            ->assertSee(__('pos.payment.do_not_charge_again'))
            ->call('finalize')
            ->assertHasNoErrors()
            ->assertSet('step', 'done');

        $sale = Sale::query()->sole();
        $this->assertSame('12549.90', $sale->total);
        $this->assertSame([['card', '12549.90', 'TRM-88']], $sale->payments->map(fn ($payment): array => [$payment->method->value, $payment->amount, $payment->terminal_reference])->all());
        $this->assertSame($sale->id, $this->finalize($this->cashier, $attempt->fresh())->id);
        $this->assertSame(1, Sale::query()->count());
        $this->assertSame(9, $this->cableStock());
        $this->assertSame(UnitStatus::SOLD, $this->phone->fresh()->status);
    }

    #[Test]
    public function only_a_confirmed_new_start_says_charge_and_reload_status_or_another_tab_say_check_the_terminal(): void
    {
        $terminal = $this->createTerminal();
        $chargeNow = __('pos.payment.terminal_pending_detail', ['amount' => LocalizedMoney::format(4990), 'terminal' => $terminal->displayName()]);

        $this->counter()
            ->call('scan', $this->cable->barcode)
            ->call('review')
            ->set('cardTerminalId', $terminal->id)
            ->set('cardAmount', '49,90')
            ->call('startTerminalPayment')
            ->assertSee($chargeNow)
            ->assertSeeHtml('data-terminal-payment="charge"')
            ->call('checkStatus')
            ->assertDontSee($chargeNow)
            ->assertSeeHtml('data-terminal-payment="verify"');

        $this->counter()
            ->assertSet('step', 'payment')
            ->assertSee(__('pos.payment.terminal_verify_title'))
            ->assertSee(__('pos.payment.do_not_charge_again'))
            ->assertDontSee($chargeNow)
            ->set('cardTerminalId', $terminal->id)
            ->set('cardAmount', '49,90')
            ->call('startTerminalPayment')
            ->assertHasErrors('attempt')
            ->assertSeeHtml('data-terminal-payment="verify"')
            ->assertSeeHtml('<fieldset class="mt-2 min-w-0" disabled>')
            ->assertDontSee($chargeNow);

        $this->assertNotNull(CheckoutAttempt::query()->sole()->terminal_payment_key);
    }

    #[Test]
    public function an_approval_form_showing_a_cleared_start_cannot_answer_the_next_start(): void
    {
        $terminal = $this->createTerminal();
        $staleCounter = $this->counter()
            ->call('scan', $this->cable->barcode)
            ->call('review')
            ->set('cardTerminalId', $terminal->id)
            ->set('cardAmount', '49,90')
            ->call('startTerminalPayment');
        $attempt = CheckoutAttempt::query()->sole();
        $startA = $attempt->terminal_payment_key;

        app(ResolveTerminalPayment::class)($this->owner, $this->store, $attempt->key, $startA, 'no_charge', null, 'Terminal declined');

        $this->counter()
            ->set('cardTerminalId', $terminal->id)
            ->set('cardAmount', '49,90')
            ->call('startTerminalPayment')
            ->assertSeeHtml('data-terminal-payment="charge"');
        $startB = $attempt->fresh()->terminal_payment_key;
        $this->assertNotSame($startA, $startB);

        $staleCounter->assertSet('displayedPaymentKey', $startA)
            ->set('cardReference', 'FOR-A')
            ->call('recordCardApproval')
            ->assertHasErrors('attempt')
            ->assertSet('displayedPaymentKey', $startB)
            ->assertSeeHtml('data-terminal-payment="verify"');

        $this->assertSame($startB, $attempt->fresh()->terminal_payment_key);
        $this->assertTrue($attempt->fresh()->hasPendingTerminalPayment());

        $staleCounter->set('cardReference', 'FOR-B')->call('recordCardApproval')->assertHasNoErrors()
            ->call('finalize')->assertSet('step', 'done')
            ->call('newSale')->assertSeeHtml('<fieldset class="mt-2 min-w-0"')->assertDontSeeHtml('<fieldset class="mt-2 min-w-0" disabled>');
        $this->assertSame('FOR-B', Sale::query()->sole()->payments->sole()->terminal_reference);
    }

    #[Test]
    public function the_owner_resolves_an_unverified_terminal_payment_from_the_counter(): void
    {
        $terminal = $this->createTerminal();
        $this->counter()
            ->call('scan', $this->cable->barcode)
            ->call('review')
            ->set('cardTerminalId', $terminal->id)
            ->set('cardAmount', '49,90')
            ->call('startTerminalPayment');
        $attempt = CheckoutAttempt::query()->sole();

        $this->counter()->call('openResolveTerminal', $attempt->key)->assertForbidden();

        Livewire::actingAs($this->owner)->test(PosCounter::class, ['store' => $this->store])
            ->assertSee(__('pos.recovery.resolve_terminal'))
            ->call('openResolveTerminal', $attempt->key)
            ->assertDispatched('open-dialog', id: PosCounter::RESOLVE_TERMINAL_DIALOG)
            ->assertSet('resolvePaymentKey', $attempt->terminal_payment_key)
            ->assertSee(LocalizedMoney::format(4990).' TRY')
            ->set('terminalOutcome', 'no_charge')
            ->call('resolveTerminalPayment')
            ->assertHasErrors('void_note')
            ->set('voidNote', 'Terminal shows the card was declined')
            ->call('resolveTerminalPayment')
            ->assertHasNoErrors();

        $this->assertFalse($attempt->fresh()->hasStartedTerminalPayment());

        $this->counter()
            ->assertDontSee(__('pos.payment.terminal_pending_title'))
            ->set('cashTendered', '50')
            ->call('finalize')
            ->assertSet('step', 'done');
    }

    #[Test]
    public function the_counter_renders_in_arabic_rtl_and_english_ltr(): void
    {
        session([PosLocale::SESSION_KEY => 'ar']);
        $this->actingAs($this->cashier)->get(route('pos.counter', $this->store))
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('lang="ar"', false)
            ->assertSee(__('pos.scan.label', [], 'ar'));

        session([PosLocale::SESSION_KEY => 'en']);
        $this->actingAs($this->cashier)->get(route('pos.counter', $this->store))
            ->assertOk()
            ->assertSee('dir="ltr"', false)
            ->assertSee(__('pos.scan.label', [], 'en'));

        $this->actingAs($this->cashier)->post(route('pos.locale'), ['locale' => 'ar'])->assertRedirect();
        $this->assertSame('ar', session(PosLocale::SESSION_KEY));
    }

    #[Test]
    public function a_cart_edited_after_review_survives_a_reload_and_cannot_be_paid_until_reviewed_again(): void
    {
        $key = 'd'.$this->cable->id;
        $terminal = $this->createTerminal();

        $this->counter()
            ->call('scan', $this->cable->barcode)
            ->call('review')
            ->assertSet('step', 'payment')
            ->call('setQuantity', $key, 2)
            ->assertSet('step', 'cart');

        $attempt = CheckoutAttempt::query()->sole();
        $this->assertSame([1, '49.90'], [$attempt->revision, $attempt->total]);

        $counter = $this->counter()
            ->assertSet("cart.{$key}.quantity", 2)
            ->assertSet('step', 'cart')
            ->call('checkStatus')
            ->assertSet('step', 'cart')
            ->set('cashTendered', '100')
            ->call('finalize')
            ->assertHasErrors('cart')
            ->assertSet('step', 'cart')
            ->set('cardTerminalId', $terminal->id)
            ->set('cardAmount', '49,90')
            ->call('startTerminalPayment')
            ->assertHasErrors('cart');

        $this->assertSame(0, Sale::query()->count());
        $this->assertSame([1, null], [$attempt->fresh()->revision, $attempt->fresh()->frozen_at]);
        $this->assertSame(10, $this->cableStock());

        $counter->call('review')
            ->assertHasNoErrors()
            ->assertSet('step', 'payment')
            ->assertSet('attemptRevision', 2)
            ->set('cashTendered', '100')
            ->call('finalize')
            ->assertHasNoErrors()
            ->assertSet('step', 'done');

        $sale = Sale::query()->sole();
        $this->assertSame('99.80', $sale->total);
        $this->assertSame(2, $sale->lines()->sole()->quantity);
        $this->assertSame(8, $this->cableStock());
    }

    private function counter(): Testable
    {
        return Livewire::actingAs($this->cashier)->test(PosCounter::class, ['store' => $this->store]);
    }
}
