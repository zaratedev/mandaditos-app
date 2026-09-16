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

test('every admin stat links to an order list holding the same orders', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $client = Client::factory()->create();
    $address = Address::factory()->for($client)->create();

    $base = [
        'client_id' => $client->id,
        'address_id' => $address->id,
        'created_by' => $admin->id,
    ];

    // Open, created today.
    Order::factory()->create([...$base, 'status' => OrderStatus::Requested, 'created_at' => now()]);
    // Open, created earlier.
    Order::factory()->create([...$base, 'status' => OrderStatus::OnTheWay, 'created_at' => now()->subDays(2)]);
    // Delivered today and already paid.
    Order::factory()->create([...$base,
        'status' => OrderStatus::Delivered,
        'created_at' => now()->subDay(),
        'delivered_at' => now(),
        'payment_status' => PaymentStatus::Paid,
        'paid_at' => now(),
    ]);
    // Delivered today, still unpaid.
    Order::factory()->create([...$base,
        'status' => OrderStatus::Delivered,
        'created_at' => now()->subDays(3),
        'delivered_at' => now(),
        'payment_status' => PaymentStatus::Pending,
    ]);
    // Created today but cancelled: it counts for the day, never as open.
    Order::factory()->create([...$base, 'status' => OrderStatus::Cancelled, 'created_at' => now()]);

    $today = today()->toDateString();

    $this->actingAs($admin)->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->where('isAdmin', true)
            ->where('today', $today)
            ->where('stats.today', 2)
            ->where('stats.open', 2)
            ->where('stats.deliveredToday', 2)
            ->where('stats.unpaidDelivered', 1));

    // The links the cards point at have to come back with the very same counts.
    $links = [
        ['count' => 2, 'url' => "/orders?from={$today}&to={$today}"],
        ['count' => 2, 'url' => '/orders?status=open'],
        ['count' => 2, 'url' => "/orders?status=delivered&date_field=delivered&from={$today}&to={$today}"],
        ['count' => 1, 'url' => '/orders?status=delivered&payment_status=pending'],
    ];

    foreach ($links as $link) {
        $this->actingAs($admin)->get($link['url'])
            ->assertInertia(fn (Assert $page) => $page
                ->component('orders/Index')
                ->where('orders.total', $link['count']));
    }
});

test('the admin dashboard breaks out the money and the work waiting on the admin', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $courier = User::factory()->create(['role' => UserRole::Courier]);
    $client = Client::factory()->create();
    $address = Address::factory()->for($client)->create();

    $base = [
        'client_id' => $client->id,
        'address_id' => $address->id,
        'created_by' => $admin->id,
    ];

    // Earned today: paid today, its commission is the business's own cut.
    Order::factory()->create([...$base,
        'status' => OrderStatus::Delivered,
        'courier_id' => $courier->id,
        'delivered_at' => now(),
        'payment_status' => PaymentStatus::Paid,
        'payment_method' => PaymentMethod::Cash,
        'paid_at' => now(),
        'total' => 300.00,
        'commission' => 50.00,
    ]);

    // Owed: delivered but never paid. Two of them, the oldest three days back.
    Order::factory()->create([...$base,
        'status' => OrderStatus::Delivered,
        'delivered_at' => now()->subDays(3),
        'payment_status' => PaymentStatus::Pending,
        'total' => 200.00,
    ]);
    Order::factory()->create([...$base,
        'status' => OrderStatus::Delivered,
        'delivered_at' => now()->subDay(),
        'payment_status' => PaymentStatus::Pending,
        'total' => 100.00,
    ]);

    // On the street: bought and moving, the business's money still with the courier.
    // The purchased one has been open long enough to count as stale.
    Order::factory()->create([...$base,
        'status' => OrderStatus::Purchased,
        'courier_id' => $courier->id,
        'created_at' => now()->subHours(3),
        'total' => 150.00,
    ]);
    Order::factory()->create([...$base,
        'status' => OrderStatus::OnTheWay,
        'courier_id' => $courier->id,
        'created_at' => now(),
        'total' => 250.00,
    ]);

    // Waiting on the admin: open with nobody assigned yet, and just created.
    Order::factory()->create([...$base,
        'status' => OrderStatus::Requested,
        'courier_id' => null,
        'created_at' => now(),
    ]);

    $this->actingAs($admin)->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->where('finance.commissionToday', 50)
            ->where('finance.receivable.amount', 300)
            ->where('finance.receivable.orders', 2)
            ->where('finance.receivable.oldestDays', 3)
            ->where('finance.inTheStreet.amount', 400)
            ->where('finance.inTheStreet.orders', 2)
            ->where('attention.unassigned', 1)
            ->where('attention.stale', 1)
            ->where('staleHours', 2));

    // The two new cards point at lists holding exactly what they counted.
    $this->actingAs($admin)->get('/orders?status=in_transit')
        ->assertInertia(fn (Assert $page) => $page
            ->component('orders/Index')
            ->where('orders.total', 2));

    $this->actingAs($admin)->get('/orders?status=open&courier_id=unassigned')
        ->assertInertia(fn (Assert $page) => $page
            ->component('orders/Index')
            ->where('orders.total', 1));
});

test('the orders chart covers the last fourteen days by default', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $client = Client::factory()->create();
    $address = Address::factory()->for($client)->create();

    $base = ['client_id' => $client->id, 'address_id' => $address->id, 'created_by' => $admin->id];

    Order::factory()->count(2)->create([...$base, 'created_at' => now()]);
    Order::factory()->create([...$base, 'created_at' => now()->subDays(13)]);
    // Just outside the window: it must not show up anywhere in the series.
    Order::factory()->create([...$base, 'created_at' => now()->subDays(14)]);

    $this->actingAs($admin)->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->where('ordersChart.period', 'day')
            ->where('ordersChart.unit', 'day')
            ->where('ordersChart.from', today()->subDays(13)->toDateString())
            ->where('ordersChart.to', today()->toDateString())
            ->has('ordersChart.points', 14)
            ->where('ordersChart.points.0.orders', 1)
            ->where('ordersChart.points.13.orders', 2));
});

test('the month period buckets a year of orders by month', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $client = Client::factory()->create();
    $address = Address::factory()->for($client)->create();

    $base = ['client_id' => $client->id, 'address_id' => $address->id, 'created_by' => $admin->id];

    Order::factory()->count(3)->create([...$base, 'created_at' => today()->startOfMonth()]);
    Order::factory()->count(2)->create([...$base, 'created_at' => today()->startOfMonth()->subMonths(2)]);

    $this->actingAs($admin)->get('/dashboard?period=month')
        ->assertInertia(fn (Assert $page) => $page
            ->where('ordersChart.unit', 'month')
            ->where('ordersChart.from', today()->startOfMonth()->subMonths(11)->toDateString())
            ->has('ordersChart.points', 12)
            ->where('ordersChart.points.11.orders', 3)
            ->where('ordersChart.points.9.orders', 2));
});

test('a custom range is honoured, and put back in order when picked backwards', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $client = Client::factory()->create();
    $address = Address::factory()->for($client)->create();

    $base = ['client_id' => $client->id, 'address_id' => $address->id, 'created_by' => $admin->id];

    Order::factory()->create([...$base, 'created_at' => now()->subDays(2)]);
    Order::factory()->create([...$base, 'created_at' => now()->subDays(9)]);

    $from = today()->subDays(3)->toDateString();
    $to = today()->toDateString();

    $expected = fn (Assert $page) => $page
        ->where('ordersChart.period', 'custom')
        ->where('ordersChart.unit', 'day')
        ->where('ordersChart.from', $from)
        ->where('ordersChart.to', $to)
        ->has('ordersChart.points', 4)
        ->where('ordersChart.points.1.orders', 1);

    $this->actingAs($admin)->get("/dashboard?period=custom&from={$from}&to={$to}")
        ->assertInertia($expected);

    // Same two dates, handed over the wrong way round.
    $this->actingAs($admin)->get("/dashboard?period=custom&from={$to}&to={$from}")
        ->assertInertia($expected);
});

test('a custom range too long to read as days comes back as months', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $from = today()->subDays(200)->toDateString();
    $to = today()->toDateString();

    $this->actingAs($admin)->get("/dashboard?period=custom&from={$from}&to={$to}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('ordersChart.unit', 'month')
            ->where('ordersChart.from', $from)
            ->where('ordersChart.to', $to));
});

test('an unknown chart period is rejected', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)->get('/dashboard?period=decade')->assertSessionHasErrors('period');
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
