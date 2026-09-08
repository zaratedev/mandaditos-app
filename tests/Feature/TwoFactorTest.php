<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;

test('a user can enable two-factor authentication with qr and recovery codes', function () {
    $user = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post('/user/two-factor-authentication')
        ->assertSessionHasNoErrors();

    $user->refresh();

    expect($user->two_factor_secret)->not->toBeNull()
        ->and($user->two_factor_recovery_codes)->not->toBeNull();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get('/user/two-factor-qr-code')
        ->assertOk()
        ->assertJsonStructure(['svg']);

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get('/user/two-factor-secret-key')
        ->assertOk()
        ->assertJsonStructure(['secretKey']);

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get('/user/two-factor-recovery-codes')
        ->assertOk()
        ->assertJsonCount(8);
});

test('a user can disable two-factor authentication', function () {
    $user = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post('/user/two-factor-authentication')
        ->assertSessionHasNoErrors();

    expect($user->fresh()->two_factor_secret)->not->toBeNull();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->delete('/user/two-factor-authentication')
        ->assertSessionHasNoErrors();

    expect($user->fresh()->two_factor_secret)->toBeNull();
});
