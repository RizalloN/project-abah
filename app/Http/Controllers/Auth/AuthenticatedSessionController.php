<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use PDOException;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        try {
            $this->ensureDatabaseIsReachable();
            $user = $request->authenticate();
        } catch (QueryException|PDOException $e) {
            if ($this->isDatabaseConnectionError($e)) {
                return back()
                    ->withInput($request->only('pn', 'remember'))
                    ->withErrors([
                        'pn' => 'Login sementara tidak tersedia karena koneksi database belum aktif.',
                    ]);
            }

            throw $e;
        }

        $this->startFreshAuthenticatedSession($request, $user);

        return redirect()->intended(route('dashboard'));
    }

    private function startFreshAuthenticatedSession(LoginRequest $request, User $user): void
    {
        Auth::guard('web')->login($user, $request->boolean('remember'));

        $request->session()->regenerate();
    }

    private function ensureDatabaseIsReachable(): void
    {
        DB::connection()->getPdo();
    }

    private function isDatabaseConnectionError(QueryException|PDOException $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'sqlstate[hy000] [2002]')
            || str_contains($message, 'connection refused')
            || str_contains($message, 'actively refused')
            || str_contains($message, 'server has gone away')
            || str_contains($message, 'php_network_getaddresses');
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
