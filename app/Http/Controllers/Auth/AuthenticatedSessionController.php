<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            $request->authenticate();
        } catch (QueryException|PDOException $e) {
            $message = strtolower($e->getMessage());

            if (
                str_contains($message, 'sqlstate[hy000] [2002]')
                || str_contains($message, 'connection refused')
                || str_contains($message, 'actively refused')
            ) {
                return back()
                    ->withInput($request->only('pn', 'remember'))
                    ->withErrors([
                        'pn' => 'Login sementara tidak tersedia karena koneksi database belum aktif.',
                    ]);
            }

            throw $e;
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
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
