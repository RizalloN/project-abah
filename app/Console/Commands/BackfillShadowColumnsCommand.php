<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;
use Carbon\Carbon;
use Generator;

class BackfillShadowColumnsCommand extends Command
{
    protected $signature = 'shadow:backfill
        {--periods= : Comma-separated periods (e.g., 2026-04-25,2026-04-26)}
        {--chunk-size=10000 : Rows per update chunk}
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
                // Auto-discover found nothing: all shadow columns are filled
                if (empty(trim((string) $this->option('periods')))) {
                    $this->info('✓ All shadow columns are already filled — nothing to backfill.');
                    return self::SUCCESS;
                }

                $this->error('No periods specified. Use --periods=2026-04-25,2026-04-26');
                return self::FAILURE;
            }

            if ($this->option('queue')) {
                \App\Jobs\ProcessShadowBackfillJob::dispatch(
                    $periods,
                    $chunkSize,
                    $delay,
                    $retryCount,
                    $queueName,
                    $skipSnapshot
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
        if (!empty($periodInput)) {
            return array_values(array_filter(array_map('trim', explode(',', $periodInput))));
        }

        return $this->autoDiscoverPeriodsNeedingBackfill();
    }

    private function autoDiscoverPeriodsNeedingBackfill(): array
    {
        if (!Schema::hasTable('daily_loan_dinamis')) {
            return [];
        }

        $requiredColumns = [
            'segmen_kinerja', 'produk_kinerja', 'cabang_normalized', 'unit_normalized',
            'branch_normalized', 'rm_normalized', 'cifno_clean',
        ];

        foreach ($requiredColumns as $col) {
            if (!Schema::hasColumn('daily_loan_dinamis', $col)) {
                $this->line("   Auto-discover: column {$col} not yet migrated, skipping.");
                return [];
            }
        }

        // Fast index-only scan to get distinct periods
        $allPeriods = DB::table('daily_loan_dinamis')
            ->select('periode')
            ->distinct()
            ->orderByDesc('periode')
            ->pluck('periode')
            ->filter()
            ->values()
            ->all();

        $periods = [];
        foreach ($allPeriods as $period) {
            // Highly optimized sub-check utilizing the index on `periode` and stopping immediately when 1 match is found
            $needsBackfill = DB::table('daily_loan_dinamis')
                ->where('periode', $period)
                ->where(fn ($q) => $this->applyShadowBackfillPredicate($q, $requiredColumns))
                ->exists();

            if ($needsBackfill) {
                $periods[] = $period;
                if (count($periods) >= 10) {
                    break;
                }
            }
        }

        if (empty($periods)) {
            $this->line('   Auto-discover: No periods with NULL shadow columns found.');
        } else {
            $this->info('   Auto-discover: Found ' . count($periods) . ' period(s) needing backfill: ' . implode(', ', $periods));
        }

        return $periods;
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
        $lastProcessedId = null;
        $chunksCompleted = 0;
        $periodProcessed = 0;

        do {
            $retryPass++;
            $chunkNumber = 0;
            $processed = 0;
            $failed = 0;

            if ($retryPass === 1) {
                if (! $this->hasPendingShadowRows($period)) {
                    $this->line("   ✓ All shadow columns already filled");
                    $this->chunkStats[$period] = ['total' => 0, 'processed' => 0, 'failed' => 0, 'completion_percentage' => 100.0];
                    if (!$dryRun) {
                        $this->persistBackfillCheckpoint($period, null, 0, 0, 100.0);
                    }
                    return;
                }
                $nullRows = null;
                $this->line("   Processing rows in streaming chunks (retry pass {$retryPass})");
            } else {
                $nullRows = count($previouslyFailed);
                $this->line("   Retry pass {$retryPass}: Processing <fg=yellow>{$nullRows}</> failed chunks...");
            }

            $progressBar = null;
            if (!$live && $nullRows !== null) {
                $progressBar = $this->output->createProgressBar($nullRows);
                $progressBar->setFormat('   [%bar%] %percent%% | %current%/%max% | %elapsed% / %estimated%');
            }

            if ($retryPass === 1) {
                $chunks = $this->pendingRowIdChunks($period, $chunkSize);
            } else {
                $chunkSize = max(1000, (int) ($chunkSize / 2));
                $chunks = array_chunk($previouslyFailed, $chunkSize);
            }

            $failedThisPass = [];

            foreach ($chunks as $chunk) {
                $chunkNumber++;
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

                $chunkProcessed = $this->processChunk($period, $chunk, $retryCount, $dryRun);

                $chunkTime = microtime(true) - $chunkStart;
                $this->recordPerformanceMetric($chunkCount, $chunkTime);
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
                $completionPct = $nullRows !== null && $nullRows > 0
                    ? round(min(100, 100 * ($rowsHandled / $nullRows)), 2)
                    : 0.0;
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
            $periodProcessed += $processed;

            $this->line("   Pass {$retryPass} completed: {$processed} rows processed, {$failed} failed");

            $previouslyFailed = $failedThisPass;

        } while (!empty($previouslyFailed) && $retryPass < self::MAX_RETRY_PASSES);

        $finalStats = $dryRun
            ? ['total' => $periodProcessed, 'processed' => $periodProcessed, 'failed' => 0, 'completion_percentage' => 100.0]
            : $this->validateCompletion($period, $periodProcessed);
        $this->chunkStats[$period] = $finalStats;

        if (!$dryRun) {
            $this->persistBackfillCheckpoint(
                $period,
                $lastProcessedId,
                $periodProcessed,
                $chunksCompleted,
                $finalStats['completion_percentage']
            );
        }

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

    /**
     * Stream only one page of primary keys at a time. Updated rows disappear
     * from the predicate, while the cursor prevents rescanning earlier keys.
     *
     * @return Generator<int, array<int, string>>
     */
    private function pendingRowIdChunks(string $period, int $chunkSize): Generator
    {
        $requiredColumns = $this->requiredDailyLoanShadowColumns();
        $lastId = null;

        while (true) {
            $query = DB::table('daily_loan_dinamis')
                ->where('periode', $period)
                ->where(fn ($q) => $this->applyShadowBackfillPredicate($q, $requiredColumns));

            if ($lastId !== null) {
                $query->where('uniqueid_namareport', '>', $lastId);
            }

            $rowIds = $query
                ->orderBy('uniqueid_namareport')
                ->limit(max(1, $chunkSize))
                ->pluck('uniqueid_namareport')
                ->map(static fn ($value): string => (string) $value)
                ->all();

            if ($rowIds === []) {
                return;
            }

            $lastId = (string) end($rowIds);
            yield $rowIds;
        }
    }

    private function hasPendingShadowRows(string $period): bool
    {
        $requiredColumns = $this->requiredDailyLoanShadowColumns();

        return DB::table('daily_loan_dinamis')
            ->where('periode', $period)
            ->where(fn ($q) => $this->applyShadowBackfillPredicate($q, $requiredColumns))
            ->exists();
    }

    /**
     * @return array<int, string>
     */
    private function requiredDailyLoanShadowColumns(): array
    {
        return [
            'segmen_kinerja',
            'produk_kinerja',
            'cabang_normalized',
            'unit_normalized',
            'branch_normalized',
            'rm_normalized',
            'cifno_clean',
        ];
    }

    /**
     * @param mixed $query
     * @param array<int, string> $requiredColumns
     */
    private function applyShadowBackfillPredicate($query, array $requiredColumns): void
    {
        if (Schema::hasColumn('daily_loan_dinamis', 'shadow_built_at') && Schema::hasColumn('daily_loan_dinamis', 'updated_at')) {
            $query
                ->whereNull('shadow_built_at')
                ->orWhereColumn('shadow_built_at', '<', 'updated_at');

            return;
        }

        foreach ($requiredColumns as $column) {
            $query->orWhereNull($column);
        }

        if (Schema::hasColumn('daily_loan_dinamis', 'pn_pemutus_normalized')
            && Schema::hasColumn('daily_loan_dinamis', 'pn_pemutus1')) {
            $query->orWhere(function ($pnQuery): void {
                $pnQuery->whereNull('pn_pemutus_normalized')
                    ->whereRaw("LENGTH(TRIM(COALESCE(pn_pemutus1, ''))) > 0");
            });
        }

    }

    private function validateCompletion(string $period, int $processedRows = 0): array
    {
        $hasPendingRows = $this->hasPendingShadowRows($period);

        return [
            'total' => $processedRows + ($hasPendingRows ? 1 : 0),
            'processed' => $processedRows,
            'failed' => $hasPendingRows ? 1 : 0,
            'completion_percentage' => $hasPendingRows ? 0.0 : 100.0,
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

    /**
     * @param array<int, string> $rowIds
     */
    private function processChunk(string $period, array $rowIds, int $retryCount, bool $dryRun): int
    {
        if ($rowIds === []) {
            return 0;
        }

        $firstId = (string) reset($rowIds);
        $lastId = (string) end($rowIds);
        $updates = [
            'segmen_kinerja' => DB::raw("UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(COALESCE(segmen_dashboard, '')), ' ', ''), '-', ''), '_', ''), '/', ''), '.', ''))"),
            'produk_kinerja' => DB::raw("UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(COALESCE(produk_dashboard, '')), ' ', ''), '-', ''), '_', ''), '/', ''), '.', ''))"),
            'cabang_normalized' => DB::raw("UPPER(TRIM(COALESCE(cabang1, '')))"),
            'unit_normalized' => DB::raw("UPPER(TRIM(COALESCE(unit1, '')))"),
            'branch_normalized' => DB::raw("UPPER(TRIM(COALESCE(branch1, '')))"),
            'rm_normalized' => DB::raw("UPPER(TRIM(COALESCE(pn_pengelola1, '')))"),
            'cifno_clean' => DB::raw("UPPER(TRIM(COALESCE(cifno, '')))"),
        ];

        if (Schema::hasColumn('daily_loan_dinamis', 'pn_pemutus_normalized')
            && Schema::hasColumn('daily_loan_dinamis', 'pn_pemutus1')) {
            $updates['pn_pemutus_normalized'] = DB::raw("NULLIF(TRIM(LEADING '0' FROM TRIM(SUBSTRING_INDEX(COALESCE(pn_pemutus1, ''), '-', 1))), '')");
        }

        if (Schema::hasColumn('daily_loan_dinamis', 'shadow_built_at')) {
            $updates['shadow_built_at'] = now();
        }

        if ($dryRun) {
            $this->comment("   [DRY RUN] Would update keys {$firstId} through {$lastId}.");

            return count($rowIds);
        }

        $attempt = 0;
        while ($attempt < $retryCount) {
            $attempt++;
            try {
                $requiredColumns = $this->requiredDailyLoanShadowColumns();
                DB::table('daily_loan_dinamis')
                    ->where('periode', $period)
                    ->whereBetween('uniqueid_namareport', [$firstId, $lastId])
                    ->where(fn ($query) => $this->applyShadowBackfillPredicate($query, $requiredColumns))
                    ->update($updates);

                return count($rowIds);
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
