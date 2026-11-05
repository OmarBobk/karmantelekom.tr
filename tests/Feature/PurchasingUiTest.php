<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Purchasing\ReceivePurchase;
use App\Actions\Purchasing\RecordSupplierPayment;
use App\Enums\Currency;
use App\Enums\PurchaseStatus;
use App\Enums\TrackingMode;
use App\Livewire\Backend\Purchases\PurchaseDraft;
use App\Livewire\Backend\Purchases\PurchaseShow;
use App\Livewire\Backend\Purchases\PurchasesIndex;
use App\Livewire\Backend\Purchases\ReceivePurchaseForm;
use App\Livewire\Backend\Purchases\SupplierAccountPage;
use App\Models\InventoryBalance;
use App\Models\OperationalSupplier;
use App\Models\ProductDefinition;
use App\Models\Purchase;
use App\Models\Store;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\InteractsWithOperationalInventory;
use Tests\Support\InteractsWithPurchasing;
use Tests\TestCase;

class PurchasingUiTest extends TestCase
{
    use InteractsWithOperationalInventory;
    use InteractsWithPurchasing;
    use RefreshDatabase;

    private User $owner;

    private Store $storeOne;

    private Store $storeTwo;

    private OperationalSupplier $supplier;

    private ProductDefinition $cable;

    private ProductDefinition $usedPhone;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOperationalRoles();
        $this->owner = $this->createOwner();
        $this->storeOne = Store::factory()->create(['code' => 'KT01']);
        $this->storeTwo = Store::factory()->create(['code' => 'KT02']);
        $this->supplier = OperationalSupplier::factory()->create(['name' => 'Anadolu Telekom Toptan']);
        $this->cable = ProductDefinition::factory()->create(['name' => 'USB-C Cable', 'tracking_mode' => TrackingMode::QUANTITY, 'sku' => 'CBL-100']);
        $this->usedPhone = ProductDefinition::factory()->create(['name' => 'iPhone 13', 'tracking_mode' => TrackingMode::USED_DEVICE]);
    }

    #[Test]
    public function owner_drafts_posts_receives_and_pays_the_walkthrough_invoice_through_the_screens(): void
    {
        $index = Livewire::actingAs($this->owner)
            ->test(PurchasesIndex::class)
            ->set('newStoreId', (string) $this->storeOne->id)
            ->set('newSupplierId', (string) $this->supplier->id)
            ->call('createDraft')
            ->assertHasNoErrors();

        $purchase = Purchase::query()->sole();
        $index->assertRedirect(route('subdomain.purchases.edit', $purchase));

        $draft = Livewire::actingAs($this->owner)
            ->test(PurchaseDraft::class, ['purchase' => $purchase])
            ->set('productSearch', 'CBL-100')
            ->call('addExactMatch')
            ->assertHasNoErrors()
            ->assertDispatched('purchase-line-added', index: 0)
            ->set('lines.0.quantity', '10')
            ->set('lines.0.unit_cost', '12.50')
            ->call('addProduct', $this->usedPhone->id)
            ->set('lines.1.unit_cost', '8000')
            ->set('additionalCharges', '50')
            ->set('invoiceNumber', 'TT-2026-0042')
            ->set('invoiceDate', now()->toDateString())
            ->call('reviewPosting')
            ->assertHasNoErrors()
            ->assertDispatched('open-dialog', id: 'post-review')
            ->assertSee('8,175.00');

        $this->assertSame('8175.00', $purchase->refresh()->total);

        $draft->call('post')->assertRedirect(route('subdomain.purchases.show', $purchase));
        $this->assertSame(PurchaseStatus::POSTED, $purchase->refresh()->status);

        $phoneLine = $purchase->lines()->where('product_definition_id', $this->usedPhone->id)->sole();
        $receive = Livewire::actingAs($this->owner)
            ->test(ReceivePurchaseForm::class, ['purchase' => $purchase])
            ->set("units.{$phoneLine->id}.0.condition", 'b')
            ->call('receive')
            ->assertHasErrors("units.{$phoneLine->id}.0.imei");

        $this->assertNull($purchase->refresh()->received_at);

        $receive
            ->set("units.{$phoneLine->id}.0.imei", self::imei('35693803564380'))
            ->call('receive')
            ->assertHasNoErrors()
            ->assertRedirect(route('subdomain.purchases.show', $purchase));

        $this->assertSame(10, InventoryBalance::query()->where('store_id', $this->storeOne->id)->where('product_definition_id', $this->cable->id)->value('quantity'));

        Livewire::actingAs($this->owner)
            ->test(PurchaseShow::class, ['purchase' => $purchase])
            ->set('paymentAmount', '3000')
            ->set('paymentReference', 'EFT-55001')
            ->call('recordPayment')
            ->assertHasNoErrors()
            ->assertDispatched('close-dialog', id: 'record-payment')
            ->assertSee(['5,175.00', 'EFT-55001']);

        $this->assertSame(817500 - 300000, $purchase->refresh()->outstandingMinor());
    }

    #[Test]
    public function a_stale_draft_tab_cannot_overwrite_a_newer_save(): void
    {
        $purchase = $this->draftPurchase($this->owner, $this->storeOne, $this->supplier, [[$this->cable, 10, '12.50']]);

        $firstTab = Livewire::actingAs($this->owner)->test(PurchaseDraft::class, ['purchase' => $purchase]);
        $secondTab = Livewire::actingAs($this->owner)->test(PurchaseDraft::class, ['purchase' => $purchase]);

        $firstTab->set('notes', 'Counted at the door')->call('save')->assertHasNoErrors();
        $secondTab->set('notes', 'Older edit')->call('save')->assertHasErrors('revision');

        $this->assertSame('Counted at the door', $purchase->refresh()->notes);
    }

    #[Test]
    public function enter_on_a_code_shared_by_two_products_adds_neither_until_the_operator_chooses(): void
    {
        $this->useEnglishLocale();
        $skuOwner = ProductDefinition::factory()->create(['name' => 'Charger 20W', 'model' => 'CH-20', 'sku' => '0042']);
        $barcodeOwner = ProductDefinition::factory()->create(['name' => 'Screen Guard', 'model' => 'SG-13', 'barcode' => '0042']);
        $purchase = $this->draftPurchase($this->owner, $this->storeOne, $this->supplier, [[$this->cable, 1, '12.50']]);

        $draft = Livewire::actingAs($this->owner)
            ->test(PurchaseDraft::class, ['purchase' => $purchase])
            ->set('productSearch', '0042')
            ->call('addExactMatch')
            ->assertHasErrors(['productSearch' => __('purchases.draft.exact_ambiguous', ['code' => '0042'])])
            ->assertNotDispatched('purchase-line-added')
            ->assertCount('lines', 1)
            ->assertSet('productSearch', '0042')
            ->assertSeeInOrder(['Charger 20W', 'CH-20', 'SKU', '0042'])
            ->assertSeeInOrder(['Screen Guard', 'SG-13', 'Barcode', '0042']);

        $draft->call('addProduct', $barcodeOwner->id)
            ->assertHasNoErrors()
            ->assertDispatched('purchase-line-added', index: 1)
            ->assertSet('lines.1.product_definition_id', $barcodeOwner->id)
            ->assertSet('productSearch', '');

        $this->assertNotSame($skuOwner->id, $draft->get('lines.1.product_definition_id'));
    }

    /**
     * @return array<string, array{array<string, string>, array<string, string>, string, bool}>
     */
    public static function exactCodeLookups(): array
    {
        return [
            'one product with the code as both SKU and barcode' => [['sku' => '0042', 'barcode' => '0042'], [], '0042', true],
            'the other holder of the code is inactive' => [['sku' => '0042'], ['barcode' => '0042'], '0042', true],
            'leading zeros are part of the code' => [['sku' => '0042'], [], '042', false],
        ];
    }

    /**
     * @param  array<string, string>  $identifiers
     * @param  array<string, string>  $inactiveIdentifiers
     */
    #[Test]
    #[DataProvider('exactCodeLookups')]
    public function enter_adds_a_single_exact_match_and_nothing_else(array $identifiers, array $inactiveIdentifiers, string $scanned, bool $adds): void
    {
        $this->useEnglishLocale();
        $definition = ProductDefinition::factory()->create(['name' => 'Charger 20W', ...$identifiers]);

        if ($inactiveIdentifiers !== []) {
            ProductDefinition::factory()->inactive()->create(['name' => 'Retired Charger', ...$inactiveIdentifiers]);
        }

        $purchase = $this->draftPurchase($this->owner, $this->storeOne, $this->supplier, [[$this->cable, 1, '12.50']]);

        $draft = Livewire::actingAs($this->owner)
            ->test(PurchaseDraft::class, ['purchase' => $purchase])
            ->set('productSearch', $scanned)
            ->call('addExactMatch');

        if ($adds) {
            $draft->assertHasNoErrors()
                ->assertDispatched('purchase-line-added', index: 1)
                ->assertCount('lines', 2)
                ->assertSet('lines.1.product_definition_id', $definition->id);

            return;
        }

        $draft->assertHasErrors(['productSearch' => __('purchases.draft.exact_not_found')])
            ->assertNotDispatched('purchase-line-added')
            ->assertCount('lines', 1);
    }

    /**
     * @return array<string, array{?string, list<string>, int}>
     */
    public static function deniedVisitors(): array
    {
        return [
            'guest' => [null, [], 302],
            'employee' => ['employee', [], 302],
            'salesperson' => ['salesperson', [], 403],
            'owner without purchasing permissions' => ['admin', ['manage_purchases', 'receive_purchases', 'pay_suppliers'], 403],
        ];
    }

    #[Test]
    #[DataProvider('deniedVisitors')]
    public function purchasing_pages_are_owner_only_by_direct_url(?string $role, array $revoked, int $expectedStatus): void
    {
        $purchase = $this->postedPurchase($this->owner, $this->storeOne, $this->supplier, [[$this->cable, 10, '12.50']]);

        foreach ($revoked as $permission) {
            $this->revokeFromOwners($permission);
        }

        if ($role !== null) {
            $user = User::factory()->create()->assignRole($role);
            $this->storeOne->employees()->attach($user);
            $this->actingAs($user);
        }

        $this->get(route('subdomain.purchases'))->assertStatus($expectedStatus);
        $this->get(route('subdomain.purchases.show', $purchase))->assertStatus($expectedStatus);
        $this->get(route('subdomain.purchases.edit', $purchase))->assertStatus($expectedStatus);
        $this->get(route('subdomain.purchases.receive', $purchase))->assertStatus($expectedStatus);
        $this->get(route('subdomain.suppliers.account', $this->supplier))->assertStatus($expectedStatus);
    }

    #[Test]
    public function each_purchasing_permission_unlocks_only_its_own_screen(): void
    {
        $purchase = $this->postedPurchase($this->owner, $this->storeOne, $this->supplier, [[$this->cable, 10, '12.50']]);
        $this->revokeFromOwners('manage_purchases');
        $this->revokeFromOwners('receive_purchases');

        $this->actingAs($this->owner)->get(route('subdomain.purchases.show', $purchase))
            ->assertOk()
            ->assertSee(__('purchases.payments.record'))
            ->assertDontSee(__('purchases.receipt.receive'))
            ->assertDontSee(__('purchases.reverse.action'));
        $this->get(route('subdomain.purchases.receive', $purchase))->assertForbidden();
        $this->get(route('subdomain.suppliers.account', $this->supplier))->assertOk();

        $component = Livewire::actingAs($this->owner)
            ->test(PurchaseShow::class, ['purchase' => $purchase])
            ->set('paymentAmount', '10')
            ->set('paymentReference', 'CASH-1');
        $this->revokeFromOwners('pay_suppliers');

        $component->call('recordPayment')->assertForbidden();
        $this->assertSame(0, SupplierPayment::query()->count());
    }

    #[Test]
    public function locked_ids_and_foreign_payment_ids_cannot_be_tampered_with(): void
    {
        $purchase = $this->postedPurchase($this->owner, $this->storeOne, $this->supplier, [[$this->cable, 10, '12.50']]);
        $other = $this->postedPurchase($this->owner, $this->storeTwo, $this->supplier, [[$this->cable, 4, '12.50']], ['supplier_invoice_number' => 'INV-2002']);
        $otherPayment = app(RecordSupplierPayment::class)($this->owner, $other, $this->paymentInput('20.00', 'EFT-9'), 'payment-key-1');

        $this->assertThrows(
            fn () => Livewire::actingAs($this->owner)->test(PurchaseShow::class, ['purchase' => $purchase])->set('purchaseId', $other->id),
            CannotUpdateLockedPropertyException::class,
        );
        $this->assertThrows(
            fn () => Livewire::actingAs($this->owner)->test(ReceivePurchaseForm::class, ['purchase' => $purchase])->set('submissionKey', 'attacker-chosen'),
            CannotUpdateLockedPropertyException::class,
        );
        Livewire::actingAs($this->owner)
            ->test(PurchaseShow::class, ['purchase' => $purchase])
            ->set('reversingPaymentId', (string) $otherPayment->id)
            ->set('paymentReversalReason', 'Wrong invoice')
            ->call('reversePayment')
            ->assertHasErrors('paymentReversalReason')
            ->assertNotDispatched('close-dialog');

        $this->assertFalse($otherPayment->refresh()->isReversed());
    }

    #[Test]
    public function supplier_account_shows_balances_per_currency_and_a_running_statement(): void
    {
        $tryInvoice = $this->postedPurchase($this->owner, $this->storeOne, $this->supplier, [[$this->cable, 10, '12.50'], [$this->usedPhone, 1, '8000.00']], ['additional_charges' => '50.00']);
        $this->travel(1)->minute();
        app(RecordSupplierPayment::class)($this->owner, $tryInvoice, $this->paymentInput('3000.00', 'EFT-1'), 'payment-key-1');
        $this->postedPurchase($this->owner, $this->storeTwo, $this->supplier, [[$this->cable, 8, '12.50']], ['supplier_invoice_number' => 'USD-7'], Currency::USD);

        Livewire::actingAs($this->owner)
            ->test(SupplierAccountPage::class, ['supplier' => $this->supplier])
            ->assertViewHas('balances', fn (array $balances) => $balances['TRY']['balance'] === 517500
                && $balances['TRY']['open_invoices'] === 517500
                && $balances['TRY']['stores'][$this->storeOne->id]['balance'] === 517500
                && $balances['USD']['balance'] === 10000)
            ->assertViewHas('entries', fn ($entries) => $entries->pluck('running_balance')->map(fn ($value) => (string) $value)->all() === ['5175.00', '8175.00'])
            ->assertSee(['5,175.00', 'USD'])
            ->set('currency', 'USD')
            ->assertViewHas('entries', fn ($entries) => $entries->count() === 1);
    }

    #[Test]
    public function received_stock_links_back_to_its_invoice_only_for_purchasing_viewers(): void
    {
        $purchase = $this->postedPurchase($this->owner, $this->storeOne, $this->supplier, [[$this->cable, 10, '12.50'], [$this->usedPhone, 1, '8000.00']]);
        $phoneLine = $purchase->lines()->where('product_definition_id', $this->usedPhone->id)->sole();
        $receipt = app(ReceivePurchase::class)($this->owner, $purchase, [$phoneLine->id => [['condition' => 'b', 'imei' => self::imei('35693803564380'), 'secondary_imei' => null, 'serial' => null]]], null, 'receipt-key-1');
        $unit = $receipt->lines()->where('purchase_line_id', $phoneLine->id)->sole()->units()->sole()->unit;
        $invoiceUrl = route('subdomain.purchases.show', $purchase);

        $this->actingAs($this->owner)->get(route('subdomain.inventory.product', [$this->storeOne, $this->cable]))
            ->assertOk()
            ->assertSee([$invoiceUrl, 'INV-1001', '12.50'], false);
        $this->get(route('subdomain.inventory.unit', [$this->storeOne, $unit]))
            ->assertOk()
            ->assertSee([$invoiceUrl, '8,000.00'], false);

        foreach (['manage_purchases', 'receive_purchases', 'pay_suppliers'] as $permission) {
            $this->revokeFromOwners($permission);
        }

        $this->get(route('subdomain.inventory.unit', [$this->storeOne, $unit]))
            ->assertOk()
            ->assertSee('INV-1001')
            ->assertDontSee($invoiceUrl, false)
            ->assertDontSee('8,000.00');
    }

    #[Test]
    public function the_purchase_list_query_count_does_not_grow_with_rows(): void
    {
        Purchase::factory()->count(2)->create(['store_id' => $this->storeOne->id, 'operational_supplier_id' => $this->supplier->id, 'created_by' => $this->owner->id]);
        $this->countListQueries();
        $fewRows = $this->countListQueries();

        Purchase::factory()->count(8)->create(['store_id' => $this->storeTwo->id, 'operational_supplier_id' => OperationalSupplier::factory()->create()->id, 'created_by' => $this->owner->id]);

        $this->assertSame($fewRows, $this->countListQueries());
    }

    #[Test]
    public function purchasing_screens_render_right_to_left_in_arabic_and_left_to_right_in_english(): void
    {
        $purchase = $this->postedPurchase($this->owner, $this->storeOne, $this->supplier, [[$this->cable, 10, '12.50']]);

        $this->actingAs($this->owner)->get(route('subdomain.purchases.show', $purchase))
            ->assertOk()
            ->assertSee('dir="rtl" lang="ar"', false)
            ->assertSee(['فواتير الشراء', 'المتبقي', '125.00']);

        $this->useEnglishLocale();

        $this->get(route('subdomain.purchases'))
            ->assertOk()
            ->assertSee('dir="ltr" lang="en"', false)
            ->assertSee(['Purchase invoices', 'INV-1001', 'Awaiting receipt', 'Unpaid'])
            ->assertDontSee('فواتير الشراء');
        $this->get(route('subdomain.purchases.show', $purchase))
            ->assertOk()
            ->assertSee('dir="ltr" lang="en"', false)
            ->assertSee(['Outstanding', 'Receive goods', 'Record payment']);
    }

    private function countListQueries(): int
    {
        $this->actingAs($this->owner);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('subdomain.purchases'))->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
