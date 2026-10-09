<?php

namespace App\Support;

use App\Jobs\WarmDashboardSimpananCacheJob;
use App\Jobs\WarmLandingLoanRiskCacheJob;
use App\Jobs\WarmLandingSmeQuadrantsJob;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LandingCacheWorkerGuard
{
    private const JOBS = [
        WarmDashboardSimpananCacheJob::class,
        WarmLandingLoanRiskCacheJob::class,
        WarmLandingSmeQuadrantsJob::class,
    ];

    public function started(Job $job, int $pid): void
    {
        $key = $this->key($pid);
        Cache::lock($key.':lock', 10)->block(5, function () use ($job, $key): void {
            Cache::forget($key);
            $payload = $job->payload();
            if ($job->getConnectionName() !== 'database'
                || !in_array($payload['displayName'] ?? '', self::JOBS, true)
                || (int) ($payload['timeout'] ?? 0) <= 0) {
                return;
            }
            Cache::put($key, [
                'job_id' => $job->getJobId(),
                'uuid' => $payload['uuid'] ?? null,
                'class' => $payload['displayName'],
                'attempts' => $job->attempts(),
                'started_at' => microtime(true),
                'timeout' => (int) $payload['timeout'],
            ], now()->addHours(8));
        });
    }

    public function finished(int $pid): void
    {
        $key = $this->key($pid);
        Cache::lock($key.':lock', 10)->block(5, fn () => Cache::forget($key));
    }

    /** The caller must supply only a child process resource owned by this monitor. */
    public function stopOverdueChild(mixed $process, int $pid, float $childStartedAt): bool
    {
        $key = $this->key($pid);
        $record = Cache::get($key);
        if (!$this->overdue($record, $childStartedAt)) {
            return false;
        }
        $lock = Cache::lock($key.':lock', 10);
        if (!$lock->get()) {
            return false;
        }
        try {
            // Synchronize with JobProcessed/JobProcessing so a subsequent job
            // on the same PID cannot be mistaken for the timed-out cache job.
            $record = Cache::get($key);
            if (!$this->overdue($record, $childStartedAt) || !is_resource($process)) {
                return false;
            }
            $status = proc_get_status($process);
            if (empty($status['running']) || (int) $status['pid'] !== $pid) {
                return false;
            }
            $row = DB::table('jobs')->where('id', $record['job_id'])->first();
            if (!$row || $row->reserved_at === null
                || (int) $row->attempts !== (int) $record['attempts']
                || (json_decode($row->payload, true)['uuid'] ?? null) !== $record['uuid']) {
                return false;
            }
            if (!proc_terminate($process)) {
                return false;
            }
            Cache::forget($key);
            Log::warning('Landing cache worker exceeded its job timeout; replacement worker will be started.', [
                'pid' => $pid, 'job_id' => $record['job_id'], 'job' => $record['class'],
                'timeout' => $record['timeout'], 'reservation_preserved' => true,
            ]);

            return true;
        } catch (\Throwable $exception) {
            Log::warning('Landing cache timeout inspection failed; worker retained.', ['error' => $exception->getMessage()]);

            return false;
        } finally {
            $lock->release();
        }
    }

    private function overdue(mixed $record, float $childStartedAt): bool
    {
        return is_array($record)
            && in_array($record['class'] ?? '', self::JOBS, true)
            && !empty($record['uuid'])
            && (float) ($record['started_at'] ?? 0) >= $childStartedAt
            && (int) ($record['timeout'] ?? 0) > 0
            && microtime(true) > (float) $record['started_at'] + (int) $record['timeout'] + 30;
    }

    private function key(int $pid): string
    {
        return 'queue:landing-cache:execution:'.$pid;
    }
}
