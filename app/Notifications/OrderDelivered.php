<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class OrderDelivered extends Notification
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
        $courier = $this->order->courier->name ?? 'Un repartidor';

        return [
            'type' => 'order_delivered',
            'order_id' => $this->order->id,
            'message' => "Pedido #{$this->order->id} entregado por {$courier}.",
            'url' => "/orders/{$this->order->id}",
        ];
    }

    public function toWebPush(object $notifiable, mixed $notification): WebPushMessage
    {
        $courier = $this->order->courier->name ?? 'Un repartidor';

        return (new WebPushMessage)
            ->title('Pedido entregado')
            ->body("Pedido #{$this->order->id} entregado por {$courier}.")
            ->icon('/icons/icon-192.png')
            ->data(['url' => "/orders/{$this->order->id}"]);
    }
}
