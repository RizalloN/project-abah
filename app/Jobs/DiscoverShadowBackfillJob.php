<?php

namespace App\Jobs;

use App\Services\Import\ImportProgressService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * Runs shadow-column period discovery on a queue worker instead of inside the
 * scheduler tick. Task Scheduler keeps a tick "running" while any child it
 * spawned is alive, so a long scan there skipped every following minute.
 */
class DiscoverShadowBackfillJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public $timeout = 1800;
    public $tries = 1;
    public $uniqueFor = 1800;

    public function __construct()
    {
        $this->onQueue((string) config('queue.shadow_backfill_queue', 'shadow-backfill'));
    }

    public function uniqueId(): string
    {
        return 'shadow-backfill:discover';
    }

    public function handle(ImportProgressService $imports): void
    {
        if ($imports->hasActiveProcessingJobs()) {
            return;
        }

        $startedAt = microtime(true);
        $exitCode = Artisan::call('shadow:backfill', [
            '--queue' => true,
            '--skip-snapshot' => true,
            '--chunk-size' => 10000,
            '--retry-count' => 3,
            '--no-interaction' => true,
        ]);

        Log::info('Shadow backfill discovery finished.', [
            'exit_code' => $exitCode,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);
    }
}
