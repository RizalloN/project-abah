<?php

namespace App\Services\Import;

use App\Jobs\SyncImportedReportJob;
use App\Support\ReportDataSyncService;

class ImportCleanupService
{
    public function cleanupPaths(array $paths): void
    {
        foreach ($paths as $path) {
            if (!is_string($path) || $path === '') {
                continue;
            }

            if (file_exists($path)) {
                @unlink($path);
            }
        }
    }

    public function syncImportedJob(int $jobId, ?string $tableName = null, ?string $periodHint = null, ?string $source = null): void
    {
        app(ReportDataSyncService::class)->syncImportedJob($jobId, $tableName, $periodHint, $source);
    }

    public function dispatchImportedJobSync(int $jobId, ?string $tableName = null, ?string $periodHint = null, ?string $source = null): void
    {
        if ($jobId <= 0 && (!$tableName || $tableName === '')) {
            return;
        }

        SyncImportedReportJob::dispatch($jobId > 0 ? $jobId : null, $tableName, $periodHint, $source)
            ->onQueue('reports-low');
    }
}
