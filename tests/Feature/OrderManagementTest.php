<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Address;
use App\Models\Client;
use App\Models\Order;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('admin can create an order with priced items and totals are computed', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $client = Client::factory()->create();
    $address = Address::factory()->for($client)->create();

    $response = $this->actingAs($admin)->post('/orders', [
        'client_id' => $client->id,
        'address_id' => $address->id,
        'shopping_list' => "2 kg tortillas\n1 leche",
        'commission' => 30,
        'items' => [
            ['name' => 'Tortillas', 'quantity' => 2, 'unit_price' => 25],
            ['name' => 'Leche', 'quantity' => 1, 'unit_price' => 30],
        ],
    ]);

    $order = Order::firstOrFail();

    $response->assertRedirect("/orders/{$order->id}");

    expect($order->items)->toHaveCount(2)
        ->and((float) $order->items_subtotal)->toBe(80.0)
        ->and((float) $order->total)->toBe(110.0)
        ->and($order->status)->toBe(OrderStatus::Requested)
        ->and($order->created_by)->toBe($admin->id);
});

test('assigning a courier at creation marks the order as assigned', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $courier = User::factory()->create(['role' => UserRole::Courier]);
    $client = Client::factory()->create();
    $address = Address::factory()->for($client)->create();

    $this->actingAs($admin)->post('/orders', [
        'client_id' => $client->id,
        'address_id' => $address->id,
        'courier_id' => $courier->id,
        'shopping_list' => 'algo',
    ])->assertRedirect();

    $order = Order::firstOrFail();

    expect($order->status)->toBe(OrderStatus::Assigned)
        ->and($order->courier_id)->toBe($courier->id)
        ->and($order->confirmed_at)->not->toBeNull();
});

test('the delivery address must belong to the selected client', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $client = Client::factory()->create();
    $foreignAddress = Address::factory()->create();

    $this->actingAs($admin)->post('/orders', [
        'client_id' => $client->id,
        'address_id' => $foreignAddress->id,
        'shopping_list' => 'algo',
    ])->assertSessionHasErrors('address_id');
});

test('couriers cannot access order management', function () {
    $courier = User::factory()->create(['role' => UserRole::Courier]);

    $this->actingAs($courier)->get('/orders')->assertForbidden();
    $this->actingAs($courier)->get('/orders/create')->assertForbidden();
});

test('admin can assign, advance status and register payment', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $courier = User::factory()->create(['role' => UserRole::Courier]);
    $client = Client::factory()->create();
    $address = Address::factory()->for($client)->create();
    $order = Order::factory()->create([
        'client_id' => $client->id,
        'address_id' => $address->id,
        'created_by' => $admin->id,
        'status' => OrderStatus::Requested,
    ]);

    $this->actingAs($admin)
        ->post("/orders/{$order->id}/assign", ['courier_id' => $courier->id])
        ->assertRedirect();

    expect($order->refresh()->status)->toBe(OrderStatus::Assigned)
        ->and($order->courier_id)->toBe($courier->id);

    $this->actingAs($admin)
        ->post("/orders/{$order->id}/status", ['status' => OrderStatus::Delivered->value])
        ->assertRedirect();

    expect($order->refresh()->status)->toBe(OrderStatus::Delivered)
        ->and($order->delivered_at)->not->toBeNull();

    $this->actingAs($admin)
        ->post("/orders/{$order->id}/payment", ['payment_method' => PaymentMethod::Cash->value])
        ->assertRedirect();

    expect($order->refresh()->payment_status)->toBe(PaymentStatus::Paid)
        ->and($order->payment_method)->toBe(PaymentMethod::Cash)
        ->and($order->paid_at)->not->toBeNull();
});

test('admin can register a client with an address', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)->post('/clients', [
        'name' => 'Doña Mary',
        'phone' => '5551234567',
        'address' => [
            'street' => 'Calle Falsa 123',
            'neighborhood' => 'Centro',
        ],
    ])->assertRedirect('/clients');

    $client = Client::firstOrFail();

    expect($client->name)->toBe('Doña Mary')
        ->and($client->addresses)->toHaveCount(1)
        ->and($client->addresses->first()->street)->toBe('Calle Falsa 123');
});

test('admin can view the dashboard with the daily cash cut', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $courier = User::factory()->create(['role' => UserRole::Courier]);
    $client = Client::factory()->create();
    $address = Address::factory()->for($client)->create();

    Order::factory()->create([
        'client_id' => $client->id,
        'address_id' => $address->id,
        'created_by' => $admin->id,
        'courier_id' => $courier->id,
        'status' => OrderStatus::Delivered,
        'payment_method' => PaymentMethod::Cash,
        'payment_status' => PaymentStatus::Paid,
        'total' => 150,
        'commission' => 30,
        'paid_at' => now(),
    ]);

    $this->actingAs($admin)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('isAdmin', true)
            ->has('ordersChart.points', 14)
            ->has('openByStatus')
        );
});

test('couriers see their own dashboard without the cash cut', function () {
    $courier = User::factory()->create(['role' => UserRole::Courier]);

    $this->actingAs($courier)->get('/dashboard')->assertOk();
});

test('admin can record the purchase amount and commission', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $client = Client::factory()->create();
    $address = Address::factory()->for($client)->create();
    $order = Order::factory()->create([
        'client_id' => $client->id,
        'address_id' => $address->id,
        'created_by' => $admin->id,
        'status' => OrderStatus::Purchasing,
    ]);

    $this->actingAs($admin)
        ->post("/orders/{$order->id}/purchase", ['items_subtotal' => 250, 'commission' => 40])
        ->assertRedirect();

    $order->refresh();

    expect((float) $order->items_subtotal)->toBe(250.0)
        ->and((float) $order->commission)->toBe(40.0)
        ->and((float) $order->total)->toBe(290.0);
});

test('admin can edit an order and its items', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $client = Client::factory()->create();
    $address = Address::factory()->for($client)->create();
    $newAddress = Address::factory()->for($client)->create();
    $order = Order::factory()->create([
        'client_id' => $client->id,
        'address_id' => $address->id,
        'created_by' => $admin->id,
        'shopping_list' => 'lista vieja',
    ]);

    $this->actingAs($admin)->put("/orders/{$order->id}", [
        'address_id' => $newAddress->id,
        'shopping_list' => 'lista nueva',
        'commission' => 20,
        'items' => [
            ['name' => 'Pan', 'quantity' => 3, 'unit_price' => 10],
        ],
    ])->assertRedirect("/orders/{$order->id}");

    $order->refresh();

    expect($order->address_id)->toBe($newAddress->id)
        ->and($order->shopping_list)->toBe('lista nueva')
        ->and($order->items)->toHaveCount(1)
        ->and((float) $order->items_subtotal)->toBe(30.0)
        ->and((float) $order->total)->toBe(50.0);
});

test('editing an order rejects an address from another client', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $client = Client::factory()->create();
    $address = Address::factory()->for($client)->create();
    $order = Order::factory()->create([
        'client_id' => $client->id,
        'address_id' => $address->id,
        'created_by' => $admin->id,
    ]);
    $foreignAddress = Address::factory()->create();

    $this->actingAs($admin)->put("/orders/{$order->id}", [
        'address_id' => $foreignAddress->id,
        'shopping_list' => 'algo',
    ])->assertSessionHasErrors('address_id');
});

test('admin can cancel an order', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $client = Client::factory()->create();
    $address = Address::factory()->for($client)->create();
    $order = Order::factory()->create([
        'client_id' => $client->id,
        'address_id' => $address->id,
        'created_by' => $admin->id,
        'status' => OrderStatus::Assigned,
    ]);

    $this->actingAs($admin)->post("/orders/{$order->id}/cancel")->assertRedirect('/orders');

    expect($order->refresh()->status)->toBe(OrderStatus::Cancelled);
});

test('couriers cannot edit or cancel orders', function () {
    $courier = User::factory()->create(['role' => UserRole::Courier]);
    $client = Client::factory()->create();
    $address = Address::factory()->for($client)->create();
    $order = Order::factory()->create([
        'client_id' => $client->id,
        'address_id' => $address->id,
        'created_by' => $courier->id,
        'courier_id' => $courier->id,
    ]);

    $this->actingAs($courier)->get("/orders/{$order->id}/edit")->assertForbidden();
    $this->actingAs($courier)->post("/orders/{$order->id}/cancel")->assertForbidden();
});

test('orders can be filtered by courier, client and payment status', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $courierA = User::factory()->create(['role' => UserRole::Courier]);
    $courierB = User::factory()->create(['role' => UserRole::Courier]);
    $client = Client::factory()->create();
    $address = Address::factory()->for($client)->create();

    Order::factory()->create([
        'client_id' => $client->id,
        'address_id' => $address->id,
        'created_by' => $admin->id,
        'courier_id' => $courierA->id,
        'payment_status' => PaymentStatus::Paid,
    ]);
    Order::factory()->create([
        'client_id' => $client->id,
        'address_id' => $address->id,
        'created_by' => $admin->id,
        'courier_id' => $courierB->id,
        'payment_status' => PaymentStatus::Pending,
    ]);

    $this->actingAs($admin)->get('/orders?courier_id='.$courierA->id)
        ->assertInertia(fn (Assert $page) => $page->component('orders/Index')->has('orders.data', 1));

    $this->actingAs($admin)->get('/orders?payment_status=paid')
        ->assertInertia(fn (Assert $page) => $page->has('orders.data', 1));

    $this->actingAs($admin)->get('/orders?client_id='.$client->id)
        ->assertInertia(fn (Assert $page) => $page->has('orders.data', 2));

    $this->actingAs($admin)->get('/orders?courier_id=unassigned')
        ->assertInertia(fn (Assert $page) => $page->has('orders.data', 0));
});

test('the open filter keeps every order that is neither delivered nor cancelled', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $client = Client::factory()->create();
    $address = Address::factory()->for($client)->create();

    $base = [
        'client_id' => $client->id,
        'address_id' => $address->id,
        'created_by' => $admin->id,
    ];

    Order::factory()->create([...$base, 'status' => OrderStatus::Requested]);
    Order::factory()->create([...$base, 'status' => OrderStatus::OnTheWay]);
    Order::factory()->create([...$base, 'status' => OrderStatus::Delivered]);
    Order::factory()->create([...$base, 'status' => OrderStatus::Cancelled]);

    $this->actingAs($admin)->get('/orders?status=open')
        ->assertInertia(fn (Assert $page) => $page->component('orders/Index')->has('orders.data', 2));
});

test('the date range can filter on the delivery and payment dates', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $client = Client::factory()->create();
    $address = Address::factory()->for($client)->create();

    $base = [
        'client_id' => $client->id,
        'address_id' => $address->id,
        'created_by' => $admin->id,
        'status' => OrderStatus::Delivered,
    ];

    // Ordered a week ago, delivered and paid today.
    Order::factory()->create([
        ...$base,
        'created_at' => now()->subWeek(),
        'delivered_at' => now(),
        'payment_status' => PaymentStatus::Paid,
        'paid_at' => now(),
    ]);

    // Ordered today, delivered and paid yesterday: the created range must not catch it
    // when the filter is looking at the delivery date.
    Order::factory()->create([
        ...$base,
        'created_at' => now(),
        'delivered_at' => now()->subDay(),
        'payment_status' => PaymentStatus::Paid,
        'paid_at' => now()->subDay(),
    ]);

    $today = today()->toDateString();

    $this->actingAs($admin)->get("/orders?date_field=delivered&from={$today}&to={$today}")
        ->assertInertia(fn (Assert $page) => $page->component('orders/Index')->has('orders.data', 1));

    $this->actingAs($admin)->get("/orders?date_field=paid&from={$today}&to={$today}")
        ->assertInertia(fn (Assert $page) => $page->has('orders.data', 1));

    // Without a date field the range still runs on the creation date.
    $this->actingAs($admin)->get("/orders?from={$today}&to={$today}")
        ->assertInertia(fn (Assert $page) => $page->has('orders.data', 1));
});

test('an unknown status or date field is rejected', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)->get('/orders?status=nope')->assertSessionHasErrors('status');
    $this->actingAs($admin)->get('/orders?date_field=nope')->assertSessionHasErrors('date_field');
});

test('the orders list honours the page size, and refuses one off the menu', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $client = Client::factory()->create();
    $address = Address::factory()->for($client)->create();

    Order::factory()->count(26)->create([
        'client_id' => $client->id,
        'address_id' => $address->id,
        'created_by' => $admin->id,
    ]);

    // Default: 25 rows on the page, the rest spill to page two, and the selector
    // is told which size is in effect.
    $this->actingAs($admin)->get('/orders')
        ->assertInertia(fn (Assert $page) => $page
            ->component('orders/Index')
            ->where('perPage', 25)
            ->where('orders.total', 26)
            ->has('orders.data', 25));

    // A size from the menu caps the page.
    $this->actingAs($admin)->get('/orders?per_page=10')
        ->assertInertia(fn (Assert $page) => $page
            ->where('perPage', 10)
            ->has('orders.data', 10));

    // A size that is not on the menu is refused, not silently honoured.
    $this->actingAs($admin)->get('/orders?per_page=15')->assertSessionHasErrors('per_page');
});
