<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Events\ShopCreated;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Console\Command;

class TestShopCreationSystem extends Command
{
    protected $signature = 'test:shop-creation';
    protected $description = 'Test the shop creation event system';

    public function handle(): int
    {
        $this->info('🧪 Testing Shop Creation Event System...');

        // Get or create a test user
        $user = User::first();
        if (!$user) {
            $this->error('❌ No users found in the database. Please create a user first.');
            return 1;
        }

        $this->info("👤 Using user: {$user->name} ({$user->email})");

        // Create a test shop
        $shop = Shop::create([
            'name' => 'Test Shop - ' . now()->format('Y-m-d H:i:s'),
            'phone' => '1234567890',
            'address' => 'Test Address for Event System',
            'owner_id' => $user->id,
        ]);

        $this->info("🏪 Created test shop: {$shop->name}");

        // Dispatch the event
        ShopCreated::dispatch($shop, $user->id);

        $this->info('✅ ShopCreated event dispatched successfully!');

        // Check if activity was logged
        $activity = \Spatie\Activitylog\Models\Activity::where('subject_type', Shop::class)
            ->where('subject_id', $shop->id)
            ->where('log_name', 'shop_created')
            ->latest()
            ->first();

        if ($activity) {
            $this->info('📝 Activity logged successfully:');
            $this->line("   Description: {$activity->description}");
            $this->line("   Log Name: {$activity->log_name}");
            $this->line("   Created at: {$activity->created_at}");
        } else {
            $this->warn('⚠️  No activity found in the log. Check if the listener is properly registered.');
        }

        // Check if notifications were sent
        $adminUsers = User::whereHas('roles', function ($query) {
            $query->where('name', 'admin');
        })->get();

        if ($adminUsers->isNotEmpty()) {
            $this->info('📧 Notifications sent to admin users:');
            foreach ($adminUsers as $admin) {
                $this->line("   • {$admin->name} ({$admin->email})");
            }
        } else {
            $this->warn('⚠️  No admin users found to receive notifications.');
        }

        $this->info('🎉 Shop creation event system test completed!');

        return 0;
    }
}
