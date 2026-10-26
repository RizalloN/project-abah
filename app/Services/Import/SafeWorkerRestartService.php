<?php

namespace App\Services\Import;

use App\Support\ManagedReportSnapshotRebuildCoordinator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Restarts queue workers only when nothing is in flight. An import or a
 * snapshot chain runs as several queued phases (plus external stage
 * processes); restarting between two phases would run the rest of the chain
 * on different code. A restart request therefore waits until imports,
 * snapshot/delete pipelines and every reserved job have finished, and the
 * system stayed quiet for a short settle period.
 *
 * The scheduled queue:watchdog calls tick() every minute in a fresh process,
 * so pending restarts and code-change detection always run current code.
 */
class SafeWorkerRestartService
{
    /** Short, idempotent cache/presentation work never blocks a restart. */
    public const LIGHT_QUEUES = ['reports-low', 'remote-sources'];

    /** Queues whose jobs write imported data; they always block a restart. */
    public const IMPORT_QUEUES = ['imports-high', 'imports-daily-loan'];

    /** Idle must be observed this long, spanning gaps between chained phases. */
    private const QUIET_SECONDS = 60;

    /** Code must stop changing this long before an automatic restart. */
    private const CODE_SETTLE_SECONDS = 120;

    /** Delayed follow-up phases due within this window count as in flight. */
    private const DELAYED_LOOKAHEAD_SECONDS = 300;

    /** Imports untouched this long are abandoned and must not block forever. */
    private const STALE_IMPORT_SECONDS = 21600;

    private const SYNTAX_CHECK_FILE_LIMIT = 60;

    public function __construct(
        private readonly QueueWorkerControlService $workerControl
    ) {
    }

    /**
     * Restart now when idle, otherwise schedule it for the first quiet moment.
     *
     * @return array<string, mixed>
     */
    public function request(?string $requestedBy, string $reason = 'manual'): array
    {
        if (!$this->workerControl->isEnabled()) {
            return ['outcome' => 'rejected', 'reason' => 'disabled', 'busy' => []];
        }

        $busy = $this->busyReasons();
        if ($busy === []) {
            $errors = $this->syntaxErrors();
            if ($errors !== []) {
                return ['outcome' => 'blocked', 'errors' => $errors, 'busy' => []];
            }
            $this->forgetPending();
            $status = $this->workerControl->restart($requestedBy);
            if (($status['restart_rejected'] ?? '') !== '') {
                return ['outcome' => 'rejected', 'reason' => (string) $status['restart_rejected'], 'busy' => []];
            }

            return ['outcome' => 'restarted', 'busy' => [], 'worker_status' => $status];
        }

        $pending = $this->pending() ?? [
            'requested_at' => time(),
            'requested_by' => $requestedBy,
            'reason' => $reason,
        ];
        // A manual request supersedes an automatic one and keeps its own author.
        if ($reason === 'manual') {
            $pending['requested_by'] = $requestedBy;
            $pending['reason'] = 'manual';
        }
        $pending['idle_since'] = null;
        Cache::forever($this->key('pending'), $pending);

        return ['outcome' => 'scheduled', 'busy' => $busy, 'pending' => $pending];
    }

    public function cancel(): bool
    {
        $hadPending = $this->pending() !== null;
        $this->forgetPending();

        return $hadPending;
    }

    /** @return array{requested_at:int,requested_by:?string,reason:string,idle_since:?int}|null */
    public function pending(): ?array
    {
        $pending = Cache::get($this->key('pending'));

        return is_array($pending) ? $pending : null;
    }

    /**
     * Watchdog step: schedule a restart for newly deployed code, and execute a
     * pending restart once the system has been quiet long enough.
     *
     * @return array<string, mixed>
     */
    public function tick(): array
    {
        if (!$this->workerControl->isEnabled()) {
            return ['action' => 'disabled'];
        }

        $codeChange = $this->codeChange();
        if ($codeChange['restart_needed'] && $this->pending() === null
            && (bool) config('queue.auto_restart_on_code_change', true)) {
            Cache::forever($this->key('pending'), [
                'requested_at' => time(),
                'requested_by' => null,
                'reason' => 'code_change',
                'idle_since' => null,
            ]);
            Log::warning('Kode aplikasi berubah sejak worker dimulai; restart aman dijadwalkan.', [
                'code_changed_at' => $codeChange['changed_at'],
                'oldest_process_started_at' => $codeChange['oldest_started_at'],
            ]);
        }

        $pending = $this->pending();
        if ($pending === null) {
            return ['action' => 'none'];
        }

        $busy = $this->busyReasons();
        if ($busy !== [] && $this->shouldEscalate($pending, $busy)) {
            // Snapshot/background queues that never drain (for example jobs
            // retrying a bug that the new code fixes) must not starve the
            // restart forever. queue:restart is graceful: running jobs finish.
            $errors = $this->syntaxErrors();
            if ($errors === []) {
                $status = $this->workerControl->restart('Otomatis (eskalasi: antrean non-import tidak pernah diam)');
                if (($status['restart_rejected'] ?? '') === '') {
                    $this->forgetPending();
                    Log::warning('Restart aman dieskalasi: antrean non-import tidak pernah diam.', [
                        'pending_since' => $pending['requested_at'] ?? null,
                        'busy' => $busy,
                    ]);

                    return ['action' => 'restarted', 'escalated' => true, 'worker_status' => $status];
                }
            }
        }

        if ($busy !== []) {
            if ($pending['idle_since'] !== null) {
                $pending['idle_since'] = null;
                Cache::forever($this->key('pending'), $pending);
            }

            return ['action' => 'waiting', 'busy' => $busy];
        }

        $now = time();
        if ($pending['idle_since'] === null) {
            $pending['idle_since'] = $now;
            Cache::forever($this->key('pending'), $pending);

            return ['action' => 'settling'];
        }
        if ($now - (int) $pending['idle_since'] < self::QUIET_SECONDS) {
            return ['action' => 'settling'];
        }

        // Never restart onto code that cannot boot; the running workers keep
        // serving with the previous code until the files parse again.
        $errors = $this->syntaxErrors();
        if ($errors !== []) {
            if (($pending['blocked_by'] ?? []) !== $errors) {
                $pending['blocked_by'] = $errors;
                Cache::forever($this->key('pending'), $pending);
                Log::warning('Restart aman ditahan: ada file PHP yang tidak valid.', ['errors' => $errors]);
            }

            return ['action' => 'blocked', 'errors' => $errors];
        }

        $requestedBy = $pending['reason'] === 'code_change'
            ? 'Otomatis (perubahan kode)'
            : ($pending['requested_by'] ?? null);
        $status = $this->workerControl->restart($requestedBy);
        if (($status['restart_rejected'] ?? '') !== '') {
            return ['action' => 'retry_later', 'reason' => $status['restart_rejected']];
        }
        $this->forgetPending();

        return ['action' => 'restarted', 'worker_status' => $status];
    }

    /**
     * A pending restart may stop waiting for perfect idleness only when
     * nothing that writes imported data is involved: no import job, no managed
     * snapshot rebuild/unknown pipeline state, and no work in an import queue.
     *
     * @param array<string, mixed> $pending
     * @param array<int, array{type:string,label:string,queue?:string}> $busy
     */
    public function shouldEscalate(array $pending, array $busy): bool
    {
        $escalateAfter = max(5, (int) config('queue.safe_restart_escalate_minutes', 20)) * 60;
        if ((time() - (int) ($pending['requested_at'] ?? time())) < $escalateAfter) {
            return false;
        }

        foreach ($busy as $reason) {
            if (!in_array($reason['type'] ?? '', ['job', 'queue'], true)) {
                return false;
            }
            if (in_array((string) ($reason['queue'] ?? ''), self::IMPORT_QUEUES, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Everything still in flight. An empty list means a restart cannot cut
     * off any import, snapshot, delete or job.
     *
     * @return array<int, array{type:string,label:string}>
     */
    public function busyReasons(): array
    {
        $reasons = [];
        $now = time();

        if (Schema::hasTable('import_jobs')) {
            $imports = DB::table('import_jobs')
                ->whereIn('status', ['queued', 'staging', 'processing'])
                ->where('updated_at', '>=', now()->subSeconds(self::STALE_IMPORT_SECONDS))
                ->orderBy('id')
                ->limit(10)
                ->get(['id', 'status', 'file_name']);
            foreach ($imports as $import) {
                $progress = $this->importProgress((int) $import->id);
                $reasons[] = [
                    'type' => 'import',
                    'label' => 'Import #'.$import->id.' ('.$import->status.($progress !== null ? ', '.$progress.'%' : '').')',
                ];
            }
        }

        if (Schema::hasTable('jobs')) {
            $retryAfter = max(60, (int) config('queue.connections.database.retry_after', 90));
            $rows = DB::table('jobs')
                ->select('queue')
                ->selectRaw('SUM(CASE WHEN reserved_at IS NOT NULL AND reserved_at > ? THEN 1 ELSE 0 END) as running', [$now - $retryAfter])
                ->selectRaw('SUM(CASE WHEN reserved_at IS NULL AND available_at <= ? THEN 1 ELSE 0 END) as waiting', [$now + self::DELAYED_LOOKAHEAD_SECONDS])
                ->groupBy('queue')
                ->get();
            foreach ($rows as $row) {
                $running = (int) $row->running;
                $waiting = in_array((string) $row->queue, self::LIGHT_QUEUES, true) ? 0 : (int) $row->waiting;
                if ($running > 0) {
                    $reasons[] = ['type' => 'job', 'label' => $running.' job sedang diproses di '.$row->queue, 'queue' => (string) $row->queue];
                }
                if ($waiting > 0) {
                    $reasons[] = ['type' => 'queue', 'label' => $waiting.' job menunggu di '.$row->queue, 'queue' => (string) $row->queue];
                }
            }
        }

        try {
            $coordinator = app(ManagedReportSnapshotRebuildCoordinator::class);
            foreach ($coordinator->resolveKnownRebuildIds() as $rebuildId) {
                $state = $coordinator->resolveOperationalState((string) $rebuildId);
                if (in_array((string) ($state['status'] ?? ''), ['queued', 'processing', 'running'], true)) {
                    $reasons[] = ['type' => 'snapshot', 'label' => 'Snapshot rebuild '.($state['label'] ?? $rebuildId).' ('.$state['status'].')'];
                }
            }
        } catch (Throwable $exception) {
            // An unreadable pipeline state is not proof of idleness.
            $reasons[] = ['type' => 'unknown', 'label' => 'Status snapshot rebuild tidak terbaca: '.$exception->getMessage()];
        }

        return $reasons;
    }

    /**
     * Deployed code is newer than the oldest live queue process and has
     * stopped changing, so those processes run stale classes.
     *
     * @return array{restart_needed:bool,changed_at:?int,oldest_started_at:?int}
     */
    public function codeChange(): array
    {
        $changedAt = $this->codeChangedAt();
        $oldestStartedAt = app(QueueWorkerMonitorService::class)->oldestLiveProcessStartedAt();

        return [
            'restart_needed' => $changedAt !== null && $oldestStartedAt !== null
                && $changedAt > $oldestStartedAt
                && (time() - $changedAt) >= self::CODE_SETTLE_SECONDS,
            'changed_at' => $changedAt,
            'oldest_started_at' => $oldestStartedAt,
        ];
    }

    /**
     * PHP files changed since the oldest live worker started that fail to
     * parse. Restarting onto them would make every new worker fail to boot.
     *
     * @return array<int, string> relative paths with their first error line
     */
    public function syntaxErrors(): array
    {
        $since = app(QueueWorkerMonitorService::class)->oldestLiveProcessStartedAt() ?? (time() - 86400);
        $php = (new \Symfony\Component\Process\PhpExecutableFinder())->find(false);
        if ($php === false || stripos(basename((string) $php), 'php') !== 0) {
            return [];
        }

        $errors = [];
        foreach ($this->changedPhpFiles($since) as $path) {
            $process = new \Symfony\Component\Process\Process([$php, '-l', $path], base_path(), null, null, 10);
            $process->run();
            if (!$process->isSuccessful()) {
                $message = trim(strtok($process->getOutput().$process->getErrorOutput(), "\n") ?: 'syntax error');
                $errors[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $path).': '.$message;
            }
        }

        return $errors;
    }

    /** @return array<int, string> */
    private function changedPhpFiles(int $since): array
    {
        $files = [];
        foreach (['app', 'config', 'routes', 'bootstrap/app.php', 'bootstrap/providers.php'] as $relative) {
            $path = base_path($relative);
            $candidates = is_file($path)
                ? [new \SplFileInfo($path)]
                : (is_dir($path) ? new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)) : []);
            foreach ($candidates as $file) {
                if ($file->isFile() && $file->getExtension() === 'php' && $file->getMTime() > $since) {
                    $files[] = $file->getPathname();
                }
            }
        }

        return array_slice($files, 0, self::SYNTAX_CHECK_FILE_LIMIT);
    }

    /** Latest modification time of code that long-running workers load once. */
    public function codeChangedAt(): ?int
    {
        return Cache::remember($this->key('code-mtime'), 30, function (): ?int {
            $latest = 0;
            foreach (['app', 'config', 'routes', 'bootstrap/app.php', 'bootstrap/providers.php', 'composer.lock', '.env'] as $relative) {
                $path = base_path($relative);
                if (is_file($path)) {
                    $latest = max($latest, (int) @filemtime($path));
                    continue;
                }
                if (!is_dir($path)) {
                    continue;
                }
                $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS));
                foreach ($files as $file) {
                    if ($file->isFile() && $file->getExtension() === 'php') {
                        $latest = max($latest, (int) $file->getMTime());
                    }
                }
            }

            return $latest > 0 ? $latest : null;
        });
    }

    /** @return array<string, mixed> */
    public function summary(): array
    {
        $pending = $this->pending();
        $codeChange = $this->codeChange();

        return [
            'pending' => $pending !== null ? [
                'requested_at' => now()->setTimestamp((int) $pending['requested_at'])->toIso8601String(),
                'requested_by' => $pending['requested_by'] ?? null,
                'reason' => $pending['reason'] ?? 'manual',
                'idle_since' => $pending['idle_since'] !== null ? now()->setTimestamp((int) $pending['idle_since'])->toIso8601String() : null,
                'blocked_by' => array_values((array) ($pending['blocked_by'] ?? [])),
            ] : null,
            'busy' => $this->busyReasons(),
            'code_changed_at' => $codeChange['changed_at'] !== null ? now()->setTimestamp($codeChange['changed_at'])->toIso8601String() : null,
            'code_outdated' => $codeChange['changed_at'] !== null && $codeChange['oldest_started_at'] !== null
                && $codeChange['changed_at'] > $codeChange['oldest_started_at'],
            'auto_restart_on_code_change' => (bool) config('queue.auto_restart_on_code_change', true),
            'quiet_seconds' => self::QUIET_SECONDS,
        ];
    }

    private function importProgress(int $jobId): ?int
    {
        try {
            $payload = app(ImportProgressService::class)->getStatusPayload($jobId);

            return isset($payload['percent']) ? (int) $payload['percent'] : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function forgetPending(): void
    {
        Cache::forget($this->key('pending'));
    }

    private function key(string $suffix): string
    {
        $project = realpath(base_path()) ?: base_path();

        return 'queue:safe-restart:'.sha1(strtolower(str_replace('\\', '/', $project))).':'.$suffix;
    }
}
