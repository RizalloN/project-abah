<?php

namespace App\Jobs;

use App\Support\ReportDataSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncImportedReportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 0;

    public function __construct(
        public ?int $jobId = null,
        public ?string $tableName = null,
        public ?string $periodHint = null,
        public ?string $source = null
    ) {
    }

    public function handle(ReportDataSyncService $syncService): void
    {
        if ($this->jobId !== null && $this->jobId > 0) {
            $syncService->syncImportedJob(
                $this->jobId,
                $this->tableName,
                $this->periodHint,
                $this->source ?? static::class
            );

            return;
        }

        if ($this->tableName !== null && $this->tableName !== '') {
            $syncService->syncImportedTable(
                $this->tableName,
                $this->periodHint,
                null,
                $this->source ?? static::class
            );
        }
    }
}
