<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class OrderAssigned extends Notification
{
    public function __construct(public Order $order) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'order_assigned',
            'order_id' => $this->order->id,
            'message' => "Se te asignó el pedido #{$this->order->id}.",
            'url' => "/board/{$this->order->id}",
        ];
    }

    public function toWebPush(object $notifiable, mixed $notification): WebPushMessage
    {
        return (new WebPushMessage())
            ->title('Nuevo pedido asignado')
            ->body("Se te asignó el pedido #{$this->order->id}.")
            ->icon('/icons/icon-192.png')
            ->data(['url' => "/board/{$this->order->id}"]);
    }
}
