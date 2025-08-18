<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Shop;
use App\Models\User;
use App\Notifications\ShopAssignmentNotification;
use Illuminate\Console\Command;

class TestShopAssignmentNotification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:shop-assignment-notification {shop_id} {salesperson_id} {assigned_by_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test shop assignment notification by sending it to a salesperson';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $shopId = $this->argument('shop_id');
        $salespersonId = $this->argument('salesperson_id');
        $assignedById = $this->argument('assigned_by_id');

        try {
            $shop = Shop::findOrFail($shopId);
            $salesperson = User::findOrFail($salespersonId);
            $assignedBy = User::findOrFail($assignedById);

            $this->info("Sending shop assignment notification...");
            $this->info("Shop: {$shop->name} (ID: {$shop->id})");
            $this->info("Salesperson: {$salesperson->name} (ID: {$salesperson->id})");
            $this->info("Assigned by: {$assignedBy->name} (ID: {$assignedBy->id})");

            // Send the notification
            $salesperson->notify(new ShopAssignmentNotification($shop, $assignedBy));

            $this->info("✅ Shop assignment notification sent successfully!");
            $this->info("The salesperson should now see the notification in their notification dropdown.");
            $this->info("Clicking on it should redirect to: " . route('subdomain.shop', ['shop' => $shop->id]));

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Failed to send notification: " . $e->getMessage());
            return self::FAILURE;
        }
    }
}
