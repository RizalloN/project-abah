<?php

namespace Tests\Feature;

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Middleware\AuthenticateSession;
use App\Http\Middleware\ReleaseSessionLockMiddleware;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Support\AuthenticatedSessionContext;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class AuthenticatedSessionSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array',
            'session.driver' => 'array',
        ]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('pn')->unique();
            $table->string('password');
            $table->string('role');
            $table->string('branch_scope')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('login_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id');
            $table->timestamp('login_at');
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
        });

        app('session.store')->start();
        Auth::forgetGuards();
    }

    public function test_controller_login_seeds_a_restorable_context_and_preserves_the_intended_redirect(): void
    {
        $user = $this->createUser();
        $session = $this->login($user, '/requested-report');

        $this->assertSame(AuthenticatedSessionContext::fingerprint($user), $session[AuthenticatedSessionContext::KEY]);
        $response = $this->protectedRequest($session);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($user->id, json_decode($response->getContent(), true)['id']);
        $this->assertNotSame($user, Auth::user(), 'The guard must reload the persisted user.');
    }

    #[DataProvider('responseFormats')]
    public function test_identifier_only_legacy_session_must_authenticate_again(bool $json, int $status): void
    {
        $user = $this->createUser();
        $guardKey = Auth::guard()->getName();

        $response = $this->protectedRequest([$guardKey => $user->id], json: $json);

        $this->assertSame($status, $response->getStatusCode());
        if (! $json) {
            $this->assertSame(route('login'), $response->headers->get('Location'));
        }
        $this->assertFalse(Auth::check());
        $this->assertFalse(app('session.store')->has($guardKey));
        $this->assertFalse(app('session.store')->has(AuthenticatedSessionContext::KEY));
    }

    public static function responseFormats(): array
    {
        return ['json' => [true, 401], 'browser' => [false, 302]];
    }

    public function test_a_deleted_users_session_cannot_inherit_a_reused_admin_identifier(): void
    {
        $original = $this->createUser();
        $session = $this->login($original);
        $original->delete();
        $replacement = $this->createUser(['pn' => 'replacement-admin', 'role' => 'admin']);
        $this->assertSame($original->id, $replacement->id, 'Exercise the actual reusable-ID model behavior.');

        $response = $this->protectedRequest($session);

        $this->assertSame(401, $response->getStatusCode());
        $this->assertFalse(Auth::check());
    }

    #[DataProvider('securityChanges')]
    public function test_persisted_sessions_are_revoked_after_a_security_attribute_changes(string $attribute, string $value): void
    {
        $user = $this->createUser(['role' => 'admin']);
        $session = $this->login($user);
        $user->update([$attribute => $value]);

        $response = $this->protectedRequest($session);

        $this->assertSame(401, $response->getStatusCode(), $attribute);
        $this->assertFalse(Auth::check());
    }

    public static function securityChanges(): array
    {
        return [
            'password reset' => ['password', 'replacement-password'],
            'admin demotion' => ['role', 'user'],
            'branch reassignment' => ['branch_scope', 'madiun'],
            'PN replacement' => ['pn', 'new-identity'],
        ];
    }

    public function test_a_valid_remember_cookie_establishes_a_context_for_subsequent_requests(): void
    {
        $user = $this->createUser();
        $cookie = $this->rememberCookie($user);

        $response = $this->protectedRequest([], $cookie);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue(Auth::guard()->viaRemember());
        $session = app('session.store')->all();
        $this->assertSame(AuthenticatedSessionContext::fingerprint($user), $session[AuthenticatedSessionContext::KEY]);
        $this->assertSame(200, $this->protectedRequest($session)->getStatusCode());
        $this->assertFalse(Auth::guard()->viaRemember());
    }

    #[DataProvider('invalidRecallers')]
    public function test_a_remember_cookie_must_include_a_valid_password_bound_third_segment(string $kind): void
    {
        $user = $this->createUser();
        $cookie = $this->rememberCookie($user);
        $segments = explode('|', $cookie);
        if ($kind === 'invalid token') {
            $segments[1] = 'unrecognized-token';
        } elseif ($kind === 'missing hash') {
            array_pop($segments);
        } elseif ($kind === 'empty hash') {
            $segments[2] = '';
        } else {
            $segments[2] = 'invalid-password-hash';
        }

        $response = $this->protectedRequest([], implode('|', $segments));

        $this->assertSame(401, $response->getStatusCode());
        $this->assertFalse(Auth::check());
        $this->assertFalse(app('session.store')->has(AuthenticatedSessionContext::KEY));
        if (in_array($kind, ['empty hash', 'invalid hash'], true)) {
            $this->assertTrue(Auth::guard()->viaRemember(), 'The token matched; the password segment must cause rejection.');
        }
    }

    public static function invalidRecallers(): array
    {
        return [
            ['invalid token'], ['missing hash'], ['empty hash'], ['invalid hash'],
        ];
    }

    public function test_a_password_reset_rejects_an_old_remember_cookie_even_if_its_token_still_matches(): void
    {
        $user = $this->createUser();
        $cookie = $this->rememberCookie($user);
        $token = $user->getRememberToken();
        $user->update(['password' => 'reset-password']);
        $this->assertSame($token, $user->fresh()->getRememberToken());

        $response = $this->protectedRequest([], $cookie);

        $this->assertSame(401, $response->getStatusCode());
        $this->assertFalse(Auth::check());
        $this->assertTrue(Auth::guard()->viaRemember(), 'The matching remember token alone must not authorize access.');
    }

    public function test_transient_authentication_without_a_persisted_identifier_remains_supported(): void
    {
        $user = $this->createUser();
        $request = $this->restoredRequest([]);
        $this->assertNotFalse(Auth::onceUsingId($user->id));

        $response = $this->dispatchProtected($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertFalse($request->session()->has(Auth::guard()->getName()));
        $this->assertFalse($request->session()->has(AuthenticatedSessionContext::KEY));
    }

    public function test_self_password_update_preserves_the_current_session_and_revokes_the_other_device(): void
    {
        $user = $this->createUser();
        $session = $this->login($user);
        $this->assertSame(200, $this->protectedRequest($session)->getStatusCode());
        $otherDevice = app('session.store')->all();

        $request = $this->restoredRequest($otherDevice, method: 'PUT', parameters: [
            'current_password' => 'original-password',
            'password' => 'Updated-password-123',
            'password_confirmation' => 'Updated-password-123',
        ]);
        $response = $this->dispatchProtected($request, fn (Request $request) => app(PasswordController::class)->update($request));
        $currentDevice = app('session.store')->all();

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('password-updated', $currentDevice['status']);
        $this->assertTrue(Hash::check('Updated-password-123', $user->fresh()->password));
        $this->assertSame(200, $this->protectedRequest($currentDevice)->getStatusCode());
        $this->assertSame(401, $this->protectedRequest($otherDevice)->getStatusCode());
    }

    public function test_every_authenticated_route_validates_the_context_before_releasing_the_session(): void
    {
        app(Kernel::class);
        $count = 0;
        foreach (Route::getRoutes() as $route) {
            $middleware = $route->gatherMiddleware();
            if (! collect($middleware)->contains(fn (string $name) => $name === 'auth' || str_starts_with($name, 'auth:'))) {
                continue;
            }

            $count++;
            $this->assertContains('auth.session', $middleware, $route->uri());
            $resolved = app('router')->gatherRouteMiddleware($route);
            $sessionPosition = array_search(AuthenticateSession::class, $resolved, true);
            $releasePosition = array_search(ReleaseSessionLockMiddleware::class, $resolved, true);
            $this->assertNotFalse($sessionPosition, $route->uri().' '.implode(', ', $resolved));
            if ($releasePosition !== false) {
                $this->assertLessThan($releasePosition, $sessionPosition, $route->uri());
            }
        }

        $this->assertGreaterThan(100, $count);
    }

    public function test_context_validation_adds_no_queries_and_uses_bounded_memory_after_guard_resolution(): void
    {
        $user = $this->createUser();
        $request = $this->restoredRequest($this->login($user));
        $this->assertNotSame($user, $request->user());
        $middleware = app(AuthenticateSession::class);
        $response = response()->json(['accepted' => true]);
        DB::enableQueryLog();
        DB::flushQueryLog();
        memory_reset_peak_usage();
        $memoryBefore = memory_get_usage();

        for ($index = 0; $index < 25; $index++) {
            $middleware->handle($request, fn () => $response);
        }
        $peakIncrease = memory_get_peak_usage() - $memoryBefore;
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame([], $queries);
        $this->assertLessThan(262_144, $peakIncrease);
        $this->assertSame(64, strlen($request->session()->get(AuthenticatedSessionContext::KEY)));
    }

    private function createUser(array $attributes = []): User
    {
        return User::create(array_replace([
            'name' => 'Session Security User',
            'pn' => 'session-user',
            'password' => 'original-password',
            'role' => 'user',
            'branch_scope' => 'area6',
        ], $attributes));
    }

    private function login(User $user, ?string $intended = null): array
    {
        $request = LoginRequest::create('/login', 'POST', [
            'pn' => $user->pn,
            'password' => 'original-password',
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->setLaravelSession(app('session.store'));
        if ($intended !== null) {
            $request->session()->put('url.intended', url($intended));
        }
        $response = app(AuthenticatedSessionController::class)->store($request);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame($intended === null ? route('dashboard') : url($intended), $response->getTargetUrl());

        return $request->session()->all();
    }

    private function rememberCookie(User $user): string
    {
        $user->setRememberToken('valid-remember-token');
        $user->save();

        return implode('|', [$user->id, $user->getRememberToken(), Auth::guard()->hashPasswordForCookie($user->password)]);
    }

    private function restoredRequest(array $session, ?string $recaller = null, string $method = 'GET', array $parameters = [], bool $json = true): Request
    {
        $recallerName = Auth::guard()->getRecallerName();
        Auth::forgetGuards();
        app('session.store')->flush();
        app('session.store')->put($session);
        $request = Request::create('/session-security-probe', $method, $parameters);
        $request->setLaravelSession(app('session.store'));
        $request->setUserResolver(fn () => Auth::user());
        if ($recaller !== null) {
            $request->cookies->set($recallerName, $recaller);
        }
        if ($json) {
            $request->headers->set('Accept', 'application/json');
        }
        app()->instance('request', $request);

        return $request;
    }

    private function protectedRequest(array $session, ?string $recaller = null, bool $json = true): Response
    {
        return $this->dispatchProtected($this->restoredRequest($session, $recaller, json: $json));
    }

    private function dispatchProtected(Request $request, ?callable $endpoint = null): Response
    {
        try {
            return app(Pipeline::class)->send($request)
                ->through([Authenticate::class, AuthenticateSession::class])
                ->then($endpoint ?? fn (Request $request) => response()->json(['id' => $request->user()->id]));
        } catch (AuthenticationException $exception) {
            return app(ExceptionHandler::class)->render($request, $exception);
        }
    }
}
