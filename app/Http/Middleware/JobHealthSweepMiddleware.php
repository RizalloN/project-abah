<?php

namespace App\Http\Middleware;

use App\Services\JobHealthService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class JobHealthSweepMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            app(JobHealthService::class)->sweepIfDue();
        } catch (\Throwable $e) {
            Log::warning('Job health sweep gagal dijalankan.', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }

        return $next($request);
    }
}
