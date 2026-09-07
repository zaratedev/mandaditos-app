<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    /**
     * Admins can act on any order; couriers only on the ones assigned to them.
     */
    public function view(User $user, Order $order): bool
    {
        return $user->isAdmin() || $order->courier_id === $user->id;
    }

    public function update(User $user, Order $order): bool
    {
        return $user->isAdmin() || $order->courier_id === $user->id;
    }
}
