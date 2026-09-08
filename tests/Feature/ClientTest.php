<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Address;
use App\Models\Client;
use App\Models\Order;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('clients can be searched by name', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    Client::factory()->create(['name' => 'Maria Lopez']);
    Client::factory()->create(['name' => 'Juan Perez']);

    $this->actingAs($admin)->get('/clients?search=mar')
        ->assertInertia(fn (Assert $page) => $page->component('clients/Index')->has('clients.data', 1));
});

test('clients can be filtered by whether they have orders', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $withOrders = Client::factory()->create();
    $address = Address::factory()->for($withOrders)->create();
    Order::factory()->create([
        'client_id' => $withOrders->id,
        'address_id' => $address->id,
        'created_by' => $admin->id,
    ]);

    Client::factory()->create();

    $this->actingAs($admin)->get('/clients?has_orders=with')
        ->assertInertia(fn (Assert $page) => $page->has('clients.data', 1));

    $this->actingAs($admin)->get('/clients?has_orders=without')
        ->assertInertia(fn (Assert $page) => $page->has('clients.data', 1));
});

test('couriers cannot view the clients list', function () {
    $courier = User::factory()->create(['role' => UserRole::Courier]);

    $this->actingAs($courier)->get('/clients')->assertForbidden();
});
