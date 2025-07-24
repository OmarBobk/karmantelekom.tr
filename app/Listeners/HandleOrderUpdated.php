<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\OrderUpdated;
use App\Models\Order;
use App\Models\User;
use App\Notifications\NewActivityLogged;
use App\Services\NotificationRecipientsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\Models\Activity;

/**
 * HandleOrderUpdated Listener
 *
 * Handles the OrderUpdated event by logging activity and sending notifications
 */
class HandleOrderUpdated implements ShouldQueue
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
    public function handle(OrderUpdated $event): void
    {
        try {
            $order = $event->order;
            $originalData = $event->originalData;

            // Build the changes array for activity logging
            $changes = [];
            $changedFields = [];

            foreach (['status', 'total_price', 'shop_id', 'notes'] as $field) {
                if (isset($originalData[$field]) && $originalData[$field] !== $order->$field) {
                    $changes[$field] = [
                        'old' => $originalData[$field],
                        'new' => $order->$field,
                    ];
                    $changedFields[] = $field;
                }
            }
            // Only log if there are actual changes
            if (!empty($changes)) {
                // Build descriptive message based on changes
                $description = $this->buildChangeDescription($order, $changes);

                // Log the order update activity with meaningful fields only
                $activityLogger = activity('order')
                    ->performedOn($order)
                    ->by(auth()->user()->id ?? User::find(1)->id) // Fallback to system user if not authenticated
                    ->causedBy(auth()->user()->id ?? User::find(1)->id) // Fallback to system user if not authenticated
                    ->withProperties([
                        'order_id' => $order->id,
                        'status' => $order->status->value,
                        'shop_id' => $order->shop_id,
                        'total_price' => $order->total_price,
                        'notes' => $order->notes,
                        'changes' => $changes,
                    ]);

                $activityLogger->log($description);

                // Send notification to resolved recipients
                $recipients = $this->recipientsService->resolveNotificationRecipientsFor($order, excludeCauser: true);

                $activity = Activity::where('subject_type', Order::class)
                    ->where('subject_id', $order->id)
                    ->where('description', $description)
                    ->latest()
                    ->first();

                foreach ($recipients as $recipient) {
                    $recipient->notify(new NewActivityLogged($activity));
                }

                Log::info('Order updated event handled successfully', [
                    'order_id' => $order->id,
                    'changed_fields' => $changedFields,
                    'changes' => $changes,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to handle OrderUpdated event', [
                'order_id' => $event->order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Build a descriptive message for order changes
     */
    private function buildChangeDescription($order, array $changes): string
    {
        $descriptions = [];

        foreach ($changes as $field => $change) {
            switch ($field) {
                case 'status':
                    $oldStatus = ucfirst($change['old']);
                    $newStatus = ucfirst($change['new']->value);
                    $descriptions[] = "status changed from {$oldStatus} to {$newStatus}";
                    break;
                case 'total_price':
                    $oldTotal = number_format((float) $change['old'], 2);
                    $newTotal = number_format((float) $change['new'], 2);
                    $descriptions[] = "total changed from {$oldTotal} to {$newTotal}";
                    break;
                case 'shop_id':
                    $descriptions[] = "shop changed";
                    break;
                case 'notes':
                    $descriptions[] = "notes updated";
                    break;
                default:
                    $descriptions[] = "{$field} changed";
            }
        }

        $changeText = implode(', ', $descriptions);
        return "Order #{$order->id} {$changeText}";
    }
}
