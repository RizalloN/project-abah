<?php

namespace App\Jobs;

use App\Jobs\Middleware\DeferSnapshotJobsDuringImport;
use App\Support\ReportDataSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ExecuteBatchedSnapshotJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use SnapshotJobRetryWindow;

    public int $timeout = 0;
    public int $tries = 40;
    public array $backoff = [60, 300];

    public function __construct(
        public string $batchKey,
        public array $requests = []
    ) {
    }

    public function handle(ReportDataSyncService $syncService): void
    {
        $requests = $this->compactRequests($this->requests);

        if (empty($requests)) {
            Log::warning('ExecuteBatchedSnapshotJob received empty requests list.', [
                'batch_key' => $this->batchKey,
            ]);

            return;
        }

        $startTime = microtime(true);
        $processed = 0;
        $failed = 0;
        $fatalErrors = [];

        Log::info('Processing batched snapshot requests.', [
            'batch_key' => $this->batchKey,
            'request_count' => count($requests),
            'original_request_count' => count($this->requests),
        ]);

        foreach ($requests as $request) {
            try {
                $tableName = trim((string) ($request['table_name'] ?? ''));
                if ($tableName === '') {
                    $failed++;
                    continue;
                }

                $syncService->syncImportedTable(
                    tableName: $tableName,
                    periodHint: $request['period_hint'] ?? null,
                    jobId: $request['job_id'] ?? null,
                    source: $request['source'] ?? static::class,
                    deleteId: null,
                    rebuildId: $request['rebuild_id'] ?? null
                );

                $processed++;

                Log::debug('Processed snapshot sync in batch.', [
                    'batch_key' => $this->batchKey,
                    'table_name' => $tableName,
                    'period_hint' => $request['period_hint'] ?? null,
                ]);
            } catch (Throwable $e) {
                $failed++;

                $isFatal = $this->isFatalError($e);
                if ($isFatal) {
                    $fatalErrors[] = [
                        'table' => $request['table_name'] ?? null,
                        'exception' => $e::class,
                        'message' => $e->getMessage(),
                    ];
                }

                Log::error('Failed to process snapshot sync in batch: ' . $e->getMessage(), [
                    'batch_key' => $this->batchKey,
                    'table_name' => $request['table_name'] ?? null,
                    'exception' => $e::class,
                    'is_fatal' => $isFatal,
                ]);
            }
        }

        $elapsed = max(microtime(true) - $startTime, 0.001);

        Log::info('Completed batched snapshot processing.', [
            'batch_key' => $this->batchKey,
            'total_requests' => count($requests),
            'original_request_count' => count($this->requests),
            'processed' => $processed,
            'failed' => $failed,
            'fatal_errors' => count($fatalErrors),
            'elapsed_seconds' => round($elapsed, 2),
        ]);

        if (!empty($fatalErrors) && $this->attempts() < 3) {
            Log::warning('Batch has fatal errors but will retry. Releasing job back to queue.', [
                'batch_key' => $this->batchKey,
                'fatal_count' => count($fatalErrors),
                'attempt' => $this->attempts(),
            ]);

            $this->release(30);
        }
    }

    public function middleware(): array
    {
        return [
            new DeferSnapshotJobsDuringImport(),
        ];
    }

    private function isFatalError(Throwable $e): bool
    {
        $class = $e::class;
        $message = strtolower($e->getMessage());

        $fatalClasses = [
            'OutOfMemoryError',
            'Error',
            'ParseError',
            'CompileError',
        ];

        foreach ($fatalClasses as $fatalClass) {
            if (str_contains($class, $fatalClass)) {
                return true;
            }
        }

        $fatalPatterns = [
            'out of memory',
            'allowed memory',
            'fatal error',
            'segmentation fault',
            'killed',
        ];

        foreach ($fatalPatterns as $pattern) {
            if (str_contains($message, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, mixed> $requests
     * @return array<int, array<string, mixed>>
     */
    private function compactRequests(array $requests): array
    {
        $compacted = [];

        foreach ($requests as $request) {
            if (!is_array($request)) {
                continue;
            }

            $tableName = strtolower(trim((string) ($request['table_name'] ?? '')));
            if ($tableName === '') {
                continue;
            }

            $periodHint = trim((string) ($request['period_hint'] ?? ''));
            $rebuildId = trim((string) ($request['rebuild_id'] ?? ''));
            $scope = $tableName . ':' . ($periodHint !== '' ? $periodHint : '__all__');

            $compacted[$scope] = $request;
            $compacted[$scope]['table_name'] = $tableName;
            $compacted[$scope]['period_hint'] = $periodHint !== '' ? $periodHint : null;
            $compacted[$scope]['job_id'] = isset($request['job_id']) && (int) $request['job_id'] > 0 ? (int) $request['job_id'] : null;
            $compacted[$scope]['source'] = $request['source'] ?? null;
            $compacted[$scope]['rebuild_id'] = $rebuildId !== '' ? $rebuildId : null;
        }

        return array_values($compacted);
    }
}
