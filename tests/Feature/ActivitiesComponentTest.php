<?php

namespace Tests\Feature;

use App\Livewire\Backend\ActivitiesComponent;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ActivitiesComponentTest extends TestCase
{
    use RefreshDatabase;

    public function test_salesperson_can_only_see_activities_related_to_assigned_shops()
    {
        // Create admin user
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        // Create salesperson
        $salesperson = User::factory()->create();
        $salesperson->assignRole('salesperson');

        // Create shop owner
        $shopOwner = User::factory()->create();
        $shopOwner->assignRole('shop_owner');

        // Create shops
        $assignedShop = Shop::factory()->create([
            'salesperson_id' => $salesperson->id,
            'owner_id' => $shopOwner->id,
        ]);

        $unassignedShop = Shop::factory()->create([
            'salesperson_id' => null,
            'owner_id' => $shopOwner->id,
        ]);

        // Create activities
        activity('shop_created')
            ->performedOn($assignedShop)
            ->causedBy($admin)
            ->log('Shop created');

        activity('shop_created')
            ->performedOn($unassignedShop)
            ->causedBy($admin)
            ->log('Shop created');

        activity('user_login')
            ->causedBy($salesperson)
            ->log('User logged in');

        // Test as salesperson
        Livewire::actingAs($salesperson)
            ->test(ActivitiesComponent::class)
            ->assertSee('Shop created') // Should see activity for assigned shop
            ->assertSee('User logged in'); // Should see their own activity

        // Verify that the salesperson cannot see activities for unassigned shops
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => 'App\\Models\\Shop',
            'subject_id' => $unassignedShop->id,
        ]);

        // The component should filter out the unassigned shop activity
        $component = Livewire::actingAs($salesperson)
            ->test(ActivitiesComponent::class);

        $activities = $component->get('activities');
        $this->assertCount(2, $activities); // Only assigned shop + own activity
    }

    public function test_shop_owner_can_only_see_activities_related_to_owned_shop()
    {
        // Create admin user
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        // Create shop owner
        $shopOwner = User::factory()->create();
        $shopOwner->assignRole('shop_owner');

        // Create another shop owner
        $otherShopOwner = User::factory()->create();
        $otherShopOwner->assignRole('shop_owner');

        // Create shops
        $ownedShop = Shop::factory()->create([
            'owner_id' => $shopOwner->id,
        ]);

        $otherShop = Shop::factory()->create([
            'owner_id' => $otherShopOwner->id,
        ]);

        // Create activities
        activity('shop_created')
            ->performedOn($ownedShop)
            ->causedBy($admin)
            ->log('Shop created');

        activity('shop_created')
            ->performedOn($otherShop)
            ->causedBy($admin)
            ->log('Shop created');

        activity('user_login')
            ->causedBy($shopOwner)
            ->log('User logged in');

        // Test as shop owner
        Livewire::actingAs($shopOwner)
            ->test(ActivitiesComponent::class)
            ->assertSee('Shop created') // Should see activity for owned shop
            ->assertSee('User logged in'); // Should see their own activity

        // Verify that the shop owner cannot see activities for other shops
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => 'App\\Models\\Shop',
            'subject_id' => $otherShop->id,
        ]);

        // The component should filter out the other shop activity
        $component = Livewire::actingAs($shopOwner)
            ->test(ActivitiesComponent::class);

        $activities = $component->get('activities');
        $this->assertCount(2, $activities); // Only owned shop + own activity
    }

    public function test_admin_can_see_all_activities()
    {
        // Create admin user
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        // Create salesperson
        $salesperson = User::factory()->create();
        $salesperson->assignRole('salesperson');

        // Create shop owner
        $shopOwner = User::factory()->create();
        $shopOwner->assignRole('shop_owner');

        // Create shop
        $shop = Shop::factory()->create([
            'owner_id' => $shopOwner->id,
        ]);

        // Create activities
        activity('shop_created')
            ->performedOn($shop)
            ->causedBy($admin)
            ->log('Shop created');

        activity('user_login')
            ->causedBy($salesperson)
            ->log('User logged in');

        activity('user_login')
            ->causedBy($shopOwner)
            ->log('User logged in');

        // Test as admin
        Livewire::actingAs($admin)
            ->test(ActivitiesComponent::class)
            ->assertSee('Shop created')
            ->assertSee('User logged in');

        // Admin should see all activities
        $component = Livewire::actingAs($admin)
            ->test(ActivitiesComponent::class);

        $activities = $component->get('activities');
        $this->assertCount(3, $activities); // All activities
    }

    public function test_unauthorized_user_cannot_access_activities()
    {
        // Create regular user without roles
        $user = User::factory()->create();

        // Test access
        $this->actingAs($user)
            ->get('/activities')
            ->assertStatus(403);
    }
}
