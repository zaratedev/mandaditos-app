<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Address;
use App\Models\Client;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

test('admins can list couriers with their workload', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $courier = User::factory()->create(['role' => UserRole::Courier]);

    $client = Client::factory()->create();
    $address = Address::factory()->for($client)->create();

    Order::factory()->create([
        'client_id' => $client->id,
        'address_id' => $address->id,
        'created_by' => $admin->id,
        'courier_id' => $courier->id,
        'status' => OrderStatus::Assigned,
    ]);
    Order::factory()->create([
        'client_id' => $client->id,
        'address_id' => $address->id,
        'created_by' => $admin->id,
        'courier_id' => $courier->id,
        'status' => OrderStatus::Delivered,
    ]);

    $this->actingAs($admin)->get('/couriers')
        ->assertInertia(fn (Assert $page) => $page
            ->component('couriers/Index')
            ->has('couriers.data', 1)
            ->where('couriers.data.0.orders_count', 2)
            ->where('couriers.data.0.open_orders_count', 1)
            ->where('couriers.data.0.is_active', true));
});

test('the couriers list never includes administrators', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    User::factory()->create(['role' => UserRole::Admin]);
    User::factory()->create(['role' => UserRole::Courier]);

    $this->actingAs($admin)->get('/couriers')
        ->assertInertia(fn (Assert $page) => $page->has('couriers.data', 1));
});

test('couriers can be filtered by status', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    User::factory()->create(['role' => UserRole::Courier, 'is_active' => true]);
    User::factory()->create(['role' => UserRole::Courier, 'is_active' => false]);

    $this->actingAs($admin)->get('/couriers?status=active')
        ->assertInertia(fn (Assert $page) => $page->has('couriers.data', 1)
            ->where('couriers.data.0.is_active', true));

    $this->actingAs($admin)->get('/couriers?status=inactive')
        ->assertInertia(fn (Assert $page) => $page->has('couriers.data', 1)
            ->where('couriers.data.0.is_active', false));
});

test('couriers cannot reach the couriers module', function () {
    $courier = User::factory()->create(['role' => UserRole::Courier]);

    $this->actingAs($courier)->get('/couriers')->assertForbidden();
    $this->actingAs($courier)->get('/couriers/create')->assertForbidden();
});

test('an admin creates a courier with the password they choose', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)->post('/couriers', [
        'name' => 'Luis Ramirez',
        'email' => 'luis@mandaditos.test',
        'password' => 'secret-password',
        'password_confirmation' => 'secret-password',
    ])->assertRedirect('/couriers');

    $courier = User::where('email', 'luis@mandaditos.test')->firstOrFail();

    expect($courier->role)->toBe(UserRole::Courier)
        ->and($courier->is_active)->toBeTrue()
        ->and($courier->email_verified_at)->not->toBeNull()
        ->and(Hash::check('secret-password', $courier->password))->toBeTrue();
});

test('a new courier can immediately sign in with that password', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)->post('/couriers', [
        'name' => 'Luis Ramirez',
        'email' => 'luis@mandaditos.test',
        'password' => 'secret-password',
        'password_confirmation' => 'secret-password',
    ]);

    auth()->logout();

    $this->post(route('login.store'), [
        'email' => 'luis@mandaditos.test',
        'password' => 'secret-password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

test('a courier email must be unique and the password confirmed', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    User::factory()->create(['email' => 'taken@mandaditos.test']);

    $this->actingAs($admin)->post('/couriers', [
        'name' => 'Luis',
        'email' => 'taken@mandaditos.test',
        'password' => 'secret-password',
        'password_confirmation' => 'different-password',
    ])->assertSessionHasErrors(['email', 'password']);

    expect(User::where('name', 'Luis')->exists())->toBeFalse();
});

test('an admin edits a courier without touching the password', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $courier = User::factory()->create([
        'role' => UserRole::Courier,
        'name' => 'Luis',
        'email' => 'luis@mandaditos.test',
    ]);

    $this->actingAs($admin)->put("/couriers/{$courier->id}", [
        'name' => 'Luis Ramirez',
        'email' => 'luis.ramirez@mandaditos.test',
        'password' => '',
        'password_confirmation' => '',
    ])->assertRedirect('/couriers');

    $courier->refresh();

    expect($courier->name)->toBe('Luis Ramirez')
        ->and($courier->email)->toBe('luis.ramirez@mandaditos.test')
        ->and(Hash::check('password', $courier->password))->toBeTrue();
});

test('an admin resets a forgotten courier password', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $courier = User::factory()->create(['role' => UserRole::Courier]);

    $this->actingAs($admin)->put("/couriers/{$courier->id}", [
        'name' => $courier->name,
        'email' => $courier->email,
        'password' => 'brand-new-password',
        'password_confirmation' => 'brand-new-password',
    ])->assertRedirect('/couriers');

    expect(Hash::check('brand-new-password', $courier->refresh()->password))->toBeTrue();
});

test('the courier module cannot reach an administrator account', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $other = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)->get("/couriers/{$other->id}/edit")->assertNotFound();

    $this->actingAs($admin)->put("/couriers/{$other->id}", [
        'name' => 'Hijacked',
        'email' => 'hijacked@mandaditos.test',
    ])->assertNotFound();

    $this->actingAs($admin)->post("/couriers/{$other->id}/deactivate")->assertNotFound();

    expect($other->refresh()->name)->not->toBe('Hijacked')
        ->and($other->is_active)->toBeTrue();
});

test('an admin deactivates and reactivates a courier', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $courier = User::factory()->create(['role' => UserRole::Courier]);

    $this->actingAs($admin)->post("/couriers/{$courier->id}/deactivate");
    expect($courier->refresh()->is_active)->toBeFalse();

    $this->actingAs($admin)->post("/couriers/{$courier->id}/activate");
    expect($courier->refresh()->is_active)->toBeTrue();
});

test('a deactivated courier cannot sign in', function () {
    $courier = User::factory()->create([
        'role' => UserRole::Courier,
        'is_active' => false,
    ]);

    $this->post(route('login.store'), [
        'email' => $courier->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('deactivating a courier terminates their open session', function () {
    $courier = User::factory()->create(['role' => UserRole::Courier]);

    $this->actingAs($courier)->get('/dashboard')->assertOk();

    $courier->update(['is_active' => false]);

    $this->get('/dashboard')->assertRedirect(route('login'));

    $this->assertGuest();
});

test('inactive couriers are not offered for assignment', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    User::factory()->create(['role' => UserRole::Courier, 'name' => 'Activo']);
    User::factory()->create(['role' => UserRole::Courier, 'name' => 'Inactivo', 'is_active' => false]);

    $this->actingAs($admin)->get('/orders/create')
        ->assertInertia(fn (Assert $page) => $page
            ->has('couriers', 1)
            ->where('couriers.0.name', 'Activo'));
});

test('inactive couriers remain available as an orders list filter', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    User::factory()->create(['role' => UserRole::Courier, 'name' => 'Activo']);
    User::factory()->create(['role' => UserRole::Courier, 'name' => 'Inactivo', 'is_active' => false]);

    $this->actingAs($admin)->get('/orders')
        ->assertInertia(fn (Assert $page) => $page->has('couriers', 2));
});

test('an order cannot be assigned to an inactive courier', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $courier = User::factory()->create(['role' => UserRole::Courier, 'is_active' => false]);

    $client = Client::factory()->create();
    $address = Address::factory()->for($client)->create();
    $order = Order::factory()->create([
        'client_id' => $client->id,
        'address_id' => $address->id,
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)->post("/orders/{$order->id}/assign", [
        'courier_id' => $courier->id,
    ])->assertSessionHasErrors('courier_id');

    expect($order->refresh()->courier_id)->toBeNull();
});

test('editing an order keeps the inactive courier already assigned to it', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $courier = User::factory()->create(['role' => UserRole::Courier]);

    $client = Client::factory()->create();
    $address = Address::factory()->for($client)->create();
    $order = Order::factory()->create([
        'client_id' => $client->id,
        'address_id' => $address->id,
        'created_by' => $admin->id,
        'courier_id' => $courier->id,
        'status' => OrderStatus::Assigned,
    ]);

    $courier->update(['is_active' => false]);

    $this->actingAs($admin)->put("/orders/{$order->id}", [
        'address_id' => $address->id,
        'courier_id' => $courier->id,
        'shopping_list' => 'Pan y leche',
    ])->assertSessionHasNoErrors();

    expect($order->refresh()->shopping_list)->toBe('Pan y leche')
        ->and($order->courier_id)->toBe($courier->id);

    // The edit form still shows them, flagged as inactive, so the admin can reassign.
    $this->actingAs($admin)->get("/orders/{$order->id}/edit")
        ->assertInertia(fn (Assert $page) => $page
            ->has('couriers', 1)
            ->where('couriers.0.is_active', false));
});
