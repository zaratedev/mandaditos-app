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

function reportOrder(array $attributes = []): Order
{
    $admin = User::firstWhere('role', UserRole::Admin->value)
        ?? User::factory()->create(['role' => UserRole::Admin]);

    $client = Client::first() ?? Client::factory()->create();
    $address = Address::firstWhere('client_id', $client->id)
        ?? Address::factory()->for($client)->create();

    return Order::factory()->create([
        'client_id' => $client->id,
        'address_id' => $address->id,
        'created_by' => $admin->id,
        ...$attributes,
    ]);
}

test('admin can view reports with computed totals', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $client = Client::factory()->create();
    $address = Address::factory()->for($client)->create();

    Order::factory()->count(2)->create([
        'client_id' => $client->id,
        'address_id' => $address->id,
        'created_by' => $admin->id,
        'status' => OrderStatus::Delivered,
        'payment_status' => PaymentStatus::Paid,
        'payment_method' => PaymentMethod::Cash,
        'delivered_at' => now(),
        'paid_at' => now(),
        'total' => 100,
        'commission' => 20,
    ]);

    $this->actingAs($admin)->get('/reports')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/Index')
            ->where('operations.orders', 2)
            ->where('operations.delivered', 2)
            ->where('collected.orders', 2)
            ->where('collected.commission', 40)
            ->where('collected.moved', 200)
            ->where('collected.cash', 200)
            ->where('collected.ticket', 100)
            ->where('collected.fee', 20)
        );
});

test('reports accept a date range that excludes older orders', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $client = Client::factory()->create();
    $address = Address::factory()->for($client)->create();

    Order::factory()->create([
        'client_id' => $client->id,
        'address_id' => $address->id,
        'created_by' => $admin->id,
        'created_at' => now()->subMonths(3),
    ]);

    $this->actingAs($admin)
        ->get('/reports?from='.now()->startOfMonth()->toDateString().'&to='.now()->toDateString())
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('operations.orders', 0));
});

test('couriers cannot view reports', function () {
    $courier = User::factory()->create(['role' => UserRole::Courier]);

    $this->actingAs($courier)->get('/reports')->assertForbidden();
});

test('the money is counted on the day it was collected, not the day it was ordered', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    // Ordered before the window, paid inside it: this month's money.
    reportOrder([
        'created_at' => now()->subMonths(2),
        'status' => OrderStatus::Delivered,
        'payment_status' => PaymentStatus::Paid,
        'payment_method' => PaymentMethod::Cash,
        'delivered_at' => now()->subMonths(2),
        'paid_at' => now(),
        'total' => 500,
        'commission' => 45,
    ]);

    // Ordered inside the window, not paid yet: no money, but it is an order.
    reportOrder([
        'created_at' => now(),
        'status' => OrderStatus::Delivered,
        'payment_status' => PaymentStatus::Pending,
        'delivered_at' => now(),
        'total' => 900,
        'commission' => 60,
    ]);

    $this->actingAs($admin)->get('/reports')
        ->assertInertia(fn (Assert $page) => $page
            ->where('collected.commission', 45)
            ->where('collected.moved', 500)
            ->where('operations.orders', 1)
            // What is owed ignores the date filter and only counts delivered orders.
            ->where('receivable.orders', 1)
            ->where('receivable.amount', 900)
            ->where('receivable.commission', 60));
});

test('an order still in flight is not money anybody owes', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    reportOrder([
        'status' => OrderStatus::Purchasing,
        'payment_status' => PaymentStatus::Pending,
        'total' => 700,
        'commission' => 40,
    ]);

    $this->actingAs($admin)->get('/reports')
        ->assertInertia(fn (Assert $page) => $page
            ->where('operations.orders', 1)
            ->where('receivable.orders', 0)
            ->where('receivable.amount', 0));
});

test('each headline number is compared with the period before it', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $from = now()->startOfMonth();

    // Twice the fees of the previous period of the same length.
    reportOrder([
        'created_at' => $from,
        'status' => OrderStatus::Delivered,
        'payment_status' => PaymentStatus::Paid,
        'delivered_at' => $from,
        'paid_at' => $from,
        'total' => 300,
        'commission' => 100,
    ]);
    reportOrder([
        'created_at' => $from->copy()->subDay(),
        'status' => OrderStatus::Delivered,
        'payment_status' => PaymentStatus::Paid,
        'delivered_at' => $from->copy()->subDay(),
        'paid_at' => $from->copy()->subDay(),
        'total' => 200,
        'commission' => 50,
    ]);

    $this->actingAs($admin)->get('/reports')
        ->assertInertia(fn (Assert $page) => $page
            ->where('collected.commission', 100)
            ->where('change.commission', 100));
});

test('with nothing to compare against there is no percentage', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    reportOrder([
        'created_at' => now(),
        'status' => OrderStatus::Delivered,
        'payment_status' => PaymentStatus::Paid,
        'delivered_at' => now(),
        'paid_at' => now(),
        'total' => 300,
        'commission' => 100,
    ]);

    $this->actingAs($admin)->get('/reports')
        ->assertInertia(fn (Assert $page) => $page->where('change.commission', null));
});

test('reports measure how long deliveries took', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $courier = User::factory()->create(['role' => UserRole::Courier, 'name' => 'Beto']);

    reportOrder([
        'courier_id' => $courier->id,
        'created_at' => now()->subMinutes(90),
        'status' => OrderStatus::Delivered,
        'delivered_at' => now()->subMinutes(30),
        'commission' => 30,
    ]);
    reportOrder([
        'courier_id' => $courier->id,
        'created_at' => now()->subMinutes(60),
        'status' => OrderStatus::Delivered,
        'delivered_at' => now()->subMinutes(40),
        'commission' => 30,
    ]);

    $this->actingAs($admin)->get('/reports')
        ->assertInertia(fn (Assert $page) => $page
            ->where('delivery.orders', 2)
            ->where('delivery.average', 40)   // 60 and 20 minutes
            ->where('delivery.slowest', 60)
            ->where('perCourier.0.courier', 'Beto')
            ->where('perCourier.0.average', 40));
});

test('couriers are ranked by the fees they brought in, not the cash they carried', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $bigBasket = User::factory()->create(['role' => UserRole::Courier, 'name' => 'Canasta grande']);
    $manyErrands = User::factory()->create(['role' => UserRole::Courier, 'name' => 'Muchos mandados']);

    // One expensive delivery: a lot of somebody else's money, one fee.
    reportOrder([
        'courier_id' => $bigBasket->id,
        'status' => OrderStatus::Delivered,
        'delivered_at' => now(),
        'total' => 5000,
        'commission' => 25,
    ]);

    // Three cheap ones: less money moved, three times the fees.
    foreach (range(1, 3) as $ignored) {
        reportOrder([
            'courier_id' => $manyErrands->id,
            'status' => OrderStatus::Delivered,
            'delivered_at' => now(),
            'total' => 200,
            'commission' => 30,
        ]);
    }

    $this->actingAs($admin)->get('/reports')
        ->assertInertia(fn (Assert $page) => $page
            ->where('perCourier.0.courier', 'Muchos mandados')
            ->where('perCourier.0.commission', 90)
            ->where('perCourier.1.courier', 'Canasta grande'));
});

test('reports rank the clients the business lives off', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $loyal = Client::factory()->create(['name' => 'Doña Chelo']);
    $loyalAddress = Address::factory()->for($loyal)->create();
    $occasional = Client::factory()->create(['name' => 'Pasajero']);
    $occasionalAddress = Address::factory()->for($occasional)->create();

    foreach (range(1, 3) as $ignored) {
        Order::factory()->create([
            'client_id' => $loyal->id,
            'address_id' => $loyalAddress->id,
            'created_by' => $admin->id,
            'commission' => 40,
            'total' => 300,
        ]);
    }

    Order::factory()->create([
        'client_id' => $occasional->id,
        'address_id' => $occasionalAddress->id,
        'created_by' => $admin->id,
        'commission' => 25,
        'total' => 900,
    ]);

    // Cancelled orders left nothing behind and must not rank anybody.
    Order::factory()->create([
        'client_id' => $occasional->id,
        'address_id' => $occasionalAddress->id,
        'created_by' => $admin->id,
        'status' => OrderStatus::Cancelled,
        'commission' => 500,
        'total' => 500,
    ]);

    $this->actingAs($admin)->get('/reports')
        ->assertInertia(fn (Assert $page) => $page
            ->has('perClient', 2)
            ->where('perClient.0.client', 'Doña Chelo')
            ->where('perClient.0.orders', 3)
            ->where('perClient.0.commission', 120)
            ->where('perClient.1.client', 'Pasajero')
            ->where('perClient.1.commission', 25));
});

test('reports include a product breakdown', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $client = Client::factory()->create();
    $address = Address::factory()->for($client)->create();

    $orderA = Order::factory()->create([
        'client_id' => $client->id,
        'address_id' => $address->id,
        'created_by' => $admin->id,
    ]);
    $orderA->items()->create(['name' => 'Tortillas', 'quantity' => 2, 'unit_price' => 25, 'line_total' => 50]);
    $orderA->items()->create(['name' => 'Leche', 'quantity' => 1, 'unit_price' => 30, 'line_total' => 30]);

    $orderB = Order::factory()->create([
        'client_id' => $client->id,
        'address_id' => $address->id,
        'created_by' => $admin->id,
    ]);
    $orderB->items()->create(['name' => 'Tortillas', 'quantity' => 3, 'unit_price' => 25, 'line_total' => 75]);

    $this->actingAs($admin)->get('/reports')
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/Index')
            ->has('topProducts', 2)
            ->where('topProducts.0.name', 'Tortillas')
            ->where('topProducts.0.orders', 2)
        );
});
