<?php

namespace App\Jobs;

use App\Jobs\SyncImportedReportJob;
use App\Services\Import\ImportProgressService;
use App\Services\Import\MySqlBulkLoadService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RunLoadDataJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 0;
    public int $tries = 1;

    public function __construct(
        public readonly int $jobId,
        public readonly string $stagingCsvPath,
        public readonly string $tableName,
        public readonly array $bulkColumns,
    ) {
        $this->queue = 'imports-high';
    }

    public function handle(ImportProgressService $progressService, MySqlBulkLoadService $bulkLoader): void
    {
        try {
            $progressService->markProcessing($this->jobId, [
                'status' => 'processing',
                'percent' => 50,
                'message' => 'Memuat staging CSV ke database via LOAD DATA LOCAL INFILE...',
            ]);

            if (!file_exists($this->stagingCsvPath)) {
                throw new \Exception('Staging CSV file not found: ' . $this->stagingCsvPath);
            }

            $totalSuccess = $bulkLoader->loadCsvIntoMysqlChunked(
                $this->stagingCsvPath,
                $this->tableName,
                $this->bulkColumns,
                function (int $processedLines, int $totalLines) use ($progressService): void {
                    $ratio = $totalLines > 0 ? min(1, $processedLines / $totalLines) : 1;
                    $percent = 50 + (int) floor($ratio * 48);
                    $progressService->cacheProgress($this->jobId, [
                        'status' => 'processing',
                        'percent' => min(98, $percent),
                        'message' => "Memuat ke database ({$processedLines}/{$totalLines} baris)...",
                    ]);
                },
                8000,
                0
            );

            $progressService->markCompleted(
                $this->jobId,
                $totalSuccess,
                0,
                $totalSuccess,
                [
                    'status' => 'completed',
                    'percent' => 100,
                    'message' => 'Import selesai.',
                    'total_success' => $totalSuccess,
                    'total_failed' => 0,
                ]
            );

            DB::table('import_jobs')->where('id', $this->jobId)->update([
                'status' => 'completed',
                'total_success' => $totalSuccess,
                'total_failed' => 0,
                'updated_at' => now(),
            ]);

            $jobParams = Cache::get("csv_import_params_{$this->jobId}", []);
            $syncPeriod = $jobParams['syncPeriod'] ?? $jobParams['sync_period'] ?? null;
            if ($totalSuccess > 0 && !empty($syncPeriod)) {
                try {
                    SyncImportedReportJob::dispatch(
                        $this->jobId,
                        $this->tableName,
                        $syncPeriod,
                        static::class
                    )->onQueue('imports-high');
                } catch (\Throwable $e) {
                    Log::warning('Post-import sync dispatch skipped after successful load: ' . $e->getMessage(), [
                        'job_id' => $this->jobId,
                        'table' => $this->tableName,
                        'period' => $syncPeriod,
                    ]);
                }
            }

            Log::info('Load data completed', [
                'job_id' => $this->jobId,
                'table' => $this->tableName,
                'rows_loaded' => $totalSuccess,
            ]);

        } catch (\Throwable $e) {
            Log::error('RunLoadDataJob failed', [
                'job_id' => $this->jobId,
                'table' => $this->tableName,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            $progressService->markFailed(
                $this->jobId,
                'Gagal memuat CSV ke database: ' . mb_substr($e->getMessage(), 0, 200)
            );

            throw $e;
        } finally {
            if (file_exists($this->stagingCsvPath)) {
                @unlink($this->stagingCsvPath);
            }
            Cache::forget("import_staging_csv:{$this->jobId}");
        }
    }
}
