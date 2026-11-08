<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Cashbox\CloseCashboxSession;
use App\Actions\Cashbox\OpenCashboxSession;
use App\Actions\Expenses\ConfirmCashPayout;
use App\Actions\Expenses\ReleaseCashPayout;
use App\Actions\Expenses\SubmitExpense;
use App\Enums\CashMovementDirection;
use App\Enums\CashMovementType;
use App\Enums\CashPayoutStatus;
use App\Enums\ExpenseMethod;
use App\Exceptions\Inventory\IdempotencyConflictException;
use App\Livewire\Pos\ExpensesPage;
use App\Models\CashboxSession;
use App\Models\CashMovement;
use App\Models\CashPayout;
use App\Models\Expense;
use App\Models\ExpenseSubmission;
use App\Models\InventoryMovement;
use App\Models\OperationalSupplier;
use App\Models\Sale;
use App\Models\Store;
use App\Models\SupplierLedgerEntry;
use App\Models\User;
use App\Services\Idempotency;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\InteractsWithPos;
use Tests\TestCase;

class ExpenseTest extends TestCase
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
    public function the_worked_example_closes_800_expected_after_a_200_cash_and_a_50_card_expense(): void
    {
        $session = $this->openSession('1000');
        $page = Livewire::actingAs($this->owner)->test(ExpensesPage::class, ['store' => $this->store])
            ->assertSet('sessionShown', $session->id)
            ->set('method', 'cash')
            ->set('category', 'supplies')
            ->set('description', 'Cleaning supplies')
            ->set('amount', '200')
            ->call('record')
            ->assertHasNoErrors()
            ->assertSee('Hand out 200,00 TRY in cash');

        $payout = CashPayout::query()->sole();
        $this->assertSame([CashPayoutStatus::PENDING, $session->id, 0], [$payout->status, $payout->cashbox_session_id, Expense::query()->count()]);
        $this->assertSame(100_000, $session->expectedMinor());

        $page->set('method', 'card')
            ->set('category', 'utilities')
            ->set('description', 'Internet bill')
            ->set('amount', '50')
            ->set('reference', 'TT-2026-11')
            ->call('record')
            ->assertHasNoErrors()
            ->call('confirm', $payout->id, $session->id)
            ->assertHasNoErrors()
            ->assertSet('instructedPayoutId', null);

        $expenses = Expense::query()->orderBy('id')->get();
        $this->assertSame([[ExpenseMethod::CARD, '50.00', null], [ExpenseMethod::CASH, '200.00', $payout->id]], $expenses->map(fn (Expense $expense): array => [$expense->method, $expense->amount, $expense->cash_payout_id])->all());
        $movement = CashMovement::query()->sole();
        $this->assertSame([CashMovementType::EXPENSE_CASH, CashMovementDirection::OUT, '200.00', $expenses[1]->id, $this->owner->id], [$movement->type, $movement->direction, $movement->amount, $movement->expense_id, $movement->user_id]);
        $this->assertSame(80_000, $session->expectedMinor());

        $closed = app(CloseCashboxSession::class)($this->owner, $this->store, $session->id, '800', '800', null);
        $this->assertSame(['800.00', '800.00', '0.00'], [$closed->expected_closing, $closed->counted_closing, $closed->closing_difference]);
    }

    #[Test]
    public function payouts_reserve_available_cash_survive_reload_block_closing_and_release_with_an_audited_resolution(): void
    {
        $session = $this->openSession('100');
        $first = $this->startPayout($session, '80');

        $this->assertValidationError(fn () => $this->startPayout($session, '30'), 'amount');
        $this->assertValidationError(fn () => app(CloseCashboxSession::class)($this->owner, $this->store, $session->id, '100', '100', null), 'cashbox');

        Livewire::actingAs($this->owner)->test(ExpensesPage::class, ['store' => $this->store])
            ->assertSee('Cash waiting to be handed out')
            ->assertSee('Cash handed out: 80,00 TRY');

        $this->assertThrows(fn () => app(ReleaseCashPayout::class)($this->cashier, $this->store, $first->id, 'returned', 'Handed back'), AuthorizationException::class);
        $this->assertValidationError(fn () => app(ReleaseCashPayout::class)($this->owner, $this->store, $first->id, 'returned', ' '), 'release_note');
        $this->assertValidationError(fn () => app(ReleaseCashPayout::class)($this->owner, $this->store, $first->id, null, 'Handed back'), 'resolution');

        $released = app(ReleaseCashPayout::class)($this->owner, $this->store, $first->id, 'returned', 'Supplier was closed; cash handed back');

        $this->assertSame([CashPayoutStatus::RELEASED, 'returned', $this->owner->id], [$released->status, $released->resolution->value, $released->released_by]);
        $this->assertDatabaseHas('activity_log', ['log_name' => 'cashbox', 'event' => 'cash_payout_released', 'subject_id' => $first->id]);
        $this->assertSame([0, 0], [Expense::query()->count(), CashMovement::query()->count()]);
        $this->assertValidationError(fn () => app(ConfirmCashPayout::class)($this->owner, $this->store, $first->id, $session->id), 'cashbox');

        $second = $this->startPayout($session, '30');
        $this->assertTrue($second->isPending());
        $this->assertNotSame($first->id, $second->id);
    }

    #[Test]
    public function a_retry_after_a_lost_confirmation_returns_the_original_expense_and_confirmation_names_the_displayed_payout_and_session(): void
    {
        $session = $this->openSession('500');
        $requestKey = (string) Str::uuid();
        $payout = $this->startPayout($session, '120', $requestKey);
        $this->assertSame($payout->id, $this->startPayout($session, '120', $requestKey)->id);
        $this->assertThrows(fn () => $this->startPayout($session, '121', $requestKey), IdempotencyConflictException::class);

        $this->assertValidationError(fn () => app(ConfirmCashPayout::class)($this->owner, $this->store, $payout->id, $session->id + 1), 'cashbox');

        $expense = app(ConfirmCashPayout::class)($this->owner, $this->store, $payout->id, $session->id);
        $page = Livewire::actingAs($this->owner)->test(ExpensesPage::class, ['store' => $this->store]);
        $page->call('confirm', $payout->id, $session->id)->assertHasNoErrors();

        $this->assertSame($expense->id, app(ConfirmCashPayout::class)($this->owner, $this->store, $payout->id, $session->id)->id);
        $this->assertSame($payout->id, $this->startPayout($session, '120', $requestKey)->id);
        $this->assertSame([1, 1, 38_000], [Expense::query()->count(), CashMovement::query()->count(), $session->expectedMinor()]);

        $otherStore = Store::factory()->create(['code' => 'KT02']);
        $this->assertThrows(fn () => app(ConfirmCashPayout::class)($this->owner, $otherStore, $payout->id, $session->id), ModelNotFoundException::class);
    }

    #[Test]
    public function a_cash_payout_needs_the_open_session_the_form_showed(): void
    {
        $this->assertValidationError(fn () => $this->submit($this->details('10'), null), 'cashbox');

        $first = $this->openSession('100');
        $page = Livewire::actingAs($this->owner)->test(ExpensesPage::class, ['store' => $this->store]);
        app(CloseCashboxSession::class)($this->owner, $this->store, $first->id, '100', '100', null);
        $second = $this->openSession('100');

        $page->set('method', 'cash')->set('category', 'other')->set('description', 'Tea')->set('amount', '10')
            ->call('record')
            ->assertHasErrors('cashbox')
            ->assertSet('sessionShown', $second->id);
        $this->assertSame(0, CashPayout::query()->count());

        $page->call('record')->assertHasNoErrors();
        $this->assertSame($second->id, CashPayout::query()->sole()->cashbox_session_id);
    }

    #[Test]
    public function card_and_transfer_expenses_need_no_session_replay_by_request_key_and_are_owner_only(): void
    {
        $key = (string) Str::uuid();
        $input = [...$this->details('1500.75', 'bank_transfer'), 'category' => 'rent', 'reference' => 'EFT-0042'];
        $submission = $this->submit($input, null, $key);
        $expense = $submission->expense;

        $this->assertTrue($submission->wasRecentlyCreated);
        $this->assertSame(['1500.75', ExpenseMethod::BANK_TRANSFER, 'EFT-0042'], [$expense->amount, $expense->method, $expense->reference]);
        $replay = $this->submit($input, null, $key);
        $this->assertSame([$submission->id, $expense->id, false], [$replay->id, $replay->expense_id, $replay->wasRecentlyCreated]);
        $this->assertThrows(fn () => $this->submit([...$input, 'amount' => '1500.76'], null, $key), IdempotencyConflictException::class);
        $tomorrow = Carbon::now(Sale::DISPLAY_TIMEZONE)->addDay()->toDateString();
        $this->assertValidationError(fn () => $this->submit([...$input, 'expense_date' => $tomorrow], null), 'expense_date');
        $this->assertSame([1, 0, false], [Expense::query()->count(), CashMovement::query()->count(), CashboxSession::trackingStarted($this->store)]);

        $this->assertThrows(fn () => app(SubmitExpense::class)($this->cashier, $this->store, null, $input, (string) Str::uuid()), AuthorizationException::class);
        $this->actingAs($this->cashier)->get(route('pos.expenses', $this->store))->assertForbidden();
        $this->actingAs($this->owner)->get(route('pos.expenses', $this->store))->assertOk()->assertSee('1.500,75')->assertSee('EFT-0042');
    }

    #[Test]
    public function only_a_newly_created_payout_says_hand_out_cash_and_a_resent_submission_shows_its_current_state(): void
    {
        $session = $this->openSession('1000');
        $resend = function (string $amount, string $method = 'cash', ?callable $afterFirst = null) use ($session) {
            $page = Livewire::actingAs($this->owner)->test(ExpensesPage::class, ['store' => $this->store]);
            $first = $this->submit($this->details($amount, $method), $session->id, $page->get('requestKey'));
            $afterFirst && $afterFirst($first);

            return $page->set('method', $method)->set('category', 'supplies')->set('description', 'Printer paper')->set('amount', $amount)
                ->call('record')
                ->assertHasNoErrors()
                ->assertSet('instructedPayoutId', null)
                ->assertSee('This expense was already submitted')
                ->assertDontSee("Hand out {$amount},00 TRY in cash");
        };

        $resend('120')->assertSeeHtml('data-replay-state="pending"');
        $resend('80', afterFirst: fn (ExpenseSubmission $first) => app(ConfirmCashPayout::class)($this->owner, $this->store, $first->cash_payout_id, $session->id))
            ->assertSeeHtml('data-replay-state="completed"');
        $resend('50', afterFirst: fn (ExpenseSubmission $first) => app(ReleaseCashPayout::class)($this->owner, $this->store, $first->cash_payout_id, 'not_handed_out', 'Shop was closed'))
            ->assertSeeHtml('data-replay-state="released"');
        $resend('40', 'card')->assertSeeHtml('data-replay-state="recorded"');

        $this->assertSame([4, 3, 2], [ExpenseSubmission::query()->count(), CashPayout::query()->count(), Expense::query()->count()]);

        Livewire::actingAs($this->owner)->test(ExpensesPage::class, ['store' => $this->store])
            ->assertSeeHtml('data-payout-verify')
            ->assertDontSee('Hand out 120,00 TRY in cash')
            ->set('method', 'cash')->set('category', 'supplies')->set('description', 'Tea')->set('amount', '30')
            ->call('record')
            ->assertHasNoErrors()
            ->assertSee('Hand out 30,00 TRY in cash')
            ->assertSet('replayedSubmissionId', null);
    }

    #[Test]
    public function one_request_key_records_one_submission_across_cash_and_non_cash_methods(): void
    {
        $session = $this->openSession('1000');
        $cardKey = (string) Str::uuid();
        $card = $this->submit($this->details('50', 'card'), null, $cardKey);

        $this->assertThrows(fn () => $this->submit($this->details('50'), $session->id, $cardKey), IdempotencyConflictException::class);
        $this->assertThrows(fn () => $this->submit($this->details('50', 'bank_transfer'), null, $cardKey), IdempotencyConflictException::class);
        $this->assertSame($card->id, $this->submit($this->details('50', 'card'), $session->id, $cardKey)->id);

        $cashKey = (string) Str::uuid();
        $cash = $this->submit($this->details('70'), $session->id, $cashKey);

        $this->assertThrows(fn () => $this->submit($this->details('70', 'card'), null, $cashKey), IdempotencyConflictException::class);
        $this->assertSame([$cash->id, $cash->cash_payout_id], [$this->submit($this->details('70'), $session->id, $cashKey)->id, $cash->payout->id]);
        $this->assertSame([2, 1, 1], [ExpenseSubmission::query()->count(), CashPayout::query()->count(), Expense::query()->count()]);
    }

    #[Test]
    public function supplier_and_device_references_follow_the_payout_into_the_expense_and_replay_only_identically(): void
    {
        $session = $this->openSession('1000');
        $supplier = OperationalSupplier::factory()->create(['name' => 'Fix Parts Ltd']);
        $otherSupplier = OperationalSupplier::factory()->create();
        $untouched = fn (): array => [SupplierLedgerEntry::count(), InventoryMovement::count(), $this->phone->costEntries()->count(), $this->phone->fresh()->status];
        $before = $untouched();

        $page = Livewire::actingAs($this->owner)->test(ExpensesPage::class, ['store' => $this->store])
            ->set('deviceCode', $this->phoneImei)
            ->call('findDevice')
            ->assertSet('unitId', $this->phone->id)
            ->assertSeeHtml('data-expense-device-chosen');
        $key = $page->get('requestKey');
        $page->set('method', 'cash')->set('category', 'supplies')->set('description', 'Screen glue')->set('amount', '150')
            ->set('supplierId', (string) $supplier->id)
            ->call('record')
            ->assertHasNoErrors();

        $payout = CashPayout::query()->sole();
        $this->assertSame([$supplier->id, $this->phone->id], [$payout->operational_supplier_id, $payout->inventory_unit_id]);

        $input = [...$this->details('150'), 'description' => 'Screen glue', 'operational_supplier_id' => (string) $supplier->id, 'inventory_unit_id' => $this->phone->id];
        $this->assertSame($payout->id, $this->submit($input, $session->id, $key)->cash_payout_id);
        $this->assertThrows(fn () => $this->submit([...$input, 'operational_supplier_id' => $otherSupplier->id], $session->id, $key), IdempotencyConflictException::class);
        $this->assertThrows(fn () => $this->submit([...$input, 'inventory_unit_id' => null], $session->id, $key), IdempotencyConflictException::class);

        $expense = app(ConfirmCashPayout::class)($this->owner, $this->store, $payout->id, $session->id);
        $this->assertSame([$supplier->id, $this->phone->id], [$expense->operational_supplier_id, $expense->inventory_unit_id]);
        $this->assertSame($before, $untouched());

        Livewire::actingAs($this->owner)->test(ExpensesPage::class, ['store' => $this->store])
            ->assertSeeHtml('data-expense-links')
            ->assertSee('Fix Parts Ltd')
            ->assertSee($this->phoneImei);

        $inactive = OperationalSupplier::factory()->create(['is_active' => false]);
        $elsewhere = $this->registerDevice($this->owner, Store::factory()->create(['code' => 'KT02']), $this->usedPhone, self::imei('35693803564414'));
        $this->assertValidationError(fn () => $this->submit([...$this->details('20', 'card'), 'operational_supplier_id' => $inactive->id], null), 'operational_supplier_id');
        $this->assertValidationError(fn () => $this->submit([...$this->details('20', 'card'), 'inventory_unit_id' => $elsewhere->id], null), 'inventory_unit_id');

        $plain = $this->submit($this->details('40', 'card'), null);
        $this->assertSame(Idempotency::fingerprint([
            'operation' => 'expense_submission', 'store_id' => $this->store->id, 'method' => 'card', 'session_id' => null,
            'category' => 'supplies', 'description' => 'Printer paper', 'amount' => 4_000, 'expense_date' => Carbon::now(Sale::DISPLAY_TIMEZONE)->toDateString(), 'reference' => null,
        ]), $plain->request_fingerprint);
    }

    private function openSession(string $counted): CashboxSession
    {
        return app(OpenCashboxSession::class)($this->owner, $this->store, $counted, null);
    }

    private function startPayout(CashboxSession $session, string $amount, ?string $requestKey = null): CashPayout
    {
        return $this->submit($this->details($amount), $session->id, $requestKey)->payout;
    }

    /**
     * @param  array<string, string>  $input
     */
    private function submit(array $input, ?int $sessionId, ?string $requestKey = null): ExpenseSubmission
    {
        return app(SubmitExpense::class)($this->owner, $this->store, $sessionId, $input, $requestKey ?? (string) Str::uuid());
    }

    /**
     * @return array{method: string, category: string, description: string, amount: string, expense_date: string}
     */
    private function details(string $amount, string $method = 'cash'): array
    {
        return [
            'method' => $method,
            'category' => 'supplies',
            'description' => 'Printer paper',
            'amount' => $amount,
            'expense_date' => Carbon::now(Sale::DISPLAY_TIMEZONE)->toDateString(),
        ];
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
