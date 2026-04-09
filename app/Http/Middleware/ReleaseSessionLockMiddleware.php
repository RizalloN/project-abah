<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ReleaseSessionLockMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->isMethodCacheable()) {
            return $next($request);
        }

        try {
            if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            } elseif ($request->hasSession()) {
                $request->session()->save();
            }
        } catch (Throwable) {
            // Keep request flow intact even if lock release fails.
        }

        return $next($request);
    }
}
