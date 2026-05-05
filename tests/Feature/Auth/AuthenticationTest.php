<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

test('login screen can be rendered', function () {
    $response = app()->handle(Request::create('/login', 'GET'));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getContent())->toContain('Login');
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $request = LoginRequest::create('/login', 'POST', [
        'pn' => $user->pn,
        'password' => 'password',
    ]);
    $request->setContainer(app());
    $request->setRedirector(app('redirect'));
    $request->setLaravelSession(app('session')->driver());

    $response = app(AuthenticatedSessionController::class)->store($request);

    expect(Auth::check())->toBeTrue();
    expect($response->getStatusCode())->toBeIn([302, 303]);
    expect($response->getTargetUrl())->toBe(route('dashboard'));
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $request = LoginRequest::create('/login', 'POST', [
        'pn' => $user->pn,
        'password' => 'wrong-password',
    ]);
    $request->setContainer(app());
    $request->setRedirector(app('redirect'));
    $request->setLaravelSession(app('session')->driver());

    expect(fn () => app(AuthenticatedSessionController::class)->store($request))
        ->toThrow(\Illuminate\Validation\ValidationException::class);

    expect(Auth::check())->toBeFalse();
});

test('valid credentials can recover from a stale login lock', function () {
    $user = User::factory()->create([
        'pn' => '90179583',
        'role' => 'admin',
    ]);

    $request = LoginRequest::create('/login', 'POST', [
        'pn' => $user->pn,
        'password' => 'password',
    ], [], [], ['REMOTE_ADDR' => '127.0.0.1']);
    $request->setContainer(app());
    $request->setRedirector(app('redirect'));
    $request->setLaravelSession(app('session')->driver());

    RateLimiter::hit($request->throttleKey(), 900);
    RateLimiter::hit($request->throttleKey(), 900);
    RateLimiter::hit($request->throttleKey(), 900);
    RateLimiter::hit($request->throttleKey(), 900);
    RateLimiter::hit($request->throttleKey(), 900);

    $response = app(AuthenticatedSessionController::class)->store($request);

    expect(Auth::check())->toBeTrue();
    expect($response->getStatusCode())->toBeIn([302, 303]);
    expect(RateLimiter::tooManyAttempts($request->throttleKey(), 5))->toBeFalse();
});

test('users can logout', function () {
    $user = User::factory()->create();

    Auth::login($user);

    $request = Request::create('/logout', 'POST');
    $request->setLaravelSession(app('session')->driver());
    $request->setUserResolver(fn () => $user);

    $response = app(AuthenticatedSessionController::class)->destroy($request);

    expect(Auth::check())->toBeFalse();
    expect($response->getStatusCode())->toBeIn([302, 303]);
    expect($response->getTargetUrl())->toBe(url('/'));
});
