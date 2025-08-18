<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ShopCreated;
use App\Models\User;
use App\Notifications\ShopCreatedNotification;
use App\Services\NotificationRecipientsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\Models\Activity;

/**
 * HandleShopCreated Listener
 *
 * Handles the ShopCreated event by logging activity and sending notifications
 */
class HandleShopCreated implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Create the event listener.
     */
    public function __construct(
        private NotificationRecipientsService $recipientsService
    ) {
    }

    /**
     * Handle the event.
     */
    public function handle(ShopCreated $event): void
    {
        try {
            $shop = $event->shop;
            $createdBy = $event->userId ? User::find($event->userId) : auth()->user();

            // Build detailed description and properties
            $description = $this->buildDetailedCreationDescription($shop, $createdBy);
            $properties = $this->buildDetailedProperties($shop, $createdBy);

            // Log the shop creation activity with enhanced details
            $activityLogger = activity('shop_created')
                ->performedOn($shop)
                ->causedBy($createdBy)
                ->withProperties($properties);

            $activityLogger->log($description);

            // Get admin users for notification
            $adminUsers = $this->getAdminUsers();

            // Send notification to admin users
            foreach ($adminUsers as $admin) {
                try {
                    $admin->notify(new ShopCreatedNotification($shop, $createdBy));
                } catch (\Exception $notificationError) {
                    Log::warning('Failed to send shop creation notification to admin', [
                        'shop_id' => $shop->id,
                        'admin_id' => $admin->id,
                        'error' => $notificationError->getMessage(),
                    ]);
                    // Don't throw the error, just log it and continue
                }
            }

            // Log success
            Log::info('Shop created event handled successfully', [
                'shop_id' => $shop->id,
                'shop_name' => $shop->name,
                'created_by_id' => $createdBy?->id,
                'created_by_name' => $createdBy?->name,
                'admin_notifications_sent' => $adminUsers->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to handle ShopCreated event', [
                'shop_id' => $event->shop->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Build a detailed description for the shop creation
     */
    private function buildDetailedCreationDescription($shop, $createdBy): string
    {
        $shopName = $shop->name;
        $createdByName = $createdBy ? $createdBy->name : 'System';
        $shopPhone = $shop->phone;
        $shopAddress = $shop->address;

        return "New shop '{$shopName}' has been created by {$createdByName}. Phone: {$shopPhone}, Address: {$shopAddress}";
    }

    /**
     * Build detailed properties for activity logging
     */
    private function buildDetailedProperties($shop, $createdBy): array
    {
        $properties = [
            'shop_id' => $shop->id,
            'shop_name' => $shop->name,
            'shop_phone' => $shop->phone,
            'shop_address' => $shop->address,
            'shop_links' => $shop->links ?? [],
            'created_by_id' => $createdBy?->id,
            'created_by_name' => $createdBy?->name,
            'created_by_email' => $createdBy?->email,
            'creation_timestamp' => now()->toISOString(),
            'shop_details' => [
                'name' => $shop->name,
                'phone' => $shop->phone,
                'address' => $shop->address,
                'links' => $shop->links ?? [],
                'owner_id' => $shop->owner_id,
                'salesperson_id' => $shop->salesperson_id,
            ],
            'creator_details' => $createdBy ? [
                'name' => $createdBy->name,
                'email' => $createdBy->email,
                'role' => $createdBy->roles->first()?->name ?? 'user',
            ] : [
                'name' => 'System',
                'email' => 'system@example.com',
                'role' => 'system',
            ],
        ];

        // Add owner details if shop has an owner
        if ($shop->owner) {
            $properties['owner_details'] = [
                'name' => $shop->owner->name,
                'email' => $shop->owner->email,
                'role' => $shop->owner->roles->first()?->name ?? 'shop_owner',
            ];
        }

        // Add salesperson details if shop has a salesperson
        if ($shop->salesperson) {
            $properties['salesperson_details'] = [
                'name' => $shop->salesperson->name,
                'email' => $shop->salesperson->email,
                'role' => $shop->salesperson->roles->first()?->name ?? 'salesperson',
            ];
        }

        return $properties;
    }

    /**
     * Get all admin users
     */
    private function getAdminUsers()
    {
        try {
            return User::whereHas('roles', function ($query) {
                $query->where('name', 'admin');
            })->get();
        } catch (\Exception $e) {
            Log::error('Failed to get admin users for shop creation notification', [
                'error' => $e->getMessage(),
            ]);
            return collect();
        }
    }
}
