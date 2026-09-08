<?php

declare(strict_types=1);

test('the registration screen is not available', function () {
    $this->get('/register')->assertNotFound();
});

test('new users cannot register', function () {
    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();

    $this->assertDatabaseCount('users', 0);
});
