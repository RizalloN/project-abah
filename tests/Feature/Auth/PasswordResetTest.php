<?php

use Illuminate\Support\Facades\Route;

test('password reset routes are registered for guest users', function () {
    expect(Route::has('password.request'))->toBeTrue();
    expect(Route::has('password.email'))->toBeTrue();
    expect(Route::has('password.reset'))->toBeTrue();
    expect(Route::has('password.store'))->toBeTrue();
});

test('password reset request does not reveal whether an email is registered', function () {
    $this->post(route('password.email'), [
        'email' => 'not-registered-' . random_int(10000, 99999) . '@example.com',
    ])
        ->assertRedirect()
        ->assertSessionHas('status')
        ->assertSessionDoesntHaveErrors();
});
