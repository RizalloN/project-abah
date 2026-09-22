<?php

namespace App\Jobs\Middleware;

use App\Services\Import\ImportProgressService;
use Closure;
use Illuminate\Support\Facades\Log;

class DeferSnapshotJobsDuringImport
{
    public function __construct(
        private readonly ?ImportProgressService $importProgressService = null,
        private readonly ?string $sourceTable = null
    ) {
    }

    public function handle(object $job, Closure $next): mixed
    {
        $importProgressService = $this->importProgressService ?? app(ImportProgressService::class);

        if ($this->hasActiveImports($importProgressService)) {
            $attempts = method_exists($job, 'attempts') ? (int) $job->attempts() : 0;
            $delay = max(1, (int) config('import.snapshot.defer_seconds', 60));
            $jobName = method_exists($job, 'resolveName')
                ? (string) $job->resolveName()
                : $job::class;

            Log::info('Snapshot job ditunda karena import masih berjalan.', [
                'job' => $jobName,
                'source_table' => $this->sourceTable,
                'delay_seconds' => $delay,
                'attempts' => $attempts,
            ]);

            if (method_exists($job, 'release')) {
                $job->release($delay);
            }

            return null;
        }

        return $next($job);
    }

    private function hasActiveImports(ImportProgressService $importProgressService): bool
    {
        return $this->sourceTable !== null && trim($this->sourceTable) !== ''
            ? $importProgressService->hasActiveProcessingJobsForTable($this->sourceTable)
            : $importProgressService->hasActiveProcessingJobs();
    }
}
