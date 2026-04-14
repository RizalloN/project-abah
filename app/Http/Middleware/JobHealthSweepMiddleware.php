<?php

namespace App\Http\Middleware;

use App\Services\JobHealthService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class JobHealthSweepMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            app(JobHealthService::class)->sweepIfDue();
        } catch (\Throwable) {
        }

        return $next($request);
    }
}
