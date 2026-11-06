<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Backend\Stores\StoreDetail;
use App\Livewire\Pos\PosCounter;
use App\Livewire\Pos\PosHome;
use App\Livewire\Pos\SalesHistory;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\InteractsWithPos;
use Tests\TestCase;

class PosAccessTest extends TestCase
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
    public function guests_are_sent_to_login(): void
    {
        $this->get(route('pos.home'))->assertRedirect();
        $this->get(route('pos.counter', $this->store))->assertRedirect();
        $this->get(route('pos.sales', $this->store))->assertRedirect();
    }

    #[Test]
    public function an_assigned_employee_with_counter_access_opens_their_store_but_not_the_admin_area(): void
    {
        $cashier = $this->createCashier();

        $this->actingAs($cashier)->get(route('pos.counter', $this->store))->assertOk()->assertSee('KT01');
        $this->actingAs($cashier)->get(route('pos.sales', $this->store))->assertOk();
        $this->actingAs($cashier)->get(route('subdomain.inventory'))->assertRedirect();
        $this->actingAs($cashier)->get(route('subdomain.inventory.store', $this->store))->assertRedirect();
    }

    #[Test]
    public function the_store_picker_opens_a_single_store_directly(): void
    {
        $cashier = $this->createCashier();

        Livewire::actingAs($cashier)->test(PosHome::class)->assertRedirect(route('pos.counter', $this->store));
    }

    #[Test]
    public function employees_without_the_permission_or_the_assignment_are_refused(): void
    {
        $withoutPermission = $this->createCashier(withPos: false);
        $otherStore = Store::factory()->create(['code' => 'KT02']);
        $elsewhere = $this->createCashier($otherStore, name: 'Elsewhere');
        $salesperson = User::factory()->create()->assignRole('salesperson');
        $salesperson->givePermissionTo('use_pos');
        $this->store->employees()->attach($salesperson);

        foreach ([$withoutPermission, $elsewhere] as $user) {
            $this->actingAs($user)->get(route('pos.counter', $this->store))->assertForbidden();
            $this->actingAs($user)->get(route('pos.sales', $this->store))->assertForbidden();
        }

        $this->actingAs($salesperson->fresh())->get(route('pos.counter', $this->store))->assertForbidden();

        Livewire::actingAs($withoutPermission)->test(PosHome::class)->assertSee(__('pos.home.no_access'));
    }

    #[Test]
    public function revoking_access_or_the_assignment_blocks_the_next_livewire_request(): void
    {
        $cashier = $this->createCashier();
        $counter = Livewire::actingAs($cashier)->test(PosCounter::class, ['store' => $this->store]);

        $cashier->revokePermissionTo('use_pos');
        $counter->call('scan', $this->cable->barcode)->assertForbidden();

        $cashier->givePermissionTo('use_pos');
        $counter = Livewire::actingAs($cashier->fresh())->test(PosCounter::class, ['store' => $this->store]);
        $this->store->employees()->detach($cashier);
        $counter->call('scan', $this->cable->barcode)->assertForbidden();
    }

    #[Test]
    public function an_employee_sees_only_their_own_sales_and_the_owner_sees_all(): void
    {
        $first = $this->createCashier(name: 'First');
        $second = $this->createCashier(name: 'Second');
        $firstSale = $this->finalize($first, $this->review($first, $this->cableLine(1)), '50');
        $secondSale = $this->finalize($second, $this->review($second, $this->cableLine(1)), '50');

        Livewire::actingAs($first)->test(SalesHistory::class, ['store' => $this->store])
            ->assertSee($firstSale->number)
            ->assertDontSee($secondSale->number);

        Livewire::actingAs($this->owner)->test(SalesHistory::class, ['store' => $this->store])
            ->assertSee($firstSale->number)
            ->assertSee($secondSale->number);

        $this->actingAs($first)->get(route('pos.sales.show', [$this->store, $firstSale]))->assertOk()->assertSee($firstSale->number);
        $this->actingAs($first)->get(route('pos.sales.show', [$this->store, $secondSale]))->assertForbidden();
        $this->actingAs($this->owner)->get(route('pos.sales.show', [$this->store, $secondSale]))->assertOk();
    }

    #[Test]
    public function a_receipt_is_only_reachable_through_its_own_store(): void
    {
        $cashier = $this->createCashier();
        $otherStore = Store::factory()->create(['code' => 'KT02']);
        $otherStore->employees()->attach($cashier);
        $sale = $this->finalize($cashier, $this->review($cashier, $this->cableLine(1)), '50');

        $this->withoutExceptionHandling();

        $this->assertThrows(
            fn () => $this->actingAs($cashier)->get(route('pos.sales.show', [$otherStore, $sale])),
            ModelNotFoundException::class,
        );
    }

    #[Test]
    public function receipts_and_history_never_show_costs(): void
    {
        $cashier = $this->createCashier();
        $sale = $this->finalize($cashier, $this->review($cashier, [...$this->cableLine(1), ...$this->phoneLine()]), '12549.90');

        $this->actingAs($this->owner)->get(route('pos.sales.show', [$this->store, $sale]))
            ->assertOk()
            ->assertSee($this->phoneImei)
            ->assertSee('12,549.90')
            ->assertDontSee(__('inventory.movement_types.device_cost'))
            ->assertDontSee('purchase_price');
    }

    #[Test]
    public function the_owner_grants_and_revokes_counter_access_from_store_management_with_an_audit_trail(): void
    {
        $employee = $this->createCashier(withPos: false, name: 'Leyla');
        Store::factory()->create(['code' => 'KT05'])->employees()->attach($employee);

        $detail = Livewire::actingAs($this->owner)->test(StoreDetail::class, ['store' => $this->store])
            ->assertSee(__('pos.access.scope_note'))
            ->call('setPosAccess', $employee->id, true);

        $this->assertTrue($employee->fresh()->hasDirectPermission('use_pos'));
        $this->actingAs($employee->fresh())->get(route('pos.counter', $this->store))->assertOk();

        $this->actingAs($this->owner);
        $detail->call('setPosAccess', $employee->id, false);
        $this->assertFalse($employee->fresh()->hasDirectPermission('use_pos'));

        $activities = Activity::query()->where('log_name', 'employee_permission')->orderBy('id')->get();
        $this->assertSame(['pos_access_granted', 'pos_access_revoked'], $activities->pluck('event')->all());
        $this->assertSame(['KT01', 'KT05'], $activities[0]->properties['store_codes']);
        $this->assertSame($this->owner->id, $activities[0]->causer_id);
    }

    #[Test]
    public function counter_access_cannot_be_set_for_users_outside_the_store_or_by_non_owners(): void
    {
        $outsider = $this->createCashier(Store::factory()->create(), withPos: false);
        $cashier = $this->createCashier();

        Livewire::actingAs($cashier)->test(StoreDetail::class, ['store' => $this->store])->assertForbidden();

        $this->withoutExceptionHandling();
        $this->assertThrows(
            fn () => Livewire::actingAs($this->owner)->test(StoreDetail::class, ['store' => $this->store])->call('setPosAccess', $outsider->id, true),
            ModelNotFoundException::class,
        );
        $this->assertFalse($outsider->fresh()->hasDirectPermission('use_pos'));
    }

    #[Test]
    public function the_owner_manages_card_terminals_per_store(): void
    {
        Livewire::actingAs($this->owner)->test(StoreDetail::class, ['store' => $this->store])
            ->set('terminalLabel', 'Front desk')
            ->set('terminalMerchantNumber', 'm-100')
            ->set('terminalNumber', 't 01')
            ->call('addTerminal')
            ->assertHasNoErrors();

        $terminal = $this->store->cardTerminals()->sole();
        $this->assertSame(['M-100', 'T01'], [$terminal->merchant_number, $terminal->terminal_number]);

        Livewire::actingAs($this->owner)->test(StoreDetail::class, ['store' => $this->store])
            ->set('terminalLabel', 'Duplicate')
            ->set('terminalMerchantNumber', 'M-100')
            ->set('terminalNumber', 'T01')
            ->call('addTerminal')
            ->assertHasErrors('terminal_number');

        Livewire::actingAs($this->owner)->test(StoreDetail::class, ['store' => $this->store])
            ->call('setTerminalActive', $terminal->id, false);
        $this->assertFalse($terminal->fresh()->is_active);
    }
}
