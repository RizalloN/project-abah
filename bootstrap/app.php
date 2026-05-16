<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\SecurityHeadersMiddleware::class,
        ]);

        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'release.session.lock' => \App\Http\Middleware\ReleaseSessionLockMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $renderDatabaseUnavailable = function (Request $request): Response {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Koneksi database tidak tersedia. Pastikan MySQL sedang berjalan lalu coba lagi.',
                ], 503);
            }

            return response()->view('errors.database-unavailable', [], 503);
        };

        $isConnectionRefused = static function (string $message): bool {
            $message = strtolower($message);

            return str_contains($message, 'sqlstate[hy000] [2002]')
                || str_contains($message, 'connection refused')
                || str_contains($message, 'actively refused');
        };

        $exceptions->render(function (QueryException $e, Request $request) use ($renderDatabaseUnavailable, $isConnectionRefused) {
            if ($isConnectionRefused($e->getMessage())) {
                return $renderDatabaseUnavailable($request);
            }

            return null;
        });

        $exceptions->render(function (\PDOException $e, Request $request) use ($renderDatabaseUnavailable, $isConnectionRefused) {
            if ($isConnectionRefused($e->getMessage())) {
                return $renderDatabaseUnavailable($request);
            }

            return null;
        });

        $exceptions->render(function (PostTooLargeException $e, Request $request) {
            $maxSize = ini_get('post_max_size') ?: 'unknown';
            $message = 'Ukuran upload melebihi batas server (' . $maxSize . ').';

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $message,
                ], 413);
            }

            return redirect()->back()->with('error', $message);
        });

        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419
                || !$e->getPrevious() instanceof TokenMismatchException
                || !$request->isMethod('POST')
                || !$request->is('login')) {
                return null;
            }

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'message' => 'Sesi login sudah kedaluwarsa. Silakan muat ulang halaman login.',
                ], 419);
            }

            if (auth()->check()) {
                return redirect()->route('dashboard');
            }

            return redirect()
                ->route('login')
                ->withInput($request->only('pn', 'remember'))
                ->with('status', 'Sesi login sudah kedaluwarsa. Silakan masuk kembali.');
        });

        $exceptions->render(function (MaxAttemptsExceededException $e, Request $request) {
            \Illuminate\Support\Facades\Log::error('Queue job melebihi batas percobaan maksimum.', [
                'job' => $e->job?->resolveName() ?? 'unknown',
                'message' => $e->getMessage(),
            ]);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Proses background gagal setelah percobaan maksimum. Silakan ulangi operasi.',
                ], 500);
            }

            return null;
        });

        $exceptions->render(function (QueryException $e, Request $request) use ($renderDatabaseUnavailable, $isConnectionRefused) {
            // Already handled connection refused above; this catches other DB errors for JSON clients
            if ($request->expectsJson() || $request->ajax()) {
                \Illuminate\Support\Facades\Log::error('Database query error pada request.', [
                    'sql' => $e->getSql(),
                    'message' => $e->getMessage(),
                    'url' => $request->fullUrl(),
                ]);
            }

            return null;
        });
    })->create();
