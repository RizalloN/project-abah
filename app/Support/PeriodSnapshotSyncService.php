<?php

namespace App\Support;

use App\Jobs\WarmLandingLoanRiskCacheJob;
use App\Jobs\WarmReportCacheJob;
use App\Models\NamaReport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * Force-rebuilds every snapshot of one selected period, but only for sources
 * that actually hold rows for exactly that date. A source without data for the
 * period is skipped (never treated as "deleted"), so a sync can not wipe
 * snapshots or silently fall back to an older date.
 */
class PeriodSnapshotSyncService
{
    public const STATE_PREFIX = 'snapshot_force_sync:';

    /** Snapshot steps in dependency order: loan sources first, composites last. */
    private const STEPS = [
        'dashboard' => ['label' => 'Dashboard Pinjaman', 'source' => 'daily_loan_dinamis', 'column' => 'periode'],
        'chart_periodik' => ['label' => 'Chart Periodik Pinjaman', 'source' => 'daily_loan_dinamis', 'column' => 'periode'],
        'kinerja_rm' => ['label' => 'Kinerja RM', 'source' => 'daily_loan_dinamis', 'column' => 'periode'],
        'rasio' => ['label' => 'Rasio CASA Debitur', 'source' => 'daily_loan_dinamis', 'column' => 'periode'],
        'dashboard_simpanan' => ['label' => 'Dashboard Simpanan', 'source' => 'simpanan_multipn', 'column' => 'posisi'],
        'dormant' => ['label' => 'Rekening Dormant', 'source' => 'simpanan_multipn', 'column' => 'posisi'],
        'new_payroll' => ['label' => 'Performance New Payroll', 'source' => 'performance_pis_per_produk', 'column' => 'posisi'],
        'ssa_simpanan' => ['label' => 'SSA Simpanan', 'source' => 'ssa_simpanan', 'column' => 'Month_Day_Year_of_Posisi'],
        'dashboard_harian' => ['label' => 'Dashboard Harian', 'source' => null, 'column' => null],
    ];

    public function __construct(
        private readonly ReportSnapshotBuilder $snapshotBuilder,
        private readonly DashboardHarianSnapshotService $dashboardHarian,
        private readonly SsaSimpananSnapshotBuilder $ssaSimpananBuilder,
        private readonly ManagedReportManagementService $reportManagement
    ) {
    }

    /**
     * Accepts dd-mm-yyyy (UI format) or yyyy-mm-dd and returns yyyy-mm-dd.
     */
    public static function normalizePeriod(?string $value): ?string
    {
        $value = trim((string) $value);
        foreach (['d-m-Y' => '/^\d{2}-\d{2}-\d{4}$/', 'd/m/Y' => '/^\d{2}\/\d{2}\/\d{4}$/', 'Y-m-d' => '/^\d{4}-\d{2}-\d{2}$/'] as $format => $pattern) {
            if (preg_match($pattern, $value) !== 1) {
                continue;
            }

            $date = Carbon::createFromFormat('!' . $format, $value);
            $errors = Carbon::getLastErrors();
            if ($date === false || (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
                || $date->format($format) !== $value) {
                return null;
            }

            return $date->toDateString();
        }

        return null;
    }

    /**
     * @return array{period: string, steps: array<int, array<string, mixed>>, reports: array<int, array<string, mixed>>}
     */
    public function plan(string $period): array
    {
        $sourceHasData = [];
        $steps = [];
        foreach (self::STEPS as $key => $definition) {
            if ($key === 'dashboard_harian') {
                $available = ($this->dashboardHarian->describeRebuildPlan($period)['periods'] ?? []) === [$period];
            } else {
                $source = (string) $definition['source'];
                $sourceHasData[$source] ??= $this->tableHasPeriod($source, (string) $definition['column'], $period);
                $available = $sourceHasData[$source];
            }

            $steps[] = [
                'key' => $key,
                'label' => $definition['label'],
                'source' => $definition['source'],
                'available' => $available,
            ];
        }

        return [
            'period' => $period,
            'steps' => $steps,
            'reports' => $this->reportsWithPeriod($period),
        ];
    }

    /**
     * @param  callable(array<string, mixed>): void|null  $progress
     * @return array<string, mixed>
     */
    public function run(string $period, ?callable $progress = null): array
    {
        $lock = Cache::lock('snapshot:period-sync:' . $period, 10800);
        if (! $lock->get()) {
            throw new RuntimeException("Sinkronisasi periode {$this->displayDate($period)} masih berjalan di proses lain.");
        }

        try {
            $plan = $this->plan($period);
            $runnable = array_values(array_filter($plan['steps'], fn (array $step): bool => $step['available']));
            $total = count($runnable) + 1;
            $results = [];
            $errors = [];

            foreach ($runnable as $index => $step) {
                $this->emit($progress, $index, $total, 'Membangun ulang ' . $step['label'] . '...', $plan);
                try {
                    $results[$step['key']] = $this->runStep($step['key'], $period);
                } catch (Throwable $e) {
                    $errors[$step['key']] = $e->getMessage();
                    Log::warning('Sinkronisasi periode: snapshot gagal dibangun ulang.', [
                        'period' => $period,
                        'step' => $step['key'],
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $this->emit($progress, $total - 1, $total, 'Menyegarkan cache seluruh report...', $plan);
            foreach (['pinjaman', 'simpanan', 'harian', 'global'] as $scope) {
                ReportCacheVersion::bump($scope);
            }
            $this->dispatchWarmups($period, $plan);

            return [
                'plan' => $plan,
                'results' => $results,
                'errors' => $errors,
                'rebuilt' => count($runnable) - count($errors),
                'total_steps' => $total,
            ];
        } finally {
            $lock->release();
        }
    }

    public function displayDate(string $period): string
    {
        return Carbon::parse($period)->format('d-m-Y');
    }

    private function runStep(string $key, string $period): int
    {
        $result = match ($key) {
            'dashboard' => $this->snapshotBuilder->rebuildDashboard($period, true),
            'chart_periodik' => $this->snapshotBuilder->rebuildChartPeriodik($period, true),
            'kinerja_rm' => $this->snapshotBuilder->rebuildPerformanceRm($period, true),
            'rasio' => $this->snapshotBuilder->rebuildRasioCasa($period, true),
            'dashboard_simpanan' => $this->snapshotBuilder->rebuildDashboardSimpanan($period, true),
            'dormant' => $this->snapshotBuilder->rebuildRekeningDormant($period, true),
            'new_payroll' => $this->snapshotBuilder->rebuildPerformanceNewPayroll($period, true),
            'ssa_simpanan' => $this->ssaSimpananBuilder->rebuild($period, true),
            'dashboard_harian' => $this->dashboardHarian->rebuild($period, true),
        };

        if ($key === 'ssa_simpanan') {
            if (empty($result['success'])) {
                throw new RuntimeException((string) ($result['message'] ?? 'Rebuild SSA Simpanan gagal.'));
            }

            return (int) ($result['records_inserted'] ?? 0);
        }

        $periods = array_keys((array) $result);
        if ($periods !== [] && $periods !== [$period]) {
            // Builders must stay on the requested date; anything else is a bug.
            throw new RuntimeException('Builder memproses periode lain: ' . implode(', ', $periods));
        }

        return (int) array_sum(array_map('intval', (array) $result));
    }

    /**
     * Every active report that holds rows for the period (cache refresh only
     * for reports without a snapshot builder).
     *
     * @return array<int, array<string, mixed>>
     */
    private function reportsWithPeriod(string $period): array
    {
        $reports = [];
        if (! Schema::hasTable('nama_report')) {
            return $reports;
        }

        foreach (NamaReport::query()->where('active', 1)->orderBy('id_report')->get() as $report) {
            $table = trim((string) $report->table_name);
            if ($table === '' || ! Schema::hasTable($table)) {
                continue;
            }

            try {
                [$periodColumn] = $this->reportManagement->resolveManagementScopeColumns($table, Schema::getColumnListing($table));
                if ($periodColumn === null || ! $this->tableHasPeriod($table, (string) $periodColumn, $period)) {
                    continue;
                }
            } catch (Throwable $e) {
                Log::warning('Sinkronisasi periode: ketersediaan periode report gagal dicek.', [
                    'table' => $table,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            $reports[] = [
                'id_report' => (int) $report->id_report,
                'name' => (string) ($report->nama_report ?? $table),
                'table' => $table,
                'period_column' => (string) $periodColumn,
            ];
        }

        return $reports;
    }

    private function tableHasPeriod(string $table, string $column, string $period): bool
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return false;
        }

        $type = strtolower((string) (DB::getDriverName() === 'mysql'
            ? DB::table('information_schema.COLUMNS')
                ->where('TABLE_SCHEMA', DB::getDatabaseName())
                ->where('TABLE_NAME', $table)
                ->where('COLUMN_NAME', $column)
                ->value('DATA_TYPE')
            : 'date'));

        $query = DB::table($table);
        if (in_array($type, ['datetime', 'timestamp'], true)) {
            $query->where($column, '>=', $period . ' 00:00:00')
                ->where($column, '<', Carbon::parse($period)->addDay()->toDateString() . ' 00:00:00');
        } elseif ($type === 'date') {
            $query->where($column, $period);
        } else {
            // Text period columns hold several spellings of the same date.
            $date = Carbon::parse($period);
            $query->whereIn($column, array_values(array_unique([
                $period, $date->format('d-m-Y'), $date->format('d/m/Y'), $date->format('Ymd'), $date->format('Y-m-d 00:00:00'),
            ])));
        }

        return $query->limit(1)->exists();
    }

    private function dispatchWarmups(string $period, array $plan): void
    {
        if (config('queue.default') === 'sync') {
            return;
        }

        try {
            WarmReportCacheJob::dispatch();
            $loanAvailable = collect($plan['steps'])->contains(fn (array $step): bool => $step['key'] === 'dashboard' && $step['available']);
            if ($loanAvailable) {
                WarmLandingLoanRiskCacheJob::dispatch($period);
            }
        } catch (Throwable $e) {
            Log::warning('Sinkronisasi periode: warm-up cache gagal dijadwalkan.', ['error' => $e->getMessage()]);
        }
    }

    private function emit(?callable $progress, int $completed, int $total, string $message, array $plan): void
    {
        if ($progress === null) {
            return;
        }

        $progress([
            'completed' => $completed,
            'total' => $total,
            'percent' => (int) floor(($completed / max(1, $total)) * 100),
            'message' => $message,
            'plan' => $plan,
        ]);
    }
}
