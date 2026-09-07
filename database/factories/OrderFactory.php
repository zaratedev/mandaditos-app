<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Address;
use App\Models\Client;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'address_id' => Address::factory(),
            'courier_id' => null,
            'status' => OrderStatus::Requested,
            'shopping_list' => implode("\n", fake()->words(4)),
            'items_subtotal' => null,
            'commission' => null,
            'total' => null,
            'payment_method' => null,
            'payment_status' => PaymentStatus::Pending,
            'notes' => null,
            'created_by' => User::factory(),
        ];
    }
}
