<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class OrderPlaced extends Notification
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
            'type' => 'order_placed',
            'order_id' => $this->order->id,
            'message' => "Nuevo pedido #{$this->order->id} desde el portal.",
            'url' => "/orders/{$this->order->id}",
        ];
    }

    public function toWebPush(object $notifiable, mixed $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Nuevo pedido')
            ->body("Pedido #{$this->order->id} recibido desde el portal.")
            ->icon('/icons/icon-192.png')
            ->data(['url' => "/orders/{$this->order->id}"]);
    }
}
