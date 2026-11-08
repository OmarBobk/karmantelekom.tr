<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Cashbox\CloseCashboxSession;
use App\Actions\Cashbox\ConfirmCashboxTransfer;
use App\Actions\Cashbox\OpenCashboxSession;
use App\Actions\Cashbox\StartCashboxTransfer;
use App\Enums\CashboxSessionStatus;
use App\Livewire\Pos\CashboxPage;
use App\Livewire\Pos\CashboxSessionPage;
use App\Models\CashboxSession;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\InteractsWithPos;
use Tests\TestCase;

class CashboxReconciliationTest extends TestCase
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
    public function the_owner_reviews_a_closed_session_of_this_branch_from_its_stored_snapshot_with_paginated_movements(): void
    {
        $session = $this->openSession('1000');

        foreach (range(1, 26) as $number) {
            $this->deposit($session, '1', "Float {$number}");
        }

        app(CloseCashboxSession::class)($this->owner, $this->store, $session->id, '1026', '1020', 'Six short');

        $this->actingAs($this->owner)->get(route('pos.cashbox', $this->store))
            ->assertOk()
            ->assertSee(route('pos.cashbox.session', [$this->store, $session->id]), false);

        $this->actingAs($this->owner)->get(route('pos.cashbox.session', [$this->store, $session->id]))
            ->assertOk()
            ->assertSee(['1.026,00', '1.020,00', '-6,00', 'Six short', 'Closed by Omar', '26 in · 0 out'])
            ->assertSee('Float 25')
            ->assertDontSee('Float 26');

        Livewire::actingAs($this->owner)->test(CashboxSessionPage::class, ['store' => $this->store, 'session' => $session->id])
            ->call('gotoPage', 2, 'movements')
            ->assertSee('Float 26')
            ->assertDontSee('Float 25');

        DB::table('cashbox_sessions')->where('id', $session->id)->update(['expected_closing' => '1030.00', 'closing_difference' => '-10.00']);
        $this->actingAs($this->owner)->get(route('pos.cashbox.session', [$this->store, $session->id]))
            ->assertSeeInOrder(['data-expected-closing', '1.030,00', '-10,00'], false);

        $this->actingAs($this->cashier)->get(route('pos.cashbox.session', [$this->store, $session->id]))->assertForbidden();

        $other = Store::factory()->create(['code' => 'KT02']);
        $otherSession = app(OpenCashboxSession::class)($this->owner, $other, '0', null);
        app(CloseCashboxSession::class)($this->owner, $other, $otherSession->id, '0', '0', null);
        $open = $this->openSession('1020');

        $this->withoutExceptionHandling();

        foreach ([$otherSession->id, $open->id] as $hiddenId) {
            $this->assertThrows(fn () => $this->actingAs($this->owner)->get(route('pos.cashbox.session', [$this->store, $hiddenId])), ModelNotFoundException::class);
        }
    }

    #[Test]
    public function a_session_holding_more_than_one_payment_limit_closes_with_stale_and_replay_protection(): void
    {
        $session = $this->openSession('0');

        $this->assertValidationError(fn () => $this->deposit($session, '10000000000', 'Over the payment limit'), 'amount');
        $this->deposit($session, '9999999999.99', 'First bag');

        $page = Livewire::actingAs($this->owner)->test(CashboxPage::class, ['store' => $this->store])
            ->assertSet('expectedShown', 999_999_999_999);

        $this->deposit($session, '9999999999.99', 'Second bag');
        $this->assertSame(1_999_999_999_998, $session->expectedMinor());

        $page->set('countedClosing', '19.999.999.999,98')
            ->call('close')
            ->assertHasErrors('counted_closing')
            ->assertSet('expectedShown', 1_999_999_999_998)
            ->call('close')
            ->assertHasNoErrors();

        $closed = $session->fresh();
        $this->assertSame([CashboxSessionStatus::CLOSED, '19999999999.98', '19999999999.98', '0.00'], [
            $closed->status, $closed->expected_closing, $closed->counted_closing, $closed->closing_difference,
        ]);
        $this->assertSame($closed->id, app(CloseCashboxSession::class)($this->owner, $this->store, $session->id, '19999999999.98', '19999999999.98', null)->id);

        $next = $this->openSession('19999999999.98');
        $this->assertSame(['19999999999.98', '0.00'], [$next->opening_expected, $next->opening_difference]);
    }

    private function openSession(string $counted): CashboxSession
    {
        return app(OpenCashboxSession::class)($this->owner, $this->store, $counted, null);
    }

    private function deposit(CashboxSession $session, string $amount, string $reason): void
    {
        $transfer = app(StartCashboxTransfer::class)($this->owner, $this->store, $session->id, ['kind' => 'deposit', 'amount' => $amount, 'reason' => $reason], (string) Str::uuid());
        app(ConfirmCashboxTransfer::class)($this->owner, $this->store, $transfer->id, $session->id);
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
