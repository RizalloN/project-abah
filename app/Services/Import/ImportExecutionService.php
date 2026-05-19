<?php

namespace App\Services\Import;

use App\Http\Controllers\Import\ImportExcelController;
use App\Http\Controllers\Import\ImportReportPhController;
use App\Http\Controllers\Import\ImportSimpananMultiPnCsvController;
use App\Jobs\RunImportJob;
use App\Jobs\SyncImportedReportJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Carbon;

class ImportExecutionService
{
    private const IMPORT_QUEUE = 'imports-high';
    private const DAILY_LOAN_IMPORT_QUEUE = 'imports-high';
    private const DAILY_LOAN_REPORT_ID = 8;
    private const DISPATCHED_KEY_PREFIX = 'import_excel_dispatched_job_';
    private const DISPATCHED_TTL_HOURS = 6;
    private const STALE_QUEUED_MINUTES = 10;
    private const TERMINATION_EXCEPTION_PREFIX = 'import_job_terminated_by_request:';
    private const ZERO_PROGRESS_RECOVERABLE_TABLES = [
        'daily_loan_dinamis',
        'ssa_simpanan',
        'ssa_pinjaman',
        'simpanan_multipn',
        'hourly_dpk',
        'lw325_ph',
    ];

    public function __construct(
        private readonly ImportProgressService $progressService,
    ) {
    }

    public function dispatch(int $jobId, ?string $queueMessage = null): bool
    {
        if ($jobId <= 0) {
            return false;
        }

        $job = $this->progressService->findJob($jobId);
        if ($job && strtolower((string) ($job->status ?? '')) === 'processing') {
            $job = $this->recoverZeroProgressProcessingJob($jobId, $job);
        }

        if (!$job || in_array($job->status, ['processing', 'completed', 'failed', 'failed_partial', 'terminated'], true)) {
            $this->releaseDispatchMarker($jobId);
            return false;
        }

        $cache = $this->importCache();
        $lock = $cache->lock('import_excel_dispatch_job_' . $jobId, 30);

        try {
            if (!$lock->get()) {
                return false;
            }

            // Re-fetch inside lock to guard against concurrent dispatch races
            $job = $this->progressService->findJob($jobId);
            if ($job && strtolower((string) ($job->status ?? '')) === 'processing') {
                $job = $this->recoverZeroProgressProcessingJob($jobId, $job);
            }

            if (!$job || in_array($job->status, ['processing', 'completed', 'failed', 'failed_partial', 'terminated'], true)) {
                $this->releaseDispatchMarker($jobId);
                return false;
            }

            if ($cache->has($this->dispatchedKey($jobId)) && !$this->shouldRedispatchQueuedJob($job)) {
                return false;
            }

            if ($this->isSimpananMultiPnCsvStreamJob($jobId, $job)) {
                $this->progressService->cleanupQueuedImportJobRowsForJob($jobId);
                $this->releaseDispatchMarker($jobId);

                Log::info('Simpanan MultiPN CSV import kept on dedicated stream executor; generic queue dispatch skipped.', [
                    'job_id' => $jobId,
                ]);

                return false;
            }

            $queue = $this->resolveImportQueue($job);

            $this->progressService->markQueued($jobId, [
                'status' => 'queued',
                'phase' => 'polars',
                'mode' => 'polars',
                'percent' => 5,
                'message' => $queueMessage ?: 'Fase Polars dimulai. Menyiapkan import fresh.',
                'processed_rows' => 0,
                'total_success' => (int) ($job->total_success ?? 0),
                'total_failed' => (int) ($job->total_failed ?? 0),
                'total_rows' => (int) ($job->total_files ?? 0),
                'queue' => $queue,
            ]);

            $this->progressService->cleanupQueuedImportJobRowsForJob($jobId);
            $cache->put($this->dispatchedKey($jobId), true, now()->addHours(self::DISPATCHED_TTL_HOURS));
            dispatch(
                (new RunImportJob($jobId))
                    ->onConnection('database')
                    ->onQueue($queue)
            );
            return true;
        } catch (\Throwable $e) {
            $this->releaseDispatchMarker($jobId);

            $this->progressService->markFailed(
                $jobId,
                'Gagal menjadwalkan job import ke queue: ' . $e->getMessage()
            );

            return false;
        } finally {
            optional($lock)->release();
        }
    }

    private function resolveImportQueue(object $job): string
    {
        $state = $this->progressService->getJobState((int) $job->id);
        $tableName = $this->resolveExpectedTableName($job);
        if ($tableName === '') {
            $tableName = strtolower(trim((string) ($state['params']['table_name'] ?? '')));
        }
        $reportId = (int) ($job->id_report ?? 0);

        if ($reportId === self::DAILY_LOAN_REPORT_ID || $tableName === 'daily_loan_dinamis') {
            return self::DAILY_LOAN_IMPORT_QUEUE;
        }

        return self::IMPORT_QUEUE;
    }

    private function resolvePostImportSyncQueue(int $jobId, array $params = []): string
    {
        $tableName = strtolower(trim((string) ($params['table_name'] ?? '')));
        $job = $this->progressService->findJob($jobId);

        if ((int) ($job->id_report ?? 0) === self::DAILY_LOAN_REPORT_ID || $tableName === 'daily_loan_dinamis') {
            return self::DAILY_LOAN_IMPORT_QUEUE;
        }

        $queue = trim((string) config('queue.report_queue', 'default'));

        return $queue !== '' ? $queue : 'default';
    }

    private function dispatchPostImportSyncForTerminalJob(int $jobId, object $job, array $params): void
    {
        $status = (string) ($job->status ?? '');
        $hasImportedRows = (int) ($job->total_success ?? 0) > 0;

        if ($status !== 'completed' && !($status === 'failed_partial' && $hasImportedRows)) {
            return;
        }

        try {
            app(ImportCleanupService::class)->dispatchImportedJobSync(
                $jobId,
                null,
                null,
                static::class,
                $this->resolvePostImportSyncQueue($jobId, $params)
            );
        } catch (\Throwable $syncError) {
            Log::warning('Import already terminal but failed to dispatch post-import sync: ' . $syncError->getMessage(), [
                'job_id' => $jobId,
                'status' => $status,
            ]);
        }
    }

    public function streamStatus(Request $request, int $jobId, bool $startInlineImmediately = false): StreamedResponse
    {
        return response()->stream(function () use ($request, $jobId, $startInlineImmediately) {
            $send = static function (string $event, array $data): void {
                echo "event: {$event}\n";
                echo 'data: ' . json_encode($data) . "\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            };

            $lastPayloadHash = null;
            $startedAt = time();
            $maxSeconds = 7200;
            $inlineFallbackAttempted = false;

            if ($startInlineImmediately) {
                $inlineFallbackAttempted = true;
                $this->progressService->cleanupQueuedImportJobRowsForJob($jobId);
                $this->run($jobId, function (string $event, array $streamPayload) use ($send): void {
                    $send($event, $streamPayload);
                }, 'inline_direct');
            }

            while (true) {
                if ($request->isMethod('GET') && function_exists('connection_aborted') && connection_aborted()) {
                    break;
                }

                $payload = $this->progressService->getStatusPayload($jobId);
                $hash = md5(json_encode($payload));
                $isStaleQueue = ($payload['status'] ?? '') === 'queued' && !empty($payload['is_stale_queue']);

                if ($this->shouldRunInlineFallback($payload, $startedAt, $inlineFallbackAttempted)) {
                    $inlineFallbackAttempted = true;
                    $inlineFallbackMessage = $this->resolveInlineFallbackMessage($jobId, $payload);
                    $send('progress', [
                        'status' => 'processing',
                        'phase' => (string) ($payload['phase'] ?? 'polars'),
                        'mode' => (string) ($payload['mode'] ?? 'polars'),
                        'percent' => max(6, (int) ($payload['percent'] ?? 5)),
                        'message' => $inlineFallbackMessage,
                        'processed_rows' => (int) ($payload['processed_rows'] ?? 0),
                        'total_rows' => (int) ($payload['total_rows'] ?? 0),
                        'total_success' => (int) ($payload['total_success'] ?? 0),
                        'total_failed' => (int) ($payload['total_failed'] ?? 0),
                    ]);
                    $this->progressService->cleanupQueuedImportJobRowsForJob($jobId);
                    $lastPayloadHash = null;

                    $this->run($jobId, function (string $event, array $streamPayload) use ($send): void {
                        $send($event, $streamPayload);
                    }, 'inline_fallback');

                    continue;
                }

                if ($isStaleQueue) {
                    if ($hash !== $lastPayloadHash) {
                        $lastPayloadHash = $hash;
                        $send('progress', $payload);
                    }

                    $timeoutPayload = [
                        'job_id' => $jobId,
                        'message' => 'Job import terlalu lama berada di antrian. Silakan ulangi proses import.',
                        'status' => 'timeout',
                        'queued_for_seconds' => (int) ($payload['queued_for_seconds'] ?? 0),
                    ];

                    $this->progressService->markFailed(
                        $jobId,
                        $timeoutPayload['message'],
                        (int) ($payload['total_success'] ?? 0),
                        (int) ($payload['total_failed'] ?? 0),
                        'failed'
                    );

                    $send('error', $timeoutPayload);
                    break;
                }

                if ($hash !== $lastPayloadHash) {
                    $lastPayloadHash = $hash;

                    if (($payload['status'] ?? '') === 'error') {
                        $send('error', $payload);
                        break;
                    }

                    $send('progress', $payload);

                    if (in_array($payload['status'] ?? '', ['completed', 'failed', 'failed_partial', 'terminated'], true)) {
                        $event = ($payload['status'] ?? '') === 'completed' ? 'complete' : 'error';
                        $send($event, $payload);
                        break;
                    }
                } else {
                    echo ": keepalive\n\n";
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }

                if ((time() - $startedAt) >= $maxSeconds) {
                    $send('error', [
                        'job_id' => $jobId,
                        'message' => 'Streaming progress melebihi batas waktu 2 jam.',
                        'status' => 'timeout',
                    ]);
                    break;
                }

                usleep(500000);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-store',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ]);
    }

    public function run(int $jobId, ?callable $streamSend = null, string $executionSource = 'worker'): void
    {
        $this->progressService->purgeStaleProcessingJobs();

        $job = $this->progressService->findJob($jobId);
        if ($this->progressService->isTerminationRequested($jobId)) {
            $this->terminateRequestedJob($jobId);
            $this->releaseDispatchMarker($jobId);
            return;
        }

        if (!$job) {
            $this->releaseDispatchMarker($jobId);
            return;
        }

        if (in_array($job->status, ['completed', 'failed', 'failed_partial', 'terminated'], true)) {
            $this->progressService->cleanupQueuedImportJobRowsForJob($jobId);
            $this->releaseDispatchMarker($jobId);
            return;
        }

        if (strtolower((string) ($job->status ?? '')) === 'processing') {
            $job = $this->recoverZeroProgressProcessingJob($jobId, $job);
            if (!$job || strtolower((string) ($job->status ?? '')) === 'processing') {
                return;
            }
        }

        $state = $this->progressService->getJobState($jobId);
        $params = (array) ($state['params'] ?? []);
        $headers = array_values((array) ($state['headers'] ?? []));
        $tableName = strtolower(trim((string) ($params['table_name'] ?? '')));
        $mismatchMessage = $this->resolveJobStateMismatchMessage($job, $params);
        if ($mismatchMessage !== null) {
            Log::warning('ImportExecutionService::run() state job tidak cocok dengan record job', [
                'job_id' => $jobId,
                'message' => $mismatchMessage,
                'params_table_name' => $params['table_name'] ?? null,
                'params_file_path' => $params['file_path'] ?? null,
                'job_file_name' => $job->file_name ?? null,
            ]);
            $this->progressService->cleanupQueuedImportJobRowsForJob($jobId);
            $this->progressService->markFailed($jobId, $mismatchMessage);
            $this->releaseDispatchMarker($jobId);
            return;
        }

        if ($this->isSimpananMultiPnCsvStreamJob($jobId, $job, $params)) {
            $this->progressService->cleanupQueuedImportJobRowsForJob($jobId);
            $this->releaseDispatchMarker($jobId);

            Log::warning('Generic import worker skipped Simpanan MultiPN CSV job because it must run through the dedicated stream executor.', [
                'job_id' => $jobId,
                'table_name' => $tableName,
            ]);

            return;
        }

        $requiresPreparedStaging = in_array($tableName, ['lw321_npd', 'lw321_npdd'], true)
            && empty($params['staged_csv_path']);

        // ── OPTIMIZATION: Initialize job jika belum sepenuhnya ready ──────
        // Deteksi header dan staging CSV dilakukan ASYNC (dalam job execution)
        // Berlaku untuk SEMUA table: Simpanan, Pinjaman, Daily Loan, dll
        if (
            $requiresPreparedStaging
            || (
                (
                    empty($headers)
                    || !array_key_exists('header_index', $params)
                    || $params['header_index'] === null
                )
                && !empty($params['file_path'])
            )
        ) {
            $controllerClass = $this->resolveControllerClass($job);
            /** @var ImportExcelController $controller */
            $controller = app($controllerClass);

            try {
                $initResult = $controller->initializeQueuedImportJobForExecution($jobId);
                
                if (!$initResult) {
                    Log::error('ImportExecutionService::run() initialization gagal', [
                        'job_id' => $jobId,
                        'table_name' => $params['table_name'] ?? 'unknown',
                        'file_path' => $params['file_path'] ?? 'unknown',
                    ]);

                    $errorMsg = 'Gagal menginisialisasi import job. '
                        . 'Kemungkinan: file tidak ditemukan, format header tidak sesuai, atau akses file ditolak. '
                        . 'Silakan cek log untuk detail. (Job ID: ' . $jobId . ')';
                    
                    $this->progressService->markFailed($jobId, $errorMsg);
                    $this->releaseDispatchMarker($jobId);
                    return;
                }
            } catch (\Throwable $e) {
                Log::error('ImportExecutionService::run() initialization exception', [
                    'job_id' => $jobId,
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                $errorMsg = 'Initialization error: ' . $e->getMessage() . ' (Job ID: ' . $jobId . ')';
                $this->progressService->markFailed($jobId, $errorMsg);
                $this->releaseDispatchMarker($jobId);
                return;
            }

            // Refresh state setelah initialization
            $state = $this->progressService->getJobState($jobId);
            $params = (array) ($state['params'] ?? []);
            $headers = array_values((array) ($state['headers'] ?? []));
            $mismatchMessage = $this->resolveJobStateMismatchMessage($job, $params);
            if ($mismatchMessage !== null) {
                Log::warning('ImportExecutionService::run() state job tidak cocok setelah initialization', [
                    'job_id' => $jobId,
                    'message' => $mismatchMessage,
                    'params_table_name' => $params['table_name'] ?? null,
                    'params_file_path' => $params['file_path'] ?? null,
                    'job_file_name' => $job->file_name ?? null,
                ]);
                $this->progressService->cleanupQueuedImportJobRowsForJob($jobId);
                $this->progressService->markFailed($jobId, $mismatchMessage);
                $this->releaseDispatchMarker($jobId);
                return;
            }
        }

        if ($params === [] || $headers === []) {
            Log::error('ImportExecutionService::run() state tidak lengkap setelah initialization', [
                'job_id' => $jobId,
                'has_params' => !empty($params),
                'has_headers' => !empty($headers),
            ]);
            $this->progressService->markFailed($jobId, 'State import job hilang atau tidak valid. Silakan ulangi import dari awal.');
            $this->releaseDispatchMarker($jobId);
            return;
        }

        $lock = $this->importCache()->lock('import_excel_execute_job_' . $jobId, 7200);
        if (!$lock->get()) {
            $job = $this->progressService->findJob($jobId);
            if ($job && $this->shouldRedispatchQueuedJob($job)) {
                $this->progressService->markFailed(
                    $jobId,
                    'Job import terkunci oleh proses lain terlalu lama. Silakan ulangi proses import.',
                    (int) ($job->total_success ?? 0),
                    (int) ($job->total_failed ?? 0),
                    'failed'
                );
                $this->releaseDispatchMarker($jobId);
            }

            return;
        }

        try {
            $this->progressService->markProcessing($jobId, [
                'status' => 'processing',
                'phase' => 'polars',
                'mode' => 'polars',
                'percent' => 8,
                'message' => match ($executionSource) {
                    'inline_direct' => 'Import dijalankan langsung dari request ini.',
                    'inline_fallback' => 'Worker queue belum aktif. Fase Polars dijalankan langsung dari request ini.',
                    default => 'Worker queue masuk fase Polars.',
                },
                'processed_rows' => 0,
                'total_rows' => (int) ($params['total_rows'] ?? 0),
            ]);

            $controllerClass = $this->resolveControllerClass($job);
            /** @var ImportExcelController $controller */
            $controller = app($controllerClass);
            $result = $controller->executeQueuedImport([
                'job_id' => $jobId,
                'params' => $params,
                'headers' => $headers,
            ], function (string $event, array $payload) use ($jobId, $streamSend): void {
                if ($this->progressService->isTerminationRequested($jobId)) {
                    throw new \RuntimeException(self::TERMINATION_EXCEPTION_PREFIX . $jobId);
                }
                $status = match ($event) {
                    'complete' => 'completed',
                    'error' => 'failed',
                    default => 'processing',
                };

                $cachedPayload = $this->progressService->cacheProgress($jobId, array_merge($payload, ['status' => $status]));

                if ($streamSend !== null) {
                    $streamSend($event, $cachedPayload);
                }

                if ($this->progressService->isTerminationRequested($jobId)) {
                    throw new \RuntimeException(self::TERMINATION_EXCEPTION_PREFIX . $jobId);
                }
            });

            $job = $this->progressService->findJob($jobId);
            if ($job && in_array($job->status, ['completed', 'failed', 'failed_partial', 'terminated'], true)) {
                $this->dispatchPostImportSyncForTerminalJob($jobId, $job, $params);
                $this->releaseDispatchMarker($jobId);
                return;
            }

            $status = (string) ($result['status'] ?? 'failed');
            if ($status === 'completed') {
                $this->progressService->markCompleted(
                    $jobId,
                    (int) ($result['total_success'] ?? 0),
                    (int) ($result['total_failed'] ?? 0),
                    (int) ($result['total_rows'] ?? 0),
                    [
                        'status' => 'completed',
                        'message' => 'Import selesai diproses.',
                        'total_success' => (int) ($result['total_success'] ?? 0),
                        'total_failed' => (int) ($result['total_failed'] ?? 0),
                        'total_rows' => (int) ($result['total_rows'] ?? 0),
                        'processed_rows' => (int) ($result['total_rows'] ?? 0),
                        'percent' => 100,
                    ],
                );
                try {
                    SyncImportedReportJob::dispatch($jobId, null, null, static::class)
                        ->onQueue($this->resolvePostImportSyncQueue($jobId, $params));
                } catch (\Throwable $syncError) {
                    Log::warning('Import successful but failed to dispatch post-import sync: ' . $syncError->getMessage(), [
                        'job_id' => $jobId,
                    ]);
                }
                $this->releaseDispatchMarker($jobId);
                return;
            }

            if ($status === 'failed_partial' && (int) ($result['total_success'] ?? 0) > 0) {
                SyncImportedReportJob::dispatch($jobId, null, null, static::class)
                    ->onQueue($this->resolvePostImportSyncQueue($jobId, $params));
            }

            $this->progressService->markFailed(
                $jobId,
                (string) ($result['message'] ?? 'Import gagal diproses.'),
                (int) ($result['total_success'] ?? 0),
                (int) ($result['total_failed'] ?? 0),
                $status
            );
            $this->releaseDispatchMarker($jobId);
        } catch (\Throwable $e) {
            if ($this->isTerminationException($e) || $this->progressService->isTerminationRequested($jobId)) {
                $this->terminateRequestedJob($jobId);
                $this->releaseDispatchMarker($jobId);
                return;
            }

            Log::error('ImportExecutionService: worker import crash tidak terduga.', [
                'job_id' => $jobId,
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $job = $this->progressService->findJob($jobId);
            $this->progressService->markFailed(
                $jobId,
                'Worker import gagal: ' . $e->getMessage(),
                (int) ($job->total_success ?? 0),
                (int) ($job->total_failed ?? 0),
                ((int) ($job->total_success ?? 0) > 0 || (int) ($job->total_failed ?? 0) > 0) ? 'failed_partial' : 'failed'
            );
            $this->releaseDispatchMarker($jobId);
        } finally {
            $lock->release();
        }
    }

    private function shouldRunInlineFallback(array $payload, int $startedAt, bool $inlineFallbackAttempted): bool
    {
        if ($inlineFallbackAttempted) {
            return false;
        }

        $status = (string) ($payload['status'] ?? '');
        if ($status === 'processing') {
            return $this->shouldRunInlineFallbackForStaleProcessing($payload);
        }

        if ($status !== 'queued') {
            return false;
        }

        if ((time() - $startedAt) < $this->inlineFallbackGraceSeconds()) {
            return false;
        }

        $jobId = (int) ($payload['job_id'] ?? 0);
        if ($this->inlineFallbackDisabledForJob($jobId)) {
            return false;
        }

        return true;
    }

    private function shouldRunInlineFallbackForStaleProcessing(array $payload): bool
    {
        $jobId = (int) ($payload['job_id'] ?? 0);
        if ($jobId <= 0 || $this->inlineFallbackDisabledForJob($jobId)) {
            return false;
        }

        if ((int) ($payload['processed_rows'] ?? 0) > 0
            || (int) ($payload['total_success'] ?? 0) > 0
            || (int) ($payload['total_failed'] ?? 0) > 0
        ) {
            return false;
        }

        if ((int) ($payload['percent'] ?? 0) > 8) {
            return false;
        }

        if ($this->hasQueuedImportJobRow($jobId)) {
            return false;
        }

        $job = $this->progressService->findJob($jobId);
        if (!$job || !$this->isRecoverableZeroProgressImportJob($jobId, $job)) {
            return false;
        }

        return !$this->hasRecentProcessingPulse($jobId, $job);
    }

    private function inlineFallbackDisabledForJob(int $jobId): bool
    {
        if ($jobId <= 0) {
            return false;
        }

        $state = $this->progressService->getJobState($jobId);

        return (bool) (($state['params']['disable_inline_fallback'] ?? false))
            || $this->isSimpananMultiPnCsvStreamJob($jobId, null, (array) ($state['params'] ?? []));
    }

    private function inlineFallbackGraceSeconds(): int
    {
        return max(0, (int) config('import.queue.inline_fallback_grace_seconds', 0));
    }

    private function resolveInlineFallbackMessage(int $jobId, array $payload): string
    {
        $state = $jobId > 0 ? $this->progressService->getJobState($jobId) : [];
        $tableName = strtolower(trim((string) ($state['params']['table_name'] ?? '')));
        $phase = strtolower(trim((string) ($payload['phase'] ?? '')));

        if ($tableName === 'daily_loan_dinamis' && in_array($phase, ['polars', 'staging', 'preparing_load_plan', 'loading'], true)) {
            return 'Menyiapkan sanitasi CSV Daily Loan...';
        }

        $message = trim((string) ($payload['message'] ?? ''));
        if ($message !== '') {
            return $message;
        }

        return 'Worker queue belum mengambil job. Import dijalankan langsung dari request ini.';
    }

    private function dispatchedKey(int $jobId): string
    {
        return self::DISPATCHED_KEY_PREFIX . $jobId;
    }

    public function hasQueuedExecution(int $jobId): bool
    {
        if ($jobId <= 0) {
            return false;
        }

        if ($this->importCache()->has($this->dispatchedKey($jobId))) {
            return true;
        }

        return $this->hasQueuedImportJobRow($jobId);
    }

    private function hasQueuedImportJobRow(int $jobId): bool
    {
        if ($jobId <= 0) {
            return false;
        }

        try {
            return DB::table('jobs')
                ->whereIn('queue', [self::DAILY_LOAN_IMPORT_QUEUE, self::IMPORT_QUEUE])
                ->where('payload', 'like', '%' . str_replace('\\', '\\\\', RunImportJob::class) . '%')
                ->where('payload', 'like', '%jobId%')
                ->where('payload', 'like', '%i:' . $jobId . ';%')
                ->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    private function releaseDispatchMarker(int $jobId): void
    {
        $this->importCache()->forget($this->dispatchedKey($jobId));
    }

    private function resolveJobStateMismatchMessage(object $job, array $params): ?string
    {
        $jobId = (int) ($job->id ?? 0);
        $stateJobId = (int) ($params['job_id'] ?? $jobId);
        if ($stateJobId > 0 && $jobId > 0 && $stateJobId !== $jobId) {
            return 'State import job tidak cocok dengan record job. Silakan ulangi import dari preview terbaru.';
        }

        $expectedTableName = $this->resolveExpectedTableName($job);
        $stateTableName = strtolower(trim((string) ($params['table_name'] ?? '')));
        if ($expectedTableName !== '' && $stateTableName !== '' && $expectedTableName !== $stateTableName) {
            return 'State import job tidak cocok dengan report tujuan. Silakan ulangi import dari preview terbaru.';
        }

        $stateFileName = basename(str_replace('\\', '/', (string) ($params['file_path'] ?? '')));
        $jobFileName = basename(str_replace('\\', '/', (string) ($job->file_name ?? '')));
        if ($stateFileName !== '' && $jobFileName !== '' && $stateFileName !== $jobFileName) {
            return 'State import job tidak cocok dengan file job. Silakan ulangi import dari preview terbaru.';
        }

        return null;
    }

    private function resolveExpectedTableName(object $job): string
    {
        $reportId = (int) ($job->id_report ?? 0);
        if ($reportId <= 0) {
            return '';
        }

        try {
            return strtolower(trim((string) DB::table('nama_report')
                ->where('id_report', $reportId)
                ->value('table_name')));
        } catch (\Throwable) {
            return '';
        }
    }

    private function importCache()
    {
        $store = trim((string) config('import.cache_store', 'file'));

        if ($store === '') {
            return Cache::getFacadeRoot();
        }

        return $store !== '' ? Cache::store($store) : Cache::store();
    }

    private function recoverZeroProgressProcessingJob(int $jobId, object $job): ?object
    {
        if (!$this->isRecoverableZeroProgressProcessingJob($jobId, $job)) {
            return $job;
        }

        if ($this->hasRecentProcessingPulse($jobId, $job)) {
            return $job;
        }

        $this->forceReleaseImportRuntimeLocks($jobId);

        $message = 'Import sebelumnya berhenti sebelum ada baris masuk. Worker melanjutkan ulang otomatis.';
        $this->progressService->updateJob($jobId, [
            'status' => 'queued',
            'total_success' => 0,
            'total_failed' => 0,
        ], [
            'status' => 'queued',
            'phase' => 'polars',
            'mode' => 'polars',
            'percent' => 5,
            'message' => $message,
            'processed_rows' => 0,
            'total_rows' => (int) ($job->total_files ?? 0),
            'total_success' => 0,
            'total_failed' => 0,
        ]);

        Log::warning('Recovered stale zero-progress import job.', [
            'job_id' => $jobId,
            'previous_status' => $job->status ?? null,
            'updated_at' => $job->updated_at ?? null,
        ]);

        return $this->progressService->findJob($jobId);
    }

    private function isRecoverableZeroProgressProcessingJob(int $jobId, object $job): bool
    {
        if (!$this->isRecoverableZeroProgressImportJob($jobId, $job)) {
            return false;
        }

        if (strtolower((string) ($job->status ?? '')) !== 'processing') {
            return false;
        }

        if ((int) ($job->total_success ?? 0) > 0 || (int) ($job->total_failed ?? 0) > 0) {
            return false;
        }

        $updatedAt = $job->updated_at ?? null;
        if ($updatedAt === null || $updatedAt === '') {
            return true;
        }

        try {
            return Carbon::parse($updatedAt)->lt(now()->subMinutes($this->zeroProgressRecoveryMinutes()));
        } catch (\Throwable) {
            return true;
        }
    }

    private function isRecoverableZeroProgressImportJob(int $jobId, object $job): bool
    {
        if ((int) ($job->id_report ?? 0) === self::DAILY_LOAN_REPORT_ID) {
            return true;
        }

        $tableName = $this->resolveExpectedTableName($job);
        if ($tableName === '') {
            $state = $this->progressService->getJobState($jobId);
            $tableName = strtolower(trim((string) ($state['params']['table_name'] ?? '')));
        }

        return in_array($tableName, self::ZERO_PROGRESS_RECOVERABLE_TABLES, true);
    }

    private function zeroProgressRecoveryMinutes(): int
    {
        return max(2, (int) config('import.queue.zero_progress_recovery_minutes', 5));
    }

    private function isSimpananMultiPnCsvStreamJob(int $jobId, ?object $job = null, array $params = []): bool
    {
        if ($jobId <= 0) {
            return false;
        }

        $jobContext = $this->decodeJobContext($job);

        $tableName = strtolower(trim((string) ($params['table_name'] ?? $jobContext['table_name'] ?? '')));
        if ($tableName === '' && $job !== null) {
            $tableName = $this->resolveExpectedTableName($job);
        }

        $controller = ltrim((string) ($params['controller'] ?? $jobContext['controller'] ?? ''), '\\');

        if ($tableName === '' && $job !== null && (int) ($job->id_report ?? 0) !== 9) {
            return false;
        }

        if (($tableName === '' || ($tableName === 'simpanan_multipn' && $controller === '')) && $params === []) {
            $state = $this->progressService->getJobState($jobId);
            $params = (array) ($state['params'] ?? []);
            $tableName = strtolower(trim((string) ($params['table_name'] ?? $tableName)));
            $controller = ltrim((string) ($params['controller'] ?? $controller), '\\');
        }

        if ($tableName !== 'simpanan_multipn') {
            return false;
        }

        if ($controller === '' && $job !== null) {
            $controller = ltrim($this->resolveControllerClass($job), '\\');
        }

        return $controller === ImportSimpananMultiPnCsvController::class;
    }

    private function decodeJobContext(?object $job): array
    {
        if (!$job) {
            return [];
        }

        $jobContext = $job->job_context ?? null;
        if (is_string($jobContext) && $jobContext !== '') {
            $decoded = json_decode($jobContext, true);
            return is_array($decoded) ? $decoded : [];
        }

        return is_array($jobContext) ? $jobContext : [];
    }

    private function hasRecentProcessingPulse(int $jobId, object $job): bool
    {
        $progress = $this->progressService->getCachedProgress($jobId);
        $updatedAt = $progress['updated_at'] ?? $job->updated_at ?? null;
        if ($updatedAt === null || $updatedAt === '') {
            return false;
        }

        try {
            return Carbon::parse($updatedAt)->gte(now()->subMinutes(2));
        } catch (\Throwable) {
            return false;
        }
    }

    private function forceReleaseImportRuntimeLocks(int $jobId): void
    {
        $cache = $this->importCache();

        foreach ([
            'import_excel_execute_job_',
            'import_excel_dispatch_job_',
            'import_file_stream_job_',
        ] as $prefix) {
            try {
                $cache->lock($prefix . $jobId, 1)->forceRelease();
            } catch (\Throwable $e) {
                Log::warning('Failed to release stale import runtime lock: ' . $e->getMessage(), [
                    'job_id' => $jobId,
                    'lock' => $prefix . $jobId,
                ]);
            }
        }
    }

    private function shouldRedispatchQueuedJob(object $job): bool
    {
        if (($job->status ?? null) !== 'queued') {
            return false;
        }

        $updatedAt = $job->updated_at ?? null;
        if ($updatedAt === null || $updatedAt === '') {
            return true;
        }

        try {
            return Carbon::parse($updatedAt)->lt(now()->subMinutes(self::STALE_QUEUED_MINUTES));
        } catch (\Throwable) {
            return true;
        }
    }

    private function isTerminationException(\Throwable $e): bool
    {
        return str_starts_with($e->getMessage(), self::TERMINATION_EXCEPTION_PREFIX);
    }

    private function terminateRequestedJob(int $jobId): void
    {
        $job = $this->progressService->findJob($jobId);
        $success = (int) ($job->total_success ?? 0);
        $failed = (int) ($job->total_failed ?? 0);

        $this->progressService->markTerminated(
            $jobId,
            'Job dihentikan melalui Job Management.',
            $success,
            $failed
        );
    }

    private function resolveControllerClass(?object $job): string
    {
        $default = ImportExcelController::class;
        if (!$job) {
            return $default;
        }

        $jobContext = $job->job_context ?? null;
        if (is_string($jobContext) && $jobContext !== '') {
            $decoded = json_decode($jobContext, true);
            if (is_array($decoded)) {
                $jobContext = $decoded;
            }
        }

        $tableName = strtolower(trim((string) (is_array($jobContext) ? ($jobContext['table_name'] ?? '') : '')));
        if ($tableName === 'lw325_ph') {
            return ImportReportPhController::class;
        }

        $controller = is_array($jobContext) ? (string) ($jobContext['controller'] ?? '') : '';
        if ($controller === '' || !class_exists($controller)) {
            return $default;
        }

        if (!method_exists($controller, 'executeQueuedImport')) {
            return $default;
        }

        return $controller;
    }
}
