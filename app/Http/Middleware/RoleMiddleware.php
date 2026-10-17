<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (!Auth::check()) {
            return redirect('/login');
        }

        $user = Auth::user();
        $allowed = array_filter($roles, static fn (string $role): bool => match ($role) {
            'superadmin' => $user->isSuperAdmin(),
            'admin' => $user->isAdmin(),
            default => $user->role === $role,
        });

        if ($allowed === []) {
            abort(403, 'Akses ditolak');
        }

        return $next($request);
    }
}
