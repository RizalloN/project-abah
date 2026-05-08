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
        $billingSql = "UPPER(TRIM(COALESCE(billing, '')))";
        $ptpSql = "UPPER(TRIM(COALESCE(ptp, '')))";
        $nowKolSql = "UPPER(TRIM(COALESCE(now_kol, '')))";
        $accountSql = "NULLIF(TRIM(COALESCE(no_rekening, '')), '')";
        $amountSql = "COALESCE(`{$amountColumn}`, 0)";
        $wbaSql = 'COALESCE(wba, 0)';
        $nowOsSql = 'COALESCE(now_os, 0)';
        $billingSudah = "{$billingSql} = 'SUDAH'";
        $billingBelumToday = "{$billingSql} IN ('BELUM', 'TODAY')";
        $billingSudahToday = "{$billingSql} IN ('SUDAH', 'TODAY')";
        $billingToday = "{$billingSql} = 'TODAY'";
        $paidRekCondition = "{$billingSudah} AND ({$ptpSql} IN ('TETAP', 'MEMBAIK', 'LUNAS') OR {$nowKolSql} = 'LUNAS')";
        $unpaidCondition = "{$billingSudah} AND NOT ({$ptpSql} IN ('TETAP', 'MEMBAIK', 'LUNAS') OR {$nowKolSql} = 'LUNAS')";

        $query = DB::table($table)
            ->whereDate('periode', $period)
            ->whereNotNull('uker')
            ->whereRaw("TRIM(COALESCE(uker, '')) <> ''")
            ->whereRaw("UPPER(TRIM(COALESCE(uker, ''))) NOT IN ('KC', 'KCP')")
            ->whereRaw("UPPER(TRIM(COALESCE(uker, ''))) NOT LIKE 'KC %'")
            ->whereRaw("UPPER(TRIM(COALESCE(uker, ''))) NOT LIKE 'KCP %'");

        foreach (self::GROUPS[$level] as $alias => $column) {
            $query->selectRaw("COALESCE(NULLIF(TRIM({$column}), ''), '-') as {$alias}");
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
            ->groupBy(...array_values(self::GROUPS[$level]))
            ->orderBy('bo');

        foreach (array_keys(self::GROUPS[$level]) as $alias) {
            if ($alias !== 'bo') {
                $query->orderBy($alias);
            }
        }

        return $query->get()
            ->map(fn (object $row): array => $this->decorateRow((array) $row))
            ->values();
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
