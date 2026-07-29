<?php

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    if (!Schema::hasTable('users')) {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('pn', 20)->unique();
            $table->string('email')->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('role', 20)->default('user');
            $table->rememberToken();
            $table->timestamps();
        });
    }
});

test('password confirmation routes are registered behind auth middleware', function () {
    expect(Route::has('password.confirm'))->toBeTrue();

    $route = Route::getRoutes()->getByName('password.confirm');

    expect($route)->not->toBeNull();
    expect($route->gatherMiddleware())->toContain('auth');
});

test('password confirmation authenticates the signed in PN even without email', function () {
    $user = User::factory()->create([
        'pn' => 'confirm' . random_int(10000, 99999),
        'email' => null,
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user)
        ->post('/confirm-password', ['password' => 'password'])
        ->assertRedirect();

    expect(session('auth.password_confirmed_at'))->toBeInt();
});
