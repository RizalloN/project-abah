<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;

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

    if (!Schema::hasTable('login_histories')) {
        Schema::create('login_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id');
            $table->timestamp('login_at')->useCurrent();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
        });
    }
});

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

test('login preserves the intended route after session regeneration', function () {
    $user = User::factory()->create();

    $request = LoginRequest::create('/login', 'POST', [
        'pn' => $user->pn,
        'password' => 'password',
    ]);
    $request->setContainer(app());
    $request->setRedirector(app('redirect'));
    $request->setLaravelSession(app('session')->driver());
    $intendedUrl = url('/intended-after-login');
    $request->session()->put('url.intended', $intendedUrl);

    $response = app(AuthenticatedSessionController::class)->store($request);

    expect(Auth::check())->toBeTrue();
    expect($response->getStatusCode())->toBeIn([302, 303]);
    expect($response->getTargetUrl())->toBe($intendedUrl);
});

test('expired login token returns to login without page expired screen', function () {
    $request = Request::create('/login', 'POST', [
        'pn' => '90179583',
        'remember' => '1',
    ]);

    $response = app(ExceptionHandler::class)->render($request, new TokenMismatchException());

    expect($response->getStatusCode())->toBeIn([302, 303]);
    expect($response->getTargetUrl())->toBe(route('login'));
});

test('expired login token after successful authentication routes to dashboard', function () {
    $user = User::factory()->create();

    Auth::login($user);

    $request = Request::create('/login', 'POST');

    $response = app(ExceptionHandler::class)->render($request, new TokenMismatchException());

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

test('successful login records history to the database', function () {
    $user = User::factory()->create();

    $request = LoginRequest::create('/login', 'POST', [
        'pn' => $user->pn,
        'password' => 'password',
    ]);
    $request->setContainer(app());
    $request->setRedirector(app('redirect'));
    $request->setLaravelSession(app('session')->driver());

    app(AuthenticatedSessionController::class)->store($request);

    $this->assertDatabaseHas('login_histories', [
        'user_id' => $user->id,
    ]);
});

test('admin can retrieve user daily login history via JSON endpoint', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create();

    // Seed some login history records
    \Illuminate\Support\Facades\DB::table('login_histories')->insert([
        ['user_id' => $user->id, 'login_at' => now()->subDay(), 'ip_address' => '127.0.0.1'],
        ['user_id' => $user->id, 'login_at' => now(), 'ip_address' => '127.0.0.1'],
    ]);

    $this->actingAs($admin);

    $response = $this->get(route('user-management.login-history', $user));

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'user' => ['id', 'name', 'pn'],
        'history' => [
            '*' => ['date', 'count']
        ]
    ]);
});
