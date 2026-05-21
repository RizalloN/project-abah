<?php

use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Route;

test('email verification routes exist but current PN user model does not require email verification', function () {
    expect(Route::has('verification.notice'))->toBeTrue();
    expect(Route::has('verification.verify'))->toBeTrue();
    expect(Route::has('verification.send'))->toBeTrue();
    expect(is_subclass_of(User::class, MustVerifyEmail::class))->toBeFalse();
});
