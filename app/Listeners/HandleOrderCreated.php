<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\OrderCreated;
use App\Models\Order;
use App\Models\User;
use App\Notifications\NewActivityLogged;
use App\Services\NotificationRecipientsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\Models\Activity;

/**
 * HandleOrderCreated Listener
 *
 * Handles the OrderCreated event by logging activity and sending notifications
 */
class HandleOrderCreated implements ShouldQueue
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
    public function handle(OrderCreated $event): void
    {
        try {
            $order = $event->order;

            // Log the order creation activity with meaningful fields
            $activityLogger = activity('order_created')
                ->performedOn($order)
                ->by($event->userId ?? User::find(1)->id) // Use passed user ID or fallback
                ->causedBy($event->userId ?? User::find(1)->id) // Use passed user ID or fallback
                ->withProperties([
                    'order_id' => $order->id,
                    'shop_id' => $order->shop_id,
                    'total_price' => $order->total_price,
                    'status' => $order->status->value,
                    'notes' => $order->notes,
                ]);

            // Use authenticated user as causer
            if (auth()->check()) {
                $activityLogger->causedBy(auth()->user());
            }
            Log::info('Order created event handled successfully', [
                'order_id' => $order->id,
                'shop_id' => $order->shop_id,
                'total_price' => $order->total_price,
                'status' => $order->status->value,
                'notes' => $order->notes,
            ]);

            $activityLogger->log("Order #{$order->id} has been created with status '{$order->status->label()}' for " . number_format((float) $order->total_price, 2) . " TL");

            // Send notification to resolved recipients
            $recipients = $this->recipientsService->resolveNotificationRecipientsFor($order, excludeCauser: true);

            $activity = Activity::where('subject_type', Order::class)
                ->where('subject_id', $order->id)
                ->where('description', 'LIKE', "Order #{$order->id} has been created%")
                ->latest()
                ->first();

            foreach ($recipients as $recipient) {
                $recipient->notify(new NewActivityLogged($activity));
            }

            Log::info('Order created event handled successfully', [
                'order_id' => $order->id,
                'shop_id' => $order->shop_id,
                'user_id' => $order->user_id,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to handle OrderCreated event', [
                'order_id' => $event->order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
