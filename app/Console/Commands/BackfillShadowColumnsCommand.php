<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;
use Carbon\Carbon;

class BackfillShadowColumnsCommand extends Command
{
    protected $signature = 'shadow:backfill
        {--periods= : Comma-separated periods (e.g., 2026-04-25,2026-04-26)}
        {--chunk-size=50000 : Rows per update chunk}
        {--delay=0 : Delay in milliseconds between chunks (helps with lock contention)}
        {--retry-count=3 : Max retry attempts per chunk}
        {--dry-run : Preview changes without executing}
        {--queue : Dispatch this backfill process to background queue worker}
        {--queue-name= : Queue name for queued backfill job}
        {--resume : Resume from last checkpoint}
        {--force-completion : Rebuild snapshots even if some chunks failed (95%+ completion)}
        {--live : Print chunk-level heartbeat and persist status after every chunk}
        {--skip-snapshot : Only backfill shadow columns without rebuilding RM snapshots}
    ';

    protected $description = 'Backfill shadow columns for daily_loan_dinamis with fault-tolerant chunking';

    private int $totalProcessed = 0;
    private int $totalFailed = 0;
    private array $chunkStats = [];
    private array $errorLog = [];
    private array $performanceMetrics = [];
    private array $failedChunks = [];
    private const MAX_RETRY_PASSES = 3;

    public function handle(): int
    {
        try {
            $this->info('╔════════════════════════════════════════════════════════════════╗');
            $this->info('║  Shadow Columns Backfill - Fault-Tolerant Processing           ║');
            $this->info('║  Purpose: Restore data integrity for RM reports                ║');
            $this->info('╚════════════════════════════════════════════════════════════════╝');
            $this->newLine();

            $periods = $this->getPeriods();
            $chunkSize = (int) $this->option('chunk-size');
            $delay = (int) $this->option('delay');
            $retryCount = (int) $this->option('retry-count');
            $dryRun = (bool) $this->option('dry-run');
            $resume = (bool) $this->option('resume');
            $forceCompletion = (bool) $this->option('force-completion');
            $live = (bool) $this->option('live');
            $skipSnapshot = (bool) $this->option('skip-snapshot');
            $queueName = trim((string) $this->option('queue-name'));
            $queueName = $queueName !== '' ? $queueName : (string) config('queue.shadow_backfill_queue', 'shadow-backfill');

            if (empty($periods)) {
                $this->error('No periods specified. Use --periods=2026-04-25,2026-04-26');
                return self::FAILURE;
            }

            if ($this->option('queue')) {
                \App\Jobs\ProcessShadowBackfillJob::dispatch(
                    $periods,
                    $chunkSize,
                    $delay,
                    $retryCount,
                    $queueName
                );

                $this->info("✓ Shadow Backfill Job queued successfully");
                $this->line("   Queue: {$queueName}");
                return self::SUCCESS;
            }

            $this->displayConfiguration($periods, $chunkSize, $delay, $retryCount, $dryRun, $live);

            $startTime = now();
            $this->backfillPeriods($periods, $chunkSize, $delay, $retryCount, $dryRun, $resume, $live);

            $this->displaySummary($startTime);

            if (!$dryRun && !$skipSnapshot) {
                $this->rebuildSnapshots($periods, $forceCompletion);
            } elseif (!$dryRun && $skipSnapshot) {
                $this->info('Snapshot rebuild skipped by --skip-snapshot. Caller will rebuild dependent snapshots.');
            }

            if ($dryRun || $forceCompletion || $this->allPeriodsReachedMinimumCompletion()) {
                return self::SUCCESS;
            }

            return self::FAILURE;

        } catch (Throwable $e) {
            $this->error("Fatal error: {$e->getMessage()}");
            Log::error('BackfillShadowColumnsCommand failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return self::FAILURE;
        }
    }

    private function getPeriods(): array
    {
        $periodInput = trim((string) $this->option('periods'));
        if (empty($periodInput)) {
            // Default: latest affected periods
            return ['2026-04-25', '2026-04-26'];
        }
        return array_map('trim', explode(',', $periodInput));
    }

    private function displayConfiguration(array $periods, int $chunkSize, int $delay, int $retryCount, bool $dryRun, bool $live): void
    {
        $this->table(
            ['Configuration', 'Value'],
            [
                ['Periods', implode(', ', $periods)],
                ['Chunk Size', number_format($chunkSize) . ' rows'],
                ['Delay Between Chunks', $delay . ' ms'],
                ['Retry Attempts', $retryCount],
                ['Dry Run', $dryRun ? 'YES (no data changes)' : 'NO'],
                ['Live Heartbeat', $live ? 'YES' : 'NO'],
                ['Mode', 'Safe chunked processing'],
            ]
        );
        $this->newLine();
    }

    private function backfillPeriods(array $periods, int $chunkSize, int $delay, int $retryCount, bool $dryRun, bool $resume, bool $live): void
    {
        foreach ($periods as $period) {
            $this->backfillPeriod($period, $chunkSize, $delay, $retryCount, $dryRun, $resume, $live);
        }
    }

    private function backfillPeriod(string $period, int $chunkSize, int $delay, int $retryCount, bool $dryRun, bool $resume, bool $live): void
    {
        $this->info("📅 Processing period: <fg=cyan>{$period}</>");

        $retryPass = 0;
        $previouslyFailed = [];
        $initialNullRows = 0;
        $lastProcessedId = null;
        $chunksCompleted = 0;

        do {
            $retryPass++;
            $chunkNumber = 0;
            $processed = 0;
            $failed = 0;

            if ($retryPass === 1) {
                $nullRows = $this->countNullShadowColumns($period);
                $initialNullRows = $nullRows;
                if ($nullRows === 0) {
                    $this->line("   ✓ All shadow columns already filled");
                    $this->chunkStats[$period] = ['total' => 0, 'processed' => 0, 'failed' => 0, 'completion_percentage' => 100.0];
                    if (!$dryRun) {
                        $this->persistBackfillCheckpoint($period, null, 0, 0, 100.0);
                    }
                    return;
                }
                $this->line("   Processing <fg=yellow>{$nullRows}</> rows (retry pass {$retryPass})");
            } else {
                $nullRows = count($previouslyFailed);
                $this->line("   Retry pass {$retryPass}: Processing <fg=yellow>{$nullRows}</> failed chunks...");
            }

            $progressBar = null;
            if (!$live) {
                $progressBar = $this->output->createProgressBar($nullRows);
                $progressBar->setFormat('   [%bar%] %percent%% | %current%/%max% | %elapsed% / %estimated%');
            }

            if ($retryPass === 1) {
                $rowIds = $this->snapshotRowIds($period, $chunkSize);
            } else {
                $rowIds = $previouslyFailed;
                $chunkSize = max(1000, (int) ($chunkSize / 2));
            }

            $failedThisPass = [];

            foreach (array_chunk($rowIds, $chunkSize) as $chunk) {
                $chunkNumber++;
                $idList = implode("','", $chunk);
                $chunkStart = microtime(true);
                $chunkCount = count($chunk);
                $lastProcessedId = (string) end($chunk);

                if ($live) {
                    $this->line(sprintf(
                        '   [%s] chunk %d pass %d: updating %s rows...',
                        now()->format('H:i:s'),
                        $chunkNumber,
                        $retryPass,
                        number_format($chunkCount)
                    ));
                }

                $chunkProcessed = $this->processChunk($period, $idList, $retryCount, $dryRun);

                $chunkTime = microtime(true) - $chunkStart;
                $this->recordPerformanceMetric($chunkSize, $chunkTime);
                $chunksCompleted++;

                if ($chunkProcessed === $chunkCount) {
                    $processed += $chunkProcessed;
                    $progressBar?->advance($chunkProcessed);
                } else {
                    $failedIds = array_slice($chunk, $chunkProcessed);
                    $failedThisPass = array_merge($failedThisPass, $failedIds);
                    $failed += count($failedIds);
                    $progressBar?->advance($chunkProcessed);
                    $this->errorLog[] = "Period {$period}, Chunk {$chunkNumber}: {$chunkProcessed}/" . $chunkCount . " processed";
                }

                $rowsHandled = $processed + $failed;
                $completionPct = $initialNullRows > 0 ? round(min(100, 100 * ($rowsHandled / $initialNullRows)), 2) : 100.0;
                if (!$dryRun) {
                    $this->persistBackfillCheckpoint($period, $lastProcessedId, $rowsHandled, $chunksCompleted, $completionPct);
                    $this->persistBackfillMetric($period, $chunkNumber, $chunkCount, $chunkTime, $chunkProcessed === $chunkCount);
                }

                if ($live) {
                    $rowsPerSecond = $chunkTime > 0 ? (int) round($chunkProcessed / $chunkTime) : 0;
                    $this->line(sprintf(
                        '   [%s] chunk %d done: %s/%s rows in %.2fs (%s rows/sec), progress %.2f%%',
                        now()->format('H:i:s'),
                        $chunkNumber,
                        number_format($chunkProcessed),
                        number_format($chunkCount),
                        $chunkTime,
                        number_format($rowsPerSecond),
                        $completionPct
                    ));
                }

                if (!$dryRun) {
                    usleep($delay * 1000);
                }
            }

            if ($progressBar !== null) {
                $progressBar->finish();
                $this->newLine();
            }

            $this->totalProcessed += $processed;
            $this->totalFailed += $failed;

            $completionPct = $nullRows > 0 ? round(100 * ($processed / $nullRows), 2) : 100;
            $this->line("   Pass {$retryPass} completed: {$processed}/{$nullRows} rows ({$completionPct}%)");

            $previouslyFailed = $failedThisPass;

        } while (!empty($previouslyFailed) && $retryPass < self::MAX_RETRY_PASSES);

        $finalStats = $this->validateCompletion($period);
        $this->chunkStats[$period] = $finalStats;

        if ($finalStats['completion_percentage'] >= 95.0) {
            $this->line("   <fg=green>✓ Period {$period}: {$finalStats['completion_percentage']}% complete</>");
        } else {
            $this->line("   <fg=yellow>⚠ Period {$period}: {$finalStats['completion_percentage']}% complete (investigate)</>");
        }

        $this->newLine();
    }

    private function persistBackfillCheckpoint(
        string $period,
        ?string $lastProcessedId,
        int $rowsProcessed,
        int $chunksCompleted,
        float $completionPercentage
    ): void {
        if (!Schema::hasTable('shadow_backfill_checkpoints')) {
            return;
        }

        try {
            $now = now();
            DB::table('shadow_backfill_checkpoints')->updateOrInsert(
                ['period' => $period],
                [
                    'last_processed_id' => $lastProcessedId,
                    'rows_processed' => $rowsProcessed,
                    'chunks_completed' => $chunksCompleted,
                    'completion_percentage' => $completionPercentage,
                    'started_at' => DB::raw('COALESCE(started_at, NOW())'),
                    'last_updated_at' => $now,
                    'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                    'updated_at' => $now,
                ]
            );
        } catch (Throwable $e) {
            Log::debug('Unable to persist shadow backfill checkpoint', [
                'period' => $period,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function persistBackfillMetric(
        string $period,
        int $chunkNumber,
        int $chunkSize,
        float $durationSeconds,
        bool $success
    ): void {
        if (!Schema::hasTable('shadow_backfill_metrics')) {
            return;
        }

        try {
            DB::table('shadow_backfill_metrics')->insert([
                'period' => $period,
                'chunk_number' => $chunkNumber,
                'chunk_size' => $chunkSize,
                'duration_seconds' => round($durationSeconds, 3),
                'rows_per_second' => $durationSeconds > 0 ? (int) round($chunkSize / $durationSeconds) : 0,
                'success' => $success,
                'executed_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::debug('Unable to persist shadow backfill metric', [
                'period' => $period,
                'chunk_number' => $chunkNumber,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function snapshotRowIds(string $period, int $chunkSize): array
    {
        return DB::table('daily_loan_dinamis')
            ->where('periode', $period)
            ->where(function ($q) {
                $q->whereNull('segmen_kinerja')
                    ->orWhereNull('produk_kinerja')
                    ->orWhereNull('cabang_normalized')
                    ->orWhereNull('unit_normalized')
                    ->orWhereNull('branch_normalized')
                    ->orWhereNull('rm_normalized')
                    ->orWhereNull('pn_pemutus_normalized')
                    ->orWhereNull('cifno_clean');
            })
            ->orderBy('uniqueid_namareport')
            ->pluck('uniqueid_namareport')
            ->toArray();
    }

    private function countNullShadowColumns(string $period): int
    {
        return DB::table('daily_loan_dinamis')
            ->where('periode', $period)
            ->where(function ($q) {
                $q->whereNull('segmen_kinerja')
                    ->orWhereNull('produk_kinerja')
                    ->orWhereNull('cabang_normalized')
                    ->orWhereNull('unit_normalized')
                    ->orWhereNull('branch_normalized')
                    ->orWhereNull('rm_normalized')
                    ->orWhereNull('pn_pemutus_normalized')
                    ->orWhereNull('cifno_clean');
            })
            ->count();
    }

    private function validateCompletion(string $period): array
    {
        $totalRows = DB::table('daily_loan_dinamis')->where('periode', $period)->count();
        $nullRows = $this->countNullShadowColumns($period);
        $filledRows = $totalRows - $nullRows;
        $completion = $totalRows > 0 ? ((100.0 * $filledRows) / $totalRows) : 100.0;

        return [
            'total' => $totalRows,
            'processed' => $filledRows,
            'failed' => $nullRows,
            'completion_percentage' => round($completion, 2),
        ];
    }

    private function recordPerformanceMetric(int $chunkSize, float $duration): void
    {
        if ($duration > 0) {
            $rowsPerSec = $chunkSize / $duration;
            $this->performanceMetrics[] = [
                'chunk_size' => $chunkSize,
                'duration' => $duration,
                'rows_per_sec' => $rowsPerSec,
            ];

            if ($rowsPerSec < 1000 && $rowsPerSec > 0) {
                Log::warning('Shadow backfill performance degradation', [
                    'rows_per_sec' => $rowsPerSec,
                    'duration' => $duration,
                ]);
            }
        }
    }

    private function processChunk(string $period, string $idList, int $retryCount, bool $dryRun): int
    {
        $sql = <<<SQL
            UPDATE daily_loan_dinamis
            SET
                segmen_kinerja = UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(COALESCE(segmen_dashboard, '')), ' ', ''), '-', ''), '_', ''), '/', ''), '.', '')),
                produk_kinerja = UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(COALESCE(produk_dashboard, '')), ' ', ''), '-', ''), '_', ''), '/', ''), '.', '')),
                cabang_normalized = UPPER(TRIM(COALESCE(cabang1, ''))),
                unit_normalized = UPPER(TRIM(COALESCE(unit1, ''))),
                branch_normalized = UPPER(TRIM(COALESCE(branch1, ''))),
                rm_normalized = UPPER(TRIM(COALESCE(pn_pengelola1, ''))),
                pn_pemutus_normalized = NULLIF(TRIM(LEADING '0' FROM TRIM(SUBSTRING_INDEX(COALESCE(pn_pemutus1, ''), '-', 1))), ''),
                cifno_clean = REGEXP_REPLACE(COALESCE(cifno, ''), '[^0-9]', '')
            WHERE uniqueid_namareport IN ('{$idList}')
        SQL;

        if ($dryRun) {
            $this->comment("   [DRY RUN] Would execute: " . substr($sql, 0, 80) . "...");
            return count(explode("','", $idList));
        }

        $attempt = 0;
        while ($attempt < $retryCount) {
            $attempt++;
            try {
                DB::statement($sql);
                return count(explode("','", $idList));
            } catch (Throwable $e) {
                if ($attempt >= $retryCount) {
                    Log::error('Chunk update failed after retries', [
                        'period' => $period,
                        'attempt' => $attempt,
                        'error' => $e->getMessage(),
                    ]);
                    return 0;
                }

                // Exponential backoff
                $backoffMs = min(1000 * (2 ** ($attempt - 1)), 5000); // Max 5 seconds
                $this->warn("   Attempt {$attempt}/{$retryCount} failed, retrying in {$backoffMs}ms...");
                usleep($backoffMs * 1000);
            }
        }

        return 0;
    }

    private function displaySummary(Carbon $startTime): void
    {
        $duration = max(0, (int) $startTime->diffInSeconds(now()));

        $this->newLine();
        $this->info('╔════════════════════════════════════════════════════════════════╗');
        $this->info('║                         SUMMARY                                ║');
        $this->info('╚════════════════════════════════════════════════════════════════╝');

        $rows = [];
        foreach ($this->chunkStats as $period => $stats) {
            $statusBadge = $stats['failed'] === 0 ? '✓' : '⚠';
            $rows[] = [
                $period,
                number_format($stats['total']),
                number_format($stats['processed']),
                number_format($stats['failed']),
                $statusBadge,
            ];
        }

        $this->table(['Period', 'Total Rows', 'Processed', 'Failed', 'Status'], $rows);

        $this->line("Total processed: <fg=green>" . number_format($this->totalProcessed) . "</> rows");
        if ($this->totalFailed > 0) {
            $this->line("Total failed: <fg=red>" . number_format($this->totalFailed) . "</> rows");
        }
        $this->line("Duration: <fg=cyan>{$duration}</> seconds");

        if (!empty($this->errorLog)) {
            $this->newLine();
            $this->warn('Errors encountered:');
            foreach ($this->errorLog as $error) {
                $this->line("  • {$error}");
            }
        }

        $this->newLine();
    }

    private function rebuildSnapshots(array $periods, bool $forceCompletion): void
    {
        $this->info('🔍 Validating backfill completion before rebuild...');
        $this->newLine();

        $canRebuild = true;
        foreach ($periods as $period) {
            $stats = $this->chunkStats[$period] ?? $this->validateCompletion($period);
            $completion = $stats['completion_percentage'] ?? 0;

            if ($completion >= 99.5) {
                $this->line("   <fg=green>✓ {$period}: {$completion}% complete</>");
            } elseif ($completion >= 95.0) {
                $this->line("   <fg=yellow>⚠ {$period}: {$completion}% complete (acceptable)</>");
            } else {
                $this->line("   <fg=red>✗ {$period}: {$completion}% complete (skipping rebuild)</>");
                $canRebuild = false;
            }
        }

        $this->newLine();

        if (!$canRebuild && !$forceCompletion) {
            $this->warn('⚠ Snapshot rebuild skipped: Incomplete backfill (< 95% complete)');
            $this->line('  Run with --force-completion to rebuild anyway, or check logs for errors.');
            return;
        }

        if (!$canRebuild && $forceCompletion) {
            $this->warn('⚠ Force-completing rebuild despite incomplete backfill');
            Log::warning('Shadow backfill snapshot rebuild forced with incomplete data', [
                'stats' => $this->chunkStats,
            ]);
        }

        $this->info('🔄 Rebuilding Performance RM snapshots...');

        try {
            foreach ($periods as $period) {
                $this->call('snapshot:rebuild-rm', ['--period' => $period]);
            }

            $this->info('✓ Snapshots rebuilt successfully');
            $this->info('🧹 Clearing report cache...');
            $this->call('cache:clear');

            $this->info('✓ All done! Reports should now display correctly.');

            Log::info('Shadow backfill completed successfully', [
                'periods' => $periods,
                'stats' => $this->chunkStats,
                'performance_metrics' => $this->getAveragePerformanceMetrics(),
            ]);

        } catch (Throwable $e) {
            Log::error('Snapshot rebuild failed after backfill', [
                'error' => $e->getMessage(),
                'periods' => $periods,
            ]);
            throw $e;
        }
    }

    private function allPeriodsReachedMinimumCompletion(): bool
    {
        foreach ($this->chunkStats as $stats) {
            if (($stats['completion_percentage'] ?? 0) < 95.0) {
                return false;
            }
        }

        return true;
    }

    private function getAveragePerformanceMetrics(): array
    {
        if (empty($this->performanceMetrics)) {
            return [];
        }

        $avgRowsPerSec = array_reduce(
            $this->performanceMetrics,
            fn($carry, $metric) => $carry + $metric['rows_per_sec'],
            0
        ) / count($this->performanceMetrics);

        $avgDuration = array_reduce(
            $this->performanceMetrics,
            fn($carry, $metric) => $carry + $metric['duration'],
            0
        ) / count($this->performanceMetrics);

        return [
            'chunks_processed' => count($this->performanceMetrics),
            'avg_rows_per_sec' => round($avgRowsPerSec, 0),
            'avg_chunk_duration_sec' => round($avgDuration, 2),
        ];
    }
}
