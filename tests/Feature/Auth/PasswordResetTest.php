<?php

use Illuminate\Support\Facades\Route;

test('password reset routes are registered for guest users', function () {
    expect(Route::has('password.request'))->toBeTrue();
    expect(Route::has('password.email'))->toBeTrue();
    expect(Route::has('password.reset'))->toBeTrue();
    expect(Route::has('password.store'))->toBeTrue();
});
