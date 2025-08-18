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
 * ShopCreatedNotification
 *
 * Notification sent to admin users when a new shop is created
 */
class ShopCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Shop $shop,
        public ?User $createdBy = null
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
            $createdByName = $this->createdBy ? $this->createdBy->name : 'System';
            $shopPhone = $this->shop->phone ?? 'N/A';
            $shopAddress = $this->shop->address ?? 'N/A';

            return (new MailMessage)
                ->subject("New Shop Created: {$shopName}")
                ->greeting("Hello {$notifiable->name},")
                ->line("A new shop has been created in the system.")
                ->line("Shop Details:")
                ->line("• Name: {$shopName}")
                ->line("• Phone: {$shopPhone}")
                ->line("• Address: {$shopAddress}")
                ->line("• Created by: {$createdByName}")
                ->line("• Created at: " . $this->shop->created_at->format('M j, Y \a\t g:i A'))
                ->action('View Shop Details', route('subdomain.shop', $this->shop->id))
                ->line('Thank you for using our application!');

        } catch (\Exception $e) {
            // Fallback email in case of error
            return (new MailMessage)
                ->subject('New Shop Created')
                ->line('A new shop has been created in the system.')
                ->action('View Shops', route('subdomain.shop', $this->shop->id))
                ->line('Thank you for using our application!');
        }
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'description' => "New shop '{$this->shop->name}' has been created by " . ($this->createdBy ? $this->createdBy->name : 'System'),
            'model_type' => Shop::class,
            'model_id' => $this->shop->id,
            'performed_by' => $this->createdBy ? $this->createdBy->name : 'System',
            'shop_name' => $this->shop->name,
            'shop_phone' => $this->shop->phone,
            'shop_address' => $this->shop->address,
            'created_at' => $this->shop->created_at->toISOString(),
        ];
    }

    /**
     * Get the broadcastable representation of the notification.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
