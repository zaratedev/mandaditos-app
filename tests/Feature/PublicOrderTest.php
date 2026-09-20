<?php

use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Business;
use App\Models\Client;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderPlaced;
use Illuminate\Support\Facades\Notification;

test('a guest places an order through the public portal', function () {
    Notification::fake();

    $business = Business::factory()->create(['slug' => 'rivers']);
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $response = $this->post('/rivers/pedido', [
        'customer_name' => 'Maria Lopez',
        'phone' => '5544332211',
        'street' => 'Av. Juarez 45',
        'neighborhood' => 'Centro',
        'items' => ['1kg de jitomate', '2 refrescos'],
        'notes' => 'Porton azul',
        'company' => '',
    ]);

    $response->assertRedirect('/rivers/pedido');
    $response->assertSessionHas('success');

    $order = Order::query()->firstOrFail();

    expect($order->tenant_id)->toBe($business->id)
        ->and($order->status)->toBe(OrderStatus::Requested)
        ->and($order->source)->toBe(OrderSource::Portal)
        ->and($order->created_by)->toBeNull()
        ->and($order->shopping_list)->toBe("1kg de jitomate\n2 refrescos")
        ->and($order->items()->count())->toBe(2)
        ->and($order->client->name)->toBe('Maria Lopez')
        ->and($order->client->phone)->toBe('5544332211')
        ->and($order->address->street)->toBe('Av. Juarez 45');

    Notification::assertSentTo($admin, OrderPlaced::class);
});

test('the portal rejects a request without items', function () {
    Business::factory()->create(['slug' => 'rivers']);

    $this->post('/rivers/pedido', [
        'customer_name' => 'Maria',
        'phone' => '55',
        'street' => 'Calle 1',
        'items' => [],
    ])->assertSessionHasErrors('items');
});

test('the portal needs name, phone and street', function () {
    Business::factory()->create(['slug' => 'rivers']);

    $this->post('/rivers/pedido', ['items' => ['pan']])
        ->assertSessionHasErrors(['customer_name', 'phone', 'street']);
});

test('the honeypot blocks a bot submission', function () {
    Business::factory()->create(['slug' => 'rivers']);

    $this->post('/rivers/pedido', [
        'customer_name' => 'Bot',
        'phone' => '55',
        'street' => 'x',
        'items' => ['pan'],
        'company' => 'ACME Corp',
    ])->assertSessionHasErrors('company');
});

test('a repeat phone reuses the same client', function () {
    Business::factory()->create(['slug' => 'rivers']);
    User::factory()->create(['role' => UserRole::Admin]);

    $payload = [
        'customer_name' => 'Ana',
        'phone' => '5500000000',
        'street' => 'Calle 2',
        'items' => ['leche'],
    ];

    $this->post('/rivers/pedido', $payload);
    $this->post('/rivers/pedido', [...$payload, 'items' => ['pan']]);

    expect(Client::where('phone', '5500000000')->count())->toBe(1)
        ->and(Order::count())->toBe(2);
});
