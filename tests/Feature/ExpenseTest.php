<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Cashbox\CloseCashboxSession;
use App\Actions\Cashbox\OpenCashboxSession;
use App\Actions\Expenses\ConfirmCashPayout;
use App\Actions\Expenses\RecordExpense;
use App\Actions\Expenses\ReleaseCashPayout;
use App\Actions\Expenses\StartCashPayout;
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
use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
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
        $this->assertValidationError(fn () => app(StartCashPayout::class)($this->owner, $this->store, null, $this->details('10'), (string) Str::uuid()), 'cashbox');

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
        $input = ['method' => 'bank_transfer', ...$this->details('1500.75'), 'category' => 'rent', 'reference' => 'EFT-0042'];
        $expense = app(RecordExpense::class)($this->owner, $this->store, $input, $key);

        $this->assertSame(['1500.75', ExpenseMethod::BANK_TRANSFER, 'EFT-0042'], [$expense->amount, $expense->method, $expense->reference]);
        $this->assertSame($expense->id, app(RecordExpense::class)($this->owner, $this->store, $input, $key)->id);
        $this->assertThrows(fn () => app(RecordExpense::class)($this->owner, $this->store, [...$input, 'amount' => '1500.76'], $key), IdempotencyConflictException::class);
        $this->assertValidationError(fn () => app(RecordExpense::class)($this->owner, $this->store, [...$input, 'method' => 'cash'], (string) Str::uuid()), 'method');
        $tomorrow = Carbon::now(Sale::DISPLAY_TIMEZONE)->addDay()->toDateString();
        $this->assertValidationError(fn () => app(RecordExpense::class)($this->owner, $this->store, [...$input, 'expense_date' => $tomorrow], (string) Str::uuid()), 'expense_date');
        $this->assertSame([1, 0, false], [Expense::query()->count(), CashMovement::query()->count(), CashboxSession::trackingStarted($this->store)]);

        $this->assertThrows(fn () => app(RecordExpense::class)($this->cashier, $this->store, $input, (string) Str::uuid()), AuthorizationException::class);
        $this->actingAs($this->cashier)->get(route('pos.expenses', $this->store))->assertForbidden();
        $this->actingAs($this->owner)->get(route('pos.expenses', $this->store))->assertOk()->assertSee('1.500,75')->assertSee('EFT-0042');
    }

    private function openSession(string $counted): CashboxSession
    {
        return app(OpenCashboxSession::class)($this->owner, $this->store, $counted, null);
    }

    private function startPayout(CashboxSession $session, string $amount, ?string $requestKey = null): CashPayout
    {
        return app(StartCashPayout::class)($this->owner, $this->store, $session->id, $this->details($amount), $requestKey ?? (string) Str::uuid());
    }

    /**
     * @return array{category: string, description: string, amount: string, expense_date: string}
     */
    private function details(string $amount): array
    {
        return [
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
