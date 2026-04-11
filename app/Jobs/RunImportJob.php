<?php

namespace App\Jobs;

use App\Services\Import\ImportExecutionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunImportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 0;

    public function __construct(public readonly int $jobId)
    {
    }

    public function handle(ImportExecutionService $executionService): void
    {
        $executionService->run($this->jobId);
    }
}
