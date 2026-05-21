<?php

use Illuminate\Support\Facades\Route;

test('profile management scaffold routes stay disabled', function () {
    expect(Route::has('profile.edit'))->toBeFalse();
    expect(Route::has('profile.update'))->toBeFalse();
    expect(Route::has('profile.destroy'))->toBeFalse();
});
