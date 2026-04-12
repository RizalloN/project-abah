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
    private const DISPATCHED_KEY_PREFIX = 'import_excel_dispatched_job_';
    private const DISPATCHED_TTL_HOURS = 6;
    private const STALE_QUEUED_MINUTES = 10;

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
        if (!$job || in_array($job->status, ['processing', 'completed', 'failed', 'failed_partial'], true)) {
            $this->releaseDispatchMarker($jobId);
            return false;
        }

        $this->progressService->purgeStaleQueuedJobs();

        $job = $this->progressService->findJob($jobId);
        if (!$job || in_array($job->status, ['processing', 'completed', 'failed', 'failed_partial'], true)) {
            $this->releaseDispatchMarker($jobId);
            return false;
        }

        $lock = Cache::lock('import_excel_dispatch_job_' . $jobId, 30);

        try {
            if (!$lock->get()) {
                return false;
            }

            $job = $this->progressService->findJob($jobId);
            if (!$job || in_array($job->status, ['processing', 'completed', 'failed', 'failed_partial'], true)) {
                $this->releaseDispatchMarker($jobId);
                return false;
            }

            if (Cache::has($this->dispatchedKey($jobId)) && !$this->shouldRedispatchQueuedJob($job)) {
                return false;
            }

            $this->progressService->markQueued($jobId, [
                'status' => 'queued',
                'percent' => 1,
                'message' => $queueMessage ?: 'Job import masuk ke queue.',
                'processed_rows' => 0,
                'total_success' => (int) ($job->total_success ?? 0),
                'total_failed' => (int) ($job->total_failed ?? 0),
                'total_rows' => (int) ($job->total_files ?? 0),
            ]);

            Cache::put($this->dispatchedKey($jobId), true, now()->addHours(self::DISPATCHED_TTL_HOURS));
            RunImportJob::dispatch($jobId);
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

                    if (in_array($payload['status'] ?? '', ['completed', 'failed', 'failed_partial'], true)) {
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
                'percent' => 3,
                'message' => 'Worker queue mulai memproses import.',
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
                $status = match ($event) {
                    'complete' => 'completed',
                    'error' => 'failed',
                    default => 'processing',
                };

                $this->progressService->cacheProgress($jobId, array_merge($payload, ['status' => $status]));
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
                SyncImportedReportJob::dispatch($jobId, null, null, static::class)->onQueue('reports-low');
                $this->releaseDispatchMarker($jobId);
                return;
            }

            if ($status === 'failed_partial' && (int) ($result['total_success'] ?? 0) > 0) {
                SyncImportedReportJob::dispatch($jobId, null, null, static::class)->onQueue('reports-low');
            }

            $this->progressService->markFailed(
                $jobId,
                (string) ($result['message'] ?? 'Import gagal diproses.'),
                (int) ($result['total_success'] ?? 0),
                (int) ($result['total_failed'] ?? 0),
                $status
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
}
