<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Cashbox\CloseCashboxSession;
use App\Actions\Cashbox\OpenCashboxSession;
use App\Actions\Cashbox\ReleaseCashIntake;
use App\Actions\Cashbox\StartCustomerCashIntake;
use App\Actions\Cashbox\StartSaleCashIntake;
use App\Actions\Customers\RecordCustomerPayment;
use App\Enums\CardApprovalStatus;
use App\Enums\CashboxSessionStatus;
use App\Enums\CashIntakeStatus;
use App\Enums\CashMovementType;
use App\Livewire\Pos\CashboxPage;
use App\Livewire\Pos\CustomerAccountPage;
use App\Livewire\Pos\PosCounter;
use App\Models\CashboxSession;
use App\Models\CashIntake;
use App\Models\CashMovement;
use App\Models\CheckoutAttempt;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\SalePayment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\InteractsWithPos;
use Tests\TestCase;

class CashboxSessionTest extends TestCase
{
    use InteractsWithPos;
    use RefreshDatabase;

    private User $cashier;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCounter();
        $this->useEnglishLocale();
        $this->cashier = $this->createCashier();
        $this->customer = Customer::factory()->atCounter($this->store)->create(['name' => 'Ahmet Yılmaz']);
    }

    #[Test]
    public function the_worked_example_closes_1460_counted_against_1470_expected_with_a_note(): void
    {
        $session = $this->openSession('1000');
        $this->assertNull($session->opening_expected);

        $this->phone->update(['selling_price' => '500.00']);
        $terminal = $this->createTerminal();
        $attempt = $this->review($this->cashier, $this->phoneLine());
        $this->approveCard($this->cashier, $attempt, $terminal, '200');
        $intake = $this->startSaleCash($this->cashier, $attempt);
        $this->assertSame('300.00', $intake->amount);
        $sale = $this->finalize($this->cashier, $attempt, '350', $intake->id);

        $this->creditSale($this->customer, 4, '199.60');
        $customerIntake = $this->startCustomerCash('170');
        $this->recordCustomerPayment(['amount' => '170', 'method' => 'cash', 'cash_intake_id' => $customerIntake->id]);

        $movements = CashMovement::query()->orderBy('id')->get();
        $this->assertSame(['300.00', '170.00'], $movements->pluck('amount')->all());
        $this->assertSame([CashMovementType::SALE_CASH, CashMovementType::CUSTOMER_PAYMENT_CASH], $movements->pluck('type')->all());
        $this->assertSame([$this->cashier->id, $this->owner->id], $movements->pluck('user_id')->all());
        $this->assertSame(SalePayment::query()->where('sale_id', $sale->id)->where('method', 'cash')->value('id'), $movements[0]->sale_payment_id);
        $this->assertSame(147_000, $session->expectedMinor());

        $this->assertValidationError(fn () => $this->closeSession($session, '1300', '1460', 'Short'), 'counted_closing');
        $this->assertValidationError(fn () => $this->closeSession($session, '1470', '1460'), 'closing_note');

        $closed = $this->closeSession($session, '1470', '1460', 'Ten short after the evening rush');

        $this->assertSame([CashboxSessionStatus::CLOSED, null, '1470.00', '1460.00', '-10.00', $this->owner->id], [
            $closed->status, $closed->open_store_id, $closed->expected_closing, $closed->counted_closing, $closed->closing_difference, $closed->closed_by,
        ]);

        $this->assertValidationError(fn () => $this->openSession('1450'), 'opening_note');
        $next = $this->openSession('1460');
        $this->assertSame(['1460.00', '0.00'], [$next->opening_expected, $next->opening_difference]);
    }

    #[Test]
    public function cash_needs_a_bound_intake_that_survives_reload_blocks_closing_and_only_the_owner_releases(): void
    {
        $session = $this->openSession('0');
        $attempt = $this->review($this->cashier, $this->cableLine(2));

        $this->assertValidationError(fn () => $this->finalize($this->cashier, $attempt, '100'), 'cashbox');

        $intake = $this->startSaleCash($this->cashier, $attempt);
        $this->assertSame(['99.80', CashIntakeStatus::PENDING, $session->id], [$intake->amount, $intake->status, $intake->cashbox_session_id]);
        $this->assertTrue($attempt->fresh()->isFrozen());
        $this->assertValidationError(fn () => $this->startTerminalPayment($this->cashier, $attempt, $this->createTerminal(), '10'), 'attempt');

        session()->put('pos.working.'.$this->cashier->id.'.'.$this->store->id, ['cart' => [], 'attempt_key' => $attempt->key]);
        Livewire::actingAs($this->cashier)->test(PosCounter::class, ['store' => $this->store])
            ->assertSet('step', 'payment')
            ->assertSeeHtml('data-cash-intake="verify"')
            ->call('cancelAttempt')
            ->assertHasErrors('attempt');

        $this->assertValidationError(fn () => $this->closeSession($session, '0', '0'), 'cashbox');
        $this->assertThrows(fn () => app(ReleaseCashIntake::class)($this->cashier, $this->store, $intake->id, 'Customer left'), AuthorizationException::class);
        $this->assertValidationError(fn () => app(ReleaseCashIntake::class)($this->owner, $this->store, $intake->id, ' '), 'release_note');

        $released = app(ReleaseCashIntake::class)($this->owner, $this->store, $intake->id, 'Customer left before paying');

        $this->assertSame([CashIntakeStatus::RELEASED, null, $this->owner->id], [$released->status, $released->pending_source, $released->released_by]);
        $this->assertFalse($attempt->fresh()->isFrozen());
        $this->assertDatabaseHas('activity_log', ['log_name' => 'cashbox', 'event' => 'cash_intake_released', 'subject_id' => $intake->id]);
        $this->assertSame(0, CashMovement::query()->count());
        $this->assertSame('0.00', $this->closeSession($session, '0', '0')->closing_difference);
    }

    #[Test]
    public function card_only_and_fully_credited_sales_need_no_open_session_but_cash_does(): void
    {
        $this->closeSession($this->openSession('0'), '0', '0');

        $terminal = $this->createTerminal();
        $cardOnly = $this->review($this->cashier, $this->phoneLine());
        $this->approveCard($this->cashier, $cardOnly, $terminal, '12500');
        $this->finalize($this->cashier, $cardOnly);
        $this->creditSale($this->customer, 2, '99.80');

        $cash = $this->review($this->cashier, $this->cableLine(1));
        $this->assertValidationError(fn () => $this->startSaleCash($this->cashier, $cash), 'cashbox');
        $this->assertValidationError(fn () => $this->finalize($this->cashier, $cash, '49.90'), 'cashbox');
        $this->assertSame(0, CashMovement::query()->count());
    }

    #[Test]
    public function a_mixed_sale_keeps_its_card_approval_and_completes_after_cash_is_bound_to_a_new_session(): void
    {
        $first = $this->openSession('0');
        $terminal = $this->createTerminal();
        $attempt = $this->review($this->cashier, $this->phoneLine());
        $approval = $this->approveCard($this->cashier, $attempt, $terminal, '5000');
        $this->closeSession($first, '0', '0');

        $this->assertValidationError(fn () => $this->startSaleCash($this->cashier, $attempt), 'cashbox');
        $this->assertSame(CardApprovalStatus::APPROVED, $approval->fresh()->status);

        $second = $this->openSession('0');
        $intake = $this->startSaleCash($this->cashier, $attempt);
        $this->assertSame(['7500.00', $second->id], [$intake->amount, $intake->cashbox_session_id]);

        $this->assertValidationError(fn () => $this->finalize($this->cashier, $attempt, '7000', $intake->id), 'cash_tendered');
        $this->assertTrue($intake->fresh()->isPending());

        $sale = $this->finalize($this->cashier, $attempt, '7500', $intake->id);

        $this->assertEqualsCanonicalizing(['card:5000.00', 'cash:7500.00'], $sale->payments->map(fn (SalePayment $payment): string => "{$payment->method->value}:{$payment->amount}")->all());
        $this->assertSame(CardApprovalStatus::CAPTURED, $approval->fresh()->status);
        $this->assertSame([$second->id], CashMovement::query()->pluck('cashbox_session_id')->all());
        $this->assertSame(CashIntakeStatus::COMPLETED, $intake->fresh()->status);
    }

    #[Test]
    public function lost_responses_replay_one_intake_and_post_one_movement_per_source(): void
    {
        $this->openSession('0');
        $attempt = $this->review($this->cashier, $this->cableLine(2));
        $requestKey = (string) Str::uuid();

        $intake = $this->startSaleCash($this->cashier, $attempt, $requestKey);
        $this->assertSame($intake->id, $this->startSaleCash($this->cashier, $attempt, $requestKey)->id);

        $sale = $this->finalize($this->cashier, $attempt, '100', $intake->id);
        $this->assertSame($sale->id, $this->finalize($this->cashier, $attempt, '100', $intake->id)->id);
        $this->assertSame($intake->id, $this->startSaleCash($this->cashier, $attempt, $requestKey)->id);

        $this->creditSale($this->customer, 4, '199.60');
        $page = Livewire::actingAs($this->owner)->test(CustomerAccountPage::class, ['store' => $this->store, 'customer' => $this->customer])
            ->set('amount', '170')
            ->set('method', 'cash')
            ->call('record')
            ->assertHasNoErrors()
            ->assertSee('Take 170,00 TRY in cash');
        $this->assertSame(0, CustomerPayment::query()->count());

        $paymentKey = $page->get('requestKey');
        $customerIntakeId = $page->get('displayedCashIntakeId');
        $page->call('confirmCash')->assertHasNoErrors()->assertSet('displayedCashIntakeId', null);
        $payment = CustomerPayment::query()->sole();
        $this->assertSame($payment->id, $this->recordCustomerPayment(['amount' => '170', 'method' => 'cash', 'cash_intake_id' => $customerIntakeId], $paymentKey)->id);
        $this->assertValidationError(fn () => $this->recordCustomerPayment(['amount' => '29.60', 'method' => 'cash']), 'cashbox');

        $this->assertSame(['99.80', '170.00'], CashMovement::query()->orderBy('id')->pluck('amount')->all());
        $this->assertSame(1, CashMovement::query()->where('customer_payment_id', $payment->id)->count());
    }

    #[Test]
    public function a_pending_debt_intake_reserves_its_amount_against_other_payments(): void
    {
        $this->openSession('0');
        $this->creditSale($this->customer, 4, '199.60');
        $intake = $this->startCustomerCash('150');

        $this->assertValidationError(fn () => $this->recordCustomerPayment(['amount' => '100', 'method' => 'card', 'reference' => 'A100']), 'amount');
        $this->assertValidationError(fn () => $this->startCustomerCash('10'), 'cashbox');
        $this->recordCustomerPayment(['amount' => '49.60', 'method' => 'card', 'reference' => 'A101']);
        $this->assertValidationError(fn () => $this->recordCustomerPayment(['amount' => '100', 'method' => 'cash', 'cash_intake_id' => $intake->id]), 'amount');

        $this->recordCustomerPayment(['amount' => '150', 'method' => 'cash', 'cash_intake_id' => $intake->id]);

        $this->assertSame(['150.00'], CashMovement::query()->pluck('amount')->all());
        $this->assertSame(0, CashIntake::query()->pending()->count());
    }

    #[Test]
    public function a_stale_close_form_never_closes_the_next_session_and_an_identical_retry_returns_the_original_close(): void
    {
        $first = $this->openSession('100');
        $retryPage = Livewire::actingAs($this->owner)->test(CashboxPage::class, ['store' => $this->store]);
        $stalePage = Livewire::actingAs($this->owner)->test(CashboxPage::class, ['store' => $this->store]);
        $closed = $this->closeSession($first, '100', '100');
        $second = $this->openSession('100');

        $retryPage->set('countedClosing', '100')->call('close')->assertHasNoErrors();
        $stalePage->set('countedClosing', '95')->set('closingNote', 'Five short')->call('close')
            ->assertHasErrors('cashbox')
            ->assertSet('sessionShown', $second->id);

        $this->assertTrue($second->fresh()->isOpen());
        $this->assertSame(['100.00', $closed->closed_at->toIso8601String()], [$first->fresh()->counted_closing, $first->fresh()->closed_at->toIso8601String()]);
        $this->assertSame($first->id, $this->closeSession($first, '100', '100')->id);
        $this->assertValidationError(fn () => $this->closeSession($first, '100', '95', 'Five short'), 'cashbox');
        $this->assertTrue($second->fresh()->isOpen());
    }

    #[Test]
    public function a_confirmation_names_the_displayed_intake_and_never_completes_its_replacement(): void
    {
        $this->openSession('0');
        $attempt = $this->review($this->cashier, $this->cableLine(1));
        $released = $this->startSaleCash($this->cashier, $attempt);
        session()->put('pos.working.'.$this->cashier->id.'.'.$this->store->id, ['cart' => [], 'attempt_key' => $attempt->key]);
        $counter = Livewire::actingAs($this->cashier)->test(PosCounter::class, ['store' => $this->store])
            ->assertSet('displayedCashIntakeId', $released->id);

        app(ReleaseCashIntake::class)($this->owner, $this->store, $released->id, 'Customer left before paying');
        $replacement = $this->startSaleCash($this->cashier, $attempt);

        $this->assertValidationError(fn () => $this->finalize($this->cashier, $attempt, '50', $released->id), 'cashbox');
        $this->assertValidationError(fn () => $this->finalize($this->cashier, $attempt, '50'), 'cashbox');
        $counter->set('cashTendered', '50')->call('finalize')
            ->assertHasErrors('cashbox')
            ->assertSet('displayedCashIntakeId', $replacement->id);
        $this->assertSame([0, true], [CashMovement::query()->count(), $replacement->fresh()->isPending()]);

        $this->creditSale($this->customer, 2, '99.80');
        $releasedDebt = $this->startCustomerCash('50');
        $page = Livewire::actingAs($this->owner)->test(CustomerAccountPage::class, ['store' => $this->store, 'customer' => $this->customer])
            ->assertSet('displayedCashIntakeId', $releasedDebt->id);
        app(ReleaseCashIntake::class)($this->owner, $this->store, $releasedDebt->id, 'Customer left before paying');
        $replacementDebt = $this->startCustomerCash('50');

        $this->assertValidationError(fn () => $this->recordCustomerPayment(['amount' => '50', 'method' => 'cash', 'cash_intake_id' => $releasedDebt->id]), 'cashbox');
        $page->call('confirmCash')
            ->assertHasErrors('cashbox')
            ->assertSet('displayedCashIntakeId', $replacementDebt->id);
        $this->assertSame([0, 0, true], [CustomerPayment::query()->count(), CashMovement::query()->count(), $replacementDebt->fresh()->isPending()]);
    }

    #[Test]
    public function a_retry_after_a_lost_confirmation_response_returns_the_original_record_without_posting_again(): void
    {
        $this->openSession('0');
        $attempt = $this->review($this->cashier, $this->cableLine(1));
        $intake = $this->startSaleCash($this->cashier, $attempt);
        session()->put('pos.working.'.$this->cashier->id.'.'.$this->store->id, ['cart' => [], 'attempt_key' => $attempt->key]);
        $counter = Livewire::actingAs($this->cashier)->test(PosCounter::class, ['store' => $this->store]);

        $sale = $this->finalize($this->cashier, $attempt, '50', $intake->id);
        $counter->set('cashTendered', '50')->call('finalize')->assertHasNoErrors()->assertSet('lastSaleId', $sale->id);

        $this->creditSale($this->customer, 2, '99.80');
        $debtIntake = $this->startCustomerCash('50');
        $key = (string) Str::uuid();
        $payment = $this->recordCustomerPayment(['amount' => '50', 'method' => 'cash', 'cash_intake_id' => $debtIntake->id], $key);
        $retry = $this->recordCustomerPayment(['amount' => '50', 'method' => 'cash', 'cash_intake_id' => $debtIntake->id], $key);

        $this->assertSame([$payment->id, false], [$retry->id, $retry->wasRecentlyCreated]);
        $this->assertSame(1, CashMovement::query()->where('sale_payment_id', SalePayment::query()->where('sale_id', $sale->id)->where('method', 'cash')->value('id'))->count());
        $this->assertSame(1, CashMovement::query()->where('customer_payment_id', $payment->id)->count());
        $this->assertSame(2, CashMovement::query()->count());
    }

    #[Test]
    public function only_the_owner_opens_or_closes_and_a_branch_has_one_open_session(): void
    {
        $this->assertThrows(fn () => app(OpenCashboxSession::class)($this->cashier, $this->store, '0', null), AuthorizationException::class);
        $this->actingAs($this->cashier)->get(route('pos.cashbox', $this->store))->assertForbidden();

        $session = $this->openSession('250');
        $this->assertSame($session->id, $this->openSession('250')->id);
        $this->assertValidationError(fn () => $this->openSession('300'), 'cashbox');
        $this->assertThrows(fn () => app(CloseCashboxSession::class)($this->cashier, $this->store, $session->id, '250', '250', null), AuthorizationException::class);

        $this->actingAs($this->owner)->get(route('pos.cashbox', $this->store))->assertOk()->assertSee('250,00');
    }

    private function openSession(string $counted, ?string $note = null): CashboxSession
    {
        return app(OpenCashboxSession::class)($this->owner, $this->store, $counted, $note);
    }

    private function closeSession(CashboxSession $session, string $expectedShown, string $counted, ?string $note = null): CashboxSession
    {
        return app(CloseCashboxSession::class)($this->owner, $this->store, $session->id, $expectedShown, $counted, $note);
    }

    private function startSaleCash(User $actor, CheckoutAttempt $attempt, ?string $requestKey = null): CashIntake
    {
        return app(StartSaleCashIntake::class)($actor, $this->store, $attempt->key, $attempt->fresh()->revision, $requestKey ?? (string) Str::uuid());
    }

    private function startCustomerCash(string $amount): CashIntake
    {
        return app(StartCustomerCashIntake::class)($this->owner, $this->store, $this->customer, $amount, (string) Str::uuid());
    }

    /**
     * @param  array<string, string|int|null>  $input
     */
    private function recordCustomerPayment(array $input, ?string $key = null): CustomerPayment
    {
        return app(RecordCustomerPayment::class)($this->owner, $this->store, $this->customer, $input, $key ?? (string) Str::uuid());
    }

    private function assertValidationError(callable $action, string $field): void
    {
        try {
            $action();
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors(), json_encode($exception->errors()));

            return;
        }

        $this->fail("Expected a validation error on {$field}.");
    }
}
