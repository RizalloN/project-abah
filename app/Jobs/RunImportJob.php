<?php

namespace App\Jobs;

use App\Services\Import\ImportExecutionService;
use App\Services\Import\ImportProgressService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunImportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    // No timeout: import may process millions of rows; stale detection is handled by JobHealthService
    public int $timeout = 0;

    // Single attempt only — retrying a partial import would corrupt row counts and snapshot state
    public int $tries = 1;

    public function __construct(public readonly int $jobId)
    {
    }

    public function handle(ImportExecutionService $executionService): void
    {
        try {
            $executionService->run($this->jobId);
        } catch (\Throwable $e) {
            Log::error('RunImportJob gagal tidak terduga.', [
                'job_id' => $this->jobId,
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        try {
            $progressService = app(ImportProgressService::class);
            $job = $progressService->findJob($this->jobId);
            if (!$job || in_array((string) ($job->status ?? ''), ['completed', 'failed', 'failed_partial', 'terminated'], true)) {
                return;
            }

            $success = (int) ($job->total_success ?? 0);
            $failed = (int) ($job->total_failed ?? 0);
            $progressService->markFailed(
                $this->jobId,
                'Worker queue berhenti sebelum import selesai: ' . $exception->getMessage(),
                $success,
                $failed,
                $success > 0 || $failed > 0 ? 'failed_partial' : 'failed'
            );
        } catch (\Throwable $markError) {
            Log::error('RunImportJob gagal menandai import sebagai failed.', [
                'job_id' => $this->jobId,
                'exception' => $markError::class,
                'message' => $markError->getMessage(),
            ]);
        }
    }
}
