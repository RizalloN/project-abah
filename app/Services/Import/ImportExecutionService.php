<?php

namespace App\Services\Import;

use App\Http\Controllers\Import\ImportExcelController;
use App\Jobs\RunImportJob;
use App\Jobs\SyncImportedReportJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Carbon;

class ImportExecutionService
{
    private const IMPORT_QUEUE = 'imports-high';
    private const DAILY_LOAN_IMPORT_QUEUE = 'imports-daily-loan';
    private const DAILY_LOAN_REPORT_ID = 8;
    private const DISPATCHED_KEY_PREFIX = 'import_excel_dispatched_job_';
    private const DISPATCHED_TTL_HOURS = 6;
    private const STALE_QUEUED_MINUTES = 10;
    private const TERMINATION_EXCEPTION_PREFIX = 'import_job_terminated_by_request:';

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
        if (!$job || in_array($job->status, ['processing', 'completed', 'failed', 'failed_partial', 'terminated'], true)) {
            $this->releaseDispatchMarker($jobId);
            return false;
        }

        $this->progressService->purgeStaleQueuedJobs();
        $this->progressService->purgeStaleProcessingJobs();

        $job = $this->progressService->findJob($jobId);
        if (!$job || in_array($job->status, ['processing', 'completed', 'failed', 'failed_partial', 'terminated'], true)) {
            $this->releaseDispatchMarker($jobId);
            return false;
        }

        $lock = Cache::lock('import_excel_dispatch_job_' . $jobId, 30);

        try {
            if (!$lock->get()) {
                return false;
            }

            $queue = $this->resolveImportQueue($jobId);
            $this->progressService->purgeQueuedImportJobsForQueues(
                $this->queuesToPurgeFor($queue),
                self::STALE_QUEUED_MINUTES
            );
            $job = $this->progressService->findJob($jobId);
            if (!$job || in_array($job->status, ['processing', 'completed', 'failed', 'failed_partial', 'terminated'], true)) {
                $this->releaseDispatchMarker($jobId);
                return false;
            }

            if (Cache::has($this->dispatchedKey($jobId)) && !$this->shouldRedispatchQueuedJob($job)) {
                return false;
            }

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
            Cache::put($this->dispatchedKey($jobId), true, now()->addHours(self::DISPATCHED_TTL_HOURS));
            dispatch((new RunImportJob($jobId))->onQueue($queue));
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

    private function resolveImportQueue(int $jobId): string
    {
        $job = $this->progressService->findJob($jobId);
        $state = $this->progressService->getJobState($jobId);
        $tableName = strtolower(trim((string) ($state['params']['table_name'] ?? '')));
        $reportId = (int) ($job->id_report ?? 0);

        if ($reportId === self::DAILY_LOAN_REPORT_ID || $tableName === 'daily_loan_dinamis') {
            return self::DAILY_LOAN_IMPORT_QUEUE;
        }

        return self::IMPORT_QUEUE;
    }

    /**
     * Daily Loan harus membersihkan antrean import umum supaya worker khususnya dapat jalan lebih dulu.
     *
     * @return array<int, string>
     */
    private function queuesToPurgeFor(string $queue): array
    {
        if ($queue === self::DAILY_LOAN_IMPORT_QUEUE) {
            return [self::DAILY_LOAN_IMPORT_QUEUE, self::IMPORT_QUEUE];
        }

        return [self::IMPORT_QUEUE];
    }

    public function streamStatus(Request $request, int $jobId): StreamedResponse
    {
        return response()->stream(function () use ($request, $jobId) {
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

            while (true) {
                if ($request->isMethod('GET') && function_exists('connection_aborted') && connection_aborted()) {
                    break;
                }

                $payload = $this->progressService->getStatusPayload($jobId);
                $hash = md5(json_encode($payload));
                $isStaleQueue = ($payload['status'] ?? '') === 'queued' && !empty($payload['is_stale_queue']);

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

    public function run(int $jobId): void
    {
        $this->progressService->purgeStaleProcessingJobs();

        if ($this->progressService->isTerminationRequested($jobId)) {
            $this->terminateRequestedJob($jobId);
            $this->releaseDispatchMarker($jobId);
            return;
        }

        $state = $this->progressService->getJobState($jobId);
        $params = (array) ($state['params'] ?? []);
        $headers = array_values((array) ($state['headers'] ?? []));

        if ($params === [] || $headers === []) {
            $this->progressService->markFailed($jobId, 'State import job hilang. Silakan ulangi import dari awal.');
            $this->releaseDispatchMarker($jobId);
            return;
        }

        $lock = Cache::lock('import_excel_execute_job_' . $jobId, 7200);
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
                'message' => 'Worker queue masuk fase Polars.',
                'processed_rows' => 0,
                'total_rows' => (int) ($params['total_rows'] ?? 0),
            ]);

            /** @var ImportExcelController $controller */
            $controller = app(ImportExcelController::class);
            $result = $controller->executeQueuedImport([
                'job_id' => $jobId,
                'params' => $params,
                'headers' => $headers,
            ], function (string $event, array $payload) use ($jobId): void {
                if ($this->progressService->isTerminationRequested($jobId)) {
                    throw new \RuntimeException(self::TERMINATION_EXCEPTION_PREFIX . $jobId);
                }

                $status = match ($event) {
                    'complete' => 'completed',
                    'error' => 'failed',
                    default => 'processing',
                };

                $this->progressService->cacheProgress($jobId, array_merge($payload, ['status' => $status]));

                if ($this->progressService->isTerminationRequested($jobId)) {
                    throw new \RuntimeException(self::TERMINATION_EXCEPTION_PREFIX . $jobId);
                }
            });

            $job = $this->progressService->findJob($jobId);
            if ($job && in_array($job->status, ['completed', 'failed', 'failed_partial'], true)) {
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
                SyncImportedReportJob::dispatch($jobId, null, null, static::class)->onQueue((string) config('queue.report_queue', 'default'));
                $this->releaseDispatchMarker($jobId);
                return;
            }

            if ($status === 'failed_partial' && (int) ($result['total_success'] ?? 0) > 0) {
                SyncImportedReportJob::dispatch($jobId, null, null, static::class)->onQueue((string) config('queue.report_queue', 'default'));
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

    private function dispatchedKey(int $jobId): string
    {
        return self::DISPATCHED_KEY_PREFIX . $jobId;
    }

    private function releaseDispatchMarker(int $jobId): void
    {
        Cache::forget($this->dispatchedKey($jobId));
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
}
