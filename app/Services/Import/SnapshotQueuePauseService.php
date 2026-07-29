<?php

namespace App\Services\Import;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SnapshotQueuePauseService
{
    private const PAUSED_QUEUES_KEY = 'import:snapshot:paused_queues';
    private const LOCK_KEY = 'import:snapshot:pause_resume:lock';

    public function pauseWhileImportActive(): void
    {
        if (!$this->isEnabled() || $this->activeImportCount() <= 0) {
            return;
        }

        $queues = $this->resolveTargetQueues();
        if ($queues === []) {
            return;
        }

        $lock = Cache::lock(self::LOCK_KEY, 15);
        if (!$lock->get()) {
            return;
        }

        try {
            $pausedQueues = $this->getPausedQueues();
            $didChange = false;

            foreach ($queues as $queue) {
                if (in_array($queue, $pausedQueues, true)) {
                    continue;
                }

                try {
                    Artisan::call('queue:pause', [
                        'queue' => $queue,
                        '--no-interaction' => true,
                    ]);
                    $pausedQueues[] = $queue;
                    $didChange = true;

                    Log::info('Snapshot queue paused because import is active.', [
                        'queue' => $queue,
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('Failed to pause snapshot queue.', [
                        'queue' => $queue,
                        'message' => $e->getMessage(),
                    ]);
                }
            }

            if ($didChange) {
                Cache::put(self::PAUSED_QUEUES_KEY, array_values(array_unique($pausedQueues)), now()->addHours(6));
            }
        } finally {
            optional($lock)->release();
        }
    }

    public function resumeWhenNoActiveImports(): void
    {
        if (!$this->isEnabled() || $this->activeImportCount() > 0) {
            return;
        }

        $pausedQueues = $this->getPausedQueues();
        if ($pausedQueues === []) {
            $pausedQueues = $this->resolveTargetQueues();
            if ($pausedQueues === []) {
                return;
            }
        }

        $lock = Cache::lock(self::LOCK_KEY, 15);
        if (!$lock->get()) {
            return;
        }

        try {
            $remaining = [];

            foreach ($pausedQueues as $queue) {
                try {
                    Artisan::call('queue:resume', [
                        'queue' => $queue,
                        '--no-interaction' => true,
                    ]);

                    Log::info('Snapshot queue resumed because no active import remains.', [
                        'queue' => $queue,
                    ]);
                } catch (\Throwable $e) {
                    $remaining[] = $queue;
                    Log::warning('Failed to resume snapshot queue.', [
                        'queue' => $queue,
                        'message' => $e->getMessage(),
                    ]);
                }
            }

            if ($remaining === []) {
                Cache::forget(self::PAUSED_QUEUES_KEY);
            } else {
                Cache::put(self::PAUSED_QUEUES_KEY, array_values(array_unique($remaining)), now()->addHours(6));
            }
        } finally {
            optional($lock)->release();
        }
    }

    private function isEnabled(): bool
    {
        return (bool) config('import.snapshot.pause_during_import', true);
    }

    /**
     * @return array<int, string>
     */
    private function resolveTargetQueues(): array
    {
        $configured = (array) config('import.snapshot.pause_queues', []);
        $configured = array_values(array_filter(array_map(
            static fn ($value): string => strtolower(trim((string) $value)),
            $configured
        ), static fn (string $value): bool => $value !== ''));

        if ($configured === []) {
            $configured = ['snapshots-priority', 'snapshots-parallel', 'shadow-backfill'];
        }

        $excluded = (array) config('import.snapshot.pause_excluded_queues', ['imports-high']);
        $excluded = array_values(array_filter(array_map(
            static fn ($value): string => strtolower(trim((string) $value)),
            $excluded
        ), static fn (string $value): bool => $value !== ''));

        return array_values(array_filter(array_unique($configured), static function (string $queue) use ($excluded): bool {
            return !in_array($queue, $excluded, true);
        }));
    }

    /**
     * @return array<int, string>
     */
    private function getPausedQueues(): array
    {
        $paused = Cache::get(self::PAUSED_QUEUES_KEY, []);

        if (!is_array($paused)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($value): string => strtolower(trim((string) $value)),
            $paused
        ), static fn (string $value): bool => $value !== ''));
    }

    private function activeImportCount(): int
    {
        if (!Schema::hasTable('import_jobs')) {
            return 0;
        }

        try {
            return (int) DB::table('import_jobs')
                ->whereIn('status', ['staging', 'processing'])
                ->count();
        } catch (\Throwable) {
            return 0;
        }
    }
}
