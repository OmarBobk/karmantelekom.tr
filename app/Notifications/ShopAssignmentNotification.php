<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * ShopAssignmentNotification
 *
 * Notification sent to salesperson when they are assigned to a new shop
 */
class ShopAssignmentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Shop $shop,
        public User $assignedBy,
        public ?User $previousSalesperson = null
    ) {
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        try {
            $shopName = $this->shop->name ?? 'Unknown Shop';
            $assignedByName = $this->assignedBy->name ?? 'Omar';
            $shopPhone = $this->shop->phone ?? 'N/A';
            $shopAddress = $this->shop->address ?? 'N/A';
            $assignmentType = $this->previousSalesperson ? 'Reassignment' : 'New Assignment';

            $mailMessage = (new MailMessage)
                ->subject("Shop {$assignmentType} - {$shopName}")
                ->greeting("Hello {$notifiable->name}!")
                ->line("We are pleased to inform you that you have been assigned to manage the shop '{$shopName}'.")
                ->line("This {$assignmentType} was made by {$assignedByName} and is effective immediately.")
                ->line("As the assigned salesperson, you are now responsible for:")
                ->line("• Managing shop operations and customer relationships")
                ->line("• Processing orders and handling customer inquiries")
                ->line("• Monitoring shop performance and sales activities")
                ->line("• Coordinating with the shop owner and management team")
                ->line("Shop Information:")
                ->line("• Shop Name: {$shopName}")
                ->line("• Phone: {$shopPhone}")
                ->line("• Address: {$shopAddress}")
                ->line("Please review the shop details and familiarize yourself with the operations. If you have any questions, please contact your supervisor.")
                ->line("Thank you for your dedication and commitment to excellence!");

            // Only add action button if shop ID exists
            if ($this->shop->id) {
                try {
                    $mailMessage->action('View Shop Details', route('subdomain.shop', $this->shop->id));
                } catch (\Exception $e) {
                    // If route fails, don't add the action button
                }
            }

            return $mailMessage;
        } catch (\Exception $e) {
            // Fallback notification if something goes wrong
            return (new MailMessage)
                ->subject('Shop Assignment Notification')
                ->greeting("Hello {$notifiable->name}!")
                ->line("You have been assigned to manage a new shop.")
                ->line("Please log into your dashboard to view the shop details and begin managing your new responsibilities.")
                ->line("If you have any questions, please contact your supervisor.")
                ->line("Thank you for your dedication!");
        }
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        try {
            $shopName = $this->shop->name ?? 'Unknown Shop';
            $assignedByName = $this->assignedBy->name ?? 'Omar';
            $assignmentType = $this->previousSalesperson ? 'Reassignment' : 'New Assignment';
            
            $description = "You have been assigned to manage shop '{$shopName}'. ";
            $description .= "This {$assignmentType} was made by {$assignedByName}. ";
            $description .= "You are now responsible for this shop's operations, orders, and customer relationships.";
            
            return [
                'description' => $description,
                'performed_by' => $assignedByName,
                'model_type' => 'App\Models\Shop',
                'model_id' => $this->shop->id ?? null,
                'shop_id' => $this->shop->id ?? null,
                'shop_name' => $shopName,
                'shop_phone' => $this->shop->phone ?? 'N/A',
                'shop_address' => $this->shop->address ?? 'N/A',
                'assigned_by_id' => $this->assignedBy->id ?? null,
                'assigned_by_email' => $this->assignedBy->email ?? 'N/A',
                'assignment_type' => $assignmentType,
                'assigned_at' => now()->toISOString(),
                'priority' => 'high',
                'action_required' => true,
                'action_text' => 'View Shop Details',
                'action_url' => route('subdomain.shop', $this->shop->id ?? 1),
            ];
        } catch (\Exception $e) {
            return [
                'description' => 'You have been assigned to manage a new shop. Please check your dashboard for details.',
                'performed_by' => 'System',
                'model_type' => 'App\Models\Shop',
                'assigned_at' => now()->toISOString(),
                'priority' => 'high',
                'action_required' => true,
            ];
        }
    }

    /**
     * Get the broadcastable representation of the notification.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        try {
            $shopName = $this->shop->name ?? 'Unknown Shop';
            $assignedByName = $this->assignedBy->name ?? 'Omar';
            $assignmentType = $this->previousSalesperson ? 'Reassignment' : 'New Assignment';
            
            $description = "You have been assigned to manage shop '{$shopName}'. ";
            $description .= "This {$assignmentType} was made by {$assignedByName}. ";
            $description .= "You are now responsible for this shop's operations, orders, and customer relationships.";
            
            return new BroadcastMessage([
                'description' => $description,
                'performed_by' => $assignedByName,
                'model_type' => 'App\Models\Shop',
                'model_id' => $this->shop->id ?? null,
                'shop_id' => $this->shop->id ?? null,
                'shop_name' => $shopName,
                'shop_phone' => $this->shop->phone ?? 'N/A',
                'shop_address' => $this->shop->address ?? 'N/A',
                'assigned_by_id' => $this->assignedBy->id ?? null,
                'assigned_by_email' => $this->assignedBy->email ?? 'N/A',
                'assignment_type' => $assignmentType,
                'assigned_at' => now()->toISOString(),
                'priority' => 'high',
                'action_required' => true,
                'action_text' => 'View Shop Details',
                'action_url' => route('subdomain.shop', $this->shop->id ?? 1),
                'icon' => 'shop',
                'color' => 'indigo',
            ]);
        } catch (\Exception $e) {
            return new BroadcastMessage([
                'description' => 'You have been assigned to manage a new shop. Please check your dashboard for details.',
                'performed_by' => 'System',
                'model_type' => 'App\Models\Shop',
                'assigned_at' => now()->toISOString(),
                'priority' => 'high',
                'action_required' => true,
                'icon' => 'shop',
                'color' => 'indigo',
            ]);
        }
    }
}
