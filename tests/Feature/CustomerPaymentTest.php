<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Customers\RecordCustomerPayment;
use App\Exceptions\Inventory\IdempotencyConflictException;
use App\Livewire\Pos\CustomerAccountPage;
use App\Livewire\Pos\CustomerAccounts;
use App\Models\Customer;
use App\Models\CustomerDebt;
use App\Models\CustomerPayment;
use App\Models\CustomerPaymentAllocation;
use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use App\Services\Customers\CustomerAccount;
use Database\Seeders\OperationalStoreSetupSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\InteractsWithPos;
use Tests\TestCase;

class CustomerPaymentTest extends TestCase
{
    use InteractsWithPos;
    use RefreshDatabase;

    private Customer $customer;

    private Store $otherStore;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCounter(cableStock: 50);
        $this->useEnglishLocale();
        $this->customer = Customer::factory()->atCounter($this->store, '05321234567')->create(['name' => 'Ahmet Yılmaz']);
        $this->otherStore = Store::factory()->create(['code' => 'KT02']);
    }

    #[Test]
    public function a_partial_cash_payment_reduces_the_balance_with_a_receipt_number_and_leaves_the_debt_and_sale_unchanged(): void
    {
        $sale = $this->creditSale($this->customer, 10, '300', '199');

        $payment = $this->pay(['amount' => '120.50', 'method' => 'cash'], 'key-1');

        $this->assertSame(['KT01-CP-000001', '120.50', null, $this->owner->id], [$payment->receipt_number, $payment->amount, $payment->reference, $payment->user_id]);
        $this->assertSame([[$sale->debt->id, '120.50']], $payment->allocations->map(fn ($allocation) => [$allocation->customer_debt_id, $allocation->amount])->all());
        $this->assertSame(17_950, $this->totalDue());
        $this->assertSame(['300.00', '499.00'], [CustomerDebt::query()->sole()->original_amount, Sale::query()->sole()->total]);

        $this->assertSame('KT01-CP-000002', $this->pay(['amount' => '9.50', 'method' => 'cash'], 'key-2')->receipt_number);
        $this->assertSame(17_000, $this->totalDue());
    }

    #[Test]
    public function a_payment_is_allocated_to_the_oldest_sale_first_then_the_lower_sale_id(): void
    {
        $this->travelTo(Carbon::parse('2026-11-01 11:00:00'));
        $firstAtEleven = $this->creditSale($this->customer, 3, '100', '49.70');
        $this->travelTo(Carbon::parse('2026-11-01 10:00:00'));
        $atTen = $this->creditSale($this->customer, 2, '50', '49.80');
        $this->travelTo(Carbon::parse('2026-11-01 11:00:00'));
        $secondAtEleven = $this->creditSale($this->customer, 2, '80', '19.80');
        $this->travelBack();

        $payment = $this->pay(['amount' => '170', 'method' => 'bank_transfer', 'reference' => ' tr 2026 001 '], 'key-1');

        $this->assertSame(['tr 2026 001', 'TR2026001', null], [$payment->reference, $payment->reference_key, $payment->receipt_number]);
        $this->assertSame(
            [[$atTen->debt->id, '50.00'], [$firstAtEleven->debt->id, '100.00'], [$secondAtEleven->debt->id, '20.00']],
            $payment->allocations->map(fn ($allocation) => [$allocation->customer_debt_id, $allocation->amount])->all(),
        );
        $this->assertSame([$secondAtEleven->debt->id], app(CustomerAccount::class)->openDebts($this->customer)->modelKeys());
        $this->assertSame(6_000, $this->totalDue());
    }

    #[Test]
    public function zero_negative_malformed_and_excess_amounts_and_missing_references_are_refused(): void
    {
        $this->creditSale($this->customer, 10, '300', '199');

        foreach (['0', '0.00', '-5', '12.345', 'abc', ''] as $amount) {
            $this->assertRefused(['amount' => $amount, 'method' => 'cash'], 'amount');
        }

        $this->assertRefused(['amount' => '300.01', 'method' => 'cash'], 'amount', __('pos.customer_accounts.errors.amount_exceeds_due', ['due' => '300,00']));
        $this->assertRefused(['amount' => '10', 'method' => 'card', 'reference' => '  '], 'reference', __('pos.customer_accounts.errors.reference_required'));
        $this->assertRefused(['amount' => '10', 'method' => 'cheque'], 'method');

        $this->assertSame([0, 0], [CustomerPayment::query()->count(), CustomerPaymentAllocation::query()->count()]);
        $this->assertSame(30_000, $this->totalDue());
    }

    #[Test]
    public function only_the_owner_records_payments_and_only_for_customers_of_the_store(): void
    {
        $this->creditSale($this->customer, 10, '300', '199');
        $cashier = $this->createCashier();
        $cashier->givePermissionTo(OperationalStoreSetupSeeder::RECEIVE_CUSTOMER_PAYMENTS_PERMISSION);
        $theirs = Customer::factory()->atCounter($this->otherStore)->create();

        foreach ([
            fn () => $this->pay(['amount' => '10', 'method' => 'cash'], 'key-1', $cashier->fresh()),
            fn () => $this->pay(['amount' => '10', 'method' => 'cash'], 'key-2', store: $this->otherStore),
            fn () => $this->pay(['amount' => '10', 'method' => 'cash'], 'key-3', customer: $theirs),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('The payment must be refused.');
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->actingAs($cashier)->get(route('pos.customers', $this->store))->assertForbidden();
        $this->actingAs($cashier)->get(route('pos.customers.show', [$this->store, $this->customer]))->assertForbidden();
        $this->assertSame(0, CustomerPayment::query()->count());

        $this->withoutExceptionHandling();
        $this->expectException(ModelNotFoundException::class);
        $this->actingAs($this->owner)->get(route('pos.customers.show', [$this->store, $theirs]));
    }

    #[Test]
    public function the_owner_loses_access_when_the_permission_is_revoked(): void
    {
        $this->actingAs($this->owner)->get(route('pos.customers', $this->store))->assertOk();

        $this->revokeFromOwners(OperationalStoreSetupSeeder::RECEIVE_CUSTOMER_PAYMENTS_PERMISSION);

        $this->actingAs($this->owner)->get(route('pos.customers', $this->store))->assertForbidden();
    }

    #[Test]
    public function an_identical_replay_after_full_settlement_returns_the_original_and_a_changed_payload_conflicts(): void
    {
        $this->creditSale($this->customer, 10, '300', '199');
        $input = ['amount' => '300', 'method' => 'card', 'reference' => 'A12345'];

        $payment = $this->pay($input, 'key-1');
        $this->assertSame(0, $this->totalDue());

        $replay = $this->pay($input, 'key-1');
        $this->assertSame([$payment->id, false], [$replay->id, $replay->wasRecentlyCreated]);
        $this->assertSame($payment->allocations->modelKeys(), $replay->allocations->modelKeys());
        $this->assertSame($payment->id, $this->pay([...$input, 'reference' => 'a 12345'], 'retry-with-new-key')->id);

        try {
            $this->pay([...$input, 'amount' => '200'], 'key-1');
            $this->fail('A changed payload with the same key must conflict.');
        } catch (IdempotencyConflictException) {
            $this->addToAssertionCount(1);
        }

        $this->assertRefused([...$input, 'amount' => '1'], 'reference', __('pos.customer_accounts.errors.reference_used'), 'key-2');
        $this->assertRefused(['amount' => '1', 'method' => 'cash'], 'amount', __('pos.customer_accounts.errors.nothing_due'), 'key-3');
        $this->assertSame([1, 1], [CustomerPayment::query()->count(), CustomerPaymentAllocation::query()->count()]);
    }

    #[Test]
    public function the_account_screen_keeps_its_request_key_through_a_failure_and_renews_it_after_recording(): void
    {
        $sale = $this->creditSale($this->customer, 10, '300', '199');

        Livewire::actingAs($this->owner)->test(CustomerAccounts::class, ['store' => $this->store])
            ->assertSee(['Ahmet Yılmaz', '300,00']);

        $page = Livewire::actingAs($this->owner)->test(CustomerAccountPage::class, ['store' => $this->store, 'customer' => $this->customer])
            ->assertSee([$sale->number, '300,00']);
        $key = $page->get('requestKey');

        $page->set('amount', '300,01')
            ->call('record')
            ->assertHasErrors('amount')
            ->assertSet('requestKey', $key)
            ->set('amount', '120,50')
            ->call('record')
            ->assertHasNoErrors()
            ->assertSet('amount', '')
            ->assertSee(['KT01-CP-000001', '179,50']);

        $this->assertNotSame($key, $page->get('requestKey'));
        $this->assertSame($key, CustomerPayment::query()->sole()->idempotency_key);
    }

    #[Test]
    public function a_fully_settled_account_is_found_under_settled_and_keeps_its_history_with_entry_disabled(): void
    {
        $sale = $this->creditSale($this->customer, 10, '300', '199');
        Customer::factory()->atCounter($this->store)->create(['name' => 'Never Owed']);
        $this->pay(['amount' => '300', 'method' => 'cash'], 'key-1');

        Livewire::actingAs($this->owner)->test(CustomerAccounts::class, ['store' => $this->store])
            ->assertSet('balance', 'open')
            ->assertDontSee('Ahmet Yılmaz')
            ->call('showBalance', 'settled')
            ->assertSee(['Ahmet Yılmaz', __('pos.customer_accounts.settled')])
            ->assertDontSee('Never Owed')
            ->set('search', 'ahmet')
            ->call('applySearch')
            ->assertSee('Ahmet Yılmaz');

        Livewire::actingAs($this->owner)->test(CustomerAccountPage::class, ['store' => $this->store, 'customer' => $this->customer])
            ->assertSee([__('pos.customer_accounts.no_open_debts'), __('pos.customer_accounts.nothing_to_record'), 'KT01-CP-000001', $sale->number])
            ->set('amount', '1')
            ->call('record')
            ->assertHasErrors('amount');

        $this->assertSame(1, CustomerPayment::query()->count());
    }

    private function pay(array $input, string $key, ?User $actor = null, ?Store $store = null, ?Customer $customer = null): CustomerPayment
    {
        return app(RecordCustomerPayment::class)($actor ?? $this->owner, $store ?? $this->store, $customer ?? $this->customer, $input, $key);
    }

    private function totalDue(): int
    {
        return CustomerAccount::totalDueMinor(app(CustomerAccount::class)->openDebts($this->customer));
    }

    private function assertRefused(array $input, string $field, ?string $message = null, string $key = 'refused'): void
    {
        try {
            $this->pay($input, $key);
            $this->fail("Expected {$field} to be refused.");
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());

            if ($message !== null) {
                $this->assertSame([$message], $exception->errors()[$field]);
            }
        }
    }
}
