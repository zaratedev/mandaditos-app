<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderAssigned;
use NotificationChannels\WebPush\WebPushChannel;

test('a user can store a web push subscription', function () {
    $user = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($user)->postJson('/push-subscriptions', [
        'endpoint' => 'https://push.example.com/subscription-abc',
        'keys' => ['p256dh' => 'p256dh-key', 'auth' => 'auth-key'],
    ])->assertOk();

    expect($user->pushSubscriptions()->count())->toBe(1);
});

test('a user can remove a web push subscription', function () {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $endpoint = 'https://push.example.com/subscription-abc';
    $user->updatePushSubscription($endpoint, 'p256dh-key', 'auth-key');

    expect($user->pushSubscriptions()->count())->toBe(1);

    $this->actingAs($user)
        ->deleteJson('/push-subscriptions', ['endpoint' => $endpoint])
        ->assertOk();

    expect($user->fresh()->pushSubscriptions()->count())->toBe(0);
});

test('storing a push subscription requires authentication', function () {
    $this->postJson('/push-subscriptions', [
        'endpoint' => 'https://push.example.com/x',
        'keys' => ['p256dh' => 'a', 'auth' => 'b'],
    ])->assertUnauthorized();
});

test('order notifications are delivered via the web push channel', function () {
    $notification = new OrderAssigned(new Order());

    expect($notification->via(new User()))
        ->toContain('database')
        ->toContain(WebPushChannel::class);
});
