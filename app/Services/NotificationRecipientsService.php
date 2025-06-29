<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * NotificationRecipientsService
 *
 * Centralized service for resolving who should receive notifications for various entities
 */
class NotificationRecipientsService
{
    /**
     * Resolve notification recipients for an order
     *
     * Advanced Options:
     * $recipients = $recipientsService->resolveNotificationRecipientsFor($order,
     * excludeCauser: true,  // Exclude authenticated user
     * options: [
     *     'exclude_roles' => ['salesperson'],  // Exclude specific roles
     *     'only_roles' => ['admin'],          // Include only specific roles
     * ]
     * );
     *
     * @param Order $order The order to resolve recipients for
     * @param bool $excludeCauser Whether to exclude the authenticated user who triggered the action
     * @param array $options Additional options for recipient resolution
     * @return Collection<User>
     */
    public function resolveNotificationRecipientsFor(Order $order, bool $excludeCauser = true, array $options = []): Collection
    {
        try {
            $recipients = collect();

            // Get admin users
            $adminUsers = $this->getAdminUsers();
            $recipients = $recipients->merge($adminUsers);

            // Get users linked to the order's shop
            $shopUsers = $this->getShopRelatedUsers($order);
            $recipients = $recipients->merge($shopUsers);

            // Get the order's salesperson
            if ($order->salesperson) {
                $recipients->push($order->salesperson);
            }

            // Remove duplicates by user ID
            $recipients = $recipients->unique('id');

            // Optionally exclude the causer (authenticated user)
            if ($excludeCauser && auth()->check()) {
                $recipients = $recipients->reject(function ($user) {
                    return $user->id === auth()->id();
                });
            }

            // Apply additional filters from options
            if (isset($options['exclude_roles'])) {
                $excludeRoles = (array) $options['exclude_roles'];
                $recipients = $recipients->reject(function ($user) use ($excludeRoles) {
                    return $user->hasAnyRole($excludeRoles);
                });
            }

            if (isset($options['only_roles'])) {
                $onlyRoles = (array) $options['only_roles'];
                $recipients = $recipients->filter(function ($user) use ($onlyRoles) {
                    return $user->hasAnyRole($onlyRoles);
                });
            }

            Log::info('Notification recipients resolved for order', [
                'order_id' => $order->id,
                'recipients_count' => $recipients->count(),
                'recipient_ids' => $recipients->pluck('id')->toArray(),
                'excluded_causer' => $excludeCauser,
                'causer_id' => auth()->id(),
            ]);

            return $recipients;

        } catch (\Exception $e) {
            Log::error('Failed to resolve notification recipients for order', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return empty collection on error to prevent notification failures
            return collect();
        }
    }

    /**
     * Get all admin users
     *
     * @return Collection<User>
     */
    private function getAdminUsers(): Collection
    {
        return User::whereHas('roles', function ($query) {
            $query->where('name', 'admin');
        })->get();
    }

    /**
     * Get users related to the order's shop
     *
     */
    private function getShopRelatedUsers(Order $order): \Illuminate\Support\Collection
    {
        $users = collect();

        // Get the shop's assigned salesperson/user
        if ($order->shop && $order->shop->user) {
            $users->push($order->shop->user);
        }

        // You can extend this method to include other shop-related users
        // For example: shop managers, regional managers, etc.

        return $users->unique('id');
    }

    /**
     * Resolve recipients for admin-only notifications
     *
     * @param bool $excludeCauser
     * @return Collection<User>
     */
    public function resolveAdminRecipients(bool $excludeCauser = true): Collection
    {
        $recipients = $this->getAdminUsers();

        if ($excludeCauser && auth()->check()) {
            $recipients = $recipients->reject(function ($user) {
                return $user->id === auth()->id();
            });
        }

        return $recipients;
    }

    /**
     * Resolve recipients for shop-related notifications
     *
     * @param Order $order
     * @param bool $excludeCauser
     * @return Collection<User>
     */
    public function resolveShopRecipients(Order $order, bool $excludeCauser = true): Collection
    {
        $recipients = $this->getShopRelatedUsers($order);

        if ($excludeCauser && auth()->check()) {
            $recipients = $recipients->reject(function ($user) {
                return $user->id === auth()->id();
            });
        }

        return $recipients;
    }
}
