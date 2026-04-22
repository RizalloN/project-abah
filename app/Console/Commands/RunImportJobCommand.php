<?php

namespace App\Console\Commands;

use App\Services\Import\ImportExecutionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RunImportJobCommand extends Command
{
    protected $signature = 'import:run-job {jobId}';
    protected $description = 'Run a specific import job immediately in the background';

    public function handle(ImportExecutionService $executionService)
    {
        $jobId = (int) $this->argument('jobId');
        if ($jobId <= 0) {
            $this->error('Invalid Job ID');
            return 1;
        }

        $this->info("Starting import job #$jobId...");
        Log::info("RunImportJobCommand: Starting job #$jobId");

        try {
            $executionService->run($jobId);
            $this->info("Job #$jobId finished.");
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
