<?php

declare(strict_types=1);

namespace App\Services\Recipients;

use App\Models\Order;
use App\Models\User;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * OrderUpdatedRecipients
 *
 * Centralized service for resolving who should receive notifications for various entities
 */
class OrderUpdatedRecipients
{
    public function resolve(Order $order, int $causer_id): Collection
    {
        try {
            $causer = User::find($causer_id);
            $recipients = collect();

            // Get admin users
            $adminUsers = User::role('admin')->get();
            $recipients = $recipients->merge($adminUsers);

            // Get Users linked to the order's shop (including assigned salesperson)
            $shopSalesperson = $order->shopSalesperson;

            if ($shopSalesperson) {
                $recipients = $recipients->push($shopSalesperson);
            }

            // Get the shop Owner who own the order.
            $shopOwner = $order->shop->owner;

            if ($shopOwner) {
                $recipients = $recipients->push($shopOwner);
            }

            $recipients = $recipients->unique();

            // Optionally exclude the causer (authenticated user)
            if (auth()->check()) {
                $recipients = $recipients->reject(function ($user) use ($causer) {
                    return $user->id === $causer->id;
                });
            }

            return $recipients;

        } catch (Exception $e) {
            Log::error('Failed to resolve the Order updated recipients', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return an empty array on error to prevent notification failures
            return collect();
        }
    }
}
