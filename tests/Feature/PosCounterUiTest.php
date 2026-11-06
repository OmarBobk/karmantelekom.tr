<?php

declare(strict_types=1);

namespace Tests\Feature;

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
            ->assertSee('12,599.80')
            ->call('review')
            ->assertHasNoErrors()
            ->assertSet('step', 'payment')
            ->set('cardTerminalId', $terminal->id)
            ->set('cardAmount', '10000')
            ->set('cardReference', 'A12345')
            ->call('recordCardApproval')
            ->assertHasNoErrors()
            ->set('cashTendered', '3000')
            ->call('finalize')
            ->assertHasNoErrors()
            ->assertSet('step', 'done')
            ->assertSet('cart', [])
            ->assertSee('KT01-000001')
            ->assertSee('400.20')
            ->assertDispatched('pos-sale-completed');

        $sale = Sale::query()->sole();
        $this->assertSame($sale->id, $counter->get('lastSaleId'));
        $this->assertSame(8, $this->cableStock());
        $this->assertSame(UnitStatus::SOLD, $this->phone->fresh()->status);

        $this->actingAs($this->cashier)->get(route('pos.sales.show', [$this->store, $sale]).'?print=1')
            ->assertOk()
            ->assertSee('KT01-000001')
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
    public function quantity_cannot_exceed_stock_and_only_the_owner_can_adjust_prices(): void
    {
        $key = 'd'.$this->cable->id;

        $this->counter()
            ->call('scan', $this->cable->barcode)
            ->call('setQuantity', $key, 11)
            ->assertSet("cart.{$key}.quantity", 1)
            ->call('setQuantity', $key, 10)
            ->assertSet("cart.{$key}.quantity", 10)
            ->call('openAdjustment', $key)
            ->assertForbidden();

        Livewire::actingAs($this->owner)->test(PosCounter::class, ['store' => $this->store])
            ->call('scan', $this->cable->barcode)
            ->call('openAdjustment', $key)
            ->set('adjustedPrice', '45')
            ->call('saveAdjustment')
            ->assertHasErrors('adjustmentReason')
            ->set('adjustmentReason', 'Bundle')
            ->call('saveAdjustment')
            ->assertHasNoErrors()
            ->call('review')
            ->assertSet('step', 'payment')
            ->assertSee('45.00');
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

    private function counter(): Testable
    {
        return Livewire::actingAs($this->cashier)->test(PosCounter::class, ['store' => $this->store]);
    }
}
