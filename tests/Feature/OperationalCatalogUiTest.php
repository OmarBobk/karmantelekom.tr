<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Inventory\RecordOpeningBalance;
use App\Enums\TrackingMode;
use App\Livewire\Backend\OperationalCatalog\CatalogSettings;
use App\Livewire\Backend\OperationalCatalog\DefinitionsIndex;
use App\Livewire\Backend\Suppliers\SuppliersIndex;
use App\Models\CatalogBrand;
use App\Models\CatalogCategory;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\OperationalSupplier;
use App\Models\ProductDefinition;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\InteractsWithOperationalInventory;
use Tests\TestCase;

class OperationalCatalogUiTest extends TestCase
{
    use InteractsWithOperationalInventory;
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOperationalRoles();
        $this->owner = $this->createOwner();
    }

    #[Test]
    public function owner_manages_brands_and_categories_with_inline_duplicate_errors(): void
    {
        $this->actingAs($this->owner)
            ->get(route('subdomain.catalog.settings'))
            ->assertOk()
            ->assertSeeLivewire(CatalogSettings::class)
            ->assertSee(['العلامات التجارية', 'الفئات', 'لا توجد علامات تجارية بعد.']);

        $component = Livewire::actingAs($this->owner)
            ->test(CatalogSettings::class)
            ->set('newBrandName', '  Apple ')
            ->call('create', 'brand')
            ->assertHasNoErrors()
            ->assertDispatched('notify', ['type' => 'success', 'message' => 'تمت إضافة العلامة التجارية.'])
            ->set('newCategoryName', 'Cables')
            ->call('create', 'category')
            ->assertHasNoErrors()
            ->set('newBrandName', 'Apple')
            ->call('create', 'brand')
            ->assertHasErrors(['newBrandName' => 'unique'])
            ->assertSee('الاسم مستخدم بالفعل.');

        $brand = CatalogBrand::query()->sole();
        $this->assertSame('Apple', $brand->name);
        $this->assertSame('Cables', CatalogCategory::query()->sole()->name);

        $component
            ->call('edit', 'brand', $brand->id)
            ->set('editingName', 'Apple Inc.')
            ->call('update')
            ->assertHasNoErrors()
            ->call('toggleActive', 'brand', $brand->id);

        $this->assertDatabaseHas('catalog_brands', ['id' => $brand->id, 'name' => 'Apple Inc.', 'is_active' => false]);

        $this->assertThrows(
            fn () => Livewire::actingAs($this->owner)->test(CatalogSettings::class)->set('editingId', 999),
            CannotUpdateLockedPropertyException::class,
        );
    }

    #[Test]
    public function owner_manages_suppliers(): void
    {
        $component = Livewire::actingAs($this->owner)
            ->test(SuppliersIndex::class)
            ->assertSee('لا يوجد موردون بعد')
            ->call('openCreateForm')
            ->call('save')
            ->assertHasErrors(['name' => 'required'])
            ->assertSee('حقل اسم المورد مطلوب.')
            ->set('name', 'Istanbul Wholesale')
            ->set('phone', '+90 212 555 0000')
            ->set('taxNumber', '1234567890')
            ->set('email', 'not-an-email')
            ->call('save')
            ->assertHasErrors(['email' => 'email'])
            ->set('email', 'orders@example.com')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('formOpen', false)
            ->assertDispatched('notify', ['type' => 'success', 'message' => 'تمت إضافة المورد.']);

        $supplier = OperationalSupplier::query()->sole();
        $this->assertSame('+90 212 555 0000', $supplier->phone);
        $this->assertNull($supplier->contact_name);

        $component
            ->call('openCreateForm')
            ->set('name', 'Another')
            ->set('taxNumber', '1234567890')
            ->call('save')
            ->assertHasErrors(['taxNumber' => 'unique'])
            ->call('openEditForm', $supplier->id)
            ->set('contactName', 'Mehmet')
            ->call('save')
            ->assertHasNoErrors()
            ->set('search', 'istanbul')
            ->assertSee('Istanbul Wholesale');

        $this->assertSame('Mehmet', $supplier->fresh()->contact_name);
        $this->assertSame(1, OperationalSupplier::count());
    }

    #[Test]
    public function owner_creates_definitions_without_creating_stock(): void
    {
        $brand = CatalogBrand::factory()->create(['name' => 'Anker']);
        $category = CatalogCategory::factory()->create(['name' => 'Cables']);

        Livewire::actingAs($this->owner)
            ->test(DefinitionsIndex::class)
            ->assertSee('لا توجد تعريفات منتجات بعد')
            ->call('openCreateForm')
            ->set('name', 'USB-C Cable 1m')
            ->set('model', 'A8866')
            ->set('trackingMode', 'quantity')
            ->set('brandId', $brand->id)
            ->set('categoryId', $category->id)
            ->set('sku', ' ANK-USBC-1M ')
            ->set('barcode', '0012345678905')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('notify', ['type' => 'success', 'message' => 'تم إنشاء تعريف المنتج.'])
            ->assertSee(['USB-C Cable 1m', 'Anker', 'ANK-USBC-1M', 'بالكمية'])
            ->call('openCreateForm')
            ->set('name', 'Duplicate')
            ->set('sku', 'ANK-USBC-1M')
            ->call('save')
            ->assertHasErrors(['sku' => 'unique'])
            ->assertSee('رمز SKU مستخدم بالفعل.');

        $definition = ProductDefinition::query()->sole();
        $this->assertSame('ANK-USBC-1M', $definition->sku);
        $this->assertSame('0012345678905', $definition->barcode);
        $this->assertSame($brand->id, $definition->catalog_brand_id);
        $this->assertSame($category->id, $definition->catalog_category_id);
        $this->assertSame(0, InventoryBalance::count());
        $this->assertSame(0, InventoryMovement::count());
    }

    #[Test]
    public function definitions_are_found_by_name_model_or_exact_typed_identifier(): void
    {
        $cable = ProductDefinition::factory()->create(['name' => 'USB-C Cable', 'model' => 'A8866', 'sku' => 'ANK-1', 'barcode' => '0012345678905']);
        $charger = ProductDefinition::factory()->create(['name' => 'Wall Charger', 'model' => 'PD-20', 'sku' => 'ANK-10', 'barcode' => '12345678905']);

        $matches = fn (string $searchBy, string $search) => Livewire::actingAs($this->owner)
            ->test(DefinitionsIndex::class)
            ->set('searchBy', $searchBy)
            ->set('search', $search)
            ->viewData('definitions')
            ->pluck('id')
            ->all();

        $this->assertSame([$cable->id], $matches('name', 'cable'));
        $this->assertSame([$charger->id], $matches('name', 'PD-2'));
        $this->assertSame([$cable->id], $matches('sku', ' ANK-1 '));
        $this->assertSame([], $matches('sku', 'ank-1'));
        $this->assertSame([$cable->id], $matches('barcode', '0012345678905'));
        $this->assertSame([], $matches('barcode', '12345678'));
        $this->assertSame([$charger->id], $matches('barcode', '12345678905'));
        $this->assertSame([], $matches('sku', 'A8866'));
    }

    #[Test]
    public function tracking_mode_is_locked_in_the_form_once_stock_history_exists(): void
    {
        $definition = ProductDefinition::factory()->create(['name' => 'Cable', 'tracking_mode' => TrackingMode::QUANTITY]);
        $store = Store::factory()->create();

        $form = Livewire::actingAs($this->owner)
            ->test(DefinitionsIndex::class)
            ->call('openEditForm', $definition->id)
            ->assertViewHas('trackingModeLocked', false);

        app(RecordOpeningBalance::class)($this->owner, $store, $definition, 3, null, 'open');

        $form
            ->set('trackingMode', 'used_device')
            ->call('save')
            ->assertHasErrors('tracking_mode')
            ->assertSee('لا يمكن تغيير طريقة التتبع بعد تسجيل مخزون لهذا المنتج.')
            ->assertViewHas('trackingModeLocked', true);

        $this->assertSame(TrackingMode::QUANTITY, $definition->fresh()->tracking_mode);

        $this->assertThrows(
            fn () => $form->set('editingDefinitionId', 999),
            CannotUpdateLockedPropertyException::class,
        );
    }

    /**
     * @return array<string, array{?string, int}>
     */
    public static function deniedVisitors(): array
    {
        return [
            'guest' => [null, 302],
            'employee' => ['employee', 302],
            'salesperson' => ['salesperson', 403],
        ];
    }

    #[Test]
    #[DataProvider('deniedVisitors')]
    public function catalog_and_supplier_pages_are_owner_only_by_direct_url(?string $role, int $expectedStatus): void
    {
        if ($role !== null) {
            $this->actingAs(User::factory()->create()->assignRole($role));
        }

        $this->get(route('subdomain.catalog.definitions'))->assertStatus($expectedStatus);
        $this->get(route('subdomain.catalog.settings'))->assertStatus($expectedStatus);
        $this->get(route('subdomain.suppliers'))->assertStatus($expectedStatus);
    }

    #[Test]
    public function each_area_requires_its_own_permission(): void
    {
        $this->revokeFromOwners('manage_catalog');

        $this->actingAs($this->owner)->get(route('subdomain.catalog.definitions'))->assertForbidden();
        $this->actingAs($this->owner)->get(route('subdomain.catalog.settings'))->assertForbidden();
        $this->actingAs($this->owner)->get(route('subdomain.suppliers'))->assertOk();

        $this->revokeFromOwners('manage_suppliers');

        $this->actingAs($this->owner)->get(route('subdomain.suppliers'))->assertForbidden();
    }

    #[Test]
    public function revoking_catalog_permission_blocks_later_livewire_requests(): void
    {
        $form = Livewire::actingAs($this->owner)
            ->test(DefinitionsIndex::class)
            ->call('openCreateForm')
            ->set('name', 'Late definition');

        $this->revokeFromOwners('manage_catalog');

        $form->call('save')->assertForbidden();
        $this->assertSame(0, ProductDefinition::count());
    }

    #[Test]
    public function catalog_pages_render_left_to_right_in_english(): void
    {
        $this->useEnglishLocale();
        ProductDefinition::factory()->create(['name' => 'USB-C Cable', 'tracking_mode' => TrackingMode::SERIALIZED]);

        $this->actingAs($this->owner)
            ->get(route('subdomain.catalog.definitions'))
            ->assertOk()
            ->assertSee('dir="ltr" lang="en"', false)
            ->assertSee(['Product catalog', 'Add product definition', 'Serialized (new)', 'USB-C Cable'])
            ->assertDontSee('كتالوج المنتجات');

        $this->actingAs($this->owner)
            ->get(route('subdomain.suppliers'))
            ->assertOk()
            ->assertSee(['Suppliers', 'Add supplier', 'No suppliers yet']);
    }
}
