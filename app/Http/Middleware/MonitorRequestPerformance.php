<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class MonitorRequestPerformance
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('performance.request_monitoring.enabled', true)) {
            return $next($request);
        }

        $startedAt = hrtime(true);
        $requestId = (string) Str::uuid();
        $queryCount = 0;
        $queryTimeMs = 0.0;

        DB::listen(function (QueryExecuted $query) use (&$queryCount, &$queryTimeMs): void {
            $queryCount++;
            $queryTimeMs += (float) $query->time;
        });

        try {
            $response = $next($request);
            $response->headers->set('X-Request-ID', $requestId);

            return $response;
        } finally {
            $durationMs = (hrtime(true) - $startedAt) / 1_000_000;
            $slowRequestMs = max(100, (int) config('performance.request_monitoring.slow_request_ms', 1000));
            $slowQueryTotalMs = max(100, (int) config('performance.request_monitoring.slow_query_total_ms', 500));

            if ($durationMs >= $slowRequestMs || $queryTimeMs >= $slowQueryTotalMs) {
                Log::warning('Slow web request detected.', [
                    'request_id' => $requestId,
                    'method' => $request->method(),
                    'route' => $request->route()?->getName(),
                    'path' => $request->path(),
                    'status' => isset($response) ? $response->getStatusCode() : 500,
                    'duration_ms' => round($durationMs, 2),
                    'query_count' => $queryCount,
                    'query_time_ms' => round($queryTimeMs, 2),
                    'memory_peak_mb' => round(memory_get_peak_usage(true) / 1048576, 2),
                    'user_id' => $request->user()?->getAuthIdentifier(),
                ]);
            }
        }
    }
}
