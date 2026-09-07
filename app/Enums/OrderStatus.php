<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderStatus: string
{
    case Requested = 'requested';
    case Confirmed = 'confirmed';
    case Assigned = 'assigned';
    case Purchasing = 'purchasing';
    case Purchased = 'purchased';
    case OnTheWay = 'on_the_way';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Solicitado',
            self::Confirmed => 'Confirmado',
            self::Assigned => 'Asignado',
            self::Purchasing => 'Comprando',
            self::Purchased => 'Comprado',
            self::OnTheWay => 'En camino',
            self::Delivered => 'Entregado',
            self::Cancelled => 'Cancelado',
        };
    }

    /**
     * Whether the order is still active (not delivered nor cancelled).
     */
    public function isOpen(): bool
    {
        return ! in_array($this, [self::Delivered, self::Cancelled], true);
    }
}
