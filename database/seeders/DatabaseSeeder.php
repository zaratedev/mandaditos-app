<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Address;
use App\Models\Business;
use App\Models\Client;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $business = Business::factory()->create([
            'name' => 'Rivers',
            'slug' => 'rivers',
        ]);

        $admin = User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@mandaditos.test',
            'role' => UserRole::Admin,
        ]);

        $couriers = collect([
            User::factory()->create([
                'name' => 'Repartidor Uno',
                'email' => 'repartidor1@mandaditos.test',
                'role' => UserRole::Courier,
            ]),
            User::factory()->create([
                'name' => 'Repartidor Dos',
                'email' => 'repartidor2@mandaditos.test',
                'role' => UserRole::Courier,
            ]),
        ]);

        Client::factory()->count(6)->create()->each(function (Client $client) use ($admin, $couriers) {
            $address = Address::factory()->for($client)->create();

            // Two delivered & paid orders -> feed the daily cash cut.
            for ($i = 0; $i < 2; $i++) {
                $courier = $couriers->random();

                $order = Order::factory()->create([
                    'client_id' => $client->id,
                    'address_id' => $address->id,
                    'created_by' => $admin->id,
                    'courier_id' => $courier->id,
                    'status' => OrderStatus::Delivered,
                    'payment_method' => fake()->randomElement([PaymentMethod::Cash, PaymentMethod::Transfer]),
                    'payment_status' => PaymentStatus::Paid,
                    'confirmed_at' => now(),
                    'purchased_at' => now(),
                    'delivered_at' => now(),
                    'paid_at' => now(),
                ]);

                $items = OrderItem::factory()->count(fake()->numberBetween(2, 4))->create([
                    'order_id' => $order->id,
                ]);

                $subtotal = (float) $items->sum('line_total');
                $commission = (float) fake()->randomElement([25, 30, 40, 50]);

                $order->update([
                    'items_subtotal' => $subtotal,
                    'commission' => $commission,
                    'total' => $subtotal + $commission,
                ]);
            }

            // One open order still in progress (unpaid) for dashboard variety.
            Order::factory()->create([
                'client_id' => $client->id,
                'address_id' => $address->id,
                'created_by' => $admin->id,
                'courier_id' => $couriers->random()->id,
                'status' => fake()->randomElement([OrderStatus::Assigned, OrderStatus::Purchasing]),
            ]);
        });

        // Single-tenant pilot: everything seeded belongs to the one business.
        User::query()->update(['tenant_id' => $business->id]);
        Client::query()->update(['tenant_id' => $business->id]);
        Address::query()->update(['tenant_id' => $business->id]);
        Order::query()->update(['tenant_id' => $business->id]);
    }
}
