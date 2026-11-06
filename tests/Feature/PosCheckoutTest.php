<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Pos\CancelCheckoutAttempt;
use App\Actions\Pos\CheckCheckoutStatus;
use App\Actions\Pos\FinalizeCheckout;
use App\Actions\Pos\ResolveVoidedApproval;
use App\Enums\AdjustmentReason;
use App\Enums\CardApprovalStatus;
use App\Enums\CheckoutAttemptStatus;
use App\Enums\InspectionOutcome;
use App\Enums\MovementType;
use App\Enums\SalePaymentMethod;
use App\Enums\UnitStatus;
use App\Exceptions\Inventory\IdempotencyConflictException;
use App\Exceptions\Pos\CheckoutItemsChangedException;
use App\Models\CheckoutAttempt;
use App\Models\InventoryMovement;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\SaleNumberSequence;
use App\Models\SalePayment;
use App\Models\Store;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Support\InteractsWithPos;
use Tests\TestCase;

class PosCheckoutTest extends TestCase
{
    use InteractsWithPos;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCounter();
        $this->useEnglishLocale();
    }

    #[Test]
    public function a_cashier_sells_two_cables_and_a_used_phone_with_split_cash_and_card(): void
    {
        $cashier = $this->createCashier();
        $terminal = $this->createTerminal();

        $attempt = $this->review($cashier, [...$this->cableLine(2), ...$this->phoneLine()]);

        $this->assertSame(CheckoutAttemptStatus::OPEN, $attempt->status);
        $this->assertSame('12599.80', $attempt->total);
        $this->assertSame('0.00', $attempt->discount_total);

        $approval = $this->approveCard($cashier, $attempt, $terminal, '10000');
        $this->assertNotNull($attempt->fresh()->frozen_at);

        $sale = $this->finalize($cashier, $attempt->fresh(), '3000');

        $this->assertSame('KT01-000001', $sale->number);
        $this->assertSame('12599.80', $sale->total);
        $this->assertSame('3000.00', $sale->cash_tendered);
        $this->assertSame('400.20', $sale->change_given);
        $this->assertSame(8, $this->cableStock());
        $this->assertSame(UnitStatus::SOLD, $this->phone->fresh()->status);
        $this->assertSame($this->phone->lifecycle_revision + 1, $this->phone->fresh()->lifecycle_revision);

        $payments = $sale->payments()->orderBy('id')->get();
        $this->assertSame([SalePaymentMethod::CARD, SalePaymentMethod::CASH], $payments->pluck('method')->all());
        $this->assertSame(['10000.00', '2599.80'], $payments->pluck('amount')->all());
        $this->assertSame($approval->id, $payments[0]->card_approval_id);
        $this->assertSame('A12345', $payments[0]->terminal_reference);
        $this->assertSame(CardApprovalStatus::CAPTURED, $approval->fresh()->status);
        $this->assertSame(CheckoutAttemptStatus::COMPLETED, $attempt->fresh()->status);

        $lines = $sale->lines;
        $this->assertCount(2, $lines);
        $phoneLine = $lines->firstWhere('inventory_unit_id', $this->phone->id);
        $this->assertSame($this->phoneImei, $phoneLine->unit_identifier);
        $this->assertSame('12500.00', $phoneLine->line_total);

        $movements = InventoryMovement::query()->where('type', MovementType::SALE)->orderBy('id')->get();
        $this->assertCount(2, $movements);
        $this->assertSame([-2, -1], $movements->pluck('quantity_change')->all());
        $this->assertTrue($movements->every(fn (InventoryMovement $movement): bool => $movement->reference_type === SaleLine::MORPH_ALIAS && $movement->reason === AdjustmentReason::SALE));
        $this->assertSame(UnitStatus::SOLD, $movements[1]->unit_status_after);
    }

    #[Test]
    public function sale_numbers_are_sequential_per_store(): void
    {
        $cashier = $this->createCashier();

        $first = $this->finalize($cashier, $this->review($cashier, $this->cableLine(1)), '49.90');
        $second = $this->finalize($cashier, $this->review($cashier, $this->cableLine(1)), '50');

        $this->assertSame(['KT01-000001', 'KT01-000002'], [$first->number, $second->number]);
        $this->assertSame('0.10', $second->change_given);
    }

    #[Test]
    public function a_card_only_sale_records_no_cash(): void
    {
        $cashier = $this->createCashier();
        $attempt = $this->review($cashier, $this->cableLine(2));
        $this->approveCard($cashier, $attempt, $this->createTerminal(), '99.80');

        $sale = $this->finalize($cashier, $attempt->fresh());

        $this->assertNull($sale->cash_tendered);
        $this->assertSame([SalePaymentMethod::CARD], $sale->payments->pluck('method')->all());
    }

    #[Test]
    public function cash_must_cover_what_the_card_did_not_and_change_is_only_against_cash(): void
    {
        $cashier = $this->createCashier();
        $attempt = $this->review($cashier, $this->cableLine(2));
        $this->approveCard($cashier, $attempt, $this->createTerminal(), '50');

        $this->assertValidationError(fn () => $this->finalize($cashier, $attempt->fresh(), '49.79'), 'cash_tendered');
        $this->assertValidationError(fn () => $this->finalize($cashier, $attempt->fresh()), 'cash_tendered');

        $sale = $this->finalize($cashier, $attempt->fresh(), '٥٠');

        $this->assertSame('0.20', $sale->change_given);
        $this->assertSame(['50.00', '49.80'], $sale->payments()->orderBy('id')->pluck('amount')->all());
        $this->assertNull(CheckoutAttempt::query()->find($attempt->id)->failure_code);
    }

    #[Test]
    public function tendering_cash_when_the_card_covers_the_total_is_rejected(): void
    {
        $cashier = $this->createCashier();
        $attempt = $this->review($cashier, $this->cableLine(1));
        $this->approveCard($cashier, $attempt, $this->createTerminal(), '49.90');

        $this->assertValidationError(fn () => $this->finalize($cashier, $attempt->fresh(), '10'), 'cash_tendered');
    }

    #[Test]
    public function a_card_approval_cannot_exceed_the_total_and_needs_a_valid_reference_and_active_terminal(): void
    {
        $cashier = $this->createCashier();
        $attempt = $this->review($cashier, $this->cableLine(1));
        $terminal = $this->createTerminal();

        $this->assertValidationError(fn () => $this->approveCard($cashier, $attempt, $terminal, '49.91'), 'card_amount');
        $this->assertValidationError(fn () => $this->approveCard($cashier, $attempt, $terminal, '10', '  '), 'card_reference');
        $this->assertValidationError(fn () => $this->approveCard($cashier, $attempt, $terminal, '10', str_repeat('A', 41)), 'card_reference');
        $this->assertValidationError(fn () => $this->approveCard($cashier, $attempt, $terminal, '10', '4111 1111 1111 1111'), 'card_reference');

        $terminal->update(['is_active' => false]);
        $this->assertValidationError(fn () => $this->approveCard($cashier, $attempt, $terminal, '10'), 'card_terminal');

        $otherStoreTerminal = $this->createTerminal(Store::factory()->create());
        $this->assertValidationError(fn () => $this->approveCard($cashier, $attempt, $otherStoreTerminal, '10'), 'card_terminal');
        $this->assertNull($attempt->fresh()->frozen_at);
    }

    #[Test]
    public function recording_the_same_approval_twice_returns_it_and_a_different_one_conflicts(): void
    {
        $cashier = $this->createCashier();
        $attempt = $this->review($cashier, $this->cableLine(2));
        $terminal = $this->createTerminal();

        $first = $this->approveCard($cashier, $attempt, $terminal, '50', 'a 12345');
        $again = $this->approveCard($cashier, $attempt, $terminal, '50.00', 'A12345');

        $this->assertSame($first->id, $again->id);
        $this->assertSame('A12345', $first->reference);
        $this->assertValidationError(fn () => $this->approveCard($cashier, $attempt, $terminal, '60', 'A12345'), 'card_reference');
        $this->assertValidationError(fn () => $this->approveCard($cashier, $attempt, $terminal, '50', 'B99'), 'card_reference');
    }

    #[Test]
    public function an_approval_reference_is_unique_per_terminal_not_globally(): void
    {
        $cashier = $this->createCashier();
        $terminal = $this->createTerminal();
        $otherTerminal = $this->createTerminal();

        $this->approveCard($cashier, $this->review($cashier, $this->cableLine(1)), $terminal, '49.90', 'R1');

        $second = $this->review($cashier, $this->cableLine(1));
        $this->assertValidationError(fn () => $this->approveCard($cashier, $second, $terminal, '49.90', 'R1'), 'card_reference');

        $this->assertSame('R1', $this->approveCard($cashier, $second, $otherTerminal, '49.90', 'R1')->reference);
    }

    #[Test]
    public function one_card_approval_cannot_complete_two_sales(): void
    {
        $cashier = $this->createCashier();
        $attempt = $this->review($cashier, $this->cableLine(2));
        $approval = $this->approveCard($cashier, $attempt, $this->createTerminal(), '99.80');

        $sale = $this->finalize($cashier, $attempt->fresh());
        $replayed = $this->finalize($cashier, $attempt->fresh());

        $this->assertSame($sale->id, $replayed->id);
        $this->assertSame(1, Sale::query()->count());
        $this->assertSame(1, SalePayment::query()->where('card_approval_id', $approval->id)->count());
        $this->assertSame(8, $this->cableStock());

        $this->assertValidationError(fn () => $this->review($cashier, $this->cableLine(1), $attempt->fresh()), 'attempt');
    }

    #[Test]
    public function an_identical_finalize_retry_returns_the_same_sale_and_a_changed_one_conflicts(): void
    {
        $cashier = $this->createCashier();
        $attempt = $this->review($cashier, $this->cableLine(1));

        $sale = $this->finalize($cashier, $attempt, '100');

        $this->assertSame($sale->id, $this->finalize($cashier, $attempt, '100.00')->id);
        $this->expectException(IdempotencyConflictException::class);
        $this->finalize($cashier, $attempt, '200');
    }

    #[Test]
    public function the_reviewed_payload_is_frozen_once_a_card_is_recorded(): void
    {
        $cashier = $this->createCashier();
        $attempt = $this->review($cashier, $this->cableLine(2));
        $this->approveCard($cashier, $attempt, $this->createTerminal(), '20');

        $this->assertValidationError(fn () => $this->review($cashier, $this->cableLine(3), $attempt->fresh()), 'attempt');
        $this->assertValidationError(fn () => app(CancelCheckoutAttempt::class)($cashier, $this->store, $attempt->key), 'attempt');
        $this->assertSame(2, $attempt->fresh()->lines[0]['quantity']);
    }

    #[Test]
    public function reviewing_again_before_payment_updates_the_same_attempt_and_stale_revisions_are_rejected(): void
    {
        $cashier = $this->createCashier();
        $attempt = $this->review($cashier, $this->cableLine(1));

        $updated = $this->review($cashier, $this->cableLine(3), $attempt);
        $this->assertSame($attempt->id, $updated->id);
        $this->assertSame(2, $updated->revision);
        $this->assertSame('149.70', $updated->total);

        $same = $this->review($cashier, $this->cableLine(3), $updated);
        $this->assertSame(2, $same->revision);

        $this->assertValidationError(fn () => $this->finalize($cashier, $attempt, '200'), 'attempt');
        $this->assertSame(10, $this->cableStock());
        $this->assertSame(0, Sale::query()->count());
    }

    #[Test]
    public function prices_come_from_the_server_and_unpriced_items_cannot_be_reviewed(): void
    {
        $cashier = $this->createCashier();
        $this->phone->update(['selling_price' => null]);
        $this->usedPhone->update(['selling_price' => '9999.00']);

        $this->assertValidationError(fn () => $this->review($cashier, $this->phoneLine()), 'lines.u'.$this->phone->id.'.price');

        $this->cable->update(['selling_price' => null]);
        $this->assertValidationError(fn () => $this->review($cashier, $this->cableLine()), 'lines.d'.$this->cable->id.'.price');
        $this->assertSame(0, CheckoutAttempt::query()->count());
    }

    #[Test]
    public function a_cashier_cannot_adjust_a_price_and_the_owner_must_give_a_reason_for_a_lower_price(): void
    {
        $cashier = $this->createCashier();
        $key = 'lines.d'.$this->cable->id;

        $this->assertValidationError(fn () => $this->review($cashier, $this->cableLine(2, ['adjusted_price' => '40'])), "{$key}.adjusted_price");
        $this->assertValidationError(fn () => $this->review($this->owner, $this->cableLine(2, ['adjusted_price' => '40'])), "{$key}.adjustment_reason");
        $this->assertValidationError(fn () => $this->review($this->owner, $this->cableLine(2, ['adjusted_price' => '60', 'adjustment_reason' => 'x'])), "{$key}.adjusted_price");

        $attempt = $this->review($this->owner, $this->cableLine(2, ['adjusted_price' => '40', 'adjustment_reason' => 'Loyal customer']));

        $this->assertSame(['99.80', '19.80', '80.00'], [$attempt->subtotal, $attempt->discount_total, $attempt->total]);

        $sale = $this->finalize($this->owner, $attempt, '80');
        $line = $sale->lines->first();
        $this->assertSame(['49.90', '40.00', '19.80', '80.00', 'Loyal customer'], [$line->list_unit_price, $line->unit_price, $line->discount_amount, $line->line_total, $line->adjustment_reason]);
    }

    #[Test]
    public function a_price_change_after_review_is_rejected_before_payment_completes(): void
    {
        $cashier = $this->createCashier();
        $attempt = $this->review($cashier, $this->cableLine(2));
        $this->cable->update(['selling_price' => '59.90']);

        try {
            $this->finalize($cashier, $attempt, '200');
            $this->fail('A stale price must be rejected.');
        } catch (CheckoutItemsChangedException $exception) {
            $this->assertSame(CheckoutItemsChangedException::PRICE_CHANGED, $exception->failureCode);
        }

        $attempt->refresh();
        $this->assertSame(CheckoutAttemptStatus::OPEN, $attempt->status);
        $this->assertSame('price_changed', $attempt->failure_code);
        $this->assertSame(10, $this->cableStock());

        $repriced = $this->review($cashier, $this->cableLine(2), $attempt);
        $this->assertNull($repriced->failure_code);
        $this->assertSame('119.80', $this->finalize($cashier, $repriced, '120')->total);
    }

    #[Test]
    public function stock_sold_elsewhere_after_review_fails_the_sale_and_keeps_the_attempt_recoverable(): void
    {
        $cashier = $this->createCashier();
        $other = $this->createCashier(name: 'Second cashier');

        $attempt = $this->review($cashier, $this->cableLine(8));
        $this->finalize($other, $this->review($other, $this->cableLine(5)), '500');

        $this->assertValidationError(fn () => $this->finalize($cashier, $attempt, '500'), 'lines.d'.$this->cable->id.'.quantity');

        $this->assertSame(5, $this->cableStock());
        $this->assertSame('unavailable', $attempt->fresh()->failure_code);
        $this->assertSame(1, Sale::query()->count());
    }

    #[Test]
    public function a_phone_moved_to_inspection_or_sold_after_review_cannot_be_sold(): void
    {
        $cashier = $this->createCashier();
        $attempt = $this->review($cashier, $this->phoneLine());

        $this->inspectDevice($this->owner, $this->phone, InspectionOutcome::NEEDS_REPAIR);

        $this->assertValidationError(fn () => $this->finalize($cashier, $attempt, '13000'), 'lines.u'.$this->phone->id.'.item');
        $this->assertSame(UnitStatus::INSPECTION, $this->phone->fresh()->status);
        $this->assertSame(0, Sale::query()->count());
    }

    #[Test]
    public function an_approved_card_survives_a_failed_sale_and_the_owner_resolves_it_after_a_terminal_void(): void
    {
        $cashier = $this->createCashier();
        $attempt = $this->review($cashier, $this->phoneLine());
        $approval = $this->approveCard($cashier, $attempt, $this->createTerminal(), '12500', 'APP77');

        $this->inspectDevice($this->owner, $this->phone, InspectionOutcome::NEEDS_REPAIR);
        $this->assertValidationError(fn () => $this->finalize($cashier, $attempt->fresh()), 'lines.u'.$this->phone->id.'.item');

        $status = app(CheckCheckoutStatus::class)($cashier, $this->store, $attempt->key);
        $this->assertSame(CheckoutAttemptStatus::OPEN, $status->status);
        $this->assertSame('APP77', $status->cardApproval->reference);
        $this->assertSame('12500.00', $status->cardApproval->amount);
        $this->assertSame('unavailable', $status->failure_code);

        $this->expectAuthorizationFailure(fn () => app(ResolveVoidedApproval::class)($cashier, $this->store, $attempt->key, 'V1', 'Voided'));
        $this->assertValidationError(fn () => app(ResolveVoidedApproval::class)($this->owner, $this->store, $attempt->key, 'V1', ''), 'void_note');

        $resolved = app(ResolveVoidedApproval::class)($this->owner, $this->store, $attempt->key, 'void 1', 'Phone failed inspection; voided on terminal');

        $this->assertSame(CheckoutAttemptStatus::VOIDED, $resolved->status);
        $this->assertSame(CardApprovalStatus::VOIDED, $approval->fresh()->status);
        $this->assertSame('VOID1', $approval->fresh()->void_reference);
        $this->assertSame($this->owner->id, $resolved->resolved_by);
        $this->assertValidationError(fn () => $this->finalize($cashier, $attempt->fresh()), 'attempt');
        $this->assertSame(0, Sale::query()->count());
    }

    #[Test]
    public function a_late_failure_rolls_back_everything_and_leaves_the_attempt_open_with_its_approval(): void
    {
        $cashier = $this->createCashier();
        $attempt = $this->review($cashier, [...$this->cableLine(2), ...$this->phoneLine()]);
        $this->approveCard($cashier, $attempt, $this->createTerminal(), '10000');

        SalePayment::creating(function (SalePayment $payment): void {
            if ($payment->method === SalePaymentMethod::CASH) {
                throw new RuntimeException('Simulated failure after lines, movements, and the card payment were written.');
            }
        });

        try {
            $this->finalize($cashier, $attempt->fresh(), '3000');
            $this->fail('The simulated failure should propagate.');
        } catch (RuntimeException) {
        }

        SalePayment::flushEventListeners();

        $this->assertSame(0, Sale::query()->count());
        $this->assertSame(0, SaleLine::query()->count());
        $this->assertSame(0, SalePayment::query()->count());
        $this->assertSame(0, InventoryMovement::query()->where('type', MovementType::SALE)->count());
        $this->assertSame(10, $this->cableStock());
        $this->assertSame(UnitStatus::AVAILABLE, $this->phone->fresh()->status);
        $this->assertSame(0, (int) SaleNumberSequence::query()->value('last_number'));

        $attempt->refresh();
        $this->assertSame(CheckoutAttemptStatus::OPEN, $attempt->status);
        $this->assertSame('system_error', $attempt->failure_code);
        $this->assertSame(CardApprovalStatus::APPROVED, $attempt->cardApproval->status);

        $sale = $this->finalize($cashier, $attempt, '3000');
        $this->assertSame('KT01-000001', $sale->number);
        $this->assertSame(1, $sale->payments()->where('method', SalePaymentMethod::CARD)->count());
    }

    #[Test]
    public function only_the_cashier_who_opened_an_attempt_can_operate_it(): void
    {
        $cashier = $this->createCashier();
        $other = $this->createCashier(name: 'Other');
        $attempt = $this->review($cashier, $this->cableLine(1));

        $this->expectAuthorizationFailure(fn () => $this->finalize($other, $attempt, '50'));
        $this->expectAuthorizationFailure(fn () => $this->approveCard($other, $attempt, $this->createTerminal(), '10'));
        $this->expectAuthorizationFailure(fn () => $this->review($other, $this->cableLine(2), $attempt));
        $this->expectAuthorizationFailure(fn () => app(CheckCheckoutStatus::class)($other, $this->store, $attempt->key));

        $this->assertSame(CheckoutAttemptStatus::OPEN, app(CheckCheckoutStatus::class)($this->owner, $this->store, $attempt->key)->status);
    }

    #[Test]
    public function a_cashier_who_loses_counter_access_mid_checkout_cannot_finish_it(): void
    {
        $cashier = $this->createCashier();
        $attempt = $this->review($cashier, $this->cableLine(1));

        $cashier->revokePermissionTo('use_pos');

        $this->expectAuthorizationFailure(fn () => $this->finalize($cashier->fresh(), $attempt, '50'));

        $this->store->employees()->detach($cashier);
        $cashier->givePermissionTo('use_pos');
        $this->expectAuthorizationFailure(fn () => $this->finalize($cashier->fresh(), $attempt, '50'));
        $this->assertSame(10, $this->cableStock());
    }

    #[Test]
    public function units_and_attempts_of_another_store_are_not_reachable(): void
    {
        $otherStore = Store::factory()->create(['code' => 'KT02']);
        $cashier = $this->createCashier();
        $otherStore->employees()->attach($cashier);
        $otherPhone = $this->registerDevice($this->owner, $otherStore, $this->usedPhone, self::imei('35693803564414'));
        $otherPhone->update(['selling_price' => '9000.00']);

        $this->assertValidationError(fn () => $this->review($cashier, $this->phoneLine($otherPhone)), 'lines.u'.$otherPhone->id.'.item');

        $attempt = $this->review($cashier, $this->cableLine(1));
        $this->expectException(ModelNotFoundException::class);
        app(FinalizeCheckout::class)($cashier, $otherStore, $attempt->key, $attempt->revision, '50');
    }

    #[Test]
    public function cancelling_an_unpaid_attempt_closes_it_without_touching_stock(): void
    {
        $cashier = $this->createCashier();
        $attempt = $this->review($cashier, $this->cableLine(2));

        $cancelled = app(CancelCheckoutAttempt::class)($cashier, $this->store, $attempt->key);

        $this->assertSame(CheckoutAttemptStatus::CANCELLED, $cancelled->status);
        $this->assertValidationError(fn () => $this->finalize($cashier, $attempt, '100'), 'attempt');
        $this->assertSame(10, $this->cableStock());
    }

    #[Test]
    public function completed_sales_lines_and_payments_cannot_be_changed(): void
    {
        $cashier = $this->createCashier();
        $sale = $this->finalize($cashier, $this->review($cashier, $this->cableLine(1)), '50');

        foreach ([fn () => $sale->update(['total' => '1.00']), fn () => $sale->delete(), fn () => $sale->lines->first()->update(['quantity' => 5]), fn () => $sale->payments->first()->delete()] as $mutation) {
            try {
                $mutation();
                $this->fail('Completed sale records must be immutable.');
            } catch (\LogicException) {
            }
        }

        $this->assertSame('49.90', $sale->fresh()->total);
    }

    private function assertValidationError(callable $callback, string $field): void
    {
        try {
            $callback();
            $this->fail("Expected a validation error on {$field}.");
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors(), 'Errors: '.json_encode($exception->errors()));
        }
    }

    private function expectAuthorizationFailure(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected an authorization failure.');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }
    }
}
