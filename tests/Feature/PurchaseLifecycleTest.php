<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Catalog\SaveProductDefinition;
use App\Actions\Jetstream\DeleteUser;
use App\Actions\Purchasing\CancelPurchaseDraft;
use App\Actions\Purchasing\CopyPurchaseAsCorrection;
use App\Actions\Purchasing\PostPurchase;
use App\Actions\Purchasing\ReversePurchase;
use App\Actions\Purchasing\SavePurchaseDraft;
use App\Enums\PurchaseStatus;
use App\Enums\SupplierLedgerEntryType;
use App\Enums\TrackingMode;
use App\Models\OperationalSupplier;
use App\Models\ProductDefinition;
use App\Models\Purchase;
use App\Models\PurchaseLine;
use App\Models\Store;
use App\Models\SupplierLedgerEntry;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\InteractsWithOperationalInventory;
use Tests\Support\InteractsWithPurchasing;
use Tests\TestCase;

class PurchaseLifecycleTest extends TestCase
{
    use InteractsWithOperationalInventory;
    use InteractsWithPurchasing;
    use RefreshDatabase;

    private User $owner;

    private Store $store;

    private OperationalSupplier $supplier;

    private ProductDefinition $cable;

    private ProductDefinition $usedPhone;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOperationalRoles();
        $this->owner = $this->createOwner();
        $this->store = Store::factory()->create(['code' => 'KT01']);
        $this->supplier = OperationalSupplier::factory()->create(['name' => 'Mobil Toptan']);
        $this->cable = ProductDefinition::factory()->create(['name' => 'USB-C cable', 'tracking_mode' => TrackingMode::QUANTITY]);
        $this->usedPhone = ProductDefinition::factory()->create(['name' => 'iPhone 13 (used)', 'tracking_mode' => TrackingMode::USED_DEVICE]);
    }

    private function walkthroughDraft(array $header = []): Purchase
    {
        return $this->draftPurchase($this->owner, $this->store, $this->supplier, [
            [$this->cable, 10, '12.50'],
            [$this->usedPhone, 1, '8,000.00'],
        ], ['additional_charges' => '50.00', ...$header]);
    }

    #[Test]
    public function posting_the_walkthrough_invoice_creates_one_obligation_of_8175(): void
    {
        $draft = $this->walkthroughDraft();

        $result = app(PostPurchase::class)($this->owner, $draft, $draft->revision);
        $purchase = $result->model->refresh();

        $this->assertTrue($result->applied);
        $this->assertSame(PurchaseStatus::POSTED, $purchase->status);
        $this->assertSame('125.00', PurchaseLine::query()->where('product_definition_id', $this->cable->id)->value('line_total'));
        $this->assertSame('8125.00', $purchase->items_total);
        $this->assertSame('50.00', $purchase->additional_charges);
        $this->assertSame('8175.00', $purchase->total);
        $this->assertSame(817_500, $purchase->outstandingMinor());
        $this->assertSame('Mobil Toptan', $purchase->supplier_name);
        $this->assertSame($this->owner->id, (int) $purchase->posted_by);

        $entry = SupplierLedgerEntry::query()->sole();
        $this->assertSame(SupplierLedgerEntryType::PURCHASE_POSTED, $entry->type);
        $this->assertSame('8175.00', $entry->amount);
        $this->assertSame($this->store->id, $entry->store_id);
    }

    #[Test]
    public function retrying_a_post_of_the_same_revision_writes_nothing_more(): void
    {
        $draft = $this->walkthroughDraft();
        app(PostPurchase::class)($this->owner, $draft, $draft->revision);

        $retry = app(PostPurchase::class)($this->owner, $draft, $draft->revision);

        $this->assertFalse($retry->applied);
        $this->assertSame(1, SupplierLedgerEntry::count());
    }

    #[Test]
    public function a_stale_tab_cannot_post_a_newer_draft_than_the_one_it_reviewed(): void
    {
        $draft = $this->walkthroughDraft();
        $reviewedRevision = $draft->revision;

        app(SavePurchaseDraft::class)($this->owner, $draft, $reviewedRevision, [
            ...$this->draftInput($draft),
            'additional_charges' => '75.00',
        ]);

        try {
            app(PostPurchase::class)($this->owner, $draft, $reviewedRevision);
            $this->fail('A stale revision was posted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('revision', $exception->errors());
        }

        $this->assertSame(PurchaseStatus::DRAFT, $draft->refresh()->status);
        $this->assertSame(0, SupplierLedgerEntry::count());
    }

    #[Test]
    public function a_stale_tab_cannot_overwrite_a_newer_draft(): void
    {
        $draft = $this->walkthroughDraft();
        $staleRevision = $draft->revision;
        app(SavePurchaseDraft::class)($this->owner, $draft, $staleRevision, $this->draftInput($draft));

        $this->expectException(ValidationException::class);
        app(SavePurchaseDraft::class)($this->owner, $draft, $staleRevision, $this->draftInput($draft));
    }

    #[Test]
    public function money_input_errors_are_reported_per_field_before_anything_is_saved(): void
    {
        $draft = $this->walkthroughDraft();
        $input = $this->draftInput($draft);
        $input['lines'][0]['unit_cost'] = '12.505';
        $input['additional_charges'] = '99999999999.00';

        try {
            app(SavePurchaseDraft::class)($this->owner, $draft, $draft->revision, $input);
            $this->fail('Invalid amounts were accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('lines.0.unit_cost', $exception->errors());
            $this->assertArrayHasKey('additional_charges', $exception->errors());
        }

        $this->assertSame('12.50', $draft->lines()->first()->unit_cost);
    }

    #[Test]
    public function a_line_total_above_the_bound_is_a_validation_error(): void
    {
        $draft = $this->walkthroughDraft();
        $input = $this->draftInput($draft);
        $input['lines'][0] = ['product_definition_id' => $this->cable->id, 'quantity' => '10000', 'unit_cost' => '99999999.99'];

        $this->expectException(ValidationException::class);
        app(SavePurchaseDraft::class)($this->owner, $draft, $draft->revision, $input);
    }

    #[Test]
    public function posting_requires_an_invoice_number_date_and_lines(): void
    {
        $draft = $this->draftPurchase($this->owner, $this->store, $this->supplier, [], [
            'supplier_invoice_number' => '   ',
            'invoice_date' => null,
        ]);

        try {
            app(PostPurchase::class)($this->owner, $draft, $draft->revision);
            $this->fail('An incomplete draft was posted.');
        } catch (ValidationException $exception) {
            $this->assertEqualsCanonicalizing(['supplier_invoice_number', 'invoice_date', 'lines'], array_keys($exception->errors()));
        }
    }

    #[Test]
    public function invoice_numbers_are_unique_per_supplier_ignoring_case_and_outer_spaces(): void
    {
        $this->postedPurchase($this->owner, $this->store, $this->supplier, [[$this->cable, 1, '5.00']], ['supplier_invoice_number' => 'Fat 2026/01']);

        $duplicate = $this->draftPurchase($this->owner, $this->store, $this->supplier, [[$this->cable, 1, '5.00']], ['supplier_invoice_number' => '  fat 2026/01 ']);

        try {
            app(PostPurchase::class)($this->owner, $duplicate, $duplicate->revision);
            $this->fail('A duplicate invoice number was posted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('supplier_invoice_number', $exception->errors());
        }

        $differentSpacing = $this->postedPurchase($this->owner, $this->store, $this->supplier, [[$this->cable, 1, '5.00']], ['supplier_invoice_number' => 'FAT  2026/01']);
        $this->assertSame(PurchaseStatus::POSTED, $differentSpacing->status);
        $this->assertSame('FAT  2026/01', $differentSpacing->supplier_invoice_number);

        $otherSupplier = $this->postedPurchase($this->owner, $this->store, OperationalSupplier::factory()->create(), [[$this->cable, 1, '5.00']], ['supplier_invoice_number' => 'Fat 2026/01']);
        $this->assertSame(PurchaseStatus::POSTED, $otherSupplier->status);
    }

    #[Test]
    public function only_a_linked_correction_may_reuse_a_reversed_invoice_number(): void
    {
        $original = $this->postedPurchase($this->owner, $this->store, $this->supplier, [[$this->cable, 2, '5.00']], ['supplier_invoice_number' => 'A-77']);
        app(ReversePurchase::class)($this->owner, $original, 'Wrong quantity');

        $unrelated = $this->draftPurchase($this->owner, $this->store, $this->supplier, [[$this->cable, 1, '5.00']], ['supplier_invoice_number' => 'a-77']);

        try {
            app(PostPurchase::class)($this->owner, $unrelated, $unrelated->revision);
            $this->fail('An unrelated invoice reused a reversed number.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('supplier_invoice_number', $exception->errors());
        }

        $correction = app(CopyPurchaseAsCorrection::class)($this->owner, $original)->model;
        $this->assertSame($original->id, $correction->correction_of_purchase_id);
        $this->assertSame('A-77', $correction->supplier_invoice_number);
        $this->assertSame(1, $correction->lines()->count());

        $posted = app(PostPurchase::class)($this->owner, $correction, $correction->revision)->model;
        $this->assertSame(PurchaseStatus::POSTED, $posted->status);

        $this->assertSame(
            [SupplierLedgerEntryType::PURCHASE_POSTED, SupplierLedgerEntryType::PURCHASE_REVERSED, SupplierLedgerEntryType::PURCHASE_POSTED],
            SupplierLedgerEntry::query()->orderBy('id')->get()->pluck('type')->all(),
        );
        $this->assertSame(1_000, SupplierLedgerEntry::query()->get()->sum(fn (SupplierLedgerEntry $entry) => $entry->amountMinor()));
    }

    #[Test]
    public function copying_twice_returns_the_open_correction_draft(): void
    {
        $original = $this->postedPurchase($this->owner, $this->store, $this->supplier, [[$this->cable, 2, '5.00']]);
        app(ReversePurchase::class)($this->owner, $original, 'Duplicate entry');

        $first = app(CopyPurchaseAsCorrection::class)($this->owner, $original);
        $second = app(CopyPurchaseAsCorrection::class)($this->owner, $original);

        $this->assertTrue($first->applied);
        $this->assertFalse($second->applied);
        $this->assertSame($first->model->id, $second->model->id);
    }

    #[Test]
    public function reversal_records_actor_time_reason_and_an_opposite_entry_while_posted_facts_stay(): void
    {
        $purchase = $this->postedPurchase($this->owner, $this->store, $this->supplier, [[$this->cable, 10, '12.50']]);

        $result = app(ReversePurchase::class)($this->owner, $purchase, '  Supplier cancelled the invoice  ');
        $purchase->refresh();

        $this->assertTrue($result->applied);
        $this->assertSame(PurchaseStatus::REVERSED, $purchase->status);
        $this->assertSame('Supplier cancelled the invoice', $purchase->reversal_reason);
        $this->assertSame($this->owner->id, (int) $purchase->getAttribute('reversed_by'));
        $this->assertNotNull($purchase->reversed_at);
        $this->assertSame('125.00', $purchase->total);
        $this->assertSame(0, $purchase->outstandingMinor());
        $this->assertSame(0, SupplierLedgerEntry::query()->get()->sum(fn (SupplierLedgerEntry $entry) => $entry->amountMinor()));

        $this->assertFalse(app(ReversePurchase::class)($this->owner, $purchase, 'Again')->applied);
        $this->assertSame(2, SupplierLedgerEntry::count());
    }

    #[Test]
    public function reversal_requires_a_reason(): void
    {
        $purchase = $this->postedPurchase($this->owner, $this->store, $this->supplier, [[$this->cable, 1, '5.00']]);

        $this->expectException(ValidationException::class);
        app(ReversePurchase::class)($this->owner, $purchase, '   ');
    }

    #[Test]
    public function posted_lines_and_ledger_entries_cannot_be_changed(): void
    {
        $purchase = $this->postedPurchase($this->owner, $this->store, $this->supplier, [[$this->cable, 1, '5.00']]);

        try {
            $purchase->lines()->first()->update(['quantity' => 5]);
            $this->fail('A posted line changed.');
        } catch (LogicException) {
        }

        $this->expectException(LogicException::class);
        SupplierLedgerEntry::query()->first()->delete();
    }

    #[Test]
    public function a_draft_can_be_cancelled_but_not_posted_afterwards(): void
    {
        $draft = $this->walkthroughDraft();

        $this->assertTrue(app(CancelPurchaseDraft::class)($this->owner, $draft)->applied);
        $this->assertFalse(app(CancelPurchaseDraft::class)($this->owner, $draft)->applied);

        $this->expectException(ValidationException::class);
        app(PostPurchase::class)($this->owner, $draft, $draft->refresh()->revision);
    }

    #[Test]
    public function a_posted_invoice_cannot_be_cancelled_or_edited(): void
    {
        $purchase = $this->postedPurchase($this->owner, $this->store, $this->supplier, [[$this->cable, 1, '5.00']]);

        try {
            app(CancelPurchaseDraft::class)($this->owner, $purchase);
            $this->fail('A posted invoice was cancelled.');
        } catch (ValidationException) {
        }

        $this->expectException(ValidationException::class);
        app(SavePurchaseDraft::class)($this->owner, $purchase, $purchase->revision, $this->draftInput($purchase));
    }

    #[Test]
    public function the_tracking_mode_is_locked_once_a_posted_invoice_uses_the_product(): void
    {
        $draft = $this->draftPurchase($this->owner, $this->store, $this->supplier, [[$this->cable, 1, '5.00']]);

        app(SaveProductDefinition::class)($this->owner, $this->cable, ['name' => $this->cable->name, 'tracking_mode' => 'serialized']);
        $this->cable->refresh();

        try {
            app(PostPurchase::class)($this->owner, $draft, $draft->revision);
            $this->fail('A line with an outdated tracking snapshot was posted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('lines', $exception->errors());
        }

        app(SaveProductDefinition::class)($this->owner, $this->cable, ['name' => $this->cable->name, 'tracking_mode' => 'quantity']);
        $this->postedPurchase($this->owner, $this->store, $this->supplier, [[$this->cable->refresh(), 1, '5.00']], ['supplier_invoice_number' => 'LOCK-1']);

        $this->expectException(ValidationException::class);
        app(SaveProductDefinition::class)($this->owner, $this->cable, ['name' => $this->cable->name, 'tracking_mode' => 'serialized']);
    }

    #[Test]
    public function only_owners_with_manage_purchases_can_edit_or_post(): void
    {
        $draft = $this->walkthroughDraft();

        $this->revokeFromOwners('manage_purchases');

        $this->expectException(AuthorizationException::class);
        app(PostPurchase::class)($this->owner->fresh(), $draft, $draft->revision);
    }

    #[Test]
    public function employees_with_store_access_cannot_post(): void
    {
        $draft = $this->walkthroughDraft();
        $employee = User::factory()->create()->assignRole('employee');
        $employee->givePermissionTo('manage_purchases');
        $this->store->employees()->attach($employee);

        $this->expectException(AuthorizationException::class);
        app(PostPurchase::class)($employee, $draft, $draft->revision);
    }

    #[Test]
    public function account_deletion_is_blocked_for_actors_on_financial_records_before_side_effects(): void
    {
        $creator = $this->createOwner();
        $this->draftPurchase($creator, $this->store, $this->supplier, [[$this->cable, 1, '5.00']]);

        try {
            app(DeleteUser::class)->delete($creator);
            $this->fail('A user referenced by a purchase was deleted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('password', $exception->errors());
        }

        $this->assertNotNull($creator->fresh());
    }

    /**
     * @return array<string, mixed>
     */
    private function draftInput(Purchase $purchase): array
    {
        $purchase->refresh();

        return [
            'store_id' => $purchase->store_id,
            'operational_supplier_id' => $purchase->operational_supplier_id,
            'currency' => $purchase->currency->value,
            'tax_treatment' => $purchase->tax_treatment->value,
            'supplier_invoice_number' => $purchase->supplier_invoice_number,
            'invoice_date' => $purchase->invoice_date?->toDateString(),
            'additional_charges' => $purchase->additional_charges,
            'lines' => $purchase->lines()->get()->map(fn (PurchaseLine $line): array => [
                'product_definition_id' => $line->product_definition_id,
                'quantity' => (string) $line->quantity,
                'unit_cost' => $line->unit_cost,
            ])->all(),
        ];
    }
}
