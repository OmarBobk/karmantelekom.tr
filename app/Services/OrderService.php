<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\Recipients\OrderUpdatedRecipients;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\CircularDependencyException;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\Models\Activity;

class OrderService
{

    /**
     * Update the given order with the provided data.
     * $data = ['causer' => user_id, 'originalData' => [...],]
     * @param Order $order The order to be updated.
     * @param array $data The data to update the order with.
     */
    public function update(Order $order, array $data): void
    {

        // Only log if there are actual changes
        $activity = $this->logUpdatedOrder($order, $data);

        // Get the recipients for the notification
        try {
            $recipients = app(OrderUpdatedRecipients::class)->resolve($order, $data['causer']);

            foreach ($recipients as $recipient) {
                $recipient->notify(new \App\Notifications\OrderUpdatedNotification($activity));
            }


        } catch (BindingResolutionException|CircularDependencyException $e) {
            Log::error('Failed from OrderService', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return;
        }

    }


    private function logUpdatedOrder($order, $data)
    {
        $originalData = $data['originalData'] ?? [];
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

            // Build a descriptive message based on changes
            $description = $this->buildChangeDescription($order, $changes);

            // Log Activity.
            $activityLogger = activity('order_updated')
                ->performedOn($order)
                ->by($data['causer'])
                ->causedBy($data['causer'])
                ->withProperties([
                    'order_id' => $order->id,
                    'shop_id' => $order->shop_id,
                    'shop_name' => $order->shop->name,
                    'status' => $order->status,
                    'total_price' => $order->total_price,
                    'notes' => $order->notes,
                    'changes' => $changes,
                ]);

            $activityLogger->log($description);

            return Activity::where('subject_type', Order::class)
                ->where('subject_id', $order->id)
                ->where('description', $description)
                ->latest()
                ->first();
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
                    $descriptions[] = "status changed from <span class='font-medium text-md ". OrderStatus::tryFrom((string)(strtolower($oldStatus)))->getTextColor() ."'>{$oldStatus}</span> to <span class='font-medium text-md ". OrderStatus::tryFrom((string)(strtolower($newStatus)))->getTextColor() ."'>{$newStatus}</span>";
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
