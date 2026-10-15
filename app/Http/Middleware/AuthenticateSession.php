<?php

namespace App\Http\Middleware;

use App\Support\AuthenticatedSessionContext;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Session\Middleware\AuthenticateSession as BaseAuthenticateSession;

class AuthenticateSession extends BaseAuthenticateSession
{
    public function handle($request, Closure $next)
    {
        if (! $request->hasSession() || ! $request->user()) {
            return $next($request);
        }

        $guard = $this->auth->guard();

        if ($guard->viaRemember()) {
            // Validate Laravel's password-bound recaller before establishing context.
            return parent::handle($request, function ($request) use ($next) {
                AuthenticatedSessionContext::store($request->session(), $request->user());

                return $next($request);
            });
        }

        // Only restored sessions carry a login identifier; transient authentication
        // (for example Auth::once) has no persisted session to validate.
        if (! $request->session()->has($guard->getName())) {
            return $next($request);
        }

        $stored = $request->session()->get(AuthenticatedSessionContext::KEY);
        if (! is_string($stored)
            || ! hash_equals(AuthenticatedSessionContext::fingerprint($request->user()), $stored)) {
            $this->logout($request);
        }

        return parent::handle($request, $next);
    }

    protected function logout($request)
    {
        $this->guard()->logoutCurrentDevice();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        throw new AuthenticationException(
            'Unauthenticated.', [$this->auth->getDefaultDriver()], $this->redirectTo($request)
        );
    }
}
