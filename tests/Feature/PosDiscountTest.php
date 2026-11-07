<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Pos\DiscountAuthority;
use App\Actions\Pos\SetUnitSellingPrice;
use App\Actions\Stores\SetEmployeeDiscountGrant;
use App\Actions\Stores\SetEmployeePosAccess;
use App\Enums\CheckoutAttemptStatus;
use App\Exceptions\Pos\CheckoutItemsChangedException;
use App\Livewire\Backend\Inventory\UnitDetail;
use App\Livewire\Backend\Stores\StoreDetail;
use App\Livewire\Pos\PosCounter;
use App\Models\CheckoutAttempt;
use App\Models\PosDiscountGrant;
use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\OperationalStoreSetupSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\InteractsWithPos;
use Tests\TestCase;

class PosDiscountTest extends TestCase
{
    use InteractsWithPos;
    use RefreshDatabase;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCounter();
        $this->useEnglishLocale();
        $this->cashier = $this->createCashier(name: 'Ayşe');
        $this->grantDiscount($this->cashier, '10');
        app(SetUnitSellingPrice::class)($this->owner, $this->store, $this->phone->id, '500', '400');
    }

    #[Test]
    public function a_granted_cashier_sells_a_used_device_down_to_its_minimum_price_but_not_below(): void
    {
        $field = 'lines.u'.$this->phone->id.'.adjusted_price';

        $refused = $this->validationErrors(fn () => $this->review($this->cashier, $this->phoneLine(extra: ['adjusted_price' => '390', 'adjustment_reason' => 'Scratch'])));
        $this->assertSame(['390,00 TRY is below your limit. The lowest allowed unit price is 400,00 TRY.'], $refused[$field]);
        $this->assertSame(0, CheckoutAttempt::query()->count());

        $attempt = $this->review($this->cashier, $this->phoneLine(extra: ['adjusted_price' => '400', 'adjustment_reason' => 'Scratch on the back']));

        $this->assertSame(['500.00', '100.00', '400.00'], [$attempt->subtotal, $attempt->discount_total, $attempt->total]);
        $this->assertSame([DiscountAuthority::DEVICE_MINIMUM, 40000, $this->cashier->id], [$attempt->lines[0]['discount_limit'], $attempt->lines[0]['lowest_unit_price'], $attempt->lines[0]['discounted_by']]);
        $this->assertStringNotContainsString('cost', json_encode($attempt->lines));

        $line = $this->finalize($this->cashier, $attempt, '400')->lines->sole();

        $this->assertSame(
            ['500.00', '100.00', '400.00', '400.00', 'device_minimum', null, $this->cashier->id, 'Scratch on the back'],
            [$line->list_unit_price, $line->unit_discount, $line->unit_price, $line->discount_lowest_unit_price, $line->discount_limit, $line->discount_basis_points, $line->discounted_by, $line->adjustment_reason],
        );
    }

    #[Test]
    public function ordinary_items_use_the_cashiers_percentage_rounded_up_to_the_kurus(): void
    {
        $field = 'lines.d'.$this->cable->id.'.adjusted_price';

        $this->assertArrayHasKey($field, $this->validationErrors(fn () => $this->review($this->cashier, $this->cableLine(2, ['adjusted_price' => '44.90', 'adjustment_reason' => 'Bundle']))));
        $this->assertArrayHasKey($field, $this->validationErrors(fn () => $this->review($this->cashier, $this->cableLine(2, ['adjusted_price' => '55', 'adjustment_reason' => 'Bundle']))));
        $this->assertArrayHasKey('lines.d'.$this->cable->id.'.adjustment_reason', $this->validationErrors(fn () => $this->review($this->cashier, $this->cableLine(2, ['adjusted_price' => '44.91']))));

        $attempt = $this->review($this->cashier, $this->cableLine(2, ['adjusted_price' => '44.91', 'adjustment_reason' => 'Bundle']));

        $this->assertSame(['99.80', '9.98', '89.82'], [$attempt->subtotal, $attempt->discount_total, $attempt->total]);
        $this->assertSame([DiscountAuthority::PERCENTAGE, 1000, 4491], [$attempt->lines[0]['discount_limit'], $attempt->lines[0]['discount_basis_points'], $attempt->lines[0]['lowest_unit_price']]);

        $unchanged = $this->review($this->cashier, $this->cableLine(2, ['adjusted_price' => '49.90', 'adjustment_reason' => 'none']), $attempt);
        $this->assertSame(['0.00', null], [$unchanged->discount_total, $unchanged->lines[0]['discount_limit']]);
    }

    #[Test]
    public function employees_without_a_complete_grant_cannot_discount(): void
    {
        $plain = $this->createCashier(name: 'Plain');
        $permissionOnly = $this->createCashier(name: 'Permission only');
        $permissionOnly->givePermissionTo(OperationalStoreSetupSeeder::POS_DISCOUNT_PERMISSION);
        $discount = $this->cableLine(1, ['adjusted_price' => '48', 'adjustment_reason' => 'Regular']);
        $field = 'lines.d'.$this->cable->id.'.adjusted_price';

        foreach ([$plain, $permissionOnly->fresh()] as $employee) {
            $this->assertFalse(DiscountAuthority::for($employee, $this->store)->canDiscount());
            $this->assertSame([__('pos.errors.discount_not_allowed')], $this->validationErrors(fn () => $this->review($employee, $discount))[$field]);
        }

        app(SetEmployeeDiscountGrant::class)($this->owner, $this->cashier, '');
        $this->assertFalse($this->cashier->fresh()->hasDirectPermission(OperationalStoreSetupSeeder::POS_DISCOUNT_PERMISSION));
        $this->assertSame(0, PosDiscountGrant::query()->count());
        $this->assertArrayHasKey($field, $this->validationErrors(fn () => $this->review($this->cashier->fresh(), $discount)));
    }

    #[Test]
    public function a_grant_only_applies_in_branches_where_the_employee_uses_the_counter(): void
    {
        $otherStore = Store::factory()->create(['code' => 'KT02']);

        $this->assertTrue(DiscountAuthority::for($this->cashier, $this->store)->canDiscount());
        $this->assertFalse(DiscountAuthority::for($this->cashier, $otherStore)->canDiscount());
        $this->expectAuthorizationFailure(fn () => $this->review($this->cashier, $this->cableLine(1), store: $otherStore));

        app(SetEmployeePosAccess::class)($this->owner, $this->cashier, false);
        $this->assertFalse(DiscountAuthority::for($this->cashier->fresh(), $this->store)->canDiscount());

        $this->expectAuthorizationFailure(fn () => app(SetEmployeeDiscountGrant::class)($this->cashier, $this->createCashier(name: 'Other'), '5'));
        $this->assertArrayHasKey('employee', $this->validationErrors(fn () => app(SetEmployeeDiscountGrant::class)($this->owner, $this->owner, '5')));
    }

    #[Test]
    public function a_stricter_limit_before_tender_fails_the_cash_sale_and_keeps_the_attempt_open(): void
    {
        $cableAttempt = $this->review($this->cashier, $this->cableLine(1, ['adjusted_price' => '44.91', 'adjustment_reason' => 'Bundle']));
        $this->grantDiscount($this->cashier, '5');
        $this->assertDiscountChanged($cableAttempt);

        $phoneAttempt = $this->review($this->cashier, $this->phoneLine(extra: ['adjusted_price' => '400', 'adjustment_reason' => 'Scratch']));
        app(SetUnitSellingPrice::class)($this->owner, $this->store, $this->phone->id, '500', '450');
        $this->assertDiscountChanged($phoneAttempt);

        $this->assertSame(0, Sale::query()->count());
        $this->assertSame(10, $this->cableStock());

        $repriced = $this->review($this->cashier, $this->phoneLine(extra: ['adjusted_price' => '450', 'adjustment_reason' => 'Scratch']), $phoneAttempt->fresh());
        $this->assertSame('450.00', $this->finalize($this->cashier, $repriced, '450')->total);
    }

    #[Test]
    public function starting_a_card_payment_refuses_a_cart_whose_price_or_limit_changed_since_review(): void
    {
        $terminal = $this->createTerminal();

        $attempt = $this->review($this->cashier, $this->cableLine(1, ['adjusted_price' => '44.91', 'adjustment_reason' => 'Bundle']));
        $this->grantDiscount($this->cashier, '5');
        $errors = $this->validationErrors(fn () => $this->startTerminalPayment($this->cashier, $attempt, $terminal, '44.91'));
        $this->assertSame([__('pos.errors.review_again_before_payment')], $errors['attempt']);
        $this->assertArrayHasKey('lines.d'.$this->cable->id.'.adjusted_price', $errors);

        $this->grantDiscount($this->cashier, '10');
        $this->cable->update(['selling_price' => '50.00']);
        $this->assertArrayHasKey('lines.d'.$this->cable->id.'.price', $this->validationErrors(fn () => $this->startTerminalPayment($this->cashier, $attempt, $terminal, '44.91')));

        $this->assertFalse($attempt->fresh()->isFrozen());
        $this->assertNull($attempt->fresh()->terminal_payment_key);
    }

    #[Test]
    public function after_a_card_payment_starts_the_reviewed_discount_is_charged_and_cannot_change(): void
    {
        $terminal = $this->createTerminal();
        $attempt = $this->review($this->cashier, $this->cableLine(1, ['adjusted_price' => '44.91', 'adjustment_reason' => 'Bundle']));
        $this->startTerminalPayment($this->cashier, $attempt, $terminal, '44.91');

        $counter = Livewire::actingAs($this->cashier)->test(PosCounter::class, ['store' => $this->store])
            ->call('resumeAttempt', $attempt->key)
            ->assertSet('attemptKey', $attempt->key)
            ->call('removeDiscount', 'd'.$this->cable->id)
            ->assertSet('cart.d'.$this->cable->id.'.adjusted_price', '44.91')
            ->call('openDiscount', 'd'.$this->cable->id)
            ->assertSet('discountLineKey', '');

        $this->grantDiscount($this->cashier, '5');

        $counter->call('$refresh')->assertSee('44,91')->assertDontSee('47,41');
        $this->assertArrayHasKey('attempt', $this->validationErrors(fn () => $this->review($this->cashier, $this->cableLine(1), $attempt->fresh())));

        $this->approveCard($this->cashier, $attempt, $terminal, '44.91');
        $sale = $this->finalize($this->cashier, $attempt->fresh());

        $this->assertSame('44.91', $sale->total);
        $this->assertSame([DiscountAuthority::PERCENTAGE, 1000, '44.91'], [$sale->lines->sole()->discount_limit, $sale->lines->sole()->discount_basis_points, $sale->lines->sole()->discount_lowest_unit_price]);
        $this->assertTrue($this->finalize($this->cashier, $attempt->fresh())->is($sale));
    }

    #[Test]
    public function the_counter_shows_the_limit_applies_a_discount_at_once_and_rejects_one_below_it(): void
    {
        $key = 'u'.$this->phone->id;

        Livewire::actingAs($this->cashier)->test(PosCounter::class, ['store' => $this->store])
            ->call('scan', $this->phoneImei)
            ->assertSee(__('pos.cart.discount'))
            ->call('openDiscount', $key)
            ->assertSet('discountListPrice', 50000)
            ->assertSet('discountLowestPrice', 40000)
            ->assertSet('discountLimit', DiscountAuthority::DEVICE_MINIMUM)
            ->assertSee([__('pos.discount.original_price'), '500,00', __('pos.discount.lowest_price'), '400,00'])
            ->set('discountPrice', '390')
            ->set('discountReason', 'Scratch')
            ->call('applyDiscount')
            ->assertHasErrors('discountPrice')
            ->assertSee('The lowest allowed unit price is 400,00 TRY.')
            ->assertSet("cart.{$key}.adjusted_price", '')
            ->set('discountPrice', '400')
            ->call('applyDiscount')
            ->assertHasNoErrors()
            ->assertSet("cart.{$key}.adjusted_price", '400.00')
            ->assertSee('-100,00 each')
            ->call('openDiscount', $key)
            ->assertSet('discountPrice', '400')
            ->set('discountPrice', '450.50')
            ->call('applyDiscount')
            ->assertHasErrors('discountPrice')
            ->assertSee(__('pos.errors.amount_format', ['example' => '10.000,50']))
            ->assertSet("cart.{$key}.adjusted_price", '400.00')
            ->set('discountPrice', '٤٥٠,٥٠')
            ->call('applyDiscount')
            ->assertHasNoErrors()
            ->assertSet("cart.{$key}.adjusted_price", '450.50')
            ->assertSee('-49,50 each')
            ->call('openDiscount', $key)
            ->assertSet('discountPrice', '450,50')
            ->call('review')
            ->assertSet('step', 'payment')
            ->assertSee('450,50')
            ->call('removeDiscount', $key)
            ->assertSet("cart.{$key}.adjusted_price", '');
    }

    #[Test]
    public function the_owner_sets_and_revokes_an_employee_discount_limit_with_an_audit_trail(): void
    {
        $detail = Livewire::actingAs($this->owner)->test(StoreDetail::class, ['store' => $this->store])
            ->assertSee(__('pos.access.discount_up_to', ['percent' => '10']))
            ->call('openDiscountGrant', $this->cashier->id)
            ->assertSet('discountPercent', '10');

        foreach (['0', '100.01', '12.345', 'abc'] as $invalid) {
            $detail->set('discountPercent', $invalid)->call('saveDiscountGrant')->assertHasErrors('discountPercent');
        }

        $detail->set('discountPercent', '12.5')->call('saveDiscountGrant')->assertHasNoErrors();
        $this->assertSame(1250, $this->cashier->posDiscountGrant()->value('max_discount_basis_points'));

        $detail->call('openDiscountGrant', $this->cashier->id)->set('discountPercent', '')->call('saveDiscountGrant');
        $this->assertFalse($this->cashier->fresh()->hasDirectPermission(OperationalStoreSetupSeeder::POS_DISCOUNT_PERMISSION));
        $this->assertNull($this->cashier->posDiscountGrant()->first());

        $events = Activity::query()->where('log_name', 'employee_permission')->orderBy('id')->get();
        $this->assertSame(['pos_discount_granted', 'pos_discount_changed', 'pos_discount_revoked'], $events->pluck('event')->all());
        $this->assertSame([1000, 1250], [$events[1]->properties['old_max_discount_basis_points'], $events[1]->properties['new_max_discount_basis_points']]);

        Livewire::actingAs($this->cashier)->test(StoreDetail::class, ['store' => $this->store])->assertForbidden();
    }

    #[Test]
    public function the_owner_sets_a_device_minimum_price_that_never_exceeds_its_selling_price(): void
    {
        $setPrice = app(SetUnitSellingPrice::class);

        $this->assertArrayHasKey('minimum_selling_price', $this->validationErrors(fn () => $setPrice($this->owner, $this->store, $this->phone->id, '500', '501')));
        $this->assertArrayHasKey('minimum_selling_price', $this->validationErrors(fn () => $setPrice($this->owner, $this->store, $this->phone->id, '', '400')));
        $this->assertSame(['500.00', '400.00'], [$this->phone->fresh()->selling_price, $this->phone->fresh()->minimum_selling_price]);

        Livewire::actingAs($this->owner)->test(UnitDetail::class, ['store' => $this->store, 'unit' => $this->phone->fresh()])
            ->assertSet('unitMinimumPrice', '400.00')
            ->set('unitSellingPrice', '600')
            ->set('unitMinimumPrice', '')
            ->call('saveSellingPrice')
            ->assertHasNoErrors();

        $this->assertSame(['600.00', null], [$this->phone->fresh()->selling_price, $this->phone->fresh()->minimum_selling_price]);
        $this->assertSame('400.00', Activity::query()->where('log_name', 'selling_price')->latest('id')->first()->properties['old_minimum']);
    }

    #[Test]
    public function only_the_owner_sees_the_discount_audit_of_a_sale(): void
    {
        $sale = $this->finalize($this->cashier, $this->review($this->cashier, $this->phoneLine(extra: ['adjusted_price' => '400', 'adjustment_reason' => 'Scratch on the back'])), '400');

        $this->actingAs($this->owner)->get(route('pos.sales.show', [$this->store, $sale]))
            ->assertOk()
            ->assertSee([__('pos.discount.audit_title'), 'Scratch on the back', 'Ayşe', __('pos.discount.limit_device_minimum'), '-100,00', '400,00']);

        $this->actingAs($this->cashier)->get(route('pos.sales.show', [$this->store, $sale]))
            ->assertOk()
            ->assertSee('400,00')
            ->assertDontSee(__('pos.discount.audit_title'));
    }

    private function grantDiscount(User $employee, string $percent): void
    {
        app(SetEmployeeDiscountGrant::class)($this->owner, $employee, $percent);
    }

    private function assertDiscountChanged(CheckoutAttempt $attempt): void
    {
        try {
            $this->finalize($this->cashier, $attempt->fresh(), '500');
            $this->fail('Expected the discount to be refused.');
        } catch (CheckoutItemsChangedException $exception) {
            $this->assertSame(CheckoutItemsChangedException::DISCOUNT_CHANGED, $exception->failureCode);
        }

        $attempt->refresh();
        $this->assertSame([CheckoutAttemptStatus::OPEN, 'discount_changed'], [$attempt->status, $attempt->failure_code]);
    }

    /**
     * @return array<string, list<string>>
     */
    private function validationErrors(callable $callback): array
    {
        try {
            $callback();
        } catch (ValidationException $exception) {
            return $exception->errors();
        }

        $this->fail('Expected a validation error.');
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
