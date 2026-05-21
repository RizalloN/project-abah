<?php

use Illuminate\Support\Facades\Route;

test('registration routes stay disabled for the internal PN based app', function () {
    expect(Route::has('register'))->toBeFalse();
});
