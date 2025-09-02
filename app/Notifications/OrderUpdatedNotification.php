<?php

namespace App\Notifications;

use App\Enums\OrderStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderUpdatedNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public $activity)
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->line('The introduction to the notification.')
            ->action('Notification Action', url('/'))
            ->line('Thank you for using our application!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $statusEnum = OrderStatus::tryFrom((string)(strtolower($this->activity->properties['status'] ?? ''))) ?? OrderStatus::PENDING;

        return [
            'icon'        => $statusEnum->icon(),
            'is_icon_exist' => true,
            'description' => $this->activity->description,
            'order_id'    => $this->activity->properties['order_id'],
            'order_link'  => '#',
            'summary'     => [
                'shop_name' => $this->activity->properties['shop_name'],
                'what'      => $this->extractAction($this->activity->description),
            ],
            'time'        => $this->activity->created_at->diffForHumans(),
            'time_exact'  => $this->activity->created_at->format('M j, Y \a\t g:i A'),
            'action'        => $statusEnum->getAlertClass(),

            'notification_type' => 'order_updated',
            'model_type' => $this->activity->subject_type,
            'model_id' => $this->activity->subject_id,
            'performed_by' => $this->activity->causer ? $this->activity->causer->name : 'System From the order updated Notification',
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }


    private function getNotificationType($notification): string
    {

        if (isset($this->activity->description)) {
            $description = strtolower($this->activity->description);

            if (str_contains($description, 'created')) {
                return 'info';
            } elseif (str_contains($description, 'status changed') || str_contains($description, 'updated')) {
                return 'success';
            } elseif (str_contains($description, 'cancelled') || str_contains($description, 'failed')) {
                return 'error';
            } else {
                return 'info';
            }
        }

        return 'info';
    }

    private function extractAction(string $description): string
    {
        if (str_contains($description, 'New shop') && str_contains($description, 'has been created')) {
            return 'created a new shop';
        } elseif (str_contains($description, 'Order #') && str_contains($description, 'has been created')) {
            return 'created an order';
        } elseif (str_contains($description, 'has been created')) {
            return 'created an item';
        } elseif (str_contains($description, 'status changed')) {
            return 'updated order status';
        } elseif (str_contains($description, 'total changed')) {
            return 'updated order total';
        } elseif (str_contains($description, 'has been updated')) {
            return 'updated an order';
        } elseif (str_contains($description, 'assigned to manage shop')) {
            return 'assigned you to a shop';
        } elseif (str_contains($description, 'reassigned')) {
            return 'reassigned you to a shop';
        }

        return 'performed an action';
    }
}
