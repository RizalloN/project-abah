<?php

namespace App\Jobs\Middleware;

use App\Services\Import\ImportProgressService;
use Closure;
use Illuminate\Support\Facades\Log;

class DeferSnapshotJobsDuringImport
{
    public function __construct(
        private readonly ?ImportProgressService $importProgressService = null
    ) {
    }

    public function handle(object $job, Closure $next): mixed
    {
        $importProgressService = $this->importProgressService ?? app(ImportProgressService::class);

        if ($importProgressService->hasActiveProcessingJobs()) {
            $delay = max(1, (int) config('import.snapshot.defer_seconds', 60));
            $jobName = method_exists($job, 'resolveName')
                ? (string) $job->resolveName()
                : $job::class;

            Log::info('Snapshot job ditunda karena import masih berjalan.', [
                'job' => $jobName,
                'delay_seconds' => $delay,
            ]);

            if (method_exists($job, 'release')) {
                $job->release($delay);
            }

            return null;
        }

        return $next($job);
    }
}
