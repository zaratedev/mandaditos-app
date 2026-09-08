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
        'total' => 100,
        'commission' => 20,
    ]);

    $this->actingAs($admin)->get('/reports')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/Index')
            ->where('summary.orders', 2)
            ->where('summary.delivered', 2)
            ->where('totals.paidOrders', 2)
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
        ->assertInertia(fn (Assert $page) => $page->where('summary.orders', 0));
});

test('couriers cannot view reports', function () {
    $courier = User::factory()->create(['role' => UserRole::Courier]);

    $this->actingAs($courier)->get('/reports')->assertForbidden();
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
