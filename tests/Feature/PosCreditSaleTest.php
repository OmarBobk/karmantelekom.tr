<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Pos\ReviewCheckout;
use App\Enums\MovementType;
use App\Enums\UnitStatus;
use App\Livewire\Pos\PosCounter;
use App\Livewire\Pos\SalesHistory;
use App\Models\CheckoutAttempt;
use App\Models\Customer;
use App\Models\CustomerDebt;
use App\Models\InventoryMovement;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\OperationalStoreSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\InteractsWithPos;
use Tests\TestCase;

class PosCreditSaleTest extends TestCase
{
    use InteractsWithPos;
    use RefreshDatabase;

    private Customer $customer;

    private Store $otherStore;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCounter();
        $this->useEnglishLocale();
        $this->customer = Customer::factory()->atCounter($this->store, '05321234567')->create(['name' => 'Ahmet Yılmaz']);
        $this->otherStore = Store::factory()->create(['code' => 'KT02']);
    }

    #[Test]
    public function a_partial_credit_sale_records_the_sale_stock_payment_received_and_one_debt_and_replays(): void
    {
        $attempt = $this->reviewWithCredit($this->owner, $this->phoneLine(), '8750');

        $this->assertSame(['8750.00', $this->owner->id, '12500.00'], [$attempt->credit_amount, $attempt->credit_authorized_by, $attempt->total]);

        $sale = $this->finalize($this->owner, $attempt, '4000');
        $replay = $this->finalize($this->owner, $attempt, '4000');

        $this->assertSame($sale->id, $replay->id);
        $this->assertSame(1, Sale::query()->count());
        $this->assertSame(['12500.00', $this->customer->id], [$sale->total, $sale->customer_id]);

        $payment = SalePayment::query()->sole();
        $this->assertSame(['cash', '3750.00', '4000.00', '250.00'], [$payment->method->value, $payment->amount, $payment->cash_tendered, $payment->change_given]);

        $debt = CustomerDebt::query()->sole();
        $this->assertSame(
            [$this->store->id, $this->customer->id, $sale->id, '8750.00', $this->owner->id, $this->owner->id],
            [$debt->store_id, $debt->customer_id, $debt->sale_id, $debt->original_amount, $debt->authorized_by, $debt->user_id],
        );
        $this->assertSame([375_000, 875_000], [$sale->fresh()->paidMinor(), $sale->fresh()->creditMinor()]);

        $this->assertSame(UnitStatus::SOLD, $this->phone->fresh()->status);
        $this->assertSame(1, InventoryMovement::query()->where('type', MovementType::SALE)->count());
    }

    #[Test]
    public function a_fully_unpaid_sale_takes_no_payment_and_owes_the_whole_total(): void
    {
        $attempt = $this->reviewWithCredit($this->owner, $this->cableLine(2), '99,80');

        try {
            $this->finalize($this->owner, $attempt, '10');
            $this->fail('Cash must be refused when nothing is payable.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('cash_tendered', $exception->errors());
        }

        $sale = $this->finalize($this->owner, $attempt);

        $this->assertNull($sale->cash_tendered);
        $this->assertSame(0, SalePayment::query()->count());
        $this->assertSame('99.80', CustomerDebt::query()->sole()->original_amount);
        $this->assertSame([0, 9_980], [$sale->paidMinor(), $sale->creditMinor()]);
        $this->assertSame(8, $this->cableStock());
    }

    #[Test]
    public function credit_needs_owner_authority_a_branch_customer_and_at_most_the_total(): void
    {
        $cashier = $this->createCashier();
        $theirs = Customer::factory()->atCounter($this->otherStore)->create();

        $this->assertCreditRejected(fn () => $this->reviewWithCredit($cashier, $this->cableLine(1), '10'), __('pos.credit.errors.not_allowed'));
        $this->assertCreditRejected(fn () => $this->reviewWithCredit($this->owner, $this->cableLine(1), '10', null), __('pos.credit.errors.customer_required'));
        $this->assertCreditRejected(fn () => $this->reviewWithCredit($this->owner, $this->cableLine(1), '49.91'), __('pos.credit.errors.above_total', ['total' => '49,90']));

        try {
            $this->reviewWithCredit($this->owner, $this->cableLine(1), '10', $theirs->id);
            $this->fail('A customer of another branch must be refused.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('customer', $exception->errors());
        }

        $cashier->givePermissionTo(OperationalStoreSetupSeeder::SELL_ON_CREDIT_PERMISSION);
        $this->assertCreditRejected(fn () => $this->reviewWithCredit($cashier->fresh(), $this->cableLine(1), '10'), __('pos.credit.errors.not_allowed'));
        $this->assertSame(0, CheckoutAttempt::query()->count());

        $attempt = $this->reviewWithCredit($this->owner, $this->cableLine(1), '10');
        $this->revokeFromOwners(OperationalStoreSetupSeeder::SELL_ON_CREDIT_PERMISSION);

        $this->assertCreditRejected(fn () => $this->finalize($this->owner, $attempt, '39.90'), __('pos.credit.errors.not_allowed'));
        $this->assertCreditRejected(fn () => $this->startTerminalPayment($this->owner, $attempt, $this->createTerminal(), '39.90'), __('pos.credit.errors.not_allowed'));
        $this->assertFalse($attempt->fresh()->isFrozen());
        $this->assertSame([0, 0], [Sale::query()->count(), CustomerDebt::query()->count()]);
    }

    #[Test]
    public function changing_the_credit_is_a_new_revision_and_a_fully_paid_review_hashes_as_before(): void
    {
        $attempt = $this->reviewWithCredit($this->owner, $this->cableLine(1), '');

        $this->assertSame(['0.00', null], [$attempt->fresh()->credit_amount, $attempt->credit_authorized_by]);
        $this->assertSame(ReviewCheckout::payloadHash($this->store, $attempt->lines, $this->customer->id), $attempt->payload_hash);

        $credited = $this->reviewWithCredit($this->owner, $this->cableLine(1), '20', attempt: $attempt);
        $this->assertSame([2, '20.00'], [$credited->revision, $credited->credit_amount]);
        $this->assertSame(2, $this->reviewWithCredit($this->owner, $this->cableLine(1), '20.00', attempt: $credited)->revision);

        try {
            $this->finalize($this->owner, $attempt, '49.90');
            $this->fail('A finalize bound to the fully paid review must be refused.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('attempt', $exception->errors());
        }

        $back = $this->reviewWithCredit($this->owner, $this->cableLine(1), '0', attempt: $credited->fresh());
        $this->assertSame([3, '0.00', null, $attempt->payload_hash], [$back->revision, $back->credit_amount, $back->credit_authorized_by, $back->payload_hash]);
        $this->assertNull($this->finalize($this->owner, $back, '49.90')->debt);
    }

    #[Test]
    public function the_card_charge_is_capped_at_the_total_less_credit_before_it_starts(): void
    {
        $terminal = $this->createTerminal();
        $attempt = $this->reviewWithCredit($this->owner, $this->phoneLine(), '8750');

        try {
            $this->startTerminalPayment($this->owner, $attempt, $terminal, '3750.01');
            $this->fail('A charge above the payable amount must be refused.');
        } catch (ValidationException $exception) {
            $this->assertSame([__('pos.credit.errors.card_above_payable', ['amount' => '3.750,00'])], $exception->errors()['card_amount']);
        }

        $this->assertFalse($attempt->fresh()->isFrozen());

        $full = $this->reviewWithCredit($this->owner, $this->phoneLine(), '12500', attempt: $attempt);
        $this->expectException(ValidationException::class);
        $this->startTerminalPayment($this->owner, $full, $terminal, '1');
    }

    #[Test]
    public function a_frozen_credit_checkout_completes_with_the_recorded_credit_and_authorizer(): void
    {
        $terminal = $this->createTerminal();
        $attempt = $this->reviewWithCredit($this->owner, $this->phoneLine(), '8750');
        $this->approveCard($this->owner, $attempt, $terminal, '3750');

        try {
            $this->reviewWithCredit($this->owner, $this->phoneLine(), '1000', attempt: $attempt->fresh());
            $this->fail('A frozen checkout must keep its credit.');
        } catch (ValidationException $exception) {
            $this->assertSame([__('pos.errors.attempt_frozen')], $exception->errors()['attempt']);
        }

        $this->revokeFromOwners(OperationalStoreSetupSeeder::SELL_ON_CREDIT_PERMISSION);

        $sale = $this->finalize($this->owner, $attempt->fresh());

        $this->assertSame(['card', '3750.00'], [SalePayment::query()->sole()->method->value, SalePayment::query()->sole()->amount]);
        $this->assertSame(['8750.00', $this->owner->id], [$sale->debt->original_amount, $sale->debt->authorized_by]);
        $this->assertSame($sale->id, $this->finalize($this->owner, $attempt->fresh())->id);
        $this->assertSame(1, CustomerDebt::query()->count());
    }

    #[Test]
    public function the_counter_reviews_credit_again_after_a_change_and_shows_paid_and_credit_amounts(): void
    {
        $counter = Livewire::actingAs($this->owner)->test(PosCounter::class, ['store' => $this->store])
            ->call('scan', $this->phoneImei)
            ->call('selectCustomer', $this->customer->id)
            ->set('creditAmount', '8.750')
            ->call('review')
            ->assertHasNoErrors()
            ->assertSet('step', 'payment')
            ->assertSee(['8.750,00', '3.750,00']);

        $attempt = CheckoutAttempt::query()->sole();
        $this->assertSame([1, '8750.00'], [$attempt->revision, $attempt->credit_amount]);

        $counter->set('creditAmount', '9.000')->assertSet('step', 'cart');

        Livewire::actingAs($this->owner)->test(PosCounter::class, ['store' => $this->store])
            ->assertSet('creditAmount', '9.000')
            ->call('checkStatus')
            ->assertSet('step', 'cart')
            ->set('cashTendered', '3.750')
            ->call('finalize')
            ->assertHasErrors('credit_amount')
            ->call('review')
            ->assertSet('attemptRevision', 2)
            ->set('cashTendered', '3.500')
            ->call('finalize')
            ->assertHasNoErrors()
            ->assertSet('step', 'done')
            ->assertSet('creditAmount', '')
            ->assertSee([__('pos.fields.amount_paid'), '3.500,00', __('pos.fields.credit'), '9.000,00']);

        $sale = Sale::query()->sole();
        $this->assertSame('9000.00', $sale->debt->original_amount);

        Livewire::actingAs($this->owner)->test(SalesHistory::class, ['store' => $this->store])
            ->assertSee([$sale->number, '3.500,00', '9.000,00']);

        $this->actingAs($this->owner)->get(route('pos.sales.show', [$this->store, $sale]))
            ->assertOk()
            ->assertSeeInOrder(['12.500,00', __('pos.fields.amount_paid'), '3.500,00', __('pos.fields.credit'), '9.000,00'])
            ->assertDontSee('Ahmet Yılmaz');
    }

    /**
     * @param  array<string, array<string, mixed>>  $cart
     */
    private function reviewWithCredit(User $actor, array $cart, string $credit, ?int $customerId = -1, ?CheckoutAttempt $attempt = null): CheckoutAttempt
    {
        return app(ReviewCheckout::class)($actor, $this->store, $cart, $attempt?->key, $attempt?->revision, $customerId === -1 ? $this->customer->id : $customerId, $credit);
    }

    private function assertCreditRejected(callable $action, string $message): void
    {
        try {
            $action();
            $this->fail('The credit must be refused.');
        } catch (ValidationException $exception) {
            $this->assertSame([$message], $exception->errors()['credit_amount'] ?? $exception->errors());
        }
    }
}
