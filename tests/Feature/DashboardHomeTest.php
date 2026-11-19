<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Backend\DashboardComponent;
use App\Livewire\Backend\Reports\OperationsDashboard;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DashboardHomeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::create(['name' => 'use_pos']);
        Role::create(['name' => 'admin'])->givePermissionTo('use_pos');
        Role::create(['name' => 'salesperson']);
        Role::create(['name' => 'employee']);
    }

    #[Test]
    public function guests_are_sent_from_the_subdomain_home_to_login(): void
    {
        $this->get(route('subdomain.main'))
            ->assertRedirect(route('login'));
    }

    #[Test]
    public function owners_are_redirected_from_legacy_home_to_the_operations_dashboard(): void
    {
        $owner = User::factory()->create()->assignRole('admin');
        Store::factory()->create();

        $this->actingAs($owner)
            ->get(route('subdomain.main'))
            ->assertRedirect(route('subdomain.operations.dashboard'));

        Livewire::actingAs($owner)
            ->test(DashboardComponent::class)
            ->assertRedirect(route('subdomain.operations.dashboard'));
    }

    #[Test]
    public function salespersons_can_open_the_legacy_home_without_analytics_credentials(): void
    {
        config([
            'analytics.property_id' => null,
            'analytics.service_account_credentials_json' => storage_path('app/analytics/missing-credentials.json'),
        ]);

        $salesperson = User::factory()->create()->assignRole('salesperson');

        $this->actingAs($salesperson)
            ->get(route('subdomain.main'))
            ->assertOk()
            ->assertSeeLivewire(DashboardComponent::class)
            ->assertSee('Dashboard');

        Livewire::actingAs($salesperson)
            ->test(DashboardComponent::class)
            ->assertSet('totalProducts', 0)
            ->assertSet('totalVisitors', 0)
            ->assertSet('mostViewedProducts', [])
            ->assertOk();
    }

    #[Test]
    public function employees_are_blocked_from_the_subdomain_home(): void
    {
        $employee = User::factory()->create()->assignRole('employee');

        $this->actingAs($employee)
            ->get(route('subdomain.main'))
            ->assertRedirect(config('app.url').'/404');
    }

    #[Test]
    public function admin_login_intends_the_operations_dashboard(): void
    {
        $owner = User::factory()->create()->assignRole('admin');

        $response = $this->post(route('login'), [
            'email' => $owner->email,
            'password' => '123',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('subdomain.operations.dashboard'));
    }

    #[Test]
    public function operations_dashboard_still_renders_for_owners(): void
    {
        $owner = User::factory()->create()->assignRole('admin');
        Store::factory()->create(['code' => 'KT01', 'name' => 'Store One']);

        $this->actingAs($owner)
            ->get(route('subdomain.operations.dashboard'))
            ->assertOk()
            ->assertSeeLivewire(OperationsDashboard::class);
    }
}
