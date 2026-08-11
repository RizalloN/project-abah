<?php

namespace App\Console\Commands;

use App\Support\ReportCacheVersion;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class PruneReportDailyHistoryCommand extends Command
{
    private const TARGETS = [
        'daily_loan_dinamis' => 'periode',
        'lw325_ph' => 'periode',
        'simpanan_multipn' => 'posisi',
    ];

    private const ACTIVE_IMPORT_STATUSES = ['queued', 'staging', 'processing'];

    protected $signature = 'reports:prune-daily-history
        {--execute : Jalankan penghapusan; tanpa opsi ini command hanya menampilkan dry-run}
        {--keep-full-month=* : Bulan yang dipertahankan lengkap dalam format YYYY-MM}
        {--chunk=50000 : Jumlah maksimum baris per transaksi DELETE}
        {--sleep-ms=25 : Jeda antarbatch untuk mengurangi tekanan I/O}';

    protected $description = 'Sisakan posisi terakhir per bulan dan pertahankan dua bulan terbaru secara lengkap';

    public function handle(): int
    {
        $chunkSize = max(1_000, min(200_000, (int) $this->option('chunk')));
        $sleepMilliseconds = max(0, min(5_000, (int) $this->option('sleep-ms')));

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
                    $audit,
                    $auditPath
                );

                if ($deletedForTable > 0) {
                    $this->bumpRelevantCacheVersions($table);
                    $cacheInvalidatedTables[] = $table;
                }
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

            do {
                $this->assertNoActiveImports();
                $deleted = DB::table($table)
                    ->where($periodColumn, $period['date'])
                    ->limit($chunkSize)
                    ->delete();

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

    private function assertTargetSchemaIsSafe(): void
    {
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
        if ($table === 'simpanan_multipn') {
            ReportCacheVersion::bump('simpanan');
            ReportCacheVersion::bump('harian');

            return;
        }

        ReportCacheVersion::bump('pinjaman');
        ReportCacheVersion::bump('harian');
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
