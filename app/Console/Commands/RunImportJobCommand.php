<?php

namespace App\Console\Commands;

use App\Services\Import\ImmediateImportRunner;
use App\Services\Import\ImportExecutionService;
use App\Services\Import\ImportProgressService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RunImportJobCommand extends Command
{
    protected $signature = 'import:run-job {jobId}';
    protected $description = 'Run a specific import job immediately in the background';

    public function handle(ImportExecutionService $executionService, ImportProgressService $progressService)
    {
        $jobId = (int) $this->argument('jobId');
        if ($jobId <= 0) {
            $this->error('Invalid Job ID');
            return 1;
        }

        $this->info("Starting import job #$jobId...");
        Log::info("RunImportJobCommand: Starting job #$jobId");

        try {
            $result = app(ImmediateImportRunner::class)->run($jobId, $progressService, $executionService);
            if ($result !== 'executed') {
                $this->warn("Job #$jobId did not start another execution: $result.");

                return 1;
            }

            $job = $progressService->findJob($jobId);
            if (($job->status ?? null) !== 'completed' || (int) ($job->total_success ?? 0) <= 0) {
                $this->error("Job #$jobId has not completed successfully: ".($job->status ?? 'missing'));

                return 1;
            }
            $this->info("Job #$jobId completed: {$job->total_success} rows imported.");

            return 0;
        } catch (\Throwable $e) {
            $this->error("Job #$jobId failed: " . $e->getMessage());
            Log::error("RunImportJobCommand: Job #$jobId failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }
}
