<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Stores\AssignEmployeeToStore;
use App\Actions\Stores\RemoveEmployeeFromStore;
use App\Models\Store;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class StoreEmployeeAssignmentTest extends TestCase
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

        foreach (['employee', 'salesperson', 'shop_owner', 'customer'] as $role) {
            Role::create(['name' => $role]);
        }

        $this->owner = User::factory()->create()->assignRole('admin');
        $this->employee = User::factory()->create()->assignRole('employee');
        $this->storeOne = Store::factory()->create(['code' => 'KT01']);
        $this->storeTwo = Store::factory()->create(['code' => 'KT02']);
    }

    #[Test]
    public function owner_assigns_employee_to_two_stores_without_duplicates(): void
    {
        $assign = app(AssignEmployeeToStore::class);

        $this->assertTrue($assign($this->owner, $this->employee, $this->storeOne));
        $this->assertTrue($assign($this->owner, $this->employee, $this->storeTwo));
        $this->assertFalse($assign($this->owner, $this->employee, $this->storeOne));

        $this->assertEqualsCanonicalizing(
            [$this->storeOne->id, $this->storeTwo->id],
            $this->employee->stores()->pluck('stores.id')->all(),
        );
        $this->assertDatabaseCount('store_user', 2);
        $this->assertSame(2, Activity::where('event', 'employee_assigned')->count());

        $activity = Activity::where('event', 'employee_assigned')
            ->where('subject_id', $this->storeOne->id)
            ->sole();

        $this->assertSame('store_assignment', $activity->log_name);
        $this->assertTrue($activity->subject->is($this->storeOne));
        $this->assertTrue($activity->causer->is($this->owner));
        $this->assertSame($this->employee->id, $activity->getProperty('user_id'));
        $this->assertSame('KT01', $activity->getProperty('store_code'));
    }

    /**
     * @return array<string, array{\Closure(): User}>
     */
    public static function unauthorizedActors(): array
    {
        return [
            'admin without manage_stores' => [fn (): User => tap(
                User::factory()->create()->assignRole('admin'),
                fn () => Role::findByName('admin')->revokePermissionTo('manage_stores'),
            )],
            'employee' => [fn (): User => User::factory()->create()->assignRole('employee')],
            'salesperson with direct manage_stores' => [fn (): User => User::factory()->create()
                ->assignRole('salesperson')
                ->givePermissionTo('manage_stores')],
        ];
    }

    #[Test]
    #[DataProvider('unauthorizedActors')]
    public function unauthorized_actor_cannot_assign_or_remove(\Closure $makeActor): void
    {
        app(AssignEmployeeToStore::class)($this->owner, $this->employee, $this->storeOne);
        $otherEmployee = User::factory()->create()->assignRole('employee');
        $actor = $makeActor();

        $this->assertThrowsAuthorization(
            fn () => app(AssignEmployeeToStore::class)($actor, $otherEmployee, $this->storeOne),
        );
        $this->assertThrowsAuthorization(
            fn () => app(RemoveEmployeeFromStore::class)($actor, $this->employee, $this->storeOne),
        );

        $this->assertDatabaseCount('store_user', 1);
        $this->assertDatabaseHas('store_user', ['store_id' => $this->storeOne->id, 'user_id' => $this->employee->id]);
        $this->assertSame(1, Activity::where('log_name', 'store_assignment')->count());
    }

    #[Test]
    public function assignment_rejects_targets_without_employee_role(): void
    {
        $salesperson = User::factory()->create()->assignRole('salesperson');

        try {
            app(AssignEmployeeToStore::class)($this->owner, $salesperson, $this->storeOne);
            $this->fail('Expected assignment of a non-employee to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('user', $exception->errors());
        }

        $this->assertDatabaseCount('store_user', 0);
        $this->assertSame(0, Activity::where('log_name', 'store_assignment')->count());
        $this->assertTrue($salesperson->fresh()->hasRole('salesperson'));
        $this->assertFalse($salesperson->fresh()->hasRole('employee'));
    }

    #[Test]
    public function removal_preserves_other_membership_works_after_role_loss_and_is_repeatable(): void
    {
        $assign = app(AssignEmployeeToStore::class);
        $remove = app(RemoveEmployeeFromStore::class);

        $assign($this->owner, $this->employee, $this->storeOne);
        $assign($this->owner, $this->employee, $this->storeTwo);
        $this->employee->removeRole('employee');

        $this->assertTrue($remove($this->owner, $this->employee, $this->storeOne));
        $this->assertFalse($remove($this->owner, $this->employee, $this->storeOne));

        $this->assertSame([$this->storeTwo->id], $this->employee->stores()->pluck('stores.id')->all());

        $removal = Activity::where('event', 'employee_removed')->sole();

        $this->assertTrue($removal->subject->is($this->storeOne));
        $this->assertTrue($removal->causer->is($this->owner));
        $this->assertSame($this->employee->id, $removal->getProperty('user_id'));
    }

    private function assertThrowsAuthorization(\Closure $callback): void
    {
        try {
            $callback();
            $this->fail('Expected an AuthorizationException.');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }
    }
}
