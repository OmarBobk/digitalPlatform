<?php

use Illuminate\Support\Facades\Route;

test('registration routes are not registered when registration is disabled', function () {
    expect(Route::has('register'))->toBeFalse()
        ->and(Route::has('register.store'))->toBeFalse();
});

test('registration endpoints respond with not found', function () {
    $this->get('/register')->assertNotFound();

    $this->post('/register', [
        'name' => 'John Doe',
        'username' => 'johndoe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'preferred_currency' => 'USD',
    ])->assertNotFound();
});
