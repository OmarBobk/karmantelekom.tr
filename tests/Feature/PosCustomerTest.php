<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Customers\FindCounterCustomers;
use App\Actions\Customers\SaveCounterCustomer;
use App\Actions\Pos\ReviewCheckout;
use App\Livewire\Pos\PosCounter;
use App\Livewire\Pos\SalesHistory;
use App\Models\CheckoutAttempt;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use App\Services\Idempotency;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\InteractsWithPos;
use Tests\TestCase;

class PosCustomerTest extends TestCase
{
    use InteractsWithPos;
    use RefreshDatabase;

    private User $cashier;

    private Store $otherStore;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCounter();
        $this->useEnglishLocale();
        $this->cashier = $this->createCashier();
        $this->otherStore = Store::factory()->create(['code' => 'KT02', 'name' => 'Karman Aksaray']);
    }

    #[Test]
    public function a_phone_number_already_on_file_returns_that_customer_unchanged(): void
    {
        $customer = $this->saveCustomer($this->cashier, 'Ahmet Yılmaz', '0532 123 45 67');

        $this->assertTrue($customer->wasRecentlyCreated);
        $this->assertSame(['store_id' => $this->store->id, 'phone_normalized' => '905321234567', 'created_by' => $this->cashier->id], $customer->only('store_id', 'phone_normalized', 'created_by'));

        $again = $this->saveCustomer($this->createCashier(name: 'Second'), 'Someone Else', '+90 (532) 123-45-67');

        $this->assertFalse($again->wasRecentlyCreated);
        $this->assertSame($customer->id, $again->id);
        $this->assertSame(['Ahmet Yılmaz', '0532 123 45 67', $this->cashier->id], [$again->name, $again->phone, $again->created_by]);
        $this->assertSame(1, Customer::query()->count());

        foreach (['5321234567', '00905321234567', '٠٥٣٢١٢٣٤٥٦٧'] as $phone) {
            $this->assertSame([$customer->id], $this->find($this->cashier, $phone)->modelKeys(), $phone);
        }

        $this->assertSame([$customer->id], $this->find($this->cashier, 'yılm')->modelKeys());
        $this->assertSame([], $this->find($this->cashier, 'a')->modelKeys());
        $this->assertSame([], $this->find($this->cashier, '0532 123')->modelKeys());
        $this->assertSame('••• 4567', $customer->maskedPhone());
    }

    #[Test]
    public function a_customer_needs_a_name_and_a_valid_phone(): void
    {
        foreach ([['', '05321234567', 'customer_name'], ['Ahmet', '', 'customer_phone'], ['Ahmet', '0532-ABC', 'customer_phone'], ['Ahmet', '123', 'customer_phone']] as [$name, $phone, $field]) {
            try {
                $this->saveCustomer($this->cashier, $name, $phone);
                $this->fail("Expected {$field} to be rejected.");
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey($field, $exception->errors());
            }
        }

        $this->assertSame(0, Customer::query()->count());
    }

    #[Test]
    public function lookup_stays_in_the_active_store_and_never_returns_customers_without_one(): void
    {
        Customer::factory()->create(['name' => 'Ahmet Legacy', 'phone' => '05321234567']);
        $ours = Customer::factory()->atCounter($this->store, '05321234567')->create(['name' => 'Ahmet Fatih']);
        $theirs = Customer::factory()->atCounter($this->otherStore, '05321234567')->create(['name' => 'Ahmet Aksaray']);
        $otherCashier = $this->createCashier($this->otherStore, name: 'Aksaray cashier');

        $this->assertSame([$ours->id], $this->find($this->cashier, '05321234567')->modelKeys());
        $this->assertSame([$ours->id], $this->find($this->cashier, 'Ahmet')->modelKeys());
        $this->assertSame([$theirs->id], $this->find($otherCashier, 'Ahmet', $this->otherStore)->modelKeys());
        $this->assertSame([$ours->id], $this->find($this->owner, 'Ahmet')->modelKeys());
        $this->assertSame([$theirs->id], $this->find($this->owner, 'Ahmet', $this->otherStore)->modelKeys());

        $this->expectException(AuthorizationException::class);
        $this->find($this->cashier, 'Ahmet', $this->otherStore);
    }

    #[Test]
    public function review_rejects_a_customer_of_another_store_or_without_a_store(): void
    {
        $legacy = Customer::factory()->create();
        $theirs = Customer::factory()->atCounter($this->otherStore)->create();

        $this->assertCustomerRejected(fn () => $this->reviewFor($this->cashier, $this->cableLine(1), $theirs->id));
        $this->assertSame(0, CheckoutAttempt::query()->count());

        $attempt = $this->review($this->cashier, $this->cableLine(1));

        $this->assertCustomerRejected(fn () => $this->reviewFor($this->cashier, $this->cableLine(1), $legacy->id, $attempt));
        $this->assertCustomerRejected(fn () => $this->reviewFor($this->cashier, $this->cableLine(1), 999_999, $attempt));
        $this->assertSame([1, null], [$attempt->fresh()->revision, $attempt->fresh()->customer_id]);
    }

    #[Test]
    public function choosing_a_customer_is_a_new_revision_and_a_walk_in_hashes_as_before(): void
    {
        $customer = Customer::factory()->atCounter($this->store)->create();
        $attempt = $this->review($this->cashier, $this->cableLine(1));
        $walkInHash = $attempt->payload_hash;

        $this->assertNull($attempt->customer_id);
        $this->assertSame(ReviewCheckout::payloadHash($this->store, $attempt->lines), $walkInHash);

        $withCustomer = $this->reviewFor($this->cashier, $this->cableLine(1), $customer->id, $attempt);
        $this->assertSame([2, $customer->id], [$withCustomer->revision, $withCustomer->customer_id]);
        $this->assertNotSame($walkInHash, $withCustomer->payload_hash);

        $this->assertSame(2, $this->reviewFor($this->cashier, $this->cableLine(1), $customer->id, $withCustomer)->revision);

        try {
            $this->finalize($this->cashier, $attempt, '50');
            $this->fail('A finalize bound to the walk-in review must be refused.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('attempt', $exception->errors());
        }

        $backToWalkIn = $this->review($this->cashier, $this->cableLine(1), $withCustomer->fresh());
        $this->assertSame([3, null, $walkInHash], [$backToWalkIn->revision, $backToWalkIn->customer_id, $backToWalkIn->payload_hash]);
        $this->assertSame(0, Sale::query()->count());
    }

    #[Test]
    public function a_started_terminal_payment_freezes_the_customer(): void
    {
        $customer = Customer::factory()->atCounter($this->store)->create();
        $other = Customer::factory()->atCounter($this->store)->create();
        $attempt = $this->reviewFor($this->cashier, $this->cableLine(1), $customer->id);
        $frozen = $this->startTerminalPayment($this->cashier, $attempt, $this->createTerminal(), '49.90');

        foreach ([$other->id, null] as $changed) {
            try {
                $this->reviewFor($this->cashier, $this->cableLine(1), $changed, $frozen);
                $this->fail('A frozen attempt must keep its customer.');
            } catch (ValidationException $exception) {
                $this->assertSame([__('pos.errors.attempt_frozen')], $exception->errors()['attempt']);
            }
        }

        $this->assertSame([$frozen->revision, $customer->id], [$frozen->fresh()->revision, $frozen->fresh()->customer_id]);

        Livewire::actingAs($this->cashier)->test(PosCounter::class, ['store' => $this->store])
            ->call('resumeAttempt', $frozen->key)
            ->assertSet('customerId', $customer->id)
            ->call('selectCustomer', $other->id)
            ->call('clearCustomer')
            ->assertSet('customerId', $customer->id)
            ->assertSee($customer->name);
    }

    #[Test]
    public function finalizing_copies_the_reviewed_customer_and_a_replay_returns_the_same_sale(): void
    {
        $customer = Customer::factory()->atCounter($this->store)->create();
        $attempt = $this->reviewFor($this->cashier, $this->cableLine(1), $customer->id);

        $sale = $this->finalize($this->cashier, $attempt, '50');
        $replay = $this->finalize($this->cashier, $attempt, '50');

        $this->assertSame($customer->id, $sale->customer_id);
        $this->assertSame($sale->id, $replay->id);
        $this->assertSame($customer->id, $replay->customer_id);
        $this->assertSame(1, Sale::query()->count());
    }

    #[Test]
    public function a_walk_in_attempt_from_before_customers_still_reviews_finalizes_and_replays(): void
    {
        $legacy = $this->review($this->cashier, $this->cableLine(1));
        $hashBeforeCustomers = Idempotency::fingerprint([
            'store_id' => $this->store->id,
            'lines' => array_map(fn (array $line): array => [
                $line['key'], $line['definition_id'], $line['unit_id'], $line['unit_revision'],
                $line['quantity'], $line['list_unit_price'], $line['unit_price'], $line['adjustment_reason'],
                $line['discount_limit'] ?? null, $line['discount_basis_points'] ?? null,
                $line['lowest_unit_price'] ?? null, $line['discounted_by'] ?? null,
            ], $legacy->lines),
        ]);

        $this->assertNull($legacy->customer_id);
        $this->assertSame($hashBeforeCustomers, $legacy->payload_hash);
        $this->assertSame(1, $this->review($this->cashier, $this->cableLine(1), $legacy)->revision);

        $sale = $this->finalize($this->cashier, $legacy, '50');

        $this->assertNull($sale->customer_id);
        $this->assertSame($sale->id, $this->finalize($this->cashier, $legacy, '50')->id);
    }

    #[Test]
    public function the_counter_adds_a_customer_reviews_again_after_a_change_and_restores_it_on_resume(): void
    {
        $existing = Customer::factory()->atCounter($this->store, '05559876543')->create(['name' => 'Zeynep Kaya']);
        $theirs = Customer::factory()->atCounter($this->otherStore)->create();

        $counter = Livewire::actingAs($this->cashier)->test(PosCounter::class, ['store' => $this->store])
            ->call('scan', $this->cable->barcode)
            ->assertSee(__('pos.customer.walk_in'))
            ->call('openCustomer')
            ->set('customerSearch', 'Ali Demir')
            ->call('searchCustomers')
            ->assertSee(__('pos.customer.no_results', ['term' => 'Ali Demir']))
            ->assertSet('newCustomerName', 'Ali Demir')
            ->set('newCustomerPhone', '0532 111 22 33')
            ->call('createCustomer')
            ->assertHasNoErrors()
            ->assertDispatched('close-dialog', id: PosCounter::CUSTOMER_DIALOG)
            ->assertSee(['Ali Demir', '••• 2233'])
            ->call('review')
            ->assertSet('step', 'payment');

        $created = Customer::query()->where('name', 'Ali Demir')->sole();
        $attempt = CheckoutAttempt::query()->sole();
        $this->assertSame([1, $created->id], [$attempt->revision, $attempt->customer_id]);

        $counter->set('customerSearch', '+90 555 987 65 43')
            ->call('searchCustomers')
            ->assertSet('customerResults', [['id' => $existing->id, 'name' => 'Zeynep Kaya', 'phone' => '••• 6543']])
            ->call('selectCustomer', $existing->id)
            ->assertSet('step', 'cart')
            ->call('selectCustomer', $theirs->id)
            ->assertSet('customerId', $existing->id)
            ->call('review')
            ->assertSet('step', 'payment')
            ->assertSet('attemptRevision', 2);

        $this->assertSame($existing->id, $attempt->fresh()->customer_id);

        session()->flush();

        Livewire::actingAs($this->cashier)->test(PosCounter::class, ['store' => $this->store])
            ->assertSet('customerId', null)
            ->call('resumeAttempt', $attempt->key)
            ->assertSet('customerId', $existing->id)
            ->assertSet('attemptRevision', 2)
            ->assertSee('Zeynep Kaya')
            ->set('cashTendered', '50')
            ->call('finalize')
            ->assertSet('step', 'done')
            ->assertSet('customerId', null);

        $this->assertSame($existing->id, Sale::query()->sole()->customer_id);
    }

    #[Test]
    public function sales_history_shows_the_customer_to_those_who_may_see_the_sale_but_the_receipt_does_not(): void
    {
        $customer = Customer::factory()->atCounter($this->store, '05321234567')->create(['name' => 'Ahmet Yılmaz']);
        $sale = $this->finalize($this->cashier, $this->reviewFor($this->cashier, $this->cableLine(1), $customer->id), '50');
        $colleague = $this->createCashier(name: 'Colleague');

        foreach ([$this->cashier, $this->owner] as $viewer) {
            Livewire::actingAs($viewer)->test(SalesHistory::class, ['store' => $this->store])
                ->assertSee([$sale->number, 'Ahmet Yılmaz', '••• 4567'])
                ->assertDontSee('05321234567');
        }

        Livewire::actingAs($colleague)->test(SalesHistory::class, ['store' => $this->store])
            ->assertDontSee([$sale->number, 'Ahmet Yılmaz']);

        $this->actingAs($this->cashier)->get(route('pos.sales.show', [$this->store, $sale]).'?print=1')
            ->assertOk()
            ->assertSee($sale->number)
            ->assertDontSee(['Ahmet Yılmaz', '4567']);
    }

    private function saveCustomer(User $actor, string $name, string $phone, ?Store $store = null): Customer
    {
        return app(SaveCounterCustomer::class)($actor, $store ?? $this->store, $name, $phone);
    }

    /**
     * @return Collection<int, Customer>
     */
    private function find(User $actor, string $term, ?Store $store = null): Collection
    {
        return app(FindCounterCustomers::class)($actor, $store ?? $this->store, $term);
    }

    /**
     * @param  array<string, array<string, mixed>>  $cart
     */
    private function reviewFor(User $actor, array $cart, ?int $customerId, ?CheckoutAttempt $attempt = null): CheckoutAttempt
    {
        return app(ReviewCheckout::class)($actor, $this->store, $cart, $attempt?->key, $attempt?->revision, $customerId);
    }

    private function assertCustomerRejected(callable $review): void
    {
        try {
            $review();
            $this->fail('The customer must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertSame([__('pos.customer.errors.unavailable')], $exception->errors()['customer']);
        }
    }
}
