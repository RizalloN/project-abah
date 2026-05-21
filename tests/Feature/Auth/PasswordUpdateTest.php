<?php

use Illuminate\Support\Facades\Route;

test('password update route is registered behind auth middleware', function () {
    expect(Route::has('password.update'))->toBeTrue();

    $route = Route::getRoutes()->getByName('password.update');

    expect($route)->not->toBeNull();
    expect($route->gatherMiddleware())->toContain('auth');
});
