<?php

namespace App\Services\Import;

use App\Jobs\Middleware\SerializeImportByTable;
use App\Jobs\RunImportJob;
use Illuminate\Support\Facades\Cache;

class ImmediateImportRunner
{
    public function isRunning(int $jobId): bool
    {
        $lock = $this->executionLock($jobId);
        if (! $lock->get()) {
            return true;
        }
        $lock->release();

        return false;
    }

    public function run(int $jobId, ImportProgressService $progress, ImportExecutionService $execution): string
    {
        // Take ownership before XLSX staging, not only before database writes.
        $lock = $this->executionLock($jobId);
        if (! $lock->get()) {
            return 'already_running';
        }

        try {
            $started = false;
            (new SerializeImportByTable($progress))->handle(new RunImportJob($jobId),
                function () use ($execution, $jobId, &$started): void {
                    $started = true;
                    $execution->run($jobId);
                });
            if ($started) {
                return 'executed';
            }

            // A concurrent worker may claim the table after Force Start's
            // preflight. Restore a queue entry instead of losing the import.
            $this->requeueIfPending($jobId, $progress);

            return 'deferred';
        } finally {
            $lock->release();
        }
    }

    public function requeueIfPending(int $jobId, ImportProgressService $progress): void
    {
        $job = $progress->findJob($jobId);
        if (($job->status ?? null) !== 'queued') {
            return;
        }
        $table = data_get($progress->getJobState($jobId), 'params.table_name');
        $queue = (int) ($job->id_report ?? 0) === 8 || $table === 'daily_loan_dinamis'
            ? config('import.queue.daily_loan_queue', 'imports-daily-loan')
            : config('import.queue.import_queue', 'imports-high');
        RunImportJob::dispatch($jobId)->onConnection('database')->onQueue($queue);
    }

    private function executionLock(int $jobId)
    {
        return Cache::store(config('import.cache_store') ?: config('cache.default'))
            ->lock('import:immediate-execution:'.$jobId, 21600);
    }
}
