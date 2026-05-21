<?php

use Illuminate\Support\Facades\Route;

test('password confirmation routes are registered behind auth middleware', function () {
    expect(Route::has('password.confirm'))->toBeTrue();

    $route = Route::getRoutes()->getByName('password.confirm');

    expect($route)->not->toBeNull();
    expect($route->gatherMiddleware())->toContain('auth');
});
