<?php

namespace App\Jobs\Middleware;

use App\Support\SnapshotDirtyPeriodService;
use Closure;
use Illuminate\Support\Facades\Log;

/**
 * Drops a dirty-period job whose claim was superseded before it got to run.
 * Without this, every re-mark of a busy period left one more job releasing
 * itself on the shared lock until it exhausted its tries.
 */
class SkipSupersededDirtyClaim
{
    /**
     * @param  array<string, mixed>  $claim
     */
    public function __construct(private readonly array $claim)
    {
    }

    public function handle(object $job, Closure $next): mixed
    {
        if (app(SnapshotDirtyPeriodService::class)->isClaimCurrent($this->claim)) {
            return $next($job);
        }

        Log::info('Snapshot dirty job dilewati: klaim sudah digantikan.', [
            'source_table' => $this->claim['source_table'] ?? null,
            'period' => $this->claim['period_key'] ?? null,
            'attempts' => method_exists($job, 'attempts') ? (int) $job->attempts() : null,
        ]);

        if (method_exists($job, 'delete')) {
            $job->delete();
        }

        return null;
    }
}
