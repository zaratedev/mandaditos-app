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

test('an admin edits a client and its addresses', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $client = Client::factory()->create(['name' => 'Maria Lopez', 'phone' => '5511111111']);
    $address = Address::factory()->for($client)->create(['street' => 'Av. Juarez 45']);

    $this->actingAs($admin)->put("/clients/{$client->id}", [
        'name' => 'Maria Lopez Garcia',
        'phone' => '5522222222',
        'notes' => 'Prefiere entregas por la tarde',
        'addresses' => [
            ['id' => $address->id, 'street' => 'Av. Juarez 145', 'label' => 'Casa'],
            ['street' => 'Reforma 200', 'label' => 'Trabajo'],
        ],
    ])->assertRedirect('/clients');

    $client->refresh();

    expect($client->name)->toBe('Maria Lopez Garcia')
        ->and($client->phone)->toBe('5522222222')
        ->and($client->addresses)->toHaveCount(2)
        ->and($address->refresh()->street)->toBe('Av. Juarez 145')
        ->and($client->addresses->pluck('label')->all())->toContain('Trabajo');
});

test('an address dropped from the form is removed only when no order uses it', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $client = Client::factory()->create();

    $used = Address::factory()->for($client)->create(['street' => 'Usada en un pedido']);
    $unused = Address::factory()->for($client)->create(['street' => 'Nunca usada']);

    Order::factory()->create([
        'client_id' => $client->id,
        'address_id' => $used->id,
        'created_by' => $admin->id,
    ]);

    // Send only a third address: both existing ones are dropped from the form.
    $this->actingAs($admin)->put("/clients/{$client->id}", [
        'name' => $client->name,
        'addresses' => [['street' => 'Nueva unica']],
    ])->assertRedirect('/clients');

    expect(Address::find($unused->id))->toBeNull()
        ->and(Address::find($used->id))->not->toBeNull()
        ->and(Order::where('address_id', $used->id)->exists())->toBeTrue();
});

test('a client cannot be left without an address', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $client = Client::factory()->create();
    Address::factory()->for($client)->create();

    $this->actingAs($admin)->put("/clients/{$client->id}", [
        'name' => $client->name,
        'addresses' => [],
    ])->assertSessionHasErrors('addresses');
});

test('an address belonging to another client cannot be hijacked', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $client = Client::factory()->create();
    Address::factory()->for($client)->create();

    $stranger = Address::factory()->for(Client::factory()->create())->create(['street' => 'Ajena']);

    $this->actingAs($admin)->put("/clients/{$client->id}", [
        'name' => $client->name,
        'addresses' => [['id' => $stranger->id, 'street' => 'Secuestrada']],
    ])->assertSessionHasErrors('addresses.0.id');

    expect($stranger->refresh()->street)->toBe('Ajena');
});

test('couriers cannot edit clients', function () {
    $courier = User::factory()->create(['role' => UserRole::Courier]);
    $client = Client::factory()->create();

    $this->actingAs($courier)->get("/clients/{$client->id}/edit")->assertForbidden();
    $this->actingAs($courier)->put("/clients/{$client->id}", [
        'name' => 'Hackeado',
        'addresses' => [['street' => 'x']],
    ])->assertForbidden();

    expect($client->refresh()->name)->not->toBe('Hackeado');
});

test('a client that was never used can be deleted outright', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $client = Client::factory()->create();
    $address = Address::factory()->for($client)->create();

    $this->actingAs($admin)->delete("/clients/{$client->id}")
        ->assertRedirect('/clients');

    expect(Client::find($client->id))->toBeNull()
        ->and(Address::find($address->id))->toBeNull();
});

test('a client with orders is never deleted, history and all', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $client = Client::factory()->create();
    $address = Address::factory()->for($client)->create();

    $order = Order::factory()->create([
        'client_id' => $client->id,
        'address_id' => $address->id,
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)->delete("/clients/{$client->id}");

    expect(Client::find($client->id))->not->toBeNull()
        ->and(Order::find($order->id))->not->toBeNull();
});

test('an admin archives and restores a client', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $client = Client::factory()->create();

    $this->actingAs($admin)->post("/clients/{$client->id}/archive");
    expect($client->refresh()->is_active)->toBeFalse();

    $this->actingAs($admin)->post("/clients/{$client->id}/restore");
    expect($client->refresh()->is_active)->toBeTrue();
});

test('archived clients leave the list but stay reachable', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    Client::factory()->create(['name' => 'Activa']);
    Client::factory()->create(['name' => 'Archivada', 'is_active' => false]);

    $this->actingAs($admin)->get('/clients')
        ->assertInertia(fn (Assert $page) => $page->has('clients.data', 1)
            ->where('clients.data.0.name', 'Activa'));

    $this->actingAs($admin)->get('/clients?status=archived')
        ->assertInertia(fn (Assert $page) => $page->has('clients.data', 1)
            ->where('clients.data.0.name', 'Archivada'));

    $this->actingAs($admin)->get('/clients?status=all')
        ->assertInertia(fn (Assert $page) => $page->has('clients.data', 2));
});

test('an archived client cannot receive a new order', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $client = Client::factory()->create(['is_active' => false]);
    $address = Address::factory()->for($client)->create();

    $this->actingAs($admin)->get('/orders/create')
        ->assertInertia(fn (Assert $page) => $page->has('clients', 0));

    $this->actingAs($admin)->post('/orders', [
        'client_id' => $client->id,
        'address_id' => $address->id,
        'shopping_list' => 'Pan y leche',
    ])->assertSessionHasErrors('client_id');

    expect(Order::where('client_id', $client->id)->exists())->toBeFalse();
});

test('couriers cannot delete or archive clients', function () {
    $courier = User::factory()->create(['role' => UserRole::Courier]);
    $client = Client::factory()->create();

    $this->actingAs($courier)->delete("/clients/{$client->id}")->assertForbidden();
    $this->actingAs($courier)->post("/clients/{$client->id}/archive")->assertForbidden();

    expect(Client::find($client->id))->not->toBeNull()
        ->and($client->refresh()->is_active)->toBeTrue();
});
