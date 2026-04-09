<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Symfony\Component\HttpFoundation\Response;

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
    })->create();
