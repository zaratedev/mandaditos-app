<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Address;
use App\Models\Client;
use App\Models\Order;
use App\Models\User;
use App\Support\AppDate;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

test('a moment is written as DD/MM/YYYY with a lowercase spanish meridiem', function () {
    expect(AppDate::dateTime(CarbonImmutable::create(2026, 9, 8, 14, 33)))->toBe('08/09/2026 2:33 p.m.')
        ->and(AppDate::dateTime(CarbonImmutable::create(2026, 9, 8, 9, 5)))->toBe('08/09/2026 9:05 a.m.')
        ->and(AppDate::dateTime(CarbonImmutable::create(2026, 1, 31, 0, 0)))->toBe('31/01/2026 12:00 a.m.')
        ->and(AppDate::dateTime(CarbonImmutable::create(2026, 12, 1, 12, 0)))->toBe('01/12/2026 12:00 p.m.')
        ->and(AppDate::dateTime(null))->toBeNull();
});

test('order timestamps reach the screen in that format', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $client = Client::factory()->create();
    $address = Address::factory()->for($client)->create();

    $moment = CarbonImmutable::create(2026, 9, 8, 14, 33, 0, config('app.timezone'));

    $order = Order::factory()->create([
        'client_id' => $client->id,
        'address_id' => $address->id,
        'created_by' => $admin->id,
        'status' => OrderStatus::Delivered,
        'created_at' => $moment,
        'delivered_at' => $moment,
    ]);

    $this->actingAs($admin)->get("/orders/{$order->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('order.created_at', '08/09/2026 2:33 p.m.')
            ->where('order.delivered_at', '08/09/2026 2:33 p.m.'));
});

test('the courier board uses the same format', function () {
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
        'created_at' => CarbonImmutable::create(2026, 9, 8, 9, 5, 0, config('app.timezone')),
    ]);

    $this->actingAs($courier)->get('/board')
        ->assertInertia(fn (Assert $page) => $page->where('orders.0.created_at', '08/09/2026 9:05 a.m.'));
});
