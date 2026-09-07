<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Address;
use App\Models\Client;
use App\Models\Order;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function makeCourierOrder(User $courier, OrderStatus $status = OrderStatus::Assigned): Order
{
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $client = Client::factory()->create();
    $address = Address::factory()->for($client)->create();

    return Order::factory()->create([
        'client_id' => $client->id,
        'address_id' => $address->id,
        'created_by' => $admin->id,
        'courier_id' => $courier->id,
        'status' => $status,
    ]);
}

test('courier only sees their own open orders on the board', function () {
    $courierA = User::factory()->create(['role' => UserRole::Courier]);
    $courierB = User::factory()->create(['role' => UserRole::Courier]);

    makeCourierOrder($courierA);
    makeCourierOrder($courierA);
    makeCourierOrder($courierB);
    makeCourierOrder($courierA, OrderStatus::Delivered); // excluded (closed)

    $this->actingAs($courierA)->get('/board')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('board/Index')->has('orders', 2));
});

test('courier can advance the status of their own order', function () {
    $courier = User::factory()->create(['role' => UserRole::Courier]);
    $order = makeCourierOrder($courier);

    $this->actingAs($courier)
        ->post("/board/{$order->id}/status", ['status' => OrderStatus::Purchased->value])
        ->assertRedirect();

    expect($order->refresh()->status)->toBe(OrderStatus::Purchased)
        ->and($order->purchased_at)->not->toBeNull();
});

test('courier cannot view an order assigned to someone else', function () {
    $courierA = User::factory()->create(['role' => UserRole::Courier]);
    $courierB = User::factory()->create(['role' => UserRole::Courier]);
    $order = makeCourierOrder($courierB);

    $this->actingAs($courierA)->get("/board/{$order->id}")->assertForbidden();
});

test('courier cannot advance an order assigned to someone else', function () {
    $courierA = User::factory()->create(['role' => UserRole::Courier]);
    $courierB = User::factory()->create(['role' => UserRole::Courier]);
    $order = makeCourierOrder($courierB);

    $this->actingAs($courierA)
        ->post("/board/{$order->id}/status", ['status' => OrderStatus::Delivered->value])
        ->assertForbidden();
});

test('courier cannot set a non-operational status', function () {
    $courier = User::factory()->create(['role' => UserRole::Courier]);
    $order = makeCourierOrder($courier);

    $this->actingAs($courier)
        ->post("/board/{$order->id}/status", ['status' => OrderStatus::Cancelled->value])
        ->assertSessionHasErrors('status');
});

test('courier cannot register payment through the admin route', function () {
    $courier = User::factory()->create(['role' => UserRole::Courier]);
    $order = makeCourierOrder($courier);

    $this->actingAs($courier)
        ->post("/orders/{$order->id}/payment", ['payment_method' => 'cash'])
        ->assertForbidden();
});
