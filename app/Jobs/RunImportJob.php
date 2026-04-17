<?php

namespace App\Jobs;

use App\Services\Import\ImportExecutionService;
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
}
