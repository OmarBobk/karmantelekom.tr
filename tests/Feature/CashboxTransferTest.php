<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Cashbox\CloseCashboxSession;
use App\Actions\Cashbox\ConfirmCashboxTransfer;
use App\Actions\Cashbox\OpenCashboxSession;
use App\Actions\Cashbox\ReleaseCashboxTransfer;
use App\Actions\Cashbox\StartCashboxTransfer;
use App\Actions\Expenses\SubmitExpense;
use App\Enums\CashboxSessionStatus;
use App\Enums\CashMovementDirection;
use App\Enums\CashMovementType;
use App\Enums\CashPayoutStatus;
use App\Exceptions\Inventory\IdempotencyConflictException;
use App\Livewire\Pos\CashboxPage;
use App\Models\CashboxSession;
use App\Models\CashboxTransfer;
use App\Models\CashMovement;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\InteractsWithPos;
use Tests\TestCase;

class CashboxTransferTest extends TestCase
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
    public function the_worked_example_closes_1300_after_a_500_deposit_and_a_200_withdrawal(): void
    {
        $session = $this->openSession('1000');

        $deposit = $this->start($session->id, 'deposit', '500', 'Change float from the safe');
        $this->assertSame([CashPayoutStatus::PENDING, 100_000], [$deposit->status, $session->expectedMinor()]);
        $this->assertValidationError(fn () => $this->close($session, '1000'), 'cashbox');

        $this->confirm($deposit, $session->id);
        $withdrawal = $this->start($session->id, 'withdrawal', '200', 'Bank deposit run');
        $this->assertValidationError(fn () => $this->close($session, '1500'), 'cashbox');
        $this->confirm($withdrawal, $session->id);

        $movements = CashMovement::query()->orderBy('id')->get();
        $this->assertSame([CashMovementType::CASH_DEPOSIT, CashMovementType::CASH_WITHDRAWAL], $movements->pluck('type')->all());
        $this->assertSame([CashMovementDirection::IN, CashMovementDirection::OUT], $movements->pluck('direction')->all());
        $this->assertSame([$deposit->id, $withdrawal->id], $movements->pluck('cashbox_transfer_id')->all());
        $this->assertTrue($movements->every(fn (CashMovement $movement) => $movement->type->isInternalTransfer()));
        $this->assertSame([CashPayoutStatus::COMPLETED, CashPayoutStatus::COMPLETED], [$deposit->fresh()->status, $withdrawal->fresh()->status]);
        $this->assertSame(130_000, $session->expectedMinor());

        $closed = $this->close($session, '1300');
        $this->assertSame([CashboxSessionStatus::CLOSED, '1300.00', '0.00'], [$closed->status, $closed->expected_closing, $closed->closing_difference]);
    }

    #[Test]
    public function a_retried_start_or_confirmation_returns_the_original_record_without_posting_again(): void
    {
        $session = $this->openSession('1000');
        $key = (string) Str::uuid();

        $transfer = $this->start($session->id, 'withdrawal', '300', 'Owner draw', $key);
        $this->assertSame($transfer->id, $this->start($session->id, 'withdrawal', '300', 'Owner draw', $key)->id);
        $this->assertThrows(fn () => $this->start($session->id, 'withdrawal', '301', 'Owner draw', $key), IdempotencyConflictException::class);

        $movement = $this->confirm($transfer, $session->id);
        $this->assertSame($movement->id, $this->confirm($transfer, $session->id)->id);

        $replayed = $this->start($session->id, 'withdrawal', '300', 'Owner draw', $key);
        $this->assertSame([$transfer->id, CashPayoutStatus::COMPLETED, false], [$replayed->id, $replayed->status, $replayed->wasRecentlyCreated]);
        $this->assertSame([1, 1], [CashboxTransfer::query()->count(), CashMovement::query()->count()]);
        $this->assertSame(70_000, $session->expectedMinor());
    }

    #[Test]
    public function a_transfer_needs_a_positive_amount_a_reason_the_displayed_session_and_withdrawals_fit_after_pending_payouts(): void
    {
        $session = $this->openSession('1000');

        $this->assertValidationError(fn () => $this->start($session->id, 'deposit', '0', 'Float'), 'amount');
        $this->assertValidationError(fn () => $this->start($session->id, 'deposit', '50', '   '), 'reason');
        $this->assertValidationError(fn () => $this->start($session->id + 1, 'deposit', '50', 'Float'), 'cashbox');
        $this->assertValidationError(fn () => $this->start(null, 'deposit', '50', 'Float'), 'cashbox');

        app(SubmitExpense::class)($this->owner, $this->store, $session->id, [
            'method' => 'cash',
            'category' => 'supplies',
            'description' => 'Printer paper',
            'amount' => '700',
            'expense_date' => Carbon::now(Sale::DISPLAY_TIMEZONE)->toDateString(),
        ], (string) Str::uuid());

        $this->assertValidationError(fn () => $this->start($session->id, 'withdrawal', '300.01', 'Owner draw'), 'amount');
        $withdrawal = $this->start($session->id, 'withdrawal', '300', 'Owner draw');
        $this->assertValidationError(fn () => $this->start($session->id, 'withdrawal', '0.01', 'Owner draw'), 'amount');
        $this->assertSame(0, $session->availableForPayoutMinor());

        $this->assertValidationError(fn () => $this->confirm($withdrawal, $session->id + 1), 'cashbox');
        $this->assertSame(0, CashMovement::query()->count());
    }

    #[Test]
    public function only_the_owner_moves_cash_and_releases_with_an_audited_resolution(): void
    {
        $session = $this->openSession('1000');

        $this->assertThrows(fn () => app(StartCashboxTransfer::class)($this->cashier, $this->store, $session->id, ['kind' => 'deposit', 'amount' => '50', 'reason' => 'Float'], (string) Str::uuid()), AuthorizationException::class);
        $this->actingAs($this->cashier)->get(route('pos.cashbox', $this->store))->assertForbidden();

        $transfer = $this->start($session->id, 'withdrawal', '400', 'Owner draw');
        $this->assertThrows(fn () => app(ConfirmCashboxTransfer::class)($this->cashier, $this->store, $transfer->id, $session->id), AuthorizationException::class);
        $this->assertThrows(fn () => app(ReleaseCashboxTransfer::class)($this->cashier, $this->store, $transfer->id, 'returned', 'Put back'), AuthorizationException::class);
        $this->assertValidationError(fn () => app(ReleaseCashboxTransfer::class)($this->owner, $this->store, $transfer->id, 'returned', ' '), 'release_note');
        $this->assertValidationError(fn () => app(ReleaseCashboxTransfer::class)($this->owner, $this->store, $transfer->id, null, 'Put back'), 'resolution');

        $released = app(ReleaseCashboxTransfer::class)($this->owner, $this->store, $transfer->id, 'returned', 'Put the cash back');
        $this->assertSame([CashPayoutStatus::RELEASED, $this->owner->id], [$released->status, $released->released_by]);
        $this->assertSame($released->id, app(ReleaseCashboxTransfer::class)($this->owner, $this->store, $transfer->id, 'returned', 'Put the cash back')->id);
        $this->assertValidationError(fn () => $this->confirm($transfer, $session->id), 'cashbox');

        $activity = Activity::query()->where('event', 'cashbox_transfer_released')->sole();
        $this->assertSame(['returned', 'Put the cash back', '400.00'], [$activity->properties['resolution'], $activity->properties['note'], $activity->properties['amount']]);
        $this->assertSame([0, 100_000], [CashMovement::query()->count(), $session->availableForPayoutMinor()]);
        $this->assertSame(CashboxSessionStatus::CLOSED, $this->close($session, '1000')->status);
    }

    #[Test]
    public function the_move_instruction_shows_only_on_the_screen_that_recorded_the_transfer(): void
    {
        $session = $this->openSession('1000');

        $page = Livewire::actingAs($this->owner)->test(CashboxPage::class, ['store' => $this->store])
            ->set('transferKind', 'withdrawal')
            ->assertSee('Available to withdraw: 1.000,00 TRY')
            ->set('transferAmount', '200')
            ->set('transferReason', 'Bank deposit run')
            ->call('startTransfer')
            ->assertHasNoErrors()
            ->assertSee('Take 200,00 TRY out of the drawer now')
            ->assertSeeHtml('data-transfer-move');

        $transfer = CashboxTransfer::query()->sole();
        $page->assertSet('instructedTransferId', $transfer->id)->assertSet('transferAmount', '');

        Livewire::actingAs($this->owner)->test(CashboxPage::class, ['store' => $this->store])
            ->assertDontSee('Take 200,00 TRY out of the drawer now')
            ->assertSeeHtml('data-transfer-verify')
            ->call('confirmTransfer', $transfer->id)
            ->assertHasNoErrors()
            ->assertDontSeeHtml('data-transfer-verify')
            ->assertSee('Bank deposit run');

        $this->assertSame(80_000, $session->expectedMinor());
    }

    private function openSession(string $counted): CashboxSession
    {
        return app(OpenCashboxSession::class)($this->owner, $this->store, $counted, null);
    }

    private function close(CashboxSession $session, string $amount): CashboxSession
    {
        return app(CloseCashboxSession::class)($this->owner, $this->store, $session->id, $amount, $amount, null);
    }

    private function start(?int $sessionId, string $kind, string $amount, string $reason, ?string $key = null): CashboxTransfer
    {
        return app(StartCashboxTransfer::class)($this->owner, $this->store, $sessionId, ['kind' => $kind, 'amount' => $amount, 'reason' => $reason], $key ?? (string) Str::uuid());
    }

    private function confirm(CashboxTransfer $transfer, int $sessionId): CashMovement
    {
        return app(ConfirmCashboxTransfer::class)($this->owner, $this->store, $transfer->id, $sessionId);
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
