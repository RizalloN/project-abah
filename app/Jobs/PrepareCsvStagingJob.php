<?php

namespace App\Jobs;

use App\Http\Controllers\Import\ImportFileController;
use App\Services\Import\ImportProgressService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PrepareCsvStagingJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 0;
    public int $tries = 1;

    public function __construct(public readonly int $jobId)
    {
        $this->queue = 'imports-high';
    }

    public function handle(ImportProgressService $progressService, ImportFileController $controller): void
    {
        try {
            $progressService->markStaging($this->jobId);

            $params = Cache::get("csv_import_params_{$this->jobId}");
            if (!$params) {
                throw new \Exception('Import params not found in cache for job ' . $this->jobId);
            }

            $stagingCsvPath = $controller->prepareCsvStaging(
                $this->jobId,
                $params,
                $progressService
            );

            Cache::put("import_staging_csv:{$this->jobId}", $stagingCsvPath, now()->addHours(2));

            $params = Cache::get("csv_import_params_{$this->jobId}", $params);
            $tableName = (string) ($params['tableName'] ?? $params['table_name'] ?? '');
            $bulkColumns = (array) ($params['bulkColumns'] ?? $params['bulk_columns'] ?? []);

            Log::info('CSV staging prepared', [
                'job_id' => $this->jobId,
                'staging_csv' => $stagingCsvPath,
                'table' => $tableName ?: 'unknown',
            ]);

            RunLoadDataJob::dispatch(
                $this->jobId,
                $stagingCsvPath,
                $tableName,
                $bulkColumns
            )->onQueue('imports-high');

        } catch (\Throwable $e) {
            Log::error('PrepareCsvStagingJob failed', [
                'job_id' => $this->jobId,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            $progressService->markFailed(
                $this->jobId,
                'Gagal menyiapkan staging CSV: ' . mb_substr($e->getMessage(), 0, 200)
            );

            throw $e;
        }
    }
}
