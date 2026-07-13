<?php

namespace App\Services\Reports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class KinerjaPtpReportService
{
    private const REPORT_TYPES = [
        'npd' => [
            'label' => 'PTP NPD Micro',
            'table' => 'lw321_npd',
            'amount_column' => 'm_min_1_os',
            'total_heading' => 'TOTAL NPD',
        ],
        'npdd' => [
            'label' => 'PTP NPDD Micro',
            'table' => 'lw321_npdd',
            'amount_column' => 'os',
            'total_heading' => 'TOTAL NPDD',
        ],
    ];

    private const LEVELS = [
        'per_mbm' => 'Kinerja per MBM',
        'per_uker' => 'Kinerja per Uker',
        'per_mantri' => 'Kinerja per Mantri',
    ];

    private const GROUPS = [
        'per_mbm' => [
            'bo' => 'kanca',
            'mbm' => 'mbm',
        ],
        'per_uker' => [
            'bo' => 'kanca',
            'bc' => 'bc',
            'mbm' => 'mbm',
            'uker' => 'uker',
        ],
        'per_mantri' => [
            'bo' => 'kanca',
            'mbm' => 'mbm',
            'bc' => 'bc',
            'uker' => 'uker',
            'mantri' => 'mantri',
        ],
    ];

    private const DETAIL_COLUMNS = [
        'periode',
        'billing',
        'kanca',
        'bc',
        'mbm',
        'uker',
        'mantri',
        'no_rekening',
        'm_min_1_os',
        'os',
        'wba',
        'now_kol',
        'now_detail',
        'now_os',
        'now_t_pokok',
        'now_t_bunga',
        'now_t_total',
        'ptp',
    ];

    private const METRIC_LABELS = [
        'total_rek' => 'Total - Rek',
        'total_rupiah' => 'Total - Rupiah',
        'total_runoff' => 'Total - Run Off',
        'sudah_billing_rek' => 'Sudah Muncul Billing - Rek',
        'sudah_billing_rupiah' => 'Sudah Muncul Billing - Rupiah',
        'sudah_billing_runoff' => 'Sudah Muncul Billing - Run Off',
        'belum_muncul_rek' => 'Belum Muncul - Rek',
        'belum_muncul_rupiah' => 'Belum Muncul - Rupiah',
        'belum_muncul_runoff' => 'Belum Muncul - Run Off',
        'sudah_bayar_rek' => 'Sudah Bayar - Rek',
        'sudah_bayar_rupiah' => 'Sudah Bayar - Rupiah',
        'belum_bayar_rek' => 'Belum Bayar - Rek',
        'belum_bayar_rupiah' => 'Belum Bayar - Rupiah',
        'success_rate' => 'Success Rate - Basis Billing Sudah Muncul',
        'today_rek' => 'Today - Rek',
        'today_rupiah' => 'Today - Rupiah',
    ];

    public function reportTypes(): array
    {
        return collect(self::REPORT_TYPES)
            ->map(fn (array $config) => $config['label'])
            ->all();
    }

    public function levels(): array
    {
        return self::LEVELS;
    }

    public function normalizeReportType(?string $reportType): string
    {
        $reportType = strtolower(trim((string) $reportType));

        return array_key_exists($reportType, self::REPORT_TYPES) ? $reportType : 'npd';
    }

    public function normalizeLevel(?string $level): string
    {
        $level = strtolower(trim((string) $level));

        return array_key_exists($level, self::LEVELS) ? $level : 'per_mbm';
    }

    public function reportConfig(string $reportType): array
    {
        return self::REPORT_TYPES[$this->normalizeReportType($reportType)];
    }

    public function availablePeriods(string $reportType): Collection
    {
        $config = $this->reportConfig($reportType);
        $table = $config['table'];

        if (!Schema::hasTable($table)) {
            return collect();
        }

        return DB::table($table)
            ->whereNotNull('periode')
            ->select('periode')
            ->distinct()
            ->orderByDesc('periode')
            ->pluck('periode')
            ->map(fn ($period) => Carbon::parse($period)->toDateString())
            ->values();
    }

    public function resolveSelectedPeriod(Collection $periods, mixed $requested): ?string
    {
        try {
            $requestedDate = $requested ? Carbon::parse((string) $requested)->toDateString() : null;
        } catch (\Throwable) {
            $requestedDate = null;
        }

        if ($requestedDate !== null) {
            $match = $periods->first(fn (string $period): bool => $period === $requestedDate);
            if ($match !== null) {
                return $match;
            }
        }

        return $periods->first();
    }

    public function payload(string $reportType, string $level, ?string $period): array
    {
        $reportType = $this->normalizeReportType($reportType);
        $level = $this->normalizeLevel($level);
        $config = $this->reportConfig($reportType);
        $table = $config['table'];

        if ($period === null || !Schema::hasTable($table)) {
            return [
                'rows' => collect(),
                'total' => $this->emptyTotal(),
            ];
        }

        $rows = $this->aggregateRows($table, $config['amount_column'], $level, $period);

        return [
            'rows' => $rows,
            'total' => $this->sumRows($rows),
        ];
    }

    public function detailPayload(string $reportType, string $level, ?string $period, array $dimensions, ?string $metric, int $limit = 25, int $offset = 0): array
    {
        $reportType = $this->normalizeReportType($reportType);
        $level = $this->normalizeLevel($level);
        $config = $this->reportConfig($reportType);
        $table = $config['table'];
        $metric = $this->normalizeMetric($metric);
        $fetchAll = $limit <= 0;
        $limit = $fetchAll ? 0 : max(10, min(50, $limit));
        $offset = max(0, $offset);

        if ($period === null || !Schema::hasTable($table)) {
            return [
                'columns' => [],
                'rows' => collect(),
                'metric' => $metric,
                'metric_label' => self::METRIC_LABELS[$metric],
                'has_more' => false,
                'next_offset' => null,
                'limit' => $limit,
                'offset' => $offset,
            ];
        }

        $columns = $this->detailColumns($table);
        $query = $this->detailQuery($table, $level, $period, $dimensions, $metric, $columns);

        if (! $fetchAll) {
            $query->offset($offset)->limit($limit + 1);
        }

        $rows = $query->get();
        $hasMore = ! $fetchAll && $rows->count() > $limit;

        return [
            'columns' => $columns,
            'rows' => ($fetchAll ? $rows : $rows->take($limit))->map(fn (object $row): array => (array) $row)->values(),
            'metric' => $metric,
            'metric_label' => self::METRIC_LABELS[$metric],
            'has_more' => $hasMore,
            'next_offset' => $hasMore ? $offset + $limit : null,
            'limit' => $limit,
            'offset' => $offset,
        ];
    }

    public function groupAliases(string $level): array
    {
        $level = $this->normalizeLevel($level);

        return array_keys(self::GROUPS[$level]);
    }

    public function normalizeMetric(?string $metric): string
    {
        $metric = strtolower(trim((string) $metric));

        return array_key_exists($metric, self::METRIC_LABELS) ? $metric : 'total_rek';
    }

    public function formatCount(mixed $value): string
    {
        return number_format((int) round((float) $value), 0, ',', '.');
    }

    public function formatJuta(mixed $value): string
    {
        return number_format(((float) $value) / 1000000, 0, ',', '.');
    }

    public function formatPercent(mixed $value): string
    {
        return number_format((float) $value, 2, ',', '.') . '%';
    }

    private function aggregateRows(string $table, string $amountColumn, string $level, string $period): Collection
    {
        $billingSql = 'src.billing_status';
        $ptpSql = 'src.ptp_status';
        $nowKolSql = 'src.now_kol_status';
        $accountSql = 'src.account_key';
        $amountSql = 'src.amount_value';
        $wbaSql = 'src.wba_value';
        $nowOsSql = 'src.now_os_value';
        $billingSudah = "{$billingSql} = 'SUDAH'";
        $billingBelumToday = "{$billingSql} IN ('BELUM', 'TODAY')";
        $billingSudahToday = "{$billingSql} IN ('SUDAH', 'TODAY')";
        $billingToday = "{$billingSql} = 'TODAY'";
        $paidRekCondition = "{$billingSudah} AND ({$ptpSql} IN ('TETAP', 'MEMBAIK', 'LUNAS') OR {$nowKolSql} = 'LUNAS')";
        $unpaidCondition = "{$billingSudah} AND NOT ({$ptpSql} IN ('TETAP', 'MEMBAIK', 'LUNAS') OR {$nowKolSql} = 'LUNAS')";

        $source = $this->baseQuery($table)
            ->whereDate('ptp.periode', $period)
            ->whereNotNull('ptp.uker')
            ->whereRaw("TRIM(COALESCE(ptp.uker, '')) <> ''")
            ->whereRaw("UPPER(TRIM(COALESCE(ptp.uker, ''))) NOT IN ('KC', 'KCP')")
            ->whereRaw("UPPER(TRIM(COALESCE(ptp.uker, ''))) NOT LIKE 'KC %'")
            ->whereRaw("UPPER(TRIM(COALESCE(ptp.uker, ''))) NOT LIKE 'KCP %'");

        $groupAliases = array_keys(self::GROUPS[$level]);

        foreach ($groupAliases as $alias) {
            $source->selectRaw($this->groupExpression($alias) . " as {$alias}");
        }

        $source
            ->selectRaw("NULLIF(TRIM(COALESCE(ptp.no_rekening, '')), '') as account_key")
            ->selectRaw("UPPER(TRIM(COALESCE(ptp.billing, ''))) as billing_status")
            ->selectRaw("UPPER(TRIM(COALESCE(ptp.ptp, ''))) as ptp_status")
            ->selectRaw("UPPER(TRIM(COALESCE(ptp.now_kol, ''))) as now_kol_status")
            ->selectRaw("COALESCE(ptp.`{$amountColumn}`, 0) as amount_value")
            ->selectRaw('COALESCE(ptp.wba, 0) as wba_value')
            ->selectRaw('COALESCE(ptp.now_os, 0) as now_os_value');

        $query = DB::query()->fromSub($source, 'src');

        foreach ($groupAliases as $alias) {
            $query->selectRaw("src.{$alias} as {$alias}");
        }

        $query
            ->selectRaw("COUNT({$accountSql}) as total_rek")
            ->selectRaw("SUM({$amountSql}) as total_rupiah")
            ->selectRaw("SUM({$wbaSql}) as total_runoff")
            ->selectRaw("COUNT(CASE WHEN {$billingSudah} THEN {$accountSql} END) as sudah_billing_rek")
            ->selectRaw("SUM(CASE WHEN {$billingSudah} THEN {$amountSql} ELSE 0 END) as sudah_billing_rupiah")
            ->selectRaw("SUM(CASE WHEN {$billingSudahToday} THEN {$wbaSql} ELSE 0 END) as sudah_billing_runoff")
            ->selectRaw("COUNT(CASE WHEN {$billingBelumToday} THEN {$accountSql} END) as belum_muncul_rek")
            ->selectRaw("SUM(CASE WHEN {$billingBelumToday} THEN {$amountSql} ELSE 0 END) as belum_muncul_rupiah")
            ->selectRaw("SUM(CASE WHEN {$billingSql} = 'BELUM' THEN {$wbaSql} ELSE 0 END) as belum_muncul_runoff")
            ->selectRaw("COUNT(CASE WHEN {$paidRekCondition} THEN {$accountSql} END) as sudah_bayar_rek")
            ->selectRaw("COUNT(CASE WHEN {$unpaidCondition} THEN {$accountSql} END) as belum_bayar_rek")
            ->selectRaw("SUM(CASE WHEN {$unpaidCondition} THEN {$nowOsSql} ELSE 0 END) as belum_bayar_rupiah")
            ->selectRaw("COUNT(CASE WHEN {$billingToday} THEN {$accountSql} END) as today_rek")
            ->selectRaw("SUM(CASE WHEN {$billingToday} THEN {$amountSql} ELSE 0 END) as today_rupiah")
            ->groupBy(...array_map(fn (string $alias): string => "src.{$alias}", $groupAliases))
            ->orderBy('src.bo');

        foreach ($groupAliases as $alias) {
            if ($alias !== 'bo') {
                $query->orderBy("src.{$alias}");
            }
        }

        return $query->get()
            ->map(fn (object $row): array => $this->decorateRow((array) $row))
            ->values();
    }

    private function detailQuery(string $table, string $level, string $period, array $dimensions, string $metric, array $columns)
    {
        $query = $this->baseQuery($table)
            ->whereDate('ptp.periode', $period)
            ->whereNotNull('ptp.uker')
            ->whereRaw("TRIM(COALESCE(ptp.uker, '')) <> ''")
            ->whereRaw("UPPER(TRIM(COALESCE(ptp.uker, ''))) NOT IN ('KC', 'KCP')")
            ->whereRaw("UPPER(TRIM(COALESCE(ptp.uker, ''))) NOT LIKE 'KC %'")
            ->whereRaw("UPPER(TRIM(COALESCE(ptp.uker, ''))) NOT LIKE 'KCP %'");

        foreach ($columns as $column) {
            if ($column === 'mbm') {
                $query->selectRaw($this->groupExpression('mbm') . ' as mbm');
                continue;
            }

            $query->addSelect("ptp.{$column}");
        }

        foreach (array_keys(self::GROUPS[$level]) as $alias) {
            $value = trim((string) ($dimensions[$alias] ?? ''));
            $query->whereRaw($this->groupExpression($alias) . ' = ?', [$value !== '' ? $value : '-']);
        }

        $this->applyMetricConstraint($query, $metric);

        foreach (array_keys(self::GROUPS[$level]) as $alias) {
            $query->orderByRaw($this->groupExpression($alias));
        }

        $identityColumn = in_array('no_rekening', $columns, true) ? 'no_rekening' : ($columns[0] ?? null);
        if ($identityColumn !== null) {
            $query->orderBy("ptp.{$identityColumn}");
        }

        return $query;
    }

    private function applyMetricConstraint($query, string $metric): void
    {
        $billingSql = "UPPER(TRIM(COALESCE(ptp.billing, '')))";
        $ptpSql = "UPPER(TRIM(COALESCE(ptp.ptp, '')))";
        $nowKolSql = "UPPER(TRIM(COALESCE(ptp.now_kol, '')))";
        $billingSudah = "{$billingSql} = 'SUDAH'";
        $billingBelumToday = "{$billingSql} IN ('BELUM', 'TODAY')";
        $billingSudahToday = "{$billingSql} IN ('SUDAH', 'TODAY')";
        $billingToday = "{$billingSql} = 'TODAY'";
        $paidCondition = "{$billingSudah} AND ({$ptpSql} IN ('TETAP', 'MEMBAIK', 'LUNAS') OR {$nowKolSql} = 'LUNAS')";
        $unpaidCondition = "{$billingSudah} AND NOT ({$ptpSql} IN ('TETAP', 'MEMBAIK', 'LUNAS') OR {$nowKolSql} = 'LUNAS')";

        match ($metric) {
            'sudah_billing_rek',
            'sudah_billing_rupiah',
            'success_rate' => $query->whereRaw($billingSudah),

            'sudah_billing_runoff' => $query->whereRaw($billingSudahToday),

            'belum_muncul_rek',
            'belum_muncul_rupiah' => $query->whereRaw($billingBelumToday),

            'belum_muncul_runoff' => $query->whereRaw("{$billingSql} = 'BELUM'"),

            'sudah_bayar_rek',
            'sudah_bayar_rupiah' => $query->whereRaw($paidCondition),

            'belum_bayar_rek',
            'belum_bayar_rupiah' => $query->whereRaw($unpaidCondition),

            'today_rek',
            'today_rupiah' => $query->whereRaw($billingToday),

            default => null,
        };
    }

    private function detailColumns(string $table): array
    {
        $available = array_fill_keys(Schema::getColumnListing($table), true);

        return array_values(array_filter(
            self::DETAIL_COLUMNS,
            fn (string $column): bool => isset($available[$column])
        ));
    }

    private function baseQuery(string $table)
    {
        $query = DB::table($table . ' as ptp');

        if ($this->usesMbmMaster()) {
            $query->leftJoin('wilayah_mbm as wm', 'wm.bc', '=', 'ptp.bc');
        }

        return $query;
    }

    private function groupExpression(string $alias): string
    {
        return match ($alias) {
            'mbm' => $this->usesMbmMaster()
                ? "COALESCE(NULLIF(TRIM(ptp.mbm), ''), NULLIF(TRIM(wm.nama_mbm), ''), '-')"
                : "COALESCE(NULLIF(TRIM(ptp.mbm), ''), '-')",
            default => "COALESCE(NULLIF(TRIM(ptp." . self::GROUPS['per_mantri'][$alias] . "), ''), '-')",
        };
    }

    private function usesMbmMaster(): bool
    {
        return Schema::hasTable('wilayah_mbm')
            && Schema::hasColumn('wilayah_mbm', 'bc')
            && Schema::hasColumn('wilayah_mbm', 'nama_mbm');
    }

    private function decorateRow(array $row): array
    {
        $row['sudah_bayar_rupiah'] = max(0, (float) ($row['sudah_billing_rupiah'] ?? 0) - (float) ($row['belum_bayar_rupiah'] ?? 0));
        $row['success_rate'] = (float) ($row['sudah_billing_rupiah'] ?? 0) > 0
            ? ($row['sudah_bayar_rupiah'] / (float) $row['sudah_billing_rupiah']) * 100
            : 0.0;

        return $row;
    }

    private function sumRows(Collection $rows): array
    {
        $total = $this->emptyTotal();

        foreach ($rows as $row) {
            foreach (array_keys($total) as $key) {
                if ($key === 'success_rate') {
                    continue;
                }

                $total[$key] += (float) ($row[$key] ?? 0);
            }
        }

        $total['success_rate'] = $total['sudah_billing_rupiah'] > 0
            ? ($total['sudah_bayar_rupiah'] / $total['sudah_billing_rupiah']) * 100
            : 0.0;

        return $total;
    }

    private function emptyTotal(): array
    {
        return [
            'total_rek' => 0,
            'total_rupiah' => 0.0,
            'total_runoff' => 0.0,
            'sudah_billing_rek' => 0,
            'sudah_billing_rupiah' => 0.0,
            'sudah_billing_runoff' => 0.0,
            'belum_muncul_rek' => 0,
            'belum_muncul_rupiah' => 0.0,
            'belum_muncul_runoff' => 0.0,
            'sudah_bayar_rek' => 0,
            'sudah_bayar_rupiah' => 0.0,
            'belum_bayar_rek' => 0,
            'belum_bayar_rupiah' => 0.0,
            'success_rate' => 0.0,
            'today_rek' => 0,
            'today_rupiah' => 0.0,
        ];
    }
}
