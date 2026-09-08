<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Notifications\Notification;

class OrderAssigned extends Notification
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
        return [
            'type' => 'order_assigned',
            'order_id' => $this->order->id,
            'message' => "Se te asignó el pedido #{$this->order->id}.",
            'url' => "/board/{$this->order->id}",
        ];
    }
}
