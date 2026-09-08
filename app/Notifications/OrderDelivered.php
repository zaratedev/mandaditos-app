<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Notifications\Notification;

class OrderDelivered extends Notification
{
    public function __construct(public Order $order) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $courier = $this->order->courier?->name ?? 'Un repartidor';

        return [
            'type' => 'order_delivered',
            'order_id' => $this->order->id,
            'message' => "Pedido #{$this->order->id} entregado por {$courier}.",
            'url' => "/orders/{$this->order->id}",
        ];
    }
}
