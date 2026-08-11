<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class HourlyDpkDashboardService
{
    private const HOURLY_TABLE = 'hourly_dpk';
    private const SSA_TABLE = 'ssa_simpanan';
    private const AREA_BRANCHES = ['KC Madiun', 'KC Magetan', 'KC Ngawi', 'KC Ponorogo'];
    private const AREA_BRANCH_CODES = [
        'KC Madiun' => '00045',
        'KC Magetan' => '00049',
        'KC Ngawi' => '00057',
        'KC Ponorogo' => '00070',
    ];
    private const PRODUCTS = ['DEPOSITO', 'GIRO', 'TABUNGAN'];
    private const DELTA_PERIOD_MAP = [
        'dtd' => 'h1',
        'mtd' => 'mtd',
        'ytd' => 'ytd',
    ];

    public function filters(): array
    {
        return [
            'branches' => self::AREA_BRANCHES,
            'products' => ['all' => 'Semua Jenis', 'DEPOSITO' => 'Deposito', 'GIRO' => 'Giro', 'TABUNGAN' => 'Tabungan'],
            'segments' => $this->segmentOptions(),
        ];
    }

    public function payload(?string $branch, ?string $product, ?string $segment = null): array
    {
        if (!Schema::hasTable(self::HOURLY_TABLE) || !Schema::hasTable(self::SSA_TABLE)) {
            return $this->emptyPayload('Tabel hourly_dpk atau ssa_simpanan belum tersedia.');
        }

        $latestDate = DB::table(self::HOURLY_TABLE)->max('posisi');
        if (!$latestDate) {
            return $this->emptyPayload('Data Hourly DPK belum tersedia. Silakan import report Hourly DPK terlebih dahulu.');
        }

        $latestDate = Carbon::parse($latestDate)->toDateString();
        $selectedBranch = $this->normalizeBranchFilter($branch);
        $selectedProduct = $this->normalizeProduct($product);
        $selectedSegment = $this->normalizeSegmentFilter($segment);
        $periods = $this->referencePeriods($latestDate);
        $hours = $this->hourColumns($latestDate);
        $historical = $this->historicalMatrix($periods, $selectedBranch, $selectedProduct, $selectedSegment);
        $hourly = $this->hourlyMatrix($latestDate, $hours, $selectedBranch, $selectedProduct, $selectedSegment);
        $latestHourKey = $this->latestHourKey($hours);
        $detailRows = $this->buildRows($historical, $hourly, $periods, $hours, $latestHourKey);
        $rows = $this->withBranchSubtotals($detailRows, $selectedBranch, $periods, $hours);

        return [
            'ready' => true,
            'message' => '',
            'selectedDate' => $latestDate,
            'selectedDateLabel' => $this->formatDateLabel($latestDate),
            'selectedBranch' => $selectedBranch !== '' ? $selectedBranch : 'all',
            'selectedProduct' => $selectedProduct,
            'selectedSegment' => $selectedSegment,
            'scopeLabel' => $selectedBranch !== '' ? $selectedBranch : 'Area 6',
            'branchCode' => $selectedBranch !== '' ? $this->branchCode($selectedBranch) : '',
            'periods' => $periods,
            'hours' => $hours,
            'latestHour' => $latestHourKey,
            'rows' => $rows,
            'dataRowCount' => count($detailRows),
            'total' => $this->totalRow($detailRows, $periods, $hours),
        ];
    }

    public function exportPayload(?string $branch, ?string $segment = null): array
    {
        $selectedBranch = $this->normalizeBranchFilter($branch);
        $selectedSegment = $selectedBranch !== '' ? 'all' : $this->normalizeSegmentFilter($segment);
        $products = [
            'all' => 'All Simpanan',
            'TABUNGAN' => 'Tabungan',
            'GIRO' => 'Giro',
            'DEPOSITO' => 'Deposito',
        ];

        $tables = [];
        foreach ($products as $product => $label) {
            $payload = $this->payload($selectedBranch !== '' ? $selectedBranch : 'all', $product, $selectedSegment);
            $tables[] = [
                'key' => $product,
                'label' => $label,
                'payload' => $payload,
                'description' => $this->exportTableDescription($label, $payload, $selectedSegment),
            ];
        }

        $basePayload = $tables[0]['payload'] ?? $this->emptyPayload('Data belum tersedia.');
        $isBranchScope = $selectedBranch !== '';
        $summary = $isBranchScope
            ? $this->branchSegmentSummary($basePayload)
            : $this->areaProductSummary($tables, $selectedSegment);

        return [
            'generatedAt' => now()->translatedFormat('d M Y H:i') . ' WIB',
            'selectedBranch' => $selectedBranch !== '' ? $selectedBranch : 'all',
            'selectedSegment' => $selectedSegment,
            'summaryType' => $isBranchScope ? 'segment' : 'product',
            'scopeLabel' => $selectedBranch !== '' ? $selectedBranch : 'Area 6',
            'segmentLabel' => $this->formatSegmentLabel($selectedSegment === 'all' ? 'Semua Segmen' : $selectedSegment),
            'selectedDate' => $basePayload['selectedDate'] ?? null,
            'selectedDateLabel' => $basePayload['selectedDateLabel'] ?? '-',
            'hours' => $basePayload['hours'] ?? [],
            'latestHour' => $basePayload['latestHour'] ?? null,
            'periods' => $basePayload['periods'] ?? $this->blankPeriods(),
            'summary' => $summary,
            'summaryTotal' => $isBranchScope ? (array) ($basePayload['total'] ?? []) : [],
            'tables' => $tables,
        ];
    }

    private function areaProductSummary(array $tables, string $selectedSegment): array
    {
        $summarySegment = $selectedSegment === 'all'
            ? 'Semua Segmen'
            : $this->formatSegmentLabel($selectedSegment);

        return collect($tables)->values()->map(function (array $table, int $index) use ($summarySegment): array {
            $payload = (array) ($table['payload'] ?? []);
            $latestHour = (string) ($payload['latestHour'] ?? '');

            return [
                'no' => $index + 1,
                'segment' => $summarySegment,
                'produk' => (string) ($table['label'] ?? ''),
                'posisi' => (float) ($payload['total']['hour_values'][$latestHour] ?? 0),
            ];
        })->all();
    }

    private function branchSegmentSummary(array $payload): array
    {
        $latestHour = (string) ($payload['latestHour'] ?? '');
        $segmentRows = collect((array) ($payload['rows'] ?? []))
            ->whereIn('row_type', ['subtotal_retail', 'subtotal_micro'])
            ->keyBy('row_type');

        return collect([
            'subtotal_retail' => 'Ritel',
            'subtotal_micro' => 'Mikro',
        ])->map(function (string $label, string $rowType) use ($segmentRows, $latestHour): array {
            $row = (array) ($segmentRows->get($rowType) ?? []);

            return [
                'segment' => $label,
                'posisi' => (float) ($row['hour_values'][$latestHour] ?? 0),
                'period_values' => (array) ($row['period_values'] ?? []),
                'hour_values' => (array) ($row['hour_values'] ?? []),
                'delta_values' => (array) ($row['delta_values'] ?? []),
            ];
        })->values()
            ->map(function (array $row, int $index): array {
                $row['no'] = $index + 1;

                return $row;
            })->all();
    }

    private function exportTableDescription(string $label, array $payload, string $selectedSegment): string
    {
        $scope = (string) ($payload['scopeLabel'] ?? 'Area 6');
        $date = (string) ($payload['selectedDateLabel'] ?? '-');
        $latestHour = collect((array) ($payload['hours'] ?? []))
            ->firstWhere('key', (string) ($payload['latestHour'] ?? ''));
        $hourLabel = is_array($latestHour) ? (string) ($latestHour['label'] ?? '-') : '-';
        $segment = $selectedSegment === 'all' ? 'semua segmen' : strtolower($this->formatSegmentLabel($selectedSegment));

        return "{$label} menampilkan posisi {$scope} pada {$date}"
            ." dengan jam terakhir {$hourLabel}, filter {$segment}, satuan Rp juta.";
    }

    private function emptyPayload(string $message): array
    {
        return [
            'ready' => false,
            'message' => $message,
            'selectedDate' => null,
            'selectedDateLabel' => '-',
            'selectedBranch' => 'all',
            'selectedProduct' => 'all',
            'selectedSegment' => 'all',
            'scopeLabel' => 'Area 6',
            'branchCode' => '',
            'periods' => $this->blankPeriods(),
            'hours' => [],
            'latestHour' => null,
            'rows' => [],
            'dataRowCount' => 0,
            'total' => [],
        ];
    }

    private function referencePeriods(string $selectedDate): array
    {
        $date = Carbon::parse($selectedDate);
        $hPeriods = DB::table(self::SSA_TABLE)
            ->where('Month_Day_Year_of_Posisi', '<', $selectedDate)
            ->orderByDesc('Month_Day_Year_of_Posisi')
            ->limit(2)
            ->pluck('Month_Day_Year_of_Posisi')
            ->map(fn ($value): string => Carbon::parse($value)->toDateString())
            ->values();

        return [
            'yoy' => $this->resolveSsaPeriodOnOrBefore($date->copy()->subYearNoOverflow()->toDateString()),
            'ytd' => $this->resolveSsaPeriodOnOrBefore($date->copy()->subYearNoOverflow()->endOfYear()->toDateString()),
            'mtm' => $this->resolveSsaPeriodOnOrBefore($date->copy()->subMonthNoOverflow()->toDateString()),
            'mtd' => $this->resolveSsaPeriodOnOrBefore($date->copy()->subMonthNoOverflow()->endOfMonth()->toDateString()),
            'h2' => $hPeriods->get(1),
            'h1' => $hPeriods->get(0),
        ];
    }

    private function blankPeriods(): array
    {
        return ['yoy' => null, 'ytd' => null, 'mtm' => null, 'mtd' => null, 'h2' => null, 'h1' => null];
    }

    private function resolveSsaPeriodOnOrBefore(string $target): ?string
    {
        $period = DB::table(self::SSA_TABLE)
            ->where('Month_Day_Year_of_Posisi', '<=', $target)
            ->max('Month_Day_Year_of_Posisi');

        return $period ? Carbon::parse($period)->toDateString() : null;
    }

    private function hourColumns(string $latestDate): array
    {
        $hasPositionHour = Schema::hasColumn(self::HOURLY_TABLE, 'posisi_jam');
        if (!$hasPositionHour) {
            return [];
        }

        return DB::table(self::HOURLY_TABLE)
            ->where('posisi', $latestDate)
            ->whereNotNull('posisi_jam')
            ->select('posisi_jam')
            ->distinct()
            ->orderByDesc('posisi_jam')
            ->limit(3)
            ->pluck('posisi_jam')
            ->map(fn ($value): array => [
                'key' => Carbon::parse($value)->format('Y-m-d H:i:s'),
                'label' => Carbon::parse($value)->format('H:i'),
            ])
            ->sortBy('key')
            ->values()
            ->all();
    }

    private function historicalMatrix(array $periods, string $selectedBranch, string $selectedProduct, string $selectedSegment): array
    {
        $periodValues = array_values(array_unique(array_filter($periods)));
        if ($periodValues === []) {
            return [];
        }

        $query = DB::table(self::SSA_TABLE)
            ->whereIn('Month_Day_Year_of_Posisi', $periodValues);

        if ($selectedProduct !== 'all') {
            $query->whereRaw('UPPER(TRIM(produk)) = ?', [$selectedProduct]);
        } else {
            $query->whereRaw('UPPER(TRIM(produk)) IN (?, ?, ?)', self::PRODUCTS);
        }

        $this->applyHistoricalSegmentFilter($query, $selectedSegment);

        $records = $query
            ->select('Month_Day_Year_of_Posisi', 'nama_cabang', 'nama_uker')
            ->selectRaw('SUM(COALESCE(saldo, 0)) as total_saldo')
            ->groupBy('Month_Day_Year_of_Posisi', 'nama_cabang', 'nama_uker')
            ->get();

        $matrix = [];
        $isAreaScope = $selectedBranch === '';
        foreach ($records as $record) {
            $branch = $this->normalizeOfficeName($record->nama_cabang ?? '');
            if (!$this->isAreaBranch($branch)) {
                continue;
            }
            if ($selectedBranch !== '' && $branch !== $selectedBranch) {
                continue;
            }

            $branchCode = $this->branchCode($branch);
            $unit = $isAreaScope ? $branch : $this->normalizeOfficeName($record->nama_uker ?? '');
            $unitCode = $isAreaScope ? '' : $this->extractUkerCode($record->nama_uker ?? '');
            $key = $this->rowKey($branch, $unit, $isAreaScope ? $branchCode : $unitCode);
            $matrix[$key]['branch'] = $branch;
            $matrix[$key]['branch_code'] = $branchCode;
            $matrix[$key]['unit'] = $unit;
            $matrix[$key]['unit_code'] = $unitCode;
            $periodKey = Carbon::parse($record->Month_Day_Year_of_Posisi)->toDateString();
            $matrix[$key]['periods'][$periodKey] = (float) ($matrix[$key]['periods'][$periodKey] ?? 0)
                + (float) $record->total_saldo;
        }

        return $matrix;
    }

    private function hourlyMatrix(string $latestDate, array $hours, string $selectedBranch, string $selectedProduct, string $selectedSegment): array
    {
        $hasPositionHour = Schema::hasColumn(self::HOURLY_TABLE, 'posisi_jam');
        $query = DB::table(self::HOURLY_TABLE)
            ->where('posisi', $latestDate);

        if ($selectedProduct !== 'all') {
            $query->whereRaw('UPPER(TRIM(produk)) = ?', [$selectedProduct]);
        } else {
            $query->whereRaw('UPPER(TRIM(produk)) IN (?, ?, ?)', self::PRODUCTS);
        }

        $this->applyHourlySegmentFilter($query, $selectedSegment);

        $selects = ['mbname', 'brname'];
        if ($hasPositionHour) {
            $selects[] = 'posisi_jam';
        }

        $records = $query
            ->select($selects)
            ->selectRaw('SUM(COALESCE(saldo, 0)) as total_saldo')
            ->groupBy($selects)
            ->get();

        $hourLookup = collect($hours)->pluck('key')->all();
        $matrix = [];
        $isAreaScope = $selectedBranch === '';
        foreach ($records as $record) {
            $branch = $this->normalizeOfficeName($record->mbname ?? '');
            if (!$this->isAreaBranch($branch)) {
                continue;
            }
            if ($selectedBranch !== '' && $branch !== $selectedBranch) {
                continue;
            }

            $branchCode = $this->extractUkerCode($record->mbname ?? '') ?: $this->branchCode($branch);
            $unit = $isAreaScope ? $branch : $this->normalizeOfficeName($record->brname ?? '');
            $unitCode = $isAreaScope ? '' : $this->extractUkerCode($record->brname ?? '');
            $key = $this->rowKey($branch, $unit, $isAreaScope ? $branchCode : $unitCode);
            $matrix[$key]['branch'] = $branch;
            $matrix[$key]['branch_code'] = $branchCode;
            $matrix[$key]['unit'] = $unit;
            $matrix[$key]['unit_code'] = $unitCode;

            $hourKey = $hasPositionHour && !empty($record->posisi_jam)
                ? Carbon::parse($record->posisi_jam)->format('Y-m-d H:i:s')
                : 'latest';

            if ($hours !== [] && !in_array($hourKey, $hourLookup, true)) {
                continue;
            }

            $matrix[$key]['hours'][$hourKey] = (float) ($matrix[$key]['hours'][$hourKey] ?? 0)
                + (float) $record->total_saldo;
        }

        return $matrix;
    }

    private function buildRows(array $historical, array $hourly, array $periods, array $hours, ?string $latestHourKey): array
    {
        $keys = collect(array_keys($historical))
            ->merge(array_keys($hourly))
            ->unique()
            ->sortBy(function (string $key) use ($historical, $hourly): string {
                $base = $hourly[$key] ?? $historical[$key] ?? [];

                return sprintf(
                    '%08d|%08d|%s',
                    $this->sortableOfficeCode($base['branch_code'] ?? ''),
                    $this->sortableOfficeCode($base['unit_code'] ?? ''),
                    strtoupper((string) ($base['unit'] ?? ''))
                );
            })
            ->values();
        $rows = [];
        $no = 1;

        foreach ($keys as $key) {
            $base = $hourly[$key] ?? $historical[$key] ?? [];
            $row = [
                'no' => $no++,
                'branch' => (string) ($base['branch'] ?? ''),
                'branch_code' => (string) ($base['branch_code'] ?? ''),
                'unit_code' => (string) ($base['unit_code'] ?? ''),
                'unit' => (string) ($base['unit'] ?? ''),
                'row_type' => 'detail',
                'period_values' => [],
                'hour_values' => [],
                'delta_values' => [],
            ];

            foreach ($periods as $periodKey => $period) {
                $row['period_values'][$periodKey] = $period ? (float) ($historical[$key]['periods'][$period] ?? 0) : 0.0;
            }

            foreach ($hours as $hour) {
                $row['hour_values'][$hour['key']] = (float) ($hourly[$key]['hours'][$hour['key']] ?? 0);
            }

            $latestHourValue = $latestHourKey !== null
                ? (float) ($row['hour_values'][$latestHourKey] ?? 0)
                : 0.0;
            foreach (self::DELTA_PERIOD_MAP as $deltaKey => $periodKey) {
                $row['delta_values'][$deltaKey] = $latestHourKey !== null
                    ? $latestHourValue - (float) ($row['period_values'][$periodKey] ?? 0)
                    : 0.0;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    private function withBranchSubtotals(array $rows, string $selectedBranch, array $periods, array $hours): array
    {
        if ($selectedBranch === '' || $rows === []) {
            return $rows;
        }

        $groups = ['retail' => [], 'micro' => [], 'other' => []];
        foreach ($rows as $row) {
            $groups[$this->officeGroup((string) ($row['unit'] ?? ''))][] = $row;
        }

        $orderedRows = [];
        $no = 1;
        foreach (['retail', 'micro', 'other'] as $group) {
            foreach ($groups[$group] as $row) {
                $row['no'] = $no++;
                $orderedRows[] = $row;
            }

            if ($groups[$group] === [] || !in_array($group, ['retail', 'micro'], true)) {
                continue;
            }

            $orderedRows[] = $this->subtotalRow(
                $groups[$group],
                $selectedBranch,
                $group,
                $periods,
                $hours
            );
        }

        return $orderedRows;
    }

    private function subtotalRow(array $rows, string $branch, string $group, array $periods, array $hours): array
    {
        $total = $this->totalRow($rows, $periods, $hours);
        $groupLabel = $group === 'retail' ? 'RITEL' : 'MIKRO';

        return [
            'no' => '',
            'branch' => $branch,
            'branch_code' => (string) ($rows[0]['branch_code'] ?? $this->branchCode($branch)),
            'unit_code' => '',
            'unit' => 'TOTAL ' . $groupLabel . ' - ' . strtoupper($branch),
            'row_type' => 'subtotal_' . $group,
            'period_values' => $total['period_values'],
            'hour_values' => $total['hour_values'],
            'delta_values' => $total['delta_values'],
        ];
    }

    private function totalRow(array $rows, array $periods, array $hours): array
    {
        $total = ['period_values' => [], 'hour_values' => [], 'delta_values' => []];
        foreach (array_keys($periods) as $periodKey) {
            $total['period_values'][$periodKey] = array_sum(array_map(fn ($row) => (float) ($row['period_values'][$periodKey] ?? 0), $rows));
        }
        foreach ($hours as $hour) {
            $total['hour_values'][$hour['key']] = array_sum(array_map(fn ($row) => (float) ($row['hour_values'][$hour['key']] ?? 0), $rows));
        }
        foreach (array_keys(self::DELTA_PERIOD_MAP) as $deltaKey) {
            $total['delta_values'][$deltaKey] = array_sum(array_map(fn ($row) => (float) ($row['delta_values'][$deltaKey] ?? 0), $rows));
        }

        return $total;
    }

    private function latestHourKey(array $hours): ?string
    {
        $latest = end($hours);

        return is_array($latest) && isset($latest['key']) ? (string) $latest['key'] : null;
    }

    public function formatDateLabel(?string $date): string
    {
        if (!$date) {
            return '-';
        }

        try {
            return Carbon::parse($date)->translatedFormat('d M y');
        } catch (Throwable) {
            return (string) $date;
        }
    }

    private function normalizeProduct(?string $product): string
    {
        $value = strtoupper(trim((string) $product));

        return in_array($value, self::PRODUCTS, true) ? $value : 'all';
    }

    private function normalizeBranchFilter(?string $branch): string
    {
        $value = trim((string) $branch);
        if ($value === '' || strtolower($value) === 'all') {
            return '';
        }

        $normalized = $this->normalizeOfficeName($value);

        return $this->isAreaBranch($normalized) ? $normalized : '';
    }

    private function normalizeSegmentFilter(?string $segment): string
    {
        $value = strtoupper(trim((string) $segment));

        return $value === '' || $value === 'ALL' ? 'all' : $value;
    }

    private function normalizeOfficeName($value): string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return '';
        }

        $text = preg_replace('/^[0-9]+\s*--\s*/', '', $text) ?? $text;
        $text = preg_replace('/\s*\([^)]*\)\s*$/', '', $text) ?? $text;
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;
        $text = trim($text);

        $upper = strtoupper($text);
        foreach (self::AREA_BRANCHES as $branch) {
            if (strtoupper($branch) === $upper) {
                return $branch;
            }
        }

        return $this->titleOfficeName($text);
    }

    private function titleOfficeName(string $value): string
    {
        $upper = strtoupper($value);
        if (str_starts_with($upper, 'KC ') || str_starts_with($upper, 'KCP ') || str_starts_with($upper, 'UNIT ')) {
            $prefix = strtok($upper, ' ');
            $rest = trim(substr($upper, strlen((string) $prefix)));

            return trim($prefix . ' ' . ucwords(strtolower($rest)));
        }

        return ucwords(strtolower($value));
    }

    private function rowKey(string $branch, string $unit, string $unitCode = ''): string
    {
        return strtoupper($branch . '|' . $this->normalizeUkerCode($unitCode) . '|' . $unit);
    }

    private function extractUkerCode(mixed $office): string
    {
        $office = trim((string) $office);
        if (preg_match('/^\s*([0-9]+)\s*--/', $office, $matches) === 1) {
            return (string) $matches[1];
        }

        return '';
    }

    private function normalizeUkerCode(mixed $code): string
    {
        $digits = preg_replace('/\D+/', '', trim((string) $code)) ?? '';

        return ltrim($digits, '0');
    }

    private function formatUkerCode(mixed $code): string
    {
        $digits = preg_replace('/\D+/', '', trim((string) $code)) ?? '';

        return $digits === '' ? '' : str_pad($digits, 5, '0', STR_PAD_LEFT);
    }

    private function sortableOfficeCode(mixed $code): int
    {
        $digits = preg_replace('/\D+/', '', trim((string) $code)) ?? '';

        return $digits === '' ? 99_999_999 : (int) $digits;
    }

    private function branchCode(string $branch): string
    {
        return self::AREA_BRANCH_CODES[$branch] ?? '';
    }

    private function officeGroup(string $unit): string
    {
        $unit = strtoupper(trim($unit));
        if (str_starts_with($unit, 'KC ') || str_starts_with($unit, 'KCP ')) {
            return 'retail';
        }

        return str_starts_with($unit, 'UNIT ') ? 'micro' : 'other';
    }

    private function segmentOptions(): array
    {
        $segments = ['all' => 'Semua Segmen', 'RITEL' => 'Ritel', 'KORPORASI' => 'Korporasi'];
        if (!Schema::hasTable(self::HOURLY_TABLE)) {
            return $segments;
        }

        $segmentExpression = $this->hourlySegmentExpression();
        if ($segmentExpression === null) {
            return $segments;
        }

        DB::table(self::HOURLY_TABLE)
            ->selectRaw($segmentExpression . ' as segment_value')
            ->whereRaw($segmentExpression . ' IS NOT NULL')
            ->distinct()
            ->pluck('segment_value')
            ->map(fn ($value): string => $this->normalizeSegmentFilter((string) $value))
            ->filter(fn (string $value): bool => $value !== 'all')
            ->unique()
            ->sort()
            ->each(function (string $segment) use (&$segments): void {
                $segments[$segment] = $this->formatSegmentLabel($segment);
            });

        return $segments;
    }

    private function formatSegmentLabel(string $segment): string
    {
        return ucwords(strtolower(str_replace('_', ' ', $segment)));
    }

    private function applyHourlySegmentFilter($query, string $selectedSegment): void
    {
        if ($selectedSegment === 'all') {
            return;
        }

        $segmentExpression = $this->hourlySegmentExpression();
        if ($segmentExpression !== null) {
            $query->whereRaw('UPPER(TRIM(' . $segmentExpression . ')) = ?', [$selectedSegment]);
        }
    }

    private function hourlySegmentExpression(): ?string
    {
        $hasSegmen2 = Schema::hasColumn(self::HOURLY_TABLE, 'segmen2');
        $hasSegmen = Schema::hasColumn(self::HOURLY_TABLE, 'segmen');

        if ($hasSegmen2 && $hasSegmen) {
            return "COALESCE(NULLIF(TRIM(segmen2), ''), NULLIF(TRIM(segmen), ''))";
        }

        if ($hasSegmen2) {
            return "NULLIF(TRIM(segmen2), '')";
        }

        if ($hasSegmen) {
            return "NULLIF(TRIM(segmen), '')";
        }

        return null;
    }

    private function applyHistoricalSegmentFilter($query, string $selectedSegment): void
    {
        if ($selectedSegment === 'all') {
            return;
        }

        if (Schema::hasColumn(self::SSA_TABLE, 'segmentasi')) {
            $ssaSegments = $this->ssaSegmentsForHourlySegment($selectedSegment);
            if ($ssaSegments !== []) {
                $placeholders = implode(', ', array_fill(0, count($ssaSegments), '?'));
                $query->whereRaw('UPPER(TRIM(segmentasi)) IN (' . $placeholders . ')', $ssaSegments);
            } else {
                $query->whereRaw('UPPER(TRIM(segmentasi)) = ?', [$selectedSegment]);
            }
        }
    }

    /**
     * Hourly DPK and SSA Simpanan use different segment labels for the same scope.
     *
     * @return array<int, string>
     */
    private function ssaSegmentsForHourlySegment(string $selectedSegment): array
    {
        return match ($selectedSegment) {
            'KORPORASI', 'WHOLESALE' => ['WHOLESALE'],
            'MIKRO', 'MICRO' => ['MICRO'],
            'RITEL' => ['RITEL'],
            default => [],
        };
    }

    private function isAreaBranch(string $branch): bool
    {
        return in_array($branch, self::AREA_BRANCHES, true);
    }
}
