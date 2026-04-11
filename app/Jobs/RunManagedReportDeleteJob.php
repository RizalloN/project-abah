<?php

namespace App\Jobs;

use App\Http\Controllers\Import\ImportIndexController;
use App\Support\ReportDataSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunManagedReportDeleteJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 0;

    public function __construct(public readonly string $deleteId)
    {
    }

    public function handle(
        ImportIndexController $controller,
        ReportDataSyncService $syncService
    ): void {
        $controller->runManagedReportDelete($this->deleteId, $syncService);
    }
}
