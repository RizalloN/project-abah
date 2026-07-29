<?php

namespace App\Jobs\Middleware;

use App\Services\Import\ImportProgressService;
use Carbon\Carbon;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeferSnapshotJobsDuringImport
{
    private const STUCK_IMPORT_THRESHOLD_HOURS = 4;

    public function __construct(
        private readonly ?ImportProgressService $importProgressService = null,
        private readonly ?string $sourceTable = null
    ) {
    }

    public function handle(object $job, Closure $next): mixed
    {
        $importProgressService = $this->importProgressService ?? app(ImportProgressService::class);

        $hasActiveImports = $this->sourceTable !== null && trim($this->sourceTable) !== ''
            ? $importProgressService->hasActiveProcessingJobsForTable($this->sourceTable)
            : $importProgressService->hasActiveProcessingJobs();

        if ($hasActiveImports) {
            $stuckJob = $this->findStuckImportJob();

            if ($stuckJob !== null) {
                Log::warning('Escape hatch: Terminating stuck import job to unblock snapshot queue.', [
                    'stuck_job_id' => $stuckJob->id,
                    'stuck_duration_hours' => now()->diffInHours(Carbon::parse($stuckJob->updated_at)),
                ]);

                DB::table('import_jobs')
                    ->where('id', $stuckJob->id)
                    ->update(['status' => 'failed', 'updated_at' => now()]);

                return $next($job);
            }

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

    private function findStuckImportJob(): ?object
    {
        try {
            $cutoff = now()->subHours(self::STUCK_IMPORT_THRESHOLD_HOURS);

            return DB::table('import_jobs')
                ->whereIn('status', ['staging', 'processing'])
                ->where('updated_at', '<', $cutoff)
                ->orderByDesc('updated_at')
                ->first();
        } catch (\Throwable) {
            return null;
        }
    }
}
