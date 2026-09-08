<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Address;
use App\Models\Client;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderAssigned;
use App\Notifications\OrderDelivered;
use Illuminate\Support\Facades\Notification;

function orderForNotifications(User $admin, ?User $courier = null, OrderStatus $status = OrderStatus::Requested): Order
{
    $client = Client::factory()->create();
    $address = Address::factory()->for($client)->create();

    return Order::factory()->create([
        'client_id' => $client->id,
        'address_id' => $address->id,
        'created_by' => $admin->id,
        'courier_id' => $courier?->id,
        'status' => $status,
    ]);
}

test('the courier is notified when assigned an order', function () {
    Notification::fake();

    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $courier = User::factory()->create(['role' => UserRole::Courier]);
    $order = orderForNotifications($admin);

    $this->actingAs($admin)
        ->post("/orders/{$order->id}/assign", ['courier_id' => $courier->id])
        ->assertRedirect();

    Notification::assertSentTo($courier, OrderAssigned::class);
});

test('admins are notified when an order is delivered', function () {
    Notification::fake();

    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $courier = User::factory()->create(['role' => UserRole::Courier]);
    $order = orderForNotifications($admin, $courier, OrderStatus::OnTheWay);

    $this->actingAs($courier)
        ->post("/board/{$order->id}/status", ['status' => OrderStatus::Delivered->value])
        ->assertRedirect();

    Notification::assertSentTo($admin, OrderDelivered::class);
});

test('a user can read a notification and is redirected to its target', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $courier = User::factory()->create(['role' => UserRole::Courier]);
    $order = orderForNotifications($admin, $courier);

    $courier->notify(new OrderAssigned($order));
    $notification = $courier->notifications()->firstOrFail();

    $this->actingAs($courier)
        ->post("/notifications/{$notification->id}/read")
        ->assertRedirect("/board/{$order->id}");

    expect($courier->fresh()->unreadNotifications()->count())->toBe(0);
});

test('a user can mark all notifications as read', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $courier = User::factory()->create(['role' => UserRole::Courier]);
    $order = orderForNotifications($admin, $courier);

    $courier->notify(new OrderAssigned($order));
    $courier->notify(new OrderAssigned($order));

    expect($courier->unreadNotifications()->count())->toBe(2);

    $this->actingAs($courier)->post('/notifications/read-all')->assertRedirect();

    expect($courier->fresh()->unreadNotifications()->count())->toBe(0);
});
