<?php

namespace App\Jobs;

use App\Support\ReportDataSyncService;
use App\Support\SnapshotAuditService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SmartPartialSnapshotRebuildJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 0;

    public function __construct(
        public string $tableName,
        public array $affectedPeriods = [],
        public ?string $auditId = null,
    ) {
    }

    public function middleware(): array
    {
        $scope = strtolower(trim($this->tableName)) . ':partial-rebuild';

        return [
            (new WithoutOverlapping('snapshot:' . $scope))
                ->releaseAfter(5)
                ->expireAfter(600),
        ];
    }

    public function handle(
        SnapshotAuditService $auditService,
        ReportDataSyncService $syncService
    ): void {
        $tableName = trim($this->tableName);
        $periods = array_filter(array_map('trim', $this->affectedPeriods));

        if (empty($periods)) {
            Log::warning('SmartPartialSnapshotRebuildJob called with no affected periods.', [
                'table_name' => $tableName,
            ]);

            return;
        }

        Log::info('Starting smart partial snapshot rebuild.', [
            'table_name' => $tableName,
            'affected_periods' => $periods,
            'period_count' => count($periods),
            'audit_id' => $this->auditId,
        ]);

        $startTime = microtime(true);

        try {
            foreach ($periods as $period) {
                try {
                    Log::info('Rebuilding snapshot for period.', [
                        'table_name' => $tableName,
                        'period' => $period,
                    ]);

                    $this->rebuildForPeriod($tableName, $period, $syncService);

                    Log::info('Successfully rebuilt snapshot for period.', [
                        'table_name' => $tableName,
                        'period' => $period,
                    ]);
                } catch (Throwable $e) {
                    Log::error('Failed to rebuild snapshot for period: ' . $e->getMessage(), [
                        'table_name' => $tableName,
                        'period' => $period,
                        'exception' => $e::class,
                    ]);
                }
            }

            $elapsed = round(microtime(true) - $startTime, 2);

            Log::info('Completed smart partial snapshot rebuild.', [
                'table_name' => $tableName,
                'periods_processed' => count($periods),
                'elapsed_seconds' => $elapsed,
                'audit_id' => $this->auditId,
            ]);
        } catch (Throwable $e) {
            Log::error('Fatal error in smart partial snapshot rebuild: ' . $e->getMessage(), [
                'table_name' => $tableName,
                'exception' => $e::class,
            ]);

            throw $e;
        }
    }

    private function rebuildForPeriod(string $tableName, string $period, ReportDataSyncService $syncService): void
    {
        $normalizedTable = strtolower(trim($tableName));

        match ($normalizedTable) {
            'daily_loan_dinamis' => $this->rebuildDailyLoanPeriod($period, $syncService),
            'simpanan_multipn' => $this->rebuildSimpananPeriod($period, $syncService),
            'ssa_simpanan' => $this->rebuildSsaSimpananPeriod($period, $syncService),
            'ssa_pinjaman' => $this->rebuildSsaPinjamanPeriod($period, $syncService),
            'lw325_ph' => $this->rebuildLw325PhPeriod($period, $syncService),
            default => Log::warning('Unknown table for partial rebuild: ' . $tableName),
        };
    }

    private function rebuildDailyLoanPeriod(string $period, ReportDataSyncService $syncService): void
    {
        DB::table('dashboard_pinjaman_snapshots')
            ->where('snapshot_period', $period)
            ->delete();

        DB::table('dashboard_harian_snapshots')
            ->where('snapshot_period', $period)
            ->delete();

        $syncService->syncImportedTable(
            tableName: 'daily_loan_dinamis',
            periodHint: $period,
            jobId: null,
            source: static::class
        );
    }

    private function rebuildSimpananPeriod(string $period, ReportDataSyncService $syncService): void
    {
        DB::table('dashboard_simpanan_snapshots')
            ->where('snapshot_period', $period)
            ->delete();

        DB::table('dashboard_simpanan_branch_snapshots')
            ->where('snapshot_period', $period)
            ->delete();

        DB::table('dashboard_harian_snapshots')
            ->where('snapshot_period', $period)
            ->delete();

        $syncService->syncImportedTable(
            tableName: 'simpanan_multipn',
            periodHint: $period,
            jobId: null,
            source: static::class
        );
    }

    private function rebuildSsaSimpananPeriod(string $period, ReportDataSyncService $syncService): void
    {
        DB::table('ssa_simpanan_snapshots')
            ->where('periode', $period)
            ->delete();

        $syncService->syncImportedTable(
            tableName: 'ssa_simpanan',
            periodHint: $period,
            jobId: null,
            source: static::class
        );
    }

    private function rebuildSsaPinjamanPeriod(string $period, ReportDataSyncService $syncService): void
    {
        DB::table('ssa_pinjaman_snapshots')
            ->where('periode', $period)
            ->delete();

        $syncService->syncImportedTable(
            tableName: 'ssa_pinjaman',
            periodHint: $period,
            jobId: null,
            source: static::class
        );
    }

    private function rebuildLw325PhPeriod(string $period, ReportDataSyncService $syncService): void
    {
        DB::table('lw325_ph_snapshots')
            ->where('periode', $period)
            ->delete();

        $syncService->syncImportedTable(
            tableName: 'lw325_ph',
            periodHint: $period,
            jobId: null,
            source: static::class
        );
    }
}
