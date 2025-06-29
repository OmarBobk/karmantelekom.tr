<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Shop;
use App\Models\User;
use App\Services\NotificationRecipientsService;
use App\Enums\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NotificationRecipientsServiceTest extends TestCase
{
    use RefreshDatabase;

    private NotificationRecipientsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(NotificationRecipientsService::class);
    }

    public function test_resolves_admin_and_shop_users_for_order(): void
    {
        // Create roles
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'salesperson']);

        // Create users
        $adminUser = User::factory()->create();
        $adminUser->assignRole('admin');

        $salespersonUser = User::factory()->create();
        $salespersonUser->assignRole('salesperson');

        $shopUser = User::factory()->create();

        // Create shop with assigned user
        $shop = Shop::factory()->create(['user_id' => $shopUser->id]);

        // Create order
        $order = Order::create([
            'shop_id' => $shop->id,
            'user_id' => $salespersonUser->id,
            'status' => OrderStatus::PENDING,
            'total_price' => 100.00,
            'notes' => 'Test order',
        ]);

        // Resolve recipients
        $recipients = $this->service->resolveNotificationRecipientsFor($order, excludeCauser: false);

        // Should include admin, shop user, and salesperson
        $this->assertGreaterThanOrEqual(3, $recipients->count());
        $this->assertTrue($recipients->contains('id', $adminUser->id));
        $this->assertTrue($recipients->contains('id', $shopUser->id));
        $this->assertTrue($recipients->contains('id', $salespersonUser->id));
    }

    public function test_excludes_causer_when_requested(): void
    {
        Role::create(['name' => 'admin']);

        $adminUser = User::factory()->create();
        $adminUser->assignRole('admin');

        $shop = Shop::factory()->create();
        $order = Order::factory()->create(['shop_id' => $shop->id]);

        // Authenticate as admin user
        $this->actingAs($adminUser);

        $recipients = $this->service->resolveNotificationRecipientsFor($order, excludeCauser: true);

        // Should not include the authenticated admin user
        $this->assertFalse($recipients->contains('id', $adminUser->id));
    }

    public function test_resolves_admin_only_recipients(): void
    {
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'salesperson']);

        $adminUser = User::factory()->create();
        $adminUser->assignRole('admin');

        $salespersonUser = User::factory()->create();
        $salespersonUser->assignRole('salesperson');

        $recipients = $this->service->resolveAdminRecipients(excludeCauser: false);

        $this->assertEquals(1, $recipients->count());
        $this->assertTrue($recipients->contains('id', $adminUser->id));
        $this->assertFalse($recipients->contains('id', $salespersonUser->id));
    }
} 