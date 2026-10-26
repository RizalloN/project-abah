<?php

namespace App\Services\ControlCenter;

use App\Services\Import\SchemaIntrospectionService;
use App\Support\LatestSnapshotRecoveryService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * "Can the dashboards read current data?" For every active report table:
 * the latest data period (read through an index only), the report's natural
 * update rhythm learned from its own recent periods, how late it is against
 * that rhythm, and the outcome of its latest import job.
 */
class ReportDataFreshnessService
{
    private const CACHE_KEY = 'control-center:report-freshness:v1';
    private const CACHE_SECONDS = 300;
    private const RECENT_PERIODS = 12;

    /** Known period columns that are not discoverable by name alone. */
    private const EXTRA_PERIOD_COLUMNS = [
        'lw321pn' => 'periode',
        'gi405_singlerow' => 'periode',
    ];

    /** Date-typed column names that usually hold the data period, in priority order. */
    private const PERIOD_NAME_CANDIDATES = [
        'periode', 'posisi', 'month_day_year_of_periode', 'month_day_year_of_posisi', 'tanggal', 'tgl_posisi',
        'tanggal_posisi', 'periode_data', 'cras_periode', 'period', 'tanggal_data',
    ];

    public function __construct(private readonly SchemaIntrospectionService $schema)
    {
    }

    /**
     * @return array{generated_at: string, reports: array<int, array<string, mixed>>, summary: array<string, int>}
     */
    public function snapshot(bool $fresh = false): array
    {
        if ($fresh) {
            Cache::forget(self::CACHE_KEY);
        }

        return Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, fn (): array => $this->build());
    }

    private function build(): array
    {
        if (!Schema::hasTable('nama_report')) {
            return ['generated_at' => now()->toIso8601String(), 'reports' => [], 'summary' => []];
        }

        $reports = DB::table('nama_report')->where('active', 1)->orderBy('nama_report')->get(['id_report', 'nama_report', 'table_name']);
        $leadingIndexColumns = $this->leadingIndexColumns();
        $lastJobs = $this->latestImportJobs();
        $today = CarbonImmutable::today();
        $rows = [];

        foreach ($reports as $report) {
            $table = strtolower(trim((string) $report->table_name));
            $entry = [
                'id_report' => (int) $report->id_report,
                'report' => trim((string) $report->nama_report),
                'table' => $table,
                'period_column' => null,
                'latest_period' => null,
                'lag_days' => null,
                'cadence_days' => null,
                'indexed' => false,
                'last_import' => $lastJobs[(int) $report->id_report] ?? null,
                'status' => 'info',
                'message' => '',
            ];

            try {
                if ($table === '' || !$this->schema->hasTable($table)) {
                    $rows[] = array_merge($entry, ['status' => 'warn', 'message' => 'Tabel tujuan tidak ditemukan.']);
                    continue;
                }

                $column = $this->resolvePeriodColumn($table);
                $entry['period_column'] = $column;
                if ($column === null) {
                    $rows[] = array_merge($entry, ['message' => 'Report tanpa kolom periode; dipantau lewat riwayat import.']);
                    continue;
                }

                $entry['indexed'] = in_array(strtolower($column), $leadingIndexColumns[$table] ?? [], true);
                if (!$entry['indexed']) {
                    $rows[] = array_merge($entry, ['message' => 'Kolom periode tidak diindeks; dibaca dari riwayat import agar tidak memindai tabel.']);
                    continue;
                }

                $periods = $this->recentPeriods($table, $column);
                if ($periods === []) {
                    // An empty table only matters once someone has imported into it.
                    $rows[] = array_merge($entry, $entry['last_import'] === null
                        ? ['status' => 'info', 'message' => 'Belum pernah diimport; tabel masih kosong.']
                        : ['status' => 'warn', 'message' => 'Tabel kosong padahal sudah pernah diimport.']);
                    continue;
                }

                $latest = $periods[0];
                $cadence = $this->cadenceDays($periods);
                $lag = max(0, $latest->diffInDays($today, false));
                $entry['latest_period'] = $latest->toDateString();
                $entry['lag_days'] = (int) $lag;
                $entry['cadence_days'] = $cadence;
                [$entry['status'], $entry['message']] = $this->judge((int) $lag, $cadence, $entry['last_import']);
                if ($entry['status'] === 'fail' && $this->isDormant($entry['last_import'])) {
                    // Reports nobody uploads anymore are history, not an outage.
                    $entry['status'] = 'info';
                    $entry['dormant'] = true;
                    $entry['message'] = "Report dorman: tidak ada import 90 hari terakhir (data terakhir {$entry['latest_period']}).";
                }
                $rows[] = $entry;
            } catch (Throwable $e) {
                $rows[] = array_merge($entry, ['status' => 'warn', 'message' => 'Tidak dapat dibaca: ' . mb_strimwidth($e->getMessage(), 0, 140, '…')]);
            }
        }

        $summary = ['ok' => 0, 'warn' => 0, 'fail' => 0, 'info' => 0];
        foreach ($rows as $row) {
            $summary[$row['status']] = ($summary[$row['status']] ?? 0) + 1;
        }

        return ['generated_at' => now()->toIso8601String(), 'reports' => $rows, 'summary' => $summary];
    }

    /**
     * Daily reports may skip weekends and holidays, monthly reports move once a
     * month: lateness is judged against the report's own rhythm.
     *
     * @return array{0: string, 1: string}
     */
    public function judge(int $lagDays, ?int $cadenceDays, ?array $lastImport): array
    {
        $cadence = max(1, (int) ($cadenceDays ?? 1));
        $warnAfter = $cadence * 2 + 2;
        $failAfter = $cadence * 4 + 5;
        $importFailed = in_array((string) ($lastImport['status'] ?? ''), ['failed', 'failed_partial', 'terminated'], true);

        if ($lagDays > $failAfter) {
            return ['fail', "Data tertinggal {$lagDays} hari (ritme normal ±{$cadence} hari)."];
        }
        if ($lagDays > $warnAfter || $importFailed) {
            return ['warn', $importFailed
                ? 'Import terakhir berstatus ' . ($lastImport['status'] ?? '-') . '; data mungkin belum lengkap.'
                : "Data mulai tertinggal {$lagDays} hari (ritme normal ±{$cadence} hari)."];
        }

        return ['ok', $lagDays === 0 ? 'Data hari ini sudah tersedia.' : "Terbaru {$lagDays} hari lalu, sesuai ritme ±{$cadence} hari."];
    }

    public function isDormant(?array $lastImport): bool
    {
        $finishedAt = $lastImport['finished_at'] ?? null;
        if ($finishedAt === null) {
            return true;
        }

        try {
            return CarbonImmutable::parse((string) $finishedAt)->lt(CarbonImmutable::now()->subDays(90));
        } catch (Throwable) {
            return true;
        }
    }

    public function resolvePeriodColumn(string $table): ?string
    {
        $known = LatestSnapshotRecoveryService::sourcePeriodColumn($table) ?? (self::EXTRA_PERIOD_COLUMNS[$table] ?? null);
        $meta = $this->schema->getColumnMetadata($table);
        if ($known !== null && isset($meta[strtolower($known)])) {
            return (string) ($meta[strtolower($known)]['name'] ?? $known);
        }

        foreach (self::PERIOD_NAME_CANDIDATES as $candidate) {
            $columnMeta = $meta[$candidate] ?? null;
            if ($columnMeta !== null && in_array((string) ($columnMeta['base_type'] ?? ''), ['date', 'datetime', 'timestamp'], true)) {
                return (string) ($columnMeta['name'] ?? $candidate);
            }
        }

        return null;
    }

    /**
     * @return array<int, CarbonImmutable> newest first
     */
    private function recentPeriods(string $table, string $column): array
    {
        $values = DB::table($table)
            ->whereNotNull($column)
            ->select($column)
            ->distinct()
            ->orderByDesc($column)
            ->limit(self::RECENT_PERIODS)
            ->pluck($column)
            ->all();

        $periods = [];
        foreach ($values as $value) {
            try {
                $periods[] = CarbonImmutable::parse((string) $value)->startOfDay();
            } catch (Throwable) {
                continue;
            }
        }

        return $periods;
    }

    /**
     * Median gap between consecutive recent periods, in days.
     *
     * @param array<int, CarbonImmutable> $periods newest first
     */
    public function cadenceDays(array $periods): ?int
    {
        $gaps = [];
        for ($i = 0; $i + 1 < count($periods); $i++) {
            $gap = (int) $periods[$i + 1]->diffInDays($periods[$i]);
            if ($gap > 0) {
                $gaps[] = $gap;
            }
        }

        if ($gaps === []) {
            return null;
        }

        sort($gaps);
        $middle = intdiv(count($gaps), 2);

        return count($gaps) % 2 === 1 ? $gaps[$middle] : (int) round(($gaps[$middle - 1] + $gaps[$middle]) / 2);
    }

    /**
     * @return array<string, array<int, string>> table => columns leading an index
     */
    private function leadingIndexColumns(): array
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return [];
        }

        $columns = [];
        foreach (DB::select('SELECT LOWER(table_name) AS t, LOWER(column_name) AS c FROM information_schema.statistics WHERE table_schema = DATABASE() AND seq_in_index = 1') as $row) {
            $columns[(string) $row->t][] = (string) $row->c;
        }

        return $columns;
    }

    /**
     * @return array<int, array{status: string, finished_at: string|null, file: string|null}>
     */
    private function latestImportJobs(): array
    {
        if (!Schema::hasTable('import_jobs')) {
            return [];
        }

        $latestIds = DB::table('import_jobs')->selectRaw('MAX(id) as id')->groupBy('id_report')->pluck('id')->all();
        if ($latestIds === []) {
            return [];
        }

        $jobs = [];
        foreach (DB::table('import_jobs')->whereIn('id', $latestIds)->get(['id_report', 'status', 'updated_at', 'file_name']) as $job) {
            $jobs[(int) $job->id_report] = [
                'status' => (string) $job->status,
                'finished_at' => $job->updated_at !== null ? CarbonImmutable::parse((string) $job->updated_at)->toIso8601String() : null,
                'file' => $job->file_name !== null ? basename((string) $job->file_name) : null,
            ];
        }

        return $jobs;
    }
}
