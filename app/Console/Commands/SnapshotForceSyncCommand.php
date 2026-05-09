<?php

namespace App\Console\Commands;

use App\Support\ReportDataSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SnapshotForceSyncCommand extends Command
{
    protected $signature = 'snapshot:force-sync {--period= : Periode tertentu (YYYY-MM-DD)} {--all : Sync semua tabel, tidak hanya yang berubah} {--sync-id= : ID untuk cache progress tracking}';

    protected $description = 'Force sync semua snapshot types sekaligus (Pinjaman, Simpanan, SSA, etc) setelah perubahan data manual';

    private const SYNC_TABLES = [
        'daily_loan_dinamis',
        'simpanan_multipn',
        'ssa_simpanan',
        'ssa_pinjaman',
        'lw325_ph',
        'performance_pis_per_produk',
    ];

    public function handle(): int
    {
        try {
            $period = trim((string) $this->option('period')) ?: null;
            $syncAll = (bool) $this->option('all');

            $syncId = trim((string) $this->option('sync-id')) ?: null;

            if (!$period) {
                $this->error('--period wajib diisi. Contoh: --period=2026-04-26');
                if ($syncId) $this->updateCache($syncId, 'failed', 100, 0, count(self::SYNC_TABLES), 0, '--period wajib diisi');
                return self::FAILURE;
            }

            $this->info("🔄 Starting force sync untuk period: {$period}");

            if ($syncAll) {
                $this->line('<fg=yellow>Mode: Sync ALL tables (tidak peduli apakah ada perubahan)</fg=yellow>');
            } else {
                $this->line('<fg=cyan>Mode: Smart sync (hanya tabel yang berubah)</fg=cyan>');
            }

            $this->line('');

            $syncService = app(ReportDataSyncService::class);
            $startTime = microtime(true);
            $syncCount = 0;
            $failCount = 0;
            $totalTables = count(self::SYNC_TABLES);

            if ($syncId) $this->updateCache($syncId, 'running', 0, 0, $totalTables, 0, 'Memulai proses sinkronisasi...');

            foreach (self::SYNC_TABLES as $index => $table) {
                try {
                    $this->line("  ▪ Syncing {$table}...");
                    
                    if ($syncId) {
                        $percent = (int) (($index / $totalTables) * 100);
                        $this->updateCache($syncId, 'running', $percent, $index, $totalTables, $failCount, "Sinkronisasi tabel {$table}...");
                    }

                    $syncService->syncImportedTable(
                        tableName: $table,
                        periodHint: $period,
                        jobId: null,
                        source: 'artisan:snapshot:force-sync',
                        deleteId: null,
                        rebuildId: null
                    );

                    $this->line("    <fg=green>✓ {$table} synced</fg=green>");
                    $syncCount++;
                } catch (\Throwable $e) {
                    $this->line("    <fg=red>✗ {$table} failed: {$e->getMessage()}</fg=red>");
                    Log::error("snapshot:force-sync failed for {$table}", [
                        'period' => $period,
                        'exception' => $e->getMessage(),
                    ]);
                    $failCount++;
                }
            }

            $elapsed = microtime(true) - $startTime;

            $this->line('');
            $this->line('<fg=cyan>═══════════════════════════════════════</fg=cyan>');
            $this->line("  Total: {$syncCount} synced, {$failCount} failed");
            $this->line("  Duration: " . number_format($elapsed, 2) . " seconds");
            $this->line('<fg=cyan>═══════════════════════════════════════</fg=cyan>');

            if ($failCount === 0) {
                $this->info("✓ Semua snapshot untuk periode {$period} telah di-sync!");
                if ($syncId) $this->updateCache($syncId, 'completed', 100, $syncCount, $totalTables, $failCount, 'Sinkronisasi seluruh tabel selesai dengan sukses.');
                return self::SUCCESS;
            }

            $this->warn("⚠ Ada {$failCount} tabel yang gagal di-sync. Periksa log untuk detail.");
            if ($syncId) $this->updateCache($syncId, 'warning', 100, $syncCount, $totalTables, $failCount, "Sinkronisasi selesai dengan {$failCount} kegagalan tabel.");
            return self::FAILURE;
        } catch (\Throwable $e) {
            $this->error('Force sync failed: ' . $e->getMessage());
            Log::error('SnapshotForceSyncCommand exception', ['message' => $e->getMessage()]);
            $syncId = trim((string) $this->option('sync-id')) ?: null;
            if ($syncId) $this->updateCache($syncId, 'failed', 100, 0, count(self::SYNC_TABLES), 0, 'Terjadi kesalahan sistem: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function updateCache(string $syncId, string $status, int $progress, int $completed, int $total, int $failed, string $message): void
    {
        $cacheKey = "snapshot_force_sync:{$syncId}";
        $state = \Illuminate\Support\Facades\Cache::get($cacheKey) ?: [];
        
        $state['status'] = $status;
        $state['progress'] = $progress;
        $state['completed_tables'] = $completed;
        $state['total_tables'] = $total;
        $state['failed_tables'] = $failed;
        $state['message'] = $message;
        $state['updated_at'] = now()->toIso8601String();
        
        \Illuminate\Support\Facades\Cache::put($cacheKey, $state, now()->addHours(6));
    }
}
