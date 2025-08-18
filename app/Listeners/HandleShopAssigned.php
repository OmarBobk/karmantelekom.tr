<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ShopAssigned;
use App\Notifications\ShopAssignmentNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\Models\Activity;

/**
 * HandleShopAssigned Listener
 *
 * Handles the ShopAssigned event by logging activity and sending notifications
 */
class HandleShopAssigned implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Create the event listener.
     */
    public function __construct()
    {
    }

    /**
     * Handle the event.
     */
    public function handle(ShopAssigned $event): void
    {
        try {
            $shop = $event->shop;
            $salesperson = $event->salesperson;
            $assignedBy = $event->assignedBy;
            $previousSalesperson = $event->previousSalesperson;

            // Build detailed description and properties
            $description = $this->buildDetailedAssignmentDescription($shop, $salesperson, $assignedBy, $previousSalesperson);
            $properties = $this->buildDetailedProperties($shop, $salesperson, $assignedBy, $previousSalesperson);

            // Log the shop assignment activity with enhanced details
            $activityLogger = activity('shop_assignment')
                ->performedOn($shop)
                ->causedBy($assignedBy)
                ->withProperties($properties);

            $activityLogger->log($description);

            // Send notification to the assigned salesperson
            try {
                $salesperson->notify(new ShopAssignmentNotification($shop, $assignedBy, $previousSalesperson));
            } catch (\Exception $notificationError) {
                Log::warning('Failed to send shop assignment notification', [
                    'shop_id' => $shop->id,
                    'salesperson_id' => $salesperson->id,
                    'error' => $notificationError->getMessage(),
                ]);
                // Don't throw the error, just log it and continue
            }

            // Log success
            Log::info('Shop assignment event handled successfully', [
                'shop_id' => $shop->id,
                'shop_name' => $shop->name,
                'salesperson_id' => $salesperson->id,
                'salesperson_name' => $salesperson->name,
                'assigned_by_id' => $assignedBy->id,
                'assigned_by_name' => $assignedBy->name,
                'previous_salesperson_id' => $previousSalesperson?->id,
                'assignment_type' => $previousSalesperson ? 'reassignment' : 'new_assignment',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to handle ShopAssigned event', [
                'shop_id' => $event->shop->id,
                'salesperson_id' => $event->salesperson->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Build a detailed description for the shop assignment
     */
    private function buildDetailedAssignmentDescription($shop, $salesperson, $assignedBy, $previousSalesperson): string
    {
        $shopName = $shop->name;
        $salespersonName = $salesperson->name;
        $assignedByName = $assignedBy->name;

        if ($previousSalesperson) {
            $previousName = $previousSalesperson->name;
            return "Shop '{$shopName}' has been reassigned from {$previousName} to {$salespersonName} by {$assignedByName}";
        }

        return "Shop '{$shopName}' has been assigned to {$salespersonName} by {$assignedByName}";
    }

    /**
     * Build detailed properties for activity logging
     */
    private function buildDetailedProperties($shop, $salesperson, $assignedBy, $previousSalesperson): array
    {
        $properties = [
            'shop_id' => $shop->id,
            'shop_name' => $shop->name,
            'shop_phone' => $shop->phone,
            'shop_address' => $shop->address,
            'salesperson_id' => $salesperson->id,
            'salesperson_name' => $salesperson->name,
            'salesperson_email' => $salesperson->email,
            'assigned_by_id' => $assignedBy->id,
            'assigned_by_name' => $assignedBy->name,
            'assigned_by_email' => $assignedBy->email,
            'assignment_type' => $previousSalesperson ? 'reassignment' : 'new_assignment',
            'assignment_timestamp' => now()->toISOString(),
            'shop_details' => [
                'name' => $shop->name,
                'phone' => $shop->phone,
                'address' => $shop->address,
                'links' => $shop->links ?? [],
            ],
            'salesperson_details' => [
                'name' => $salesperson->name,
                'email' => $salesperson->email,
                'role' => $salesperson->roles->first()?->name ?? 'salesperson',
            ],
            'admin_details' => [
                'name' => $assignedBy->name,
                'email' => $assignedBy->email,
                'role' => $assignedBy->roles->first()?->name ?? 'admin',
            ],
        ];

        // Add previous salesperson details if it's a reassignment
        if ($previousSalesperson) {
            $properties['previous_salesperson_id'] = $previousSalesperson->id;
            $properties['previous_salesperson_name'] = $previousSalesperson->name;
            $properties['previous_salesperson_email'] = $previousSalesperson->email;
            $properties['previous_salesperson_details'] = [
                'name' => $previousSalesperson->name,
                'email' => $previousSalesperson->email,
                'role' => $previousSalesperson->roles->first()?->name ?? 'salesperson',
            ];
        }

        return $properties;
    }
}
