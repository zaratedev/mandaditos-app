<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Address;
use App\Models\Client;
use App\Models\Order;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('the courier panel surfaces the order closest to delivery', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $courier = User::factory()->create(['role' => UserRole::Courier]);

    $client = Client::factory()->create(['name' => 'Maria Lopez']);
    $address = Address::factory()->for($client)->create(['street' => 'Av. Juarez 45']);

    // Older, but barely started: it should lose to the one already on its way.
    Order::factory()->create([
        'client_id' => $client->id,
        'address_id' => $address->id,
        'created_by' => $admin->id,
        'courier_id' => $courier->id,
        'status' => OrderStatus::Assigned,
        'created_at' => now()->subHours(3),
    ]);

    $onTheWay = Order::factory()->create([
        'client_id' => $client->id,
        'address_id' => $address->id,
        'created_by' => $admin->id,
        'courier_id' => $courier->id,
        'status' => OrderStatus::OnTheWay,
        'created_at' => now()->subHour(),
    ]);

    $this->actingAs($courier)->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->where('isAdmin', false)
            ->where('nextOrder.id', $onTheWay->id)
            ->where('nextOrder.client', 'Maria Lopez')
            ->where('nextOrder.next_status', 'delivered')
            ->where('nextOrder.next_label', 'Entregado')
            ->where('buckets.to_buy', 1)
            ->where('buckets.to_deliver', 1));
});

test('the courier panel only counts the couriers own orders', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $courier = User::factory()->create(['role' => UserRole::Courier]);
    $other = User::factory()->create(['role' => UserRole::Courier]);

    $client = Client::factory()->create();
    $address = Address::factory()->for($client)->create();

    $base = [
        'client_id' => $client->id,
        'address_id' => $address->id,
        'created_by' => $admin->id,
        'status' => OrderStatus::Purchasing,
    ];

    Order::factory()->create([...$base, 'courier_id' => $courier->id]);
    Order::factory()->count(3)->create([...$base, 'courier_id' => $other->id]);

    $this->actingAs($courier)->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->where('courierOpenOrders', 1)
            ->where('buckets.buying', 1));
});

test('the courier panel reports the day and the cash the admin registered', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $courier = User::factory()->create(['role' => UserRole::Courier]);

    $client = Client::factory()->create();
    $address = Address::factory()->for($client)->create();

    $delivered = [
        'client_id' => $client->id,
        'address_id' => $address->id,
        'created_by' => $admin->id,
        'courier_id' => $courier->id,
        'status' => OrderStatus::Delivered,
        'delivered_at' => now(),
    ];

    Order::factory()->create([...$delivered,
        'payment_status' => PaymentStatus::Paid,
        'payment_method' => PaymentMethod::Cash,
        'paid_at' => now(),
        'total' => 250.00,
    ]);
    Order::factory()->create([...$delivered,
        'payment_status' => PaymentStatus::Paid,
        'payment_method' => PaymentMethod::Transfer,
        'paid_at' => now(),
        'total' => 400.00,
    ]);
    // Delivered but the admin has not registered the payment yet.
    Order::factory()->create([...$delivered, 'payment_status' => PaymentStatus::Pending]);

    $this->actingAs($courier)->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->where('deliveredToday', 3)
            ->where('cashCollectedToday', 250)   // the 400 transfer is excluded
            ->where('awaitingPayment', 1)
            ->where('nextOrder', null));
});

test('a courier can advance the next order straight from the panel', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $courier = User::factory()->create(['role' => UserRole::Courier]);

    $client = Client::factory()->create();
    $address = Address::factory()->for($client)->create();
    $order = Order::factory()->create([
        'client_id' => $client->id,
        'address_id' => $address->id,
        'created_by' => $admin->id,
        'courier_id' => $courier->id,
        'status' => OrderStatus::Purchasing,
    ]);

    $this->actingAs($courier)->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page->where('nextOrder.next_status', 'purchased'));

    $this->actingAs($courier)->post("/board/{$order->id}/status", ['status' => 'purchased'])
        ->assertSessionHasNoErrors();

    expect($order->refresh()->status)->toBe(OrderStatus::Purchased)
        ->and($order->purchased_at)->not->toBeNull();
});
