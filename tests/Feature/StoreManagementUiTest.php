<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Backend\Stores\StoreDetail;
use App\Livewire\Backend\Stores\StoresIndex;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class StoreManagementUiTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $employee;

    private Store $storeOne;

    private Store $storeTwo;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::create(['name' => 'manage_stores']);
        Role::create(['name' => 'admin'])->givePermissionTo('manage_stores');

        foreach (['employee', 'salesperson'] as $role) {
            Role::create(['name' => $role]);
        }

        $this->owner = User::factory()->create()->assignRole('admin');
        $this->employee = User::factory()->create(['name' => 'Employee Ahmad'])->assignRole('employee');
        $this->storeOne = Store::factory()->create(['code' => 'KT01', 'name' => 'Karman Telekom — Store 1', 'phone' => '+90 212 000 0001']);
        $this->storeTwo = Store::factory()->create(['code' => 'KT02', 'name' => 'Karman Telekom — Store 2']);
    }

    #[Test]
    public function owner_views_store_list_and_detail_through_routes(): void
    {
        $this->storeOne->employees()->attach($this->employee);

        $this->actingAs($this->owner)
            ->get(route('subdomain.stores'))
            ->assertOk()
            ->assertSeeLivewire(StoresIndex::class)
            ->assertSee('dir="rtl" lang="ar"', false)
            ->assertSee('الفروع')
            ->assertSee(['KT01', 'Karman Telekom — Store 1', '+90 212 000 0001', 'KT02']);

        $this->actingAs($this->owner)
            ->get(route('subdomain.stores.show', $this->storeOne))
            ->assertOk()
            ->assertSeeLivewire(StoreDetail::class)
            ->assertSee(['Employee Ahmad', 'الموظفون المعيّنون']);
    }

    /**
     * @return array<string, array{?string, bool, int}>
     */
    public static function deniedVisitors(): array
    {
        return [
            'guest' => [null, false, 302],
            'employee' => ['employee', false, 302],
            'salesperson' => ['salesperson', false, 403],
            'admin without manage_stores' => ['admin', true, 403],
        ];
    }

    #[Test]
    #[DataProvider('deniedVisitors')]
    public function non_owners_cannot_open_store_pages_directly(?string $role, bool $revokePermission, int $expectedStatus): void
    {
        if ($revokePermission) {
            Role::findByName('admin')->revokePermissionTo('manage_stores');
        }

        if ($role !== null) {
            $this->actingAs(User::factory()->create()->assignRole($role));
        }

        $this->get(route('subdomain.stores'))->assertStatus($expectedStatus);
        $this->get(route('subdomain.stores.show', $this->storeOne))->assertStatus($expectedStatus);
    }

    #[Test]
    public function owner_creates_and_edits_store_while_code_stays_stable(): void
    {
        Livewire::actingAs($this->owner)
            ->test(StoresIndex::class)
            ->call('openCreateForm')
            ->set('code', 'KT03')
            ->set('name', 'Karman Telekom — Store 3')
            ->set('phone', '+90 212 000 0003')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('formOpen', false)
            ->assertDispatched('notify', ['type' => 'success', 'message' => 'تم إنشاء الفرع بنجاح.']);

        $created = Store::where('code', 'KT03')->sole();
        $this->assertSame('+90 212 000 0003', $created->phone);
        $this->assertNull($created->address);

        Livewire::actingAs($this->owner)
            ->test(StoresIndex::class)
            ->call('openEditForm', $created->id)
            ->assertSet('code', 'KT03')
            ->set('code', 'CHANGED')
            ->set('name', 'Renamed Store')
            ->set('address', 'Istanbul')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('notify', ['type' => 'success', 'message' => 'تم تحديث بيانات الفرع بنجاح.']);

        $this->assertDatabaseHas('stores', ['id' => $created->id, 'code' => 'KT03', 'name' => 'Renamed Store', 'address' => 'Istanbul']);
        $this->assertDatabaseMissing('stores', ['code' => 'CHANGED']);
        $this->assertSame(
            ['store_created', 'store_updated'],
            Activity::where('log_name', 'store_management')->where('subject_id', $created->id)->orderBy('id')->pluck('event')->all(),
        );
    }

    #[Test]
    public function store_form_shows_inline_arabic_validation_errors(): void
    {
        Livewire::actingAs($this->owner)
            ->test(StoresIndex::class)
            ->call('openCreateForm')
            ->set('code', 'KT01')
            ->call('save')
            ->assertHasErrors(['code' => 'unique', 'name' => 'required'])
            ->assertSee('رمز الفرع مستخدم بالفعل.')
            ->assertSee('حقل اسم الفرع مطلوب.')
            ->assertSet('formOpen', true);

        $this->assertSame(2, Store::count());
    }

    #[Test]
    public function store_pages_render_left_to_right_in_english_when_english_is_active(): void
    {
        $this->useEnglishLocale();
        $this->storeOne->employees()->attach($this->employee);

        $this->actingAs($this->owner)
            ->get(route('subdomain.stores'))
            ->assertOk()
            ->assertSee('dir="ltr" lang="en"', false)
            ->assertSee(['Stores', 'Add store', 'Store code', 'View details'])
            ->assertDontSee('الفروع');

        $this->actingAs($this->owner)
            ->get(route('subdomain.stores.show', $this->storeOne))
            ->assertOk()
            ->assertSee('dir="ltr" lang="en"', false)
            ->assertSee(['Assigned employees', 'Back to stores', 'Employee Ahmad']);
    }

    #[Test]
    public function store_form_and_notifications_use_english_when_english_is_active(): void
    {
        $this->useEnglishLocale();

        Livewire::actingAs($this->owner)
            ->test(StoresIndex::class)
            ->call('openCreateForm')
            ->set('code', 'KT01')
            ->call('save')
            ->assertHasErrors(['code' => 'unique', 'name' => 'required'])
            ->assertSee('This store code is already in use.')
            ->assertSee('The store name field is required.')
            ->set('code', 'KT03')
            ->set('name', 'Karman Telekom — Store 3')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('notify', ['type' => 'success', 'message' => 'Store created successfully.']);
    }

    #[Test]
    public function owner_assigns_and_removes_employee_across_two_stores(): void
    {
        Livewire::actingAs($this->owner)
            ->test(StoreDetail::class, ['store' => $this->storeOne])
            ->set('selectedEmployeeId', $this->employee->id)
            ->call('assign')
            ->assertHasNoErrors()
            ->assertDispatched('notify', ['type' => 'success', 'message' => 'تم تعيين Employee Ahmad في الفرع.']);

        Livewire::actingAs($this->owner)
            ->test(StoreDetail::class, ['store' => $this->storeTwo])
            ->set('selectedEmployeeId', $this->employee->id)
            ->call('assign')
            ->assertHasNoErrors();

        Livewire::actingAs($this->owner)
            ->test(StoreDetail::class, ['store' => $this->storeOne])
            ->assertSee('KT02')
            ->call('confirmRemoval', $this->employee->id)
            ->assertSee('يبقى حساب الموظف متاحًا، ولا تتأثر تعييناته في الفروع الأخرى.')
            ->call('remove')
            ->assertSet('employeeToRemoveId', null)
            ->assertDispatched('notify', ['type' => 'success', 'message' => 'تمت إزالة Employee Ahmad من الفرع.']);

        $this->assertSame([$this->storeTwo->id], $this->employee->stores()->pluck('stores.id')->all());
        $this->assertModelExists($this->employee);
        $this->assertSame(
            ['employee_assigned', 'employee_assigned', 'employee_removed'],
            Activity::where('log_name', 'store_assignment')->orderBy('id')->pluck('event')->all(),
        );
    }

    #[Test]
    public function assigning_a_non_employee_shows_an_inline_error(): void
    {
        $salesperson = User::factory()->create()->assignRole('salesperson');

        Livewire::actingAs($this->owner)
            ->test(StoreDetail::class, ['store' => $this->storeOne])
            ->set('selectedEmployeeId', $salesperson->id)
            ->call('assign')
            ->assertHasErrors(['selectedEmployeeId'])
            ->assertSee('يمكن تعيين المستخدمين الذين يملكون دور «موظف» فقط.');

        $this->assertDatabaseCount('store_user', 0);
    }

    #[Test]
    public function mutations_are_reauthorized_after_permission_is_revoked(): void
    {
        $this->storeOne->employees()->attach($this->employee);
        $otherEmployee = User::factory()->create()->assignRole('employee');

        $editForm = Livewire::actingAs($this->owner)->test(StoresIndex::class)->call('openEditForm', $this->storeOne->id);
        $removal = Livewire::actingAs($this->owner)->test(StoreDetail::class, ['store' => $this->storeOne])
            ->call('confirmRemoval', $this->employee->id);
        $assignment = Livewire::actingAs($this->owner)->test(StoreDetail::class, ['store' => $this->storeOne])
            ->set('selectedEmployeeId', $otherEmployee->id);

        Role::findByName('admin')->revokePermissionTo('manage_stores');

        $editForm->set('name', 'Hijacked')->call('save')->assertForbidden();
        $removal->call('remove')->assertForbidden();
        $assignment->call('assign')->assertForbidden();

        $this->assertDatabaseHas('stores', ['id' => $this->storeOne->id, 'name' => 'Karman Telekom — Store 1']);
        $this->assertSame([$this->employee->id], $this->storeOne->employees()->pluck('users.id')->all());
        $this->assertSame(0, Activity::count());
    }

    #[Test]
    public function tampered_identifiers_cannot_target_other_records(): void
    {
        $this->storeTwo->employees()->attach($this->employee);
        $this->withoutExceptionHandling();

        $this->assertThrows(
            fn () => Livewire::actingAs($this->owner)
                ->test(StoreDetail::class, ['store' => $this->storeOne])
                ->call('confirmRemoval', $this->employee->id),
            ModelNotFoundException::class,
        );

        $this->assertDatabaseHas('store_user', ['store_id' => $this->storeTwo->id, 'user_id' => $this->employee->id]);

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($this->owner)
            ->test(StoreDetail::class, ['store' => $this->storeOne])
            ->set('employeeToRemoveId', $this->employee->id);
    }

    #[Test]
    public function store_being_edited_cannot_be_swapped_by_the_client(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($this->owner)
            ->test(StoresIndex::class)
            ->call('openEditForm', $this->storeOne->id)
            ->set('editingStoreId', $this->storeTwo->id);
    }

    private function useEnglishLocale(): void
    {
        config(['app.locale' => 'en']);
        App::setLocale('en');
    }
}
