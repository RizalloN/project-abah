<?php

namespace App\Console\Commands;

use App\Support\ConsumerRmPositionHistoryStore;
use App\Support\ReportCacheVersion;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class PruneReportDailyHistoryCommand extends Command
{
    private ?CarbonImmutable $deadline = null;

    private const TARGETS = [
        'daily_loan_dinamis' => 'periode',
        'lw325_ph' => 'periode',
        'lw321pn' => 'periode',
        'simpanan_multipn' => 'posisi',
        'hourly_dpk' => 'posisi',
        'dly_kap_resegmentasi' => 'periode',
        'dashboard_pinjaman_snapshots' => 'periode',
        'dashboard_pinjaman_chart_periodik_snapshots' => 'periode',
    ];

    /**
     * These sources deliberately retain their complete history. They drive
     * presentation and dashboard time-series, so monthly-only retention here
     * would silently remove points used by the charts.
     */
    private const TIMESERIES_EXCLUDED_TABLES = [
        'ssa_simpanan',
        'ssa_pinjaman',
        'gi405_recovery',
    ];

    private const ACTIVE_IMPORT_STATUSES = ['queued', 'staging', 'processing'];

    protected $signature = 'reports:prune-daily-history
        {--execute : Jalankan penghapusan; tanpa opsi ini command hanya menampilkan dry-run}
        {--keep-full-month=* : Bulan yang dipertahankan lengkap dalam format YYYY-MM}
        {--chunk=50000 : Jumlah maksimum baris per transaksi DELETE}
        {--sleep-ms=25 : Jeda antarbatch untuk mengurangi tekanan I/O}
        {--lock-retries=5 : Jumlah retry untuk lock wait timeout atau deadlock transient}
        {--retry-sleep-ms=1000 : Jeda dasar retry lock dalam milidetik}
        {--max-runtime=0 : Batas menit eksekusi; 0 berarti tanpa batas dan cocok untuk maintenance manual}';

    protected $description = 'Sisakan posisi terakhir per bulan, pertahankan dua bulan terbaru, dan kecualikan sumber timeseries';

    public function handle(): int
    {
        $chunkSize = max(1_000, min(200_000, (int) $this->option('chunk')));
        $sleepMilliseconds = max(0, min(5_000, (int) $this->option('sleep-ms')));
        $lockRetries = max(0, min(20, (int) $this->option('lock-retries')));
        $retrySleepMilliseconds = max(100, min(30_000, (int) $this->option('retry-sleep-ms')));
        $maxRuntimeMinutes = max(0, min(720, (int) $this->option('max-runtime')));
        $this->deadline = $maxRuntimeMinutes > 0
            ? CarbonImmutable::now()->addMinutes($maxRuntimeMinutes)
            : null;

        try {
            $protectedMonths = $this->resolveProtectedMonths();
            $this->assertTargetSchemaIsSafe();
            $plan = $this->buildPlan($protectedMonths);
        } catch (Throwable $exception) {
            $this->error('Audit retensi gagal: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->displayPlan($plan, $protectedMonths);

        if (! $this->option('execute')) {
            $this->newLine();
            $this->info('DRY-RUN selesai. Tidak ada baris yang dihapus.');

            return self::SUCCESS;
        }

        $lock = Cache::lock('maintenance:reports:prune-daily-history', 86_400);
        if (! $lock->get()) {
            $this->error('Pembersihan lain sedang berjalan. Command dihentikan tanpa mengubah data.');

            return self::FAILURE;
        }

        $audit = [
            'status' => 'running',
            'started_at' => now()->toIso8601String(),
            'finished_at' => null,
            'protected_months' => $protectedMonths,
            'chunk_size' => $chunkSize,
            'sleep_ms' => $sleepMilliseconds,
            'lock_retries' => $lockRetries,
            'retry_sleep_ms' => $retrySleepMilliseconds,
            'max_runtime_minutes' => $maxRuntimeMinutes,
            'planned_delete_rows' => $this->sumPlanValue($plan, 'delete_rows'),
            'deleted_rows' => 0,
            'tables' => [],
            'error' => null,
        ];
        $cacheInvalidatedTables = [];
        $auditPath = $this->makeAuditPath();
        $this->persistAudit($auditPath, $audit);

        try {
            $this->assertNoActiveImports();
            $this->newLine();
            $this->warn('Eksekusi dimulai. Setiap batch berdiri sendiri dan dapat dilanjutkan ulang dengan aman.');

            foreach ($plan as $table => $tablePlan) {
                $deletedForTable = $this->pruneTable(
                    $table,
                    $tablePlan,
                    $chunkSize,
                    $sleepMilliseconds,
                    $lockRetries,
                    $retrySleepMilliseconds,
                    $audit,
                    $auditPath
                );

                if ($deletedForTable > 0) {
                    $this->bumpRelevantCacheVersions($table);
                    $cacheInvalidatedTables[] = $table;
                }

                if ($this->runtimeLimitReached()) {
                    break;
                }
            }

            if ($this->runtimeLimitReached()) {
                $audit['status'] = 'partial';
                $audit['finished_at'] = now()->toIso8601String();
                $audit['cache_invalidated_tables'] = $cacheInvalidatedTables;
                $audit['remaining_candidate_rows'] = null;
                $this->persistAudit($auditPath, $audit);

                $this->warn('Batas durasi tercapai. Pembersihan parsial tersimpan dan akan dilanjutkan pada eksekusi berikutnya.');
                $this->line('Audit: '.$auditPath);

                return self::SUCCESS;
            }

            $remainingPlan = $this->buildPlan($protectedMonths);
            $remainingRows = $this->sumPlanValue($remainingPlan, 'delete_rows');
            if ($remainingRows !== 0) {
                throw new RuntimeException(
                    "Validasi akhir menemukan {$remainingRows} baris historis yang masih menjadi kandidat."
                );
            }

            $audit['status'] = 'completed';
            $audit['finished_at'] = now()->toIso8601String();
            $audit['remaining_candidate_rows'] = 0;
            $audit['cache_invalidated_tables'] = $cacheInvalidatedTables;
            $this->persistAudit($auditPath, $audit);

            $this->newLine();
            $this->info('Pembersihan selesai dan validasi retensi lulus.');
            $this->line('Audit: '.$auditPath);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            foreach ($audit['tables'] as $table => $tableAudit) {
                if (($tableAudit['deleted_rows'] ?? 0) > 0 && ! in_array($table, $cacheInvalidatedTables, true)) {
                    $this->bumpRelevantCacheVersions($table);
                    $cacheInvalidatedTables[] = $table;
                }
            }

            $audit['status'] = 'failed';
            $audit['finished_at'] = now()->toIso8601String();
            $audit['cache_invalidated_tables'] = $cacheInvalidatedTables;
            $audit['error'] = $exception->getMessage();
            $this->persistAudit($auditPath, $audit);
            Log::error('Report daily-history pruning failed.', [
                'audit_path' => $auditPath,
                'exception' => $exception,
            ]);

            $this->error('Pembersihan dihentikan dengan aman: '.$exception->getMessage());
            $this->line('Progress parsial tercatat di: '.$auditPath);

            return self::FAILURE;
        } finally {
            $this->releaseLock($lock);
        }
    }

    /**
     * @return array<string, array{
     *     period_column: string,
     *     total_rows: int,
     *     keep_rows: int,
     *     delete_rows: int,
     *     periods: int,
     *     delete_periods: array<int, array{date: string, month: string, rows: int}>,
     *     monthly: array<string, array{latest: string, periods: int, rows: int, latest_rows: int, mode: string}>
     * }>
     */
    private function buildPlan(array $protectedMonths): array
    {
        $plan = [];

        foreach (self::TARGETS as $table => $periodColumn) {
            $periodRows = DB::table($table)
                ->select($periodColumn)
                ->selectRaw('COUNT(*) AS row_count')
                ->whereNotNull($periodColumn)
                ->groupBy($periodColumn)
                ->orderBy($periodColumn)
                ->get();

            $monthly = [];
            $normalizedRows = [];
            foreach ($periodRows as $row) {
                $date = CarbonImmutable::parse((string) $row->{$periodColumn})->format('Y-m-d');
                $month = substr($date, 0, 7);
                $rowCount = (int) $row->row_count;

                $normalizedRows[] = [
                    'date' => $date,
                    'month' => $month,
                    'rows' => $rowCount,
                ];
                $monthly[$month] ??= [
                    'latest' => $date,
                    'periods' => 0,
                    'rows' => 0,
                    'latest_rows' => 0,
                    'mode' => in_array($month, $protectedMonths, true) ? 'FULL' : 'MONTH_END',
                ];
                $monthly[$month]['periods']++;
                $monthly[$month]['rows'] += $rowCount;

                if ($date >= $monthly[$month]['latest']) {
                    $monthly[$month]['latest'] = $date;
                    $monthly[$month]['latest_rows'] = $rowCount;
                }
            }

            $deletePeriods = array_values(array_filter(
                $normalizedRows,
                static fn (array $period): bool => ! in_array($period['month'], $protectedMonths, true)
                    && $period['date'] !== $monthly[$period['month']]['latest']
            ));
            $totalRows = array_sum(array_column($normalizedRows, 'rows'));
            $deleteRows = array_sum(array_column($deletePeriods, 'rows'));

            $plan[$table] = [
                'period_column' => $periodColumn,
                'total_rows' => $totalRows,
                'keep_rows' => $totalRows - $deleteRows,
                'delete_rows' => $deleteRows,
                'periods' => count($normalizedRows),
                'delete_periods' => $deletePeriods,
                'monthly' => $monthly,
            ];
        }

        return $plan;
    }

    private function pruneTable(
        string $table,
        array $tablePlan,
        int $chunkSize,
        int $sleepMilliseconds,
        int $lockRetries,
        int $retrySleepMilliseconds,
        array &$audit,
        string $auditPath
    ): int {
        $periodColumn = $tablePlan['period_column'];
        $tableDeleted = 0;
        $audit['tables'][$table] = [
            'period_column' => $periodColumn,
            'planned_delete_rows' => $tablePlan['delete_rows'],
            'deleted_rows' => 0,
            'completed_periods' => [],
        ];

        if ($tablePlan['delete_rows'] === 0) {
            $this->line("{$table}: tidak ada baris yang perlu dihapus.");
            $this->persistAudit($auditPath, $audit);

            return 0;
        }

        $this->newLine();
        $this->info(sprintf(
            '%s: menghapus %s baris dari %d periode historis.',
            $table,
            number_format($tablePlan['delete_rows'], 0, ',', '.'),
            count($tablePlan['delete_periods'])
        ));

        foreach ($tablePlan['delete_periods'] as $period) {
            $periodDeleted = 0;
            $batchNumber = 0;

            if ($table === 'daily_loan_dinamis') {
                $this->captureAndVerifyDailyLoanArchive($period['date']);
            }

            do {
                $this->assertNoActiveImports();
                if ($this->runtimeLimitReached()) {
                    return $tableDeleted;
                }

                $deleted = $this->deleteBatchWithRetry(
                    $table,
                    $periodColumn,
                    $period['date'],
                    $chunkSize,
                    $lockRetries,
                    $retrySleepMilliseconds
                );

                $deleted = (int) $deleted;
                $periodDeleted += $deleted;
                $tableDeleted += $deleted;
                $audit['deleted_rows'] += $deleted;
                $audit['tables'][$table]['deleted_rows'] += $deleted;
                $batchNumber++;

                if ($deleted > 0 && ($batchNumber === 1 || $batchNumber % 10 === 0)) {
                    $this->line(sprintf(
                        '  %s: %s baris terhapus...',
                        $period['date'],
                        number_format($periodDeleted, 0, ',', '.')
                    ));
                }

                if ($deleted > 0 && $sleepMilliseconds > 0) {
                    usleep($sleepMilliseconds * 1_000);
                }
            } while ($deleted > 0);

            if ($this->runtimeLimitReached()) {
                return $tableDeleted;
            }

            $remaining = (int) DB::table($table)
                ->where($periodColumn, $period['date'])
                ->count();
            if ($remaining !== 0) {
                throw new RuntimeException(
                    "{$table} periode {$period['date']} masih memiliki {$remaining} baris setelah penghapusan."
                );
            }

            $audit['tables'][$table]['completed_periods'][] = [
                'date' => $period['date'],
                'planned_rows' => $period['rows'],
                'deleted_rows' => $periodDeleted,
            ];
            $this->persistAudit($auditPath, $audit);
            $this->line(sprintf(
                '  %s selesai: %s baris.',
                $period['date'],
                number_format($periodDeleted, 0, ',', '.')
            ));
        }

        return $tableDeleted;
    }

    /**
     * Archive the exact source period before the first destructive batch.
     * Pruning intentionally fails closed when the archive table is unavailable
     * or the copied rows cannot be verified.
     *
     * @return array<string, mixed>
     */
    private function captureAndVerifyDailyLoanArchive(string $period): array
    {
        try {
            $result = app(ConsumerRmPositionHistoryStore::class)->capturePeriod($period);
        } catch (Throwable $exception) {
            throw new RuntimeException(
                "Arsip posisi RM Konsumer periode {$period} tidak tersedia atau gagal dibuat; Daily Loan tidak dihapus.",
                0,
                $exception
            );
        }

        $verified = ($result['verified'] ?? false) === true;
        $skipped = ($result['skipped'] ?? false) === true;
        if (! $verified || $skipped) {
            $reason = trim((string) ($result['reason'] ?? 'hasil capture tidak terverifikasi'));

            throw new RuntimeException(
                "Arsip posisi RM Konsumer periode {$period} belum terverifikasi ({$reason}); Daily Loan tidak dihapus."
            );
        }

        return $result;
    }

    private function assertTargetSchemaIsSafe(): void
    {
        $overlap = array_values(array_intersect(array_keys(self::TARGETS), self::TIMESERIES_EXCLUDED_TABLES));
        if ($overlap !== []) {
            throw new RuntimeException(
                'Target retensi tidak boleh memuat sumber timeseries: '.implode(', ', $overlap).'.'
            );
        }

        foreach (self::TARGETS as $table => $periodColumn) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $periodColumn)) {
                throw new RuntimeException("Target {$table}.{$periodColumn} tidak tersedia.");
            }

            if (DB::getDriverName() !== 'mysql') {
                continue;
            }

            $database = DB::getDatabaseName();
            $hasPeriodLeadingIndex = DB::table('information_schema.statistics')
                ->where('table_schema', $database)
                ->where('table_name', $table)
                ->where('column_name', $periodColumn)
                ->where('seq_in_index', 1)
                ->exists();

            if (! $hasPeriodLeadingIndex) {
                throw new RuntimeException(
                    "Pembersihan dibatalkan karena {$table}.{$periodColumn} tidak memiliki indeks leading."
                );
            }
        }
    }

    private function assertNoActiveImports(): void
    {
        if (! Schema::hasTable('import_jobs')) {
            throw new RuntimeException('Tabel import_jobs tidak tersedia untuk pemeriksaan writer aktif.');
        }

        $activeJobs = DB::table('import_jobs')
            ->whereIn('status', self::ACTIVE_IMPORT_STATUSES)
            ->orderBy('id')
            ->pluck('id')
            ->all();

        if ($activeJobs !== []) {
            throw new RuntimeException(
                'Masih ada job import aktif: #'.implode(', #', $activeJobs).'. Jalankan ulang setelah job selesai.'
            );
        }
    }

    private function resolveProtectedMonths(): array
    {
        $months = array_values(array_filter(array_map(
            static fn ($month): string => trim((string) $month),
            (array) $this->option('keep-full-month')
        )));

        if ($months === []) {
            $currentMonth = CarbonImmutable::now()->startOfMonth();
            $months = [
                $currentMonth->subMonth()->format('Y-m'),
                $currentMonth->format('Y-m'),
            ];
        }

        foreach ($months as $month) {
            if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
                throw new RuntimeException("Format bulan tidak valid: {$month}. Gunakan YYYY-MM.");
            }
        }

        $months = array_values(array_unique($months));
        sort($months);

        return $months;
    }

    private function displayPlan(array $plan, array $protectedMonths): void
    {
        $this->info('RENCANA RETENSI DATA HARIAN');
        $this->line('Bulan lengkap: '.implode(', ', $protectedMonths));
        $this->line('Bulan lainnya: hanya posisi terakhir yang tersedia pada masing-masing bulan.');
        $this->line('Dikecualikan (timeseries): '.implode(', ', self::TIMESERIES_EXCLUDED_TABLES).'.');
        $this->newLine();

        $rows = [];
        foreach ($plan as $table => $tablePlan) {
            $rows[] = [
                $table,
                $tablePlan['period_column'],
                number_format($tablePlan['total_rows'], 0, ',', '.'),
                count($tablePlan['delete_periods']),
                number_format($tablePlan['delete_rows'], 0, ',', '.'),
                number_format($tablePlan['keep_rows'], 0, ',', '.'),
            ];
        }

        $this->table(
            ['Tabel', 'Kolom', 'Baris saat ini', 'Periode dihapus', 'Baris dihapus', 'Baris disimpan'],
            $rows
        );
        $this->line('Total kandidat: '.number_format(
            $this->sumPlanValue($plan, 'delete_rows'),
            0,
            ',',
            '.'
        ).' baris.');
    }

    private function bumpRelevantCacheVersions(string $table): void
    {
        $scopes = match ($table) {
            'simpanan_multipn', 'hourly_dpk' => ['simpanan', 'harian'],
            'dly_kap_resegmentasi' => ['harian'],
            default => ['pinjaman', 'harian'],
        };

        foreach ($scopes as $scope) {
            ReportCacheVersion::bump($scope);
        }
    }

    private function runtimeLimitReached(): bool
    {
        return $this->deadline !== null && CarbonImmutable::now()->greaterThanOrEqualTo($this->deadline);
    }

    private function deleteBatchWithRetry(
        string $table,
        string $periodColumn,
        string $period,
        int $chunkSize,
        int $lockRetries,
        int $retrySleepMilliseconds
    ): int {
        for ($attempt = 0; ; $attempt++) {
            try {
                return (int) DB::table($table)
                    ->where($periodColumn, $period)
                    ->limit($chunkSize)
                    ->delete();
            } catch (QueryException $exception) {
                if (! $this->isRetryableLockException($exception) || $attempt >= $lockRetries) {
                    throw $exception;
                }

                $delay = min(30_000, $retrySleepMilliseconds * ($attempt + 1));
                $this->warn(sprintf(
                    '  %s: lock database, retry %d/%d dalam %d ms.',
                    $period,
                    $attempt + 1,
                    $lockRetries,
                    $delay
                ));
                usleep($delay * 1_000);
            }
        }
    }

    private function isRetryableLockException(QueryException $exception): bool
    {
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);
        if (in_array($driverCode, [1205, 1213], true)) {
            return true;
        }

        $message = strtolower($exception->getMessage());

        return str_contains($message, 'lock wait timeout')
            || str_contains($message, 'deadlock found');
    }

    private function sumPlanValue(array $plan, string $key): int
    {
        return array_sum(array_map(
            static fn (array $tablePlan): int => (int) ($tablePlan[$key] ?? 0),
            $plan
        ));
    }

    private function makeAuditPath(): string
    {
        return storage_path(
            'logs/report-retention-cleanup-'.now()->format('Ymd-His').'-'.getmypid().'.json'
        );
    }

    private function persistAudit(string $path, array $audit): void
    {
        File::ensureDirectoryExists(dirname($path));
        File::put(
            $path,
            json_encode($audit, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    private function releaseLock(Lock $lock): void
    {
        try {
            $lock->release();
        } catch (Throwable $exception) {
            Log::warning('Unable to release report retention lock.', [
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
