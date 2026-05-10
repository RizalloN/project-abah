<?php

namespace App\Jobs;

use App\Jobs\Middleware\DeferSnapshotJobsDuringImport;
use App\Support\ReportDataSyncService;
use Carbon\Carbon;
use App\Support\SnapshotAuditService;
use App\Support\SnapshotBatchAggregator;
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
            new DeferSnapshotJobsDuringImport(),
        ];
    }

    public function handle(
        SnapshotAuditService $auditService,
        ReportDataSyncService $syncService,
        SnapshotBatchAggregator $batchAggregator
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
            $this->deleteAffectedSnapshots($tableName, $periods);

            foreach ($periods as $period) {
                try {
                    $batchAggregator->registerSyncRequest(
                        tableName: $tableName,
                        periodHint: $period,
                        jobId: null,
                        source: static::class,
                        rebuildId: $this->auditId
                    );

                    Log::debug('Registered period for batched snapshot sync.', [
                        'table_name' => $tableName,
                        'period' => $period,
                    ]);
                } catch (Throwable $e) {
                    Log::error('Failed to register period for batched sync: ' . $e->getMessage(), [
                        'table_name' => $tableName,
                        'period' => $period,
                        'exception' => $e::class,
                    ]);
                }
            }

            $elapsed = round(microtime(true) - $startTime, 2);

            Log::info('Completed smart partial snapshot rebuild (batched).', [
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

    private function deleteAffectedSnapshots(string $tableName, array $periods): void
    {
        $normalizedTable = strtolower(trim($tableName));

        match ($normalizedTable) {
            'daily_loan_dinamis' => $this->deleteDailyLoanSnapshots($periods),
            'simpanan_multipn' => $this->deleteSimpananSnapshots($periods),
            'ssa_simpanan' => $this->deleteSsaSimpananSnapshots($periods),
            'ssa_pinjaman' => $this->deleteDashboardHarianSnapshots($periods),
            'lw325_ph' => $this->deleteDashboardHarianSnapshotsForPhPeriods($periods),
            default => null,
        };
    }

    private function deleteDailyLoanSnapshots(array $periods): void
    {
        DB::table('dashboard_pinjaman_snapshots')
            ->whereIn('periode', $periods)
            ->delete();

        DB::table('dashboard_pinjaman_chart_periodik_snapshots')
            ->whereIn('periode', $periods)
            ->delete();

        DB::table('dashboard_harian_snapshots')
            ->whereIn('snapshot_period', $periods)
            ->delete();
    }

    private function deleteSimpananSnapshots(array $periods): void
    {
        DB::table('dashboard_simpanan_snapshots')
            ->whereIn('snapshot_period', $periods)
            ->delete();

        DB::table('dashboard_simpanan_branch_snapshots')
            ->whereIn('snapshot_period', $periods)
            ->delete();

        DB::table('dashboard_harian_snapshots')
            ->whereIn('snapshot_period', $periods)
            ->delete();

        DB::table('rasio_casa_debitur_snapshots')
            ->whereIn('casa_period', $periods)
            ->delete();

        DB::table('rasio_casa_debitur_uker_snapshots')
            ->whereIn('casa_period', $periods)
            ->delete();
    }

    private function deleteSsaSimpananSnapshots(array $periods): void
    {
        DB::table('ssa_simpanan_snapshots')
            ->whereIn('periode', $periods)
            ->delete();
    }

    private function deleteDashboardHarianSnapshots(array $periods): void
    {
        DB::table('dashboard_harian_snapshots')
            ->whereIn('snapshot_period', $periods)
            ->delete();
    }

    private function deleteDashboardHarianSnapshotsForPhPeriods(array $phPeriods): void
    {
        foreach ($phPeriods as $phPeriod) {
            try {
                $phDate = Carbon::parse($phPeriod);
                $nextMonthStart = $phDate->copy()->addDay()->toDateString();
                $nextMonthEnd = $phDate->copy()->addMonthNoOverflow()->endOfMonth()->toDateString();

                DB::table('dashboard_harian_snapshots')
                    ->whereBetween('snapshot_period', [$nextMonthStart, $nextMonthEnd])
                    ->delete();
            } catch (Throwable) {
                Log::warning('Could not resolve next-month range for lw325_ph period, skipping invalidation.', [
                    'ph_period' => $phPeriod,
                ]);
            }
        }
    }

}

