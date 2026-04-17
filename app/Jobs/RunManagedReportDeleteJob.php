<?php

namespace App\Jobs;

use App\Http\Controllers\Import\ImportIndexController;
use App\Support\ReportDataSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunManagedReportDeleteJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    // No timeout: delete may process millions of rows across many batches
    public int $timeout = 0;

    // Single attempt only — retrying a partial delete would corrupt row counts and audit state
    public int $tries = 1;

    public function __construct(public readonly string $deleteId)
    {
        $this->onQueue('imports-high');
    }

    public function handle(
        ImportIndexController $controller,
        ReportDataSyncService $syncService
    ): void {
        try {
            $controller->runManagedReportDelete($this->deleteId, $syncService);
        } catch (\Throwable $e) {
            Log::error('RunManagedReportDeleteJob gagal tidak terduga.', [
                'delete_id' => $this->deleteId,
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            throw $e;
        }
    }
}
