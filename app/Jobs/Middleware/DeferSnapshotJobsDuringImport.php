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
        private readonly ?ImportProgressService $importProgressService = null
    ) {
    }

    public function handle(object $job, Closure $next): mixed
    {
        $importProgressService = $this->importProgressService ?? app(ImportProgressService::class);

        if ($importProgressService->hasActiveProcessingJobs()) {
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

    private function findStuckImportJob(): ?object
    {
        try {
            $cutoff = now()->subHours(self::STUCK_IMPORT_THRESHOLD_HOURS);

            return DB::table('import_jobs')
                ->where('status', 'processing')
                ->where('updated_at', '<', $cutoff)
                ->orderByDesc('updated_at')
                ->first();
        } catch (\Throwable) {
            return null;
        }
    }
}
