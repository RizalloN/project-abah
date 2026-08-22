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
                try {
                    $status = $importProgressService->getStatusPayload((int) $stuckJob->id);
                    $resolvedStatus = strtolower(trim((string) ($status['status'] ?? '')));

                    if (in_array($resolvedStatus, ['staging', 'processing', 'queued'], true)) {
                        $success = max(0, (int) ($status['total_success'] ?? $stuckJob->total_success ?? 0));
                        $failed = max(0, (int) ($status['total_failed'] ?? $stuckJob->total_failed ?? 0));

                        $importProgressService->markFailed(
                            (int) $stuckJob->id,
                            'Job import tidak memiliki progress lebih dari 4 jam dan telah dihentikan.',
                            $success,
                            $failed,
                            $success > 0 || $failed > 0 ? 'failed_partial' : 'failed'
                        );
                        $resolvedStatus = $success > 0 || $failed > 0 ? 'failed_partial' : 'failed';
                    }

                    Log::warning('Stale import job reconciled before snapshot queue resumed.', [
                        'stuck_job_id' => $stuckJob->id,
                        'resolved_status' => $resolvedStatus,
                        'stuck_duration_hours' => Carbon::parse($stuckJob->updated_at)->diffInHours(now(), true),
                    ]);

                    return $next($job);
                } catch (\Throwable $e) {
                    Log::warning('Stale import status could not be verified; snapshot job remains deferred.', [
                        'stuck_job_id' => $stuckJob->id,
                        'message' => $e->getMessage(),
                    ]);

                    if (method_exists($job, 'release')) {
                        $job->release(max(1, (int) config('import.snapshot.defer_seconds', 60)));
                    }

                    return null;
                }
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
