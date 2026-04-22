<?php

namespace App\Jobs;

use App\Support\DashboardHarianSnapshotService;
use App\Support\ManagedReportSnapshotRebuildStore;
use App\Support\ReportDataSyncService;
use App\Support\ReportSnapshotBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class RunManagedReportSnapshotRebuildJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 0;

    public function __construct(
        public bool $force = true,
        public ?string $source = null,
        public ?string $rebuildId = null
    ) {
    }

    public function uniqueId(): string
    {
        return 'all';
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('snapshot:managed-report:rebuild:all'))
                ->releaseAfter(10)
                ->expireAfter(10800),
        ];
    }

    public function handle(
        ReportSnapshotBuilder $snapshotBuilder,
        DashboardHarianSnapshotService $dashboardHarianSnapshotService,
        ReportDataSyncService $syncService
    ): void {
        $rebuildId = trim((string) $this->rebuildId);
        if ($rebuildId === '') {
            $rebuildId = (string) ManagedReportSnapshotRebuildStore::getActiveRebuildId();
        }

        if ($rebuildId === '') {
            $rebuildId = (string) Str::uuid();
        }

        $state = ManagedReportSnapshotRebuildStore::getState($rebuildId)
            ?? ManagedReportSnapshotRebuildStore::createInitialState($rebuildId, $this->force, $this->source ?? static::class);

        try {
            $plan = $snapshotBuilder->describeRebuildPlan();
            $reports = collect($plan['reports'] ?? [])
                ->map(function (array $report): array {
                    return [
                        'key' => (string) ($report['key'] ?? ''),
                        'label' => (string) ($report['label'] ?? ''),
                        'total_units' => max(0, (int) ($report['total_units'] ?? 0)),
                        'completed_units' => 0,
                        'current_period' => null,
                        'last_result_count' => 0,
                    ];
                })
                ->filter(fn (array $report): bool => $report['key'] !== '')
                ->values()
                ->all();

            $reportIndex = [];
            foreach ($reports as $index => $report) {
                $reportIndex[$report['key']] = $index;
            }

            $buildUnits = max(0, (int) ($plan['build_units'] ?? 0));
            $totalUnits = max(1, (int) ($plan['total_units'] ?? ($buildUnits + 1)));

            $state = $this->writeState(array_merge($state, [
                'status' => 'running',
                'stage' => 'planning',
                'queued' => false,
                'started_at' => $state['started_at'] ?? now()->toIso8601String(),
                'message' => $this->force
                    ? 'Menghitung rencana rebuild penuh snapshot report...'
                    : 'Menghitung rencana refresh snapshot report...',
                'build_units' => $buildUnits,
                'total_units' => $totalUnits,
                'completed_units' => 0,
                'progress_percent' => 0,
                'reports' => $reports,
                'results' => [],
                'current_report_key' => null,
                'current_report_label' => null,
                'current_period' => null,
                'report_completed_units' => 0,
                'report_total_units' => 0,
            ]));

            $results = [];

            $updateProgress = function (
                string $reportKey,
                string $reportLabel,
                ?string $currentPeriod,
                int $reportCompletedUnits,
                int $reportTotalUnits,
                int $currentResultCount = 0
            ) use (&$state, &$reports, $reportIndex, $totalUnits): void {
                if (isset($reportIndex[$reportKey])) {
                    $reportRow = $reports[$reportIndex[$reportKey]];
                    $reportRow['completed_units'] = max(0, $reportCompletedUnits);
                    $reportRow['total_units'] = max(0, $reportTotalUnits);
                    $reportRow['current_period'] = $currentPeriod;
                    $reportRow['last_result_count'] = max(0, $currentResultCount);
                    $reports[$reportIndex[$reportKey]] = $reportRow;
                }

                $completedBuildUnits = array_sum(array_map(
                    static fn (array $report): int => (int) ($report['completed_units'] ?? 0),
                    $reports
                ));

                $state = $this->writeState(array_merge($state, [
                    'status' => 'running',
                    'stage' => 'rebuilding',
                    'message' => $reportTotalUnits > 0
                        ? sprintf(
                            '%s: %d/%d periode selesai%s',
                            $reportLabel,
                            min($reportCompletedUnits, $reportTotalUnits),
                            $reportTotalUnits,
                            $currentPeriod ? ' (' . $currentPeriod . ')' : ''
                        )
                        : $reportLabel . ': tidak ada periode yang perlu dibangun.',
                    'reports' => array_values($reports),
                    'completed_units' => $completedBuildUnits,
                    'progress_percent' => $this->calculatePercent($completedBuildUnits, $totalUnits),
                    'current_report_key' => $reportKey,
                    'current_report_label' => $reportLabel,
                    'current_period' => $currentPeriod,
                    'report_completed_units' => max(0, $reportCompletedUnits),
                    'report_total_units' => max(0, $reportTotalUnits),
                ]));
            };

            $runRebuilder = function (string $key, string $label, callable $runner) use (
                &$state,
                &$results,
                &$reports,
                $reportIndex,
                $updateProgress
            ): void {
                $reportTotalUnits = isset($reportIndex[$key])
                    ? (int) ($reports[$reportIndex[$key]]['total_units'] ?? 0)
                    : 0;

                if ($reportTotalUnits <= 0) {
                    $updateProgress($key, $label, null, 0, 0, 0);
                    $results[$key] = [
                        'total_periods' => 0,
                        'total_result_count' => 0,
                    ];
                    return;
                }

                $state = $this->writeState(array_merge($state, [
                    'status' => 'running',
                    'stage' => 'rebuilding',
                    'current_report_key' => $key,
                    'current_report_label' => $label,
                    'current_period' => null,
                    'report_completed_units' => 0,
                    'report_total_units' => $reportTotalUnits,
                    'message' => 'Memulai ' . strtolower($label) . '...',
                ]));

                $rawResult = $runner(function (array $payload) use ($key, $label, $updateProgress): void {
                    $updateProgress(
                        $key,
                        $label,
                        $payload['current_period'] ?? null,
                        (int) ($payload['completed_units'] ?? 0),
                        (int) ($payload['total_units'] ?? 0),
                        (int) ($payload['current_result_count'] ?? 0)
                    );
                });

                $results[$key] = [
                    'total_periods' => is_array($rawResult) ? count($rawResult) : 0,
                    'total_result_count' => is_array($rawResult)
                        ? (int) array_sum(array_map(static fn ($value): int => (int) $value, $rawResult))
                        : 0,
                ];
            };

            $reportErrors = [];

            $safeRunRebuilder = function (string $key, string $label, callable $runner) use (
                &$reportErrors,
                &$state,
                &$reports,
                $reportIndex,
                $runRebuilder
            ): void {
                try {
                    $runRebuilder($key, $label, $runner);
                } catch (Throwable $e) {
                    $reportErrors[$key] = $e->getMessage();

                    if (isset($reportIndex[$key])) {
                        $reports[$reportIndex[$key]]['error'] = $e->getMessage();
                    }

                    Log::warning("Snapshot rebuild report '{$key}' gagal, dilanjutkan ke report berikutnya: " . $e->getMessage(), [
                        'key' => $key,
                        'label' => $label,
                        'rebuild_id' => $state['rebuild_id'] ?? null,
                    ]);
                }
            };

            $safeRunRebuilder('dashboard', 'Dashboard Pinjaman', fn (?callable $progress = null) => $snapshotBuilder->rebuildDashboard(null, $this->force, $progress));
            $safeRunRebuilder('dashboard_simpanan', 'Dashboard Simpanan', fn (?callable $progress = null) => $snapshotBuilder->rebuildDashboardSimpanan(null, $this->force, $progress));
            $safeRunRebuilder('dashboard_harian', 'Dashboard Harian', fn (?callable $progress = null) => $dashboardHarianSnapshotService->rebuild(null, $this->force, $progress));
            $safeRunRebuilder('rasio', 'Rasio CASA Debitur', fn (?callable $progress = null) => $snapshotBuilder->rebuildRasioCasa(null, $this->force, $progress));
            $safeRunRebuilder('dormant', 'Rekening Dormant', fn (?callable $progress = null) => $snapshotBuilder->rebuildRekeningDormant(null, $this->force, $progress));
            $safeRunRebuilder('new_payroll', 'Performance New Payroll', fn (?callable $progress = null) => $snapshotBuilder->rebuildPerformanceNewPayroll(null, $this->force, $progress));

            $completedBuildUnits = array_sum(array_map(
                static fn (array $report): int => (int) ($report['completed_units'] ?? 0),
                $reports
            ));

            $state = $this->writeState(array_merge($state, [
                'status' => 'running',
                'stage' => 'cache',
                'message' => 'Menyegarkan cache report dan menjadwalkan cache warm-up...',
                'reports' => array_values($reports),
                'results' => $results,
                'completed_units' => $completedBuildUnits,
                'progress_percent' => $this->calculatePercent($completedBuildUnits, $totalUnits),
                'current_report_key' => null,
                'current_report_label' => null,
                'current_period' => null,
                'report_completed_units' => 0,
                'report_total_units' => 0,
            ]));

            $syncService->invalidateReportCaches($this->source ?? static::class);
            WarmReportCacheJob::dispatch();

            $hasErrors = $reportErrors !== [];
            $finalStatus = $hasErrors ? 'warning' : 'completed';
            $finalMessage = $hasErrors
                ? sprintf(
                    '%d dari %d report gagal direbuild (%s). Report lain tetap diperbarui.',
                    count($reportErrors),
                    count($reports),
                    implode(', ', array_keys($reportErrors))
                )
                : ($this->force
                    ? 'Rebuild snapshot seluruh report dari awal selesai.'
                    : 'Refresh snapshot seluruh report selesai.');

            $state = $this->writeState(array_merge($state, [
                'status' => $finalStatus,
                'stage' => 'completed',
                'message' => $finalMessage,
                'results' => $results,
                'errors' => $reportErrors,
                'completed_units' => $totalUnits,
                'progress_percent' => 100,
                'finished_at' => now()->toIso8601String(),
            ]));

            if ($hasErrors) {
                Log::warning('Rebuild snapshot report management selesai dengan sebagian error.', [
                    'force' => $this->force,
                    'rebuild_id' => $rebuildId,
                    'failed_reports' => $reportErrors,
                ]);
            }
        } catch (Throwable $e) {
            $state = $this->writeState(array_merge($state, [
                'status' => 'failed',
                'stage' => 'failed',
                'message' => 'Rebuild snapshot gagal: ' . $e->getMessage(),
                'error' => $e->getMessage(),
                'finished_at' => now()->toIso8601String(),
            ]));

            Log::warning('Rebuild snapshot report management gagal: ' . $e->getMessage(), [
                'force' => $this->force,
                'source' => $this->source,
                'rebuild_id' => $rebuildId,
            ]);

            throw $e;
        } finally {
            $currentPending = Cache::get(ManagedReportSnapshotRebuildStore::PENDING_KEY);
            if ($currentPending === $rebuildId) {
                Cache::forget(ManagedReportSnapshotRebuildStore::PENDING_KEY);
            }

            if (ManagedReportSnapshotRebuildStore::getActiveRebuildId() === $rebuildId) {
                ManagedReportSnapshotRebuildStore::forgetActiveRebuildId();
            }
        }
    }

    private function writeState(array $state): array
    {
        return ManagedReportSnapshotRebuildStore::putState($state);
    }

    private function calculatePercent(int $completedUnits, int $totalUnits): int
    {
        if ($totalUnits <= 0) {
            return 0;
        }

        return max(0, min(100, (int) floor(($completedUnits / $totalUnits) * 100)));
    }
}
