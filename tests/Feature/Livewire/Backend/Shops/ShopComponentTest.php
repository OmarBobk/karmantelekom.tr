<?php

namespace Tests\Feature\Livewire\Backend\Shops;

use App\Livewire\Backend\Shops\ShopComponent;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ShopComponentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create roles
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'salesperson']);
        Role::create(['name' => 'shop_owner']);
    }

    /** @test */
    public function it_can_filter_shops_by_created_at()
    {
        $admin = User::factory()->create()->assignRole('admin');
        $salesperson = User::factory()->create()->assignRole('salesperson');

        // Create shops with different creation dates
        $todayShop = Shop::factory()->create(['created_at' => now()]);
        $yesterdayShop = Shop::factory()->create(['created_at' => now()->subDay()]);
        $lastWeekShop = Shop::factory()->create(['created_at' => now()->subWeek()]);

        Livewire::actingAs($admin)
            ->test(ShopComponent::class)
            ->set('createdAtFilter', 'today')
            ->assertSee($todayShop->name)
            ->assertDontSee($yesterdayShop->name)
            ->assertDontSee($lastWeekShop->name);
    }

    /** @test */
    public function it_can_filter_shops_by_salesperson()
    {
        $admin = User::factory()->create()->assignRole('admin');
        $salesperson1 = User::factory()->create()->assignRole('salesperson');
        $salesperson2 = User::factory()->create()->assignRole('salesperson');

        // Create shops with different salespeople
        $shop1 = Shop::factory()->create(['salesperson_id' => $salesperson1->id]);
        $shop2 = Shop::factory()->create(['salesperson_id' => $salesperson2->id]);
        $unassignedShop = Shop::factory()->create(['salesperson_id' => null]);

        Livewire::actingAs($admin)
            ->test(ShopComponent::class)
            ->set('salespersonFilter', $salesperson1->id)
            ->assertSee($shop1->name)
            ->assertDontSee($shop2->name)
            ->assertDontSee($unassignedShop->name);
    }

    /** @test */
    public function it_can_filter_shops_by_unassigned_salesperson()
    {
        $admin = User::factory()->create()->assignRole('admin');
        $salesperson = User::factory()->create()->assignRole('salesperson');

        // Create shops with different salespeople
        $assignedShop = Shop::factory()->create(['salesperson_id' => $salesperson->id]);
        $unassignedShop1 = Shop::factory()->create(['salesperson_id' => null]);
        $unassignedShop2 = Shop::factory()->create(['salesperson_id' => null]);

        Livewire::actingAs($admin)
            ->test(ShopComponent::class)
            ->set('salespersonFilter', 'unassigned')
            ->assertSee($unassignedShop1->name)
            ->assertSee($unassignedShop2->name)
            ->assertDontSee($assignedShop->name);
    }

    /** @test */
    public function it_can_clear_all_filters()
    {
        $admin = User::factory()->create()->assignRole('admin');
        $shop = Shop::factory()->create();

        Livewire::actingAs($admin)
            ->test(ShopComponent::class)
            ->set('search', 'test')
            ->set('createdAtFilter', 'today')
            ->set('salespersonFilter', 1)
            ->assertSet('search', 'test')
            ->assertSet('createdAtFilter', 'today')
            ->assertSet('salespersonFilter', 1)
            ->call('clearAllFilters')
            ->assertSet('search', '')
            ->assertSet('createdAtFilter', '')
            ->assertSet('salespersonFilter', null);
    }

    /** @test */
    public function it_resets_page_when_filters_change()
    {
        $admin = User::factory()->create()->assignRole('admin');
        
        // Create enough shops to have pagination
        Shop::factory()->count(15)->create();

        Livewire::actingAs($admin)
            ->test(ShopComponent::class)
            ->set('perPage', 10)
            ->call('setPage', 2)
            ->assertSet('page', 2)
            ->set('createdAtFilter', 'today')
            ->assertSet('page', 1);
    }

    /** @test */
    public function it_can_sort_by_created_at()
    {
        $admin = User::factory()->create()->assignRole('admin');
        
        $oldShop = Shop::factory()->create(['created_at' => now()->subDays(5)]);
        $newShop = Shop::factory()->create(['created_at' => now()]);

        Livewire::actingAs($admin)
            ->test(ShopComponent::class)
            ->call('sortBy', 'created_at')
            ->assertSet('sortField', 'created_at')
            ->assertSet('sortDirection', 'asc');
    }
}
