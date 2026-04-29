<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use Carbon\Carbon;

/**
 * Backfill shadow columns (segmen_kinerja, produk_kinerja, etc.) untuk daily_loan_dinamis
 * 
 * Masalah: Lock wait timeout saat update massal (~1.9M baris) di lingkungan XAMPP/Windows
 * Solusi: Chunked update dengan delay untuk menghindari lock contention
 * 
 * Normalization rules (dari migrasi 2026_04_26):
 * - segmen_kinerja: UPPER(REPLACE(...TRIM(segmen_dashboard)))
 * - produk_kinerja: UPPER(REPLACE(...TRIM(produk_dashboard)))
 * - cabang_normalized: UPPER(TRIM(cabang1))
 * - unit_normalized: UPPER(TRIM(unit1))
 * - branch_normalized: UPPER(TRIM(branch1))
 * - rm_normalized: UPPER(TRIM(pn_pengelola1))
 * - pn_pemutus_normalized: NULLIF(TRIM(LEADING '0' FROM TRIM(SUBSTRING_INDEX(pn_pemutus1, '-', 1))), '')
 * - cifno_clean: REGEXP_REPLACE(cifno, '[^0-9]', '')
 */
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
    ';

    protected $description = 'Backfill shadow columns for daily_loan_dinamis with chunking to avoid lock timeout';

    private int $totalProcessed = 0;
    private int $totalFailed = 0;
    private array $chunkStats = [];
    private array $errorLog = [];

    public function handle(): int
    {
        try {
            $this->info('╔════════════════════════════════════════════════════════════════╗');
            $this->info('║  Shadow Columns Backfill - Chunked Processing                  ║');
            $this->info('║  Purpose: Restore data integrity for RM reports (Kinerja RM)   ║');
            $this->info('╚════════════════════════════════════════════════════════════════╝');
            $this->newLine();

            $periods = $this->getPeriods();
            $chunkSize = (int) $this->option('chunk-size');
            $delay = (int) $this->option('delay');
            $retryCount = (int) $this->option('retry-count');
            $dryRun = (bool) $this->option('dry-run');
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
                
                $this->info("Shadow Backfill Job berhasil ditambahkan ke dalam antrian (Queue).");
                $this->line("   Queue: {$queueName}");
                $this->line("   Jalankan worker yang mendengar queue '{$queueName}' untuk memproses backfill.");
                $this->line("   Cek file log (storage/logs) untuk melihat progresnya.");
                return self::SUCCESS;
            }

            $this->displayConfiguration($periods, $chunkSize, $delay, $retryCount, $dryRun);

            $startTime = now();
            $this->backfillPeriods($periods, $chunkSize, $delay, $retryCount, $dryRun);

            $this->displaySummary($startTime);

            if (!$dryRun) {
                $this->rebuildSnapshots($periods);
            }

            return $this->totalFailed === 0 ? self::SUCCESS : self::FAILURE;

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

    private function displayConfiguration(array $periods, int $chunkSize, int $delay, int $retryCount, bool $dryRun): void
    {
        $this->table(
            ['Configuration', 'Value'],
            [
                ['Periods', implode(', ', $periods)],
                ['Chunk Size', number_format($chunkSize) . ' rows'],
                ['Delay Between Chunks', $delay . ' ms'],
                ['Retry Attempts', $retryCount],
                ['Dry Run', $dryRun ? 'YES (no data changes)' : 'NO'],
                ['Mode', 'Safe chunked processing'],
            ]
        );
        $this->newLine();
    }

    private function backfillPeriods(array $periods, int $chunkSize, int $delay, int $retryCount, bool $dryRun): void
    {
        foreach ($periods as $period) {
            $this->backfillPeriod($period, $chunkSize, $delay, $retryCount, $dryRun);
        }
    }

    private function backfillPeriod(string $period, int $chunkSize, int $delay, int $retryCount, bool $dryRun): void
    {
        $this->info("📅 Processing period: <fg=cyan>{$period}</>");

        // Count total rows to backfill
        $totalRows = DB::table('daily_loan_dinamis')
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

        if ($totalRows === 0) {
            $this->line("   ✓ All shadow columns already filled (0 rows to process)");
            $this->chunkStats[$period] = ['total' => 0, 'processed' => 0, 'failed' => 0];
            return;
        }

        $this->line("   Processing <fg=yellow>{$totalRows}</> rows in chunks of <fg=yellow>{$chunkSize}</>");

        $progressBar = $this->output->createProgressBar($totalRows);
        $progressBar->setFormat('   [%bar%] %percent%% | %current%/%max% | %elapsed% / %estimated%');

        $processed = 0;
        $failed = 0;
        $lastUniqueid = null;
        $chunkNumber = 0;

        while ($processed < $totalRows) {
            $chunkNumber++;

            // Get chunk UUIDs using uniqueid_namareport (actual primary key)
            $query = DB::table('daily_loan_dinamis')
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
                });

            // Apply offset for pagination (cursor-based with lastUniqueid)
            if ($lastUniqueid !== null) {
                $query->where('uniqueid_namareport', '>', $lastUniqueid);
            }

            $uniqueids = $query
                ->orderBy('uniqueid_namareport')
                ->limit($chunkSize)
                ->pluck('uniqueid_namareport')
                ->toArray();

            if (empty($uniqueids)) {
                break;
            }

            $lastUniqueid = end($uniqueids);

            $idList = implode("','", $uniqueids);
            $chunkProcessed = $this->processChunk($period, $idList, $retryCount, $dryRun);

            if ($chunkProcessed === count($uniqueids)) {
                $processed += $chunkProcessed;
                $progressBar->advance(count($uniqueids));
            } else {
                $chunkFailed = count($uniqueids) - $chunkProcessed;
                $failed += $chunkFailed;
                $progressBar->advance($chunkProcessed);
                $this->errorLog[] = "Period {$period}, Chunk {$chunkNumber}: {$chunkFailed} rows failed";
            }

            if ($processed < $totalRows && !$dryRun) {
                usleep($delay * 1000); // Convert ms to microseconds
            }
        }

        $progressBar->finish();
        $this->newLine();

        $this->chunkStats[$period] = [
            'total' => $totalRows,
            'processed' => $processed,
            'failed' => $failed,
        ];

        $this->totalProcessed += $processed;
        $this->totalFailed += $failed;

        // Display period result
        $processed === $totalRows
            ? $this->line("   ✓ Period completed: <fg=green>{$processed}/{$totalRows}</> rows")
            : $this->line("   ⚠ Period completed with errors: <fg=yellow>{$processed}/{$totalRows}</> rows (failed: {$failed})");

        $this->newLine();
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
        $duration = now()->diffInSeconds($startTime);

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

    private function rebuildSnapshots(array $periods): void
    {
        if ($this->totalFailed > 0) {
            $this->warn('⚠ Skipping snapshot rebuild due to processing errors.');
            $this->line('  Please review errors and retry before rebuilding snapshots.');
            return;
        }

        $this->info('🔄 Rebuilding Performance RM snapshots...');

        foreach ($periods as $period) {
            $this->call('snapshot:rebuild-rm', [
                '--period' => $period,
            ]);
        }

        $this->info('✓ Snapshots rebuilt successfully');
        $this->info('🧹 Clearing report cache...');
        $this->call('cache:clear');

        $this->info('✓ All done! Reports should now display correctly.');
    }
}
