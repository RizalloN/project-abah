<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class AlmafactsDashboardController extends Controller
{
    private const AREA_KEY = 'area6';
    private const AREA_BRANCHES = ['KC Madiun', 'KC Magetan', 'KC Ngawi', 'KC Ponorogo'];
    private const PROFIT_LABEL = '15. Laba Setelah Pajak';
    private const RKA_LABEL = 'E. LABA TOTAL';
    private const KPI_SPREADSHEET_ID = '1KgXJ4fi9u4-mJyaZADXF0cM9wJnVlh0f7sQBZeR8fLY';
    private const KPI_SPREADSHEET_URL = 'https://docs.google.com/spreadsheets/d/1KgXJ4fi9u4-mJyaZADXF0cM9wJnVlh0f7sQBZeR8fLY/edit?usp=sharing';
    private const KPI_RM_MIKRO_SPREADSHEET_ID = '1v1loife4UzSSsdJ9yGYl3SSuKtk_16CwtlKMj2f8dTM';
    private const KPI_RM_MIKRO_SPREADSHEET_URL = 'https://docs.google.com/spreadsheets/d/1v1loife4UzSSsdJ9yGYl3SSuKtk_16CwtlKMj2f8dTM/edit?usp=sharing';
    private const KPI_MANTRI_SPREADSHEET_ID = '1qiek9zPfsd7NSGSSWoQQZAhIFD9hNnfoeLvQEoz1few';
    private const KPI_MANTRI_SPREADSHEET_URL = 'https://docs.google.com/spreadsheets/d/1qiek9zPfsd7NSGSSWoQQZAhIFD9hNnfoeLvQEoz1few/edit?usp=sharing';
    private const KPI_LINK_TABLE = 'external_report_links';
    private const KPI_LINK_GROUP = 'almafacts_kpi';
    private const KPI_SHEETS = [
        'mbm' => [
            'label' => 'KPI MBM',
            'title' => 'KPI MBM',
            'sheet' => 'KPI MBM',
            'icon' => 'fas fa-users-cog',
        ],
        'ka-unit' => [
            'label' => 'KPI KA Unit',
            'title' => 'KPI KA UNIT',
            'sheet' => 'KPI Kaunit',
            'icon' => 'fas fa-user-tie',
        ],
        'rm-mikro' => [
            'label' => 'KPI RM Mikro',
            'title' => 'KPI RM MIKRO',
            'sheet' => 'rank',
            'spreadsheet_id' => self::KPI_RM_MIKRO_SPREADSHEET_ID,
            'spreadsheet_url' => self::KPI_RM_MIKRO_SPREADSHEET_URL,
            'expected_header_any' => ['NETT DISBURSEMENT KUR', 'DEBITUR MIKRO', 'RANK'],
            'icon' => 'fas fa-chart-bar',
        ],
        'mantri' => [
            'label' => 'KPI Mantri',
            'title' => 'KPI MANTRI',
            'sheet' => 'RANK KPI',
            'spreadsheet_id' => self::KPI_MANTRI_SPREADSHEET_ID,
            'spreadsheet_url' => self::KPI_MANTRI_SPREADSHEET_URL,
            'expected_header_any' => ['JG', 'LAMA DI UKER', 'NETT DISBURSEMENT', 'RANK CABANG'],
            'icon' => 'fas fa-user-check',
        ],
    ];
    private const MONTH_COLUMNS = [
        1 => 'jan',
        2 => 'feb',
        3 => 'mar',
        4 => 'apr',
        5 => 'may',
        6 => 'jun',
        7 => 'jul',
        8 => 'aug',
        9 => 'sep',
        10 => 'oct',
        11 => 'nov',
        12 => 'dec',
    ];

    public function labaRugi(Request $request)
    {
        $periodOptions = $this->periodOptions();
        $selectedPeriod = $this->resolveSelectedPeriod($request->input('periode'), $periodOptions);
        $selectedBranch = $this->resolveSelectedBranch($request->input('cabang'));
        $rkaPeriodOptions = $this->rkaPeriodOptions($selectedPeriod);
        $selectedRkaPeriod = $this->resolveSelectedRkaPeriod($request->input('rka_periode'), $rkaPeriodOptions, $selectedPeriod);
        $comparisonPeriods = $this->comparisonPeriods($selectedPeriod);
        $rows = $selectedPeriod
            ? $this->buildRows($selectedPeriod, $selectedBranch, $selectedRkaPeriod, $comparisonPeriods)
            : [];

        return view('report.almafacts.kinerja-laba-rugi', [
            'periodOptions' => $periodOptions,
            'selectedPeriod' => $selectedPeriod,
            'selectedPeriodLabel' => $this->periodLabel($selectedPeriod),
            'branchOptions' => $this->branchOptions(),
            'selectedBranch' => $selectedBranch,
            'selectedBranchLabel' => $selectedBranch === self::AREA_KEY ? 'Area 6' : $selectedBranch,
            'rkaPeriodOptions' => $rkaPeriodOptions,
            'selectedRkaPeriod' => $selectedRkaPeriod,
            'selectedRkaLabel' => $this->monthLabel($selectedRkaPeriod),
            'rkaDecLabel' => $this->decemberLabel($selectedRkaPeriod),
            'comparisonPeriods' => $comparisonPeriods,
            'comparisonLabels' => [
                'yoy' => $this->monthLabel($comparisonPeriods['yoy'] ?? null),
                'ytd' => $this->monthLabel($comparisonPeriods['ytd'] ?? null),
                'm2' => $this->monthLabel($comparisonPeriods['m2'] ?? null),
                'm1' => $this->monthLabel($comparisonPeriods['m1'] ?? null),
                'current' => $this->monthLabel($selectedPeriod),
            ],
            'rows' => $rows,
            'showUnitColumn' => $selectedBranch !== self::AREA_KEY,
            'summary' => $this->summary($rows),
        ]);
    }

    public function kpi(Request $request, ?string $sheet = null)
    {
        $selectedSheetKey = $this->resolveKpiSheetKey($sheet ?: $request->input('sheet'));
        $cacheKey = $this->kpiSheetCacheKey($selectedSheetKey);
        $selectedSheet = $this->kpiSheetConfig($selectedSheetKey);

        if ($request->boolean('refresh')) {
            Cache::forget($cacheKey);
        }

        $payload = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($selectedSheetKey): array {
            return $this->fetchKpiSheetPayload($selectedSheetKey);
        });

        return view('report.almafacts.kpi', [
            'sheetOptions' => self::KPI_SHEETS,
            'selectedSheetKey' => $selectedSheetKey,
            'selectedSheet' => $selectedSheet,
            'spreadsheetUrl' => $selectedSheet['spreadsheet_url'] ?? self::KPI_SPREADSHEET_URL,
            'csvUrl' => $this->kpiSheetCsvUrl(
                $selectedSheet['sheet'],
                $selectedSheet['spreadsheet_id'] ?? self::KPI_SPREADSHEET_ID
            ),
            'header' => $payload['header'] ?? [],
            'headerColumns' => $payload['header_columns'] ?? [],
            'headerGroups' => $payload['header_groups'] ?? [],
            'rows' => $payload['rows'] ?? [],
            'summary' => $payload['summary'] ?? [],
            'error' => $payload['error'] ?? null,
            'fetchedAt' => $payload['fetched_at'] ?? null,
        ]);
    }

    private function resolveKpiSheetKey(mixed $value): string
    {
        $key = strtolower(trim((string) $value));

        return array_key_exists($key, self::KPI_SHEETS) ? $key : array_key_first(self::KPI_SHEETS);
    }

    private function fetchKpiSheetPayload(string $sheetKey): array
    {
        $sheet = $this->kpiSheetConfig($sheetKey);
        $spreadsheetId = $sheet['spreadsheet_id'] ?? self::KPI_SPREADSHEET_ID;
        $sheetNames = array_values(array_unique(array_merge(
            [$sheet['sheet']],
            $sheet['fallback_sheets'] ?? []
        )));
        $lastError = null;

        foreach ($sheetNames as $sheetName) {
            try {
                $response = Http::timeout(20)
                    ->retry(2, 300)
                    ->get($this->kpiSheetCsvUrl($sheetName, $spreadsheetId));

                if (!$response->successful()) {
                    $lastError = 'Google Sheet mengembalikan status ' . $response->status() . '.';
                    continue;
                }

                $csv = trim($response->body());
                if ($csv === '' || str_contains(strtolower(substr($csv, 0, 300)), '<html')) {
                    $lastError = 'Sheet tidak dapat dibaca sebagai CSV. Pastikan akses spreadsheet sudah terbuka untuk viewer.';
                    continue;
                }

                $parsed = $this->parseKpiSheetCsv($csv, $sheetKey);
                if (!$this->kpiSheetPayloadMatches($parsed, $sheet)) {
                    $lastError = "Sheet {$sheetName} tidak cocok dengan struktur {$sheet['label']}.";
                    continue;
                }

                return [
                    'header' => $parsed['header'],
                    'header_columns' => $parsed['header_columns'],
                    'header_groups' => $parsed['header_groups'],
                    'rows' => $parsed['rows'],
                    'summary' => [
                        'row_count' => count($parsed['rows']),
                        'column_count' => count($parsed['header']),
                        'sheet_name' => $sheetName,
                        'sheet_title' => $parsed['sheet_title'] ?: $sheet['title'],
                    ],
                    'fetched_at' => now()->toDateTimeString(),
                    'error' => null,
                ];
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
            }
        }

        return $this->emptyKpiSheetPayload($lastError ?: 'Sheet KPI tidak dapat dibaca.');
    }

    private function emptyKpiSheetPayload(string $message): array
    {
        return [
            'header' => [],
            'header_columns' => [],
            'header_groups' => [],
            'rows' => [],
            'summary' => [
                'row_count' => 0,
                'column_count' => 0,
                'sheet_title' => '',
            ],
            'fetched_at' => now()->toDateTimeString(),
            'error' => $message,
        ];
    }

    private function parseKpiSheetCsv(string $csv, string $sheetKey): array
    {
        $lines = preg_split('/\r\n|\n|\r/', $csv) ?: [];
        $rows = [];

        foreach ($lines as $line) {
            $cells = array_map(
                static fn ($value): string => trim((string) $value),
                str_getcsv($line)
            );

            if (collect($cells)->every(static fn (string $value): bool => $value === '')) {
                continue;
            }

            $rows[] = $cells;
        }

        if ($rows === []) {
            return ['header' => [], 'header_columns' => [], 'header_groups' => [], 'rows' => [], 'sheet_title' => ''];
        }

        $maxColumns = max(array_map('count', $rows));
        $rows = array_map(
            static fn (array $row): array => array_pad($row, $maxColumns, ''),
            $rows
        );

        $header = array_shift($rows) ?? [];
        $secondHeader = null;
        if (isset($rows[0]) && $this->isKpiSheetSecondHeaderRow($rows[0])) {
            $secondHeader = array_shift($rows);
        }

        $rows = array_values(array_filter(
            $rows,
            fn (array $row): bool => !$this->isKpiSheetFilterRow($row)
        ));
        [$header, $secondHeader, $rows] = $this->trimKpiSheetTrailingBlankColumns($header, $secondHeader, $rows);

        $headerMeta = $this->buildKpiSheetHeaderMeta($header, $sheetKey, $secondHeader);

        return [
            'header' => $headerMeta['header'],
            'header_columns' => $headerMeta['columns'],
            'header_groups' => $headerMeta['groups'],
            'rows' => $rows,
            'sheet_title' => $headerMeta['title'],
        ];
    }

    private function trimKpiSheetTrailingBlankColumns(array $header, ?array $secondHeader, array $rows): array
    {
        $columnCount = max([
            count($header),
            $secondHeader === null ? 0 : count($secondHeader),
            ...array_map('count', $rows),
        ]);

        while ($columnCount > 0) {
            $index = $columnCount - 1;
            $headerIsBlank = $this->isKpiSheetBlankHeaderCell($header[$index] ?? '')
                && ($secondHeader === null || $this->isKpiSheetBlankHeaderCell($secondHeader[$index] ?? ''));

            if (!$headerIsBlank) {
                break;
            }

            $columnIsBlank = true;
            foreach ($rows as $row) {
                if (!$this->isKpiSheetBlankDataCell($row[$index] ?? '')) {
                    $columnIsBlank = false;
                    break;
                }
            }

            if (!$columnIsBlank) {
                break;
            }

            $columnCount--;
        }

        $sliceRow = static function (array $row) use ($columnCount): array {
            return array_slice(array_pad($row, $columnCount, ''), 0, $columnCount);
        };

        return [
            $sliceRow($header),
            $secondHeader === null ? null : $sliceRow($secondHeader),
            array_map($sliceRow, $rows),
        ];
    }

    private function isKpiSheetBlankHeaderCell(mixed $value): bool
    {
        return trim((string) $value) === '';
    }

    private function isKpiSheetBlankDataCell(mixed $value): bool
    {
        return in_array(trim((string) $value), ['', '-', '--'], true);
    }

    private function buildKpiSheetHeaderMeta(array $rawHeader, string $sheetKey, ?array $secondHeader = null): array
    {
        if ($secondHeader !== null) {
            return $this->buildKpiSheetTwoRowHeaderMeta($rawHeader, $secondHeader, $sheetKey);
        }

        return $this->buildKpiSheetSingleRowHeaderMeta($rawHeader, $sheetKey);
    }

    private function buildKpiSheetSingleRowHeaderMeta(array $rawHeader, string $sheetKey): array
    {
        $columns = [];
        $title = '';
        $currentGroup = null;
        $currentWeight = null;

        foreach (array_values($rawHeader) as $index => $value) {
            $raw = trim((string) $value);
            $label = $raw;
            $group = null;
            $leaf = $raw;

            if ($index === 0 && str_starts_with(strtoupper($raw), 'KEY PERFORMING INDICATOR')) {
                $title = trim(preg_replace('/\s+BO\s*$/i', '', $raw) ?? $raw);
                $label = 'BO';
                $leaf = 'BO';
            }

            if ($label === '' && $currentGroup !== null) {
                $group = $currentGroup;
                $leaf = $currentWeight ?: 'Bobot';
            } elseif (preg_match('/^(.+?)\s+(PENCP|PENCAPAIAN)$/i', $label, $matches) === 1) {
                $group = trim($matches[1]);
                $currentGroup = $group;
                $currentWeight = null;
                $leaf = strtoupper(trim($matches[2]));
            } elseif (in_array(strtoupper($label), ['SCORE', 'NILAI'], true) && $currentGroup !== null && $currentWeight === null) {
                $group = $currentGroup;
                $leaf = strtoupper($label);
                $currentGroup = null;
            } elseif (preg_match('/^(.+?)\s+BOBOT\s+(.+)$/i', $label, $matches) === 1) {
                $group = strtoupper(trim($matches[1]));
                $currentGroup = $group;
                $currentWeight = 'Bobot ' . trim($matches[2]);
                $leaf = 'Pencapaian';
            } else {
                $currentGroup = null;
                $currentWeight = null;
                $leaf = $label !== '' ? $label : 'Kolom ' . ($index + 1);
            }

            $columns[] = [
                'label' => $leaf,
                'group' => $group,
                'sortable' => true,
                'index' => $index,
            ];
        }

        return [
            'header' => array_map(static fn (array $column): string => $column['label'], $columns),
            'columns' => $columns,
            'groups' => $this->collapseKpiHeaderGroups($columns),
            'title' => $title ?: (self::KPI_SHEETS[$sheetKey]['title'] ?? ''),
        ];
    }

    private function buildKpiSheetTwoRowHeaderMeta(array $rawHeader, array $secondHeader, string $sheetKey): array
    {
        $columns = [];
        $title = '';
        $currentGroup = null;

        foreach (array_values($rawHeader) as $index => $value) {
            $raw = trim((string) $value);
            $leafRaw = trim((string) ($secondHeader[$index] ?? ''));
            $label = $raw;

            if ($index === 0 && str_starts_with(strtoupper($raw), 'KEY PERFORMING INDICATOR')) {
                $title = trim(preg_replace('/\s+BO\s*$/i', '', $raw) ?? $raw);
                $label = 'BO';
            }

            if ($label !== '') {
                $currentGroup = $label;
            }

            $group = null;
            if ($leafRaw !== '' && $currentGroup !== null && $currentGroup !== $leafRaw) {
                $group = $currentGroup;
                $leaf = $leafRaw;
            } else {
                $leaf = $label !== '' ? $label : ($leafRaw !== '' ? $leafRaw : 'Kolom ' . ($index + 1));
            }

            $columns[] = [
                'label' => $leaf,
                'group' => $group,
                'sortable' => true,
                'index' => $index,
            ];
        }

        return [
            'header' => array_map(static fn (array $column): string => $column['label'], $columns),
            'columns' => $columns,
            'groups' => $this->collapseKpiHeaderGroups($columns),
            'title' => $title ?: (self::KPI_SHEETS[$sheetKey]['title'] ?? ''),
        ];
    }

    private function collapseKpiHeaderGroups(array $columns): array
    {
        $groups = [];
        $index = 0;
        $count = count($columns);

        while ($index < $count) {
            $column = $columns[$index];
            $group = $column['group'] ?? null;

            if ($group === null || $group === '') {
                $groups[] = [
                    'label' => $column['label'],
                    'colspan' => 1,
                    'rowspan' => 2,
                    'start' => $index,
                ];
                $index++;
                continue;
            }

            $colspan = 1;
            while (($index + $colspan) < $count && ($columns[$index + $colspan]['group'] ?? null) === $group) {
                $colspan++;
            }

            $groups[] = [
                'label' => $group,
                'colspan' => $colspan,
                'rowspan' => 1,
                'start' => $index,
            ];
            $index += $colspan;
        }

        return $groups;
    }

    private function isKpiSheetFilterRow(array $row): bool
    {
        $leadingCells = array_slice($row, 0, 4);
        $leadingBlank = collect($leadingCells)->every(static fn ($value): bool => trim((string) $value) === '');
        $nonBlankCount = collect($row)->filter(static fn ($value): bool => trim((string) $value) !== '')->count();

        return $leadingBlank && $nonBlankCount <= 3;
    }

    private function isKpiSheetSecondHeaderRow(array $row): bool
    {
        $nonBlank = collect($row)
            ->map(static fn ($value): string => strtoupper(trim((string) $value)))
            ->filter()
            ->values();

        if ($nonBlank->count() < 2) {
            return false;
        }

        $headerTokenCount = $nonBlank->filter(static function (string $value): bool {
            return in_array($value, ['PENCP', 'PENCAPAIAN', 'SCORE', 'BOBOT'], true)
                || str_contains($value, 'PENCP')
                || str_contains($value, 'SCORE');
        })->count();

        return $headerTokenCount >= 2;
    }

    private function kpiSheetPayloadMatches(array $parsed, array $sheet): bool
    {
        $needles = $sheet['expected_header_any'] ?? [];
        if ($needles === []) {
            return true;
        }

        $haystack = strtoupper(implode('|', [
            ...($parsed['header'] ?? []),
            ...array_column($parsed['header_groups'] ?? [], 'label'),
            (string) ($parsed['sheet_title'] ?? ''),
        ]));

        foreach ($needles as $needle) {
            if (str_contains($haystack, strtoupper((string) $needle))) {
                return true;
            }
        }

        return false;
    }

    private function kpiSheetCsvUrl(string $sheetName, ?string $spreadsheetId = null): string
    {
        return sprintf(
            'https://docs.google.com/spreadsheets/d/%s/gviz/tq?%s',
            $spreadsheetId ?: self::KPI_SPREADSHEET_ID,
            http_build_query(['tqx' => 'out:csv', 'sheet' => $sheetName])
        );
    }

    private function kpiSheetCacheKey(string $sheetKey): string
    {
        $sheet = $this->kpiSheetConfig($sheetKey);
        $sheetName = $sheet['sheet'] ?? $sheetKey;
        $spreadsheetId = $sheet['spreadsheet_id'] ?? self::KPI_SPREADSHEET_ID;

        return 'dashboard_almafacts:kpi_sheet:v6:' . $sheetKey . ':' . md5($spreadsheetId . '|' . $sheetName);
    }

    private function kpiSheetConfig(string $sheetKey): array
    {
        $base = self::KPI_SHEETS[$sheetKey] ?? self::KPI_SHEETS[array_key_first(self::KPI_SHEETS)];

        if (!Schema::hasTable(self::KPI_LINK_TABLE)) {
            return $base;
        }

        $row = DB::table(self::KPI_LINK_TABLE)
            ->where('group_key', self::KPI_LINK_GROUP)
            ->where('link_key', $sheetKey)
            ->where('is_active', true)
            ->first();

        if (!$row) {
            return $base;
        }

        if (!empty($row->sheet_name)) {
            $base['sheet'] = (string) $row->sheet_name;
        }
        if (!empty($row->spreadsheet_id)) {
            $base['spreadsheet_id'] = (string) $row->spreadsheet_id;
        }
        if (!empty($row->link_url)) {
            $base['spreadsheet_url'] = (string) $row->link_url;
        }

        return $base;
    }

    private function periodOptions(): array
    {
        if (!Schema::hasTable('ssa_almafacts')) {
            return [];
        }

        return DB::table('ssa_almafacts')
            ->where('keterangan_1', self::PROFIT_LABEL)
            ->whereIn('kanca_konsolidasi', self::AREA_BRANCHES)
            ->select('month_day_year_of_posisi')
            ->distinct()
            ->orderByDesc('month_day_year_of_posisi')
            ->pluck('month_day_year_of_posisi')
            ->map(fn ($value): string => (string) $value)
            ->all();
    }

    private function rkaPeriodOptions(?string $selectedPeriod): array
    {
        if (!Schema::hasTable('rka')) {
            return [];
        }

        $years = DB::table('rka')
            ->where('mata_anggaran', self::RKA_LABEL)
            ->select('tahun')
            ->distinct()
            ->orderByDesc('tahun')
            ->pluck('tahun')
            ->map(fn ($value): int => (int) $value)
            ->filter()
            ->values()
            ->all();

        if ($years === [] && $selectedPeriod) {
            $years = [(int) Carbon::parse($selectedPeriod)->format('Y')];
        }

        $options = [];
        foreach ($years as $year) {
            for ($month = 12; $month >= 1; $month--) {
                $period = Carbon::create($year, $month, 1)->endOfMonth()->toDateString();
                $options[] = $period;
            }
        }

        return $options;
    }

    private function resolveSelectedPeriod($value, array $periodOptions): ?string
    {
        $requested = trim((string) $value);
        if ($requested !== '') {
            try {
                $normalized = Carbon::parse($requested)->endOfMonth()->toDateString();
                if (in_array($normalized, $periodOptions, true)) {
                    return $normalized;
                }
            } catch (\Throwable) {
            }
        }

        return $periodOptions[0] ?? null;
    }

    private function resolveSelectedRkaPeriod($value, array $rkaPeriodOptions, ?string $selectedPeriod): ?string
    {
        $requested = trim((string) $value);
        if ($requested !== '') {
            try {
                $normalized = Carbon::parse($requested)->endOfMonth()->toDateString();
                if (in_array($normalized, $rkaPeriodOptions, true)) {
                    return $normalized;
                }
            } catch (\Throwable) {
            }
        }

        if ($selectedPeriod) {
            $candidate = Carbon::parse($selectedPeriod)->endOfMonth()->toDateString();
            if (in_array($candidate, $rkaPeriodOptions, true)) {
                return $candidate;
            }
        }

        return $rkaPeriodOptions[0] ?? null;
    }

    private function resolveSelectedBranch($value): string
    {
        $branch = trim((string) $value);
        if ($branch === '' || strtolower($branch) === self::AREA_KEY) {
            return self::AREA_KEY;
        }

        return in_array($branch, self::AREA_BRANCHES, true) ? $branch : self::AREA_KEY;
    }

    private function branchOptions(): array
    {
        return array_merge([self::AREA_KEY => 'Area 6'], array_combine(self::AREA_BRANCHES, self::AREA_BRANCHES));
    }

    private function comparisonPeriods(?string $selectedPeriod): array
    {
        if (!$selectedPeriod) {
            return ['yoy' => null, 'ytd' => null, 'm2' => null, 'm1' => null];
        }

        $period = Carbon::parse($selectedPeriod)->endOfMonth();

        return [
            'yoy' => $period->copy()->subYearNoOverflow()->endOfMonth()->toDateString(),
            'ytd' => $period->copy()->subYear()->month(12)->endOfMonth()->toDateString(),
            'm2' => $period->copy()->subMonthsNoOverflow(2)->endOfMonth()->toDateString(),
            'm1' => $period->copy()->subMonthNoOverflow()->endOfMonth()->toDateString(),
        ];
    }

    private function buildRows(string $selectedPeriod, string $selectedBranch, ?string $selectedRkaPeriod, array $comparisonPeriods): array
    {
        $periods = array_values(array_filter(array_merge(array_values($comparisonPeriods), [$selectedPeriod])));
        $branches = $selectedBranch === self::AREA_KEY ? self::AREA_BRANCHES : [$selectedBranch];
        $profitRows = $this->profitRows($periods, $branches, $selectedBranch === self::AREA_KEY);
        $rkaRows = $this->rkaRows($selectedRkaPeriod, $branches, $selectedBranch === self::AREA_KEY);

        $keys = array_unique(array_merge(array_keys($profitRows), array_keys($rkaRows)));
        sort($keys, SORT_NATURAL | SORT_FLAG_CASE);

        $rows = [];
        foreach ($keys as $key) {
            $meta = $profitRows[$key]['meta'] ?? $rkaRows[$key]['meta'] ?? null;
            if (!$meta) {
                continue;
            }

            $values = [
                'yoy' => $profitRows[$key]['values'][$comparisonPeriods['yoy'] ?? ''] ?? null,
                'ytd' => $profitRows[$key]['values'][$comparisonPeriods['ytd'] ?? ''] ?? null,
                'm2' => $profitRows[$key]['values'][$comparisonPeriods['m2'] ?? ''] ?? null,
                'm1' => $profitRows[$key]['values'][$comparisonPeriods['m1'] ?? ''] ?? null,
                'current' => $profitRows[$key]['values'][$selectedPeriod] ?? null,
            ];
            $current = (float) ($values['current'] ?? 0);
            $rkaCurrent = $rkaRows[$key]['rka_current'] ?? null;
            $rkaDec = $rkaRows[$key]['rka_dec'] ?? null;

            $rows[] = [
                'key' => $key,
                'branch' => $meta['branch'],
                'unit_code' => $meta['unit_code'] ?? null,
                'unit_name' => $meta['unit_name'] ?? null,
                'unit_type' => $meta['unit_type'] ?? null,
                'values' => $values,
                'deltas' => [
                    'yoy' => $values['yoy'] === null ? null : $current - (float) $values['yoy'],
                    'ytd' => $values['ytd'] === null ? null : $current - (float) $values['ytd'],
                    'm2' => $values['m2'] === null ? null : $current - (float) $values['m2'],
                    'm1' => $values['m1'] === null ? null : $current - (float) $values['m1'],
                ],
                'rka' => [
                    'current' => $rkaCurrent,
                    'current_gap' => $rkaCurrent === null ? null : $current - (float) $rkaCurrent,
                    'dec' => $rkaDec,
                    'dec_gap' => $rkaDec === null ? null : $current - (float) $rkaDec,
                ],
            ];
        }

        usort($rows, function (array $a, array $b) use ($selectedBranch): int {
            if ($selectedBranch === self::AREA_KEY) {
                return array_search($a['branch'], self::AREA_BRANCHES, true) <=> array_search($b['branch'], self::AREA_BRANCHES, true);
            }

            return [$this->unitRank($a['unit_type'] ?? ''), $a['unit_name'] ?? '']
                <=> [$this->unitRank($b['unit_type'] ?? ''), $b['unit_name'] ?? ''];
        });

        return $rows;
    }

    private function profitRows(array $periods, array $branches, bool $areaMode): array
    {
        if ($periods === []) {
            return [];
        }

        $query = DB::table('ssa_almafacts')
            ->where('keterangan_1', self::PROFIT_LABEL)
            ->whereIn('month_day_year_of_posisi', $periods)
            ->whereIn('kanca_konsolidasi', $branches);

        if ($areaMode) {
            $query
                ->select('month_day_year_of_posisi', 'kanca_konsolidasi', DB::raw('SUM(nominal) as nominal'))
                ->groupBy('month_day_year_of_posisi', 'kanca_konsolidasi');
        } else {
            $query
                ->select('month_day_year_of_posisi', 'kanca_konsolidasi', 'jenis_unit_kerja', 'kode_unit_kerja', 'unit_kerja', DB::raw('SUM(nominal) as nominal'))
                ->groupBy('month_day_year_of_posisi', 'kanca_konsolidasi', 'jenis_unit_kerja', 'kode_unit_kerja', 'unit_kerja');
        }

        $rows = [];
        foreach ($query->get() as $row) {
            $key = $areaMode ? (string) $row->kanca_konsolidasi : $this->unitKey($row->kanca_konsolidasi, $row->kode_unit_kerja, $row->unit_kerja);
            $rows[$key]['meta'] ??= [
                'branch' => (string) $row->kanca_konsolidasi,
                'unit_code' => $areaMode ? null : (string) $row->kode_unit_kerja,
                'unit_name' => $areaMode ? null : (string) $row->unit_kerja,
                'unit_type' => $areaMode ? null : $this->normalizeUnitType((string) $row->jenis_unit_kerja, (string) $row->unit_kerja),
            ];
            $rows[$key]['values'][(string) $row->month_day_year_of_posisi] = (float) $row->nominal;
        }

        return $rows;
    }

    private function rkaRows(?string $selectedRkaPeriod, array $branches, bool $areaMode): array
    {
        if (!$selectedRkaPeriod || !Schema::hasTable('rka')) {
            return [];
        }

        $rkaDate = Carbon::parse($selectedRkaPeriod);
        $year = (int) $rkaDate->format('Y');
        $monthColumn = self::MONTH_COLUMNS[(int) $rkaDate->format('n')] ?? 'jan';
        $decColumn = 'dec';

        if ($areaMode) {
            $selects = [
                'kanca',
                DB::raw("SUM(`{$monthColumn}`) as rka_current"),
                DB::raw("SUM(`{$decColumn}`) as rka_dec"),
            ];

            $query = DB::table('rka')
                ->where('tahun', $year)
                ->where('mata_anggaran', self::RKA_LABEL)
                ->whereIn('kanca', $branches)
                ->select($selects)
                ->groupBy('kanca');

            $rows = [];
            foreach ($query->get() as $row) {
                $key = (string) $row->kanca;
                $rows[$key] = [
                    'meta' => [
                        'branch' => $key,
                        'unit_code' => null,
                        'unit_name' => null,
                        'unit_type' => null,
                    ],
                    'rka_current' => (float) $row->rka_current,
                    'rka_dec' => (float) $row->rka_dec,
                ];
            }

            return $rows;
        }

        $selects = [
            'kanca',
            'desc_uker',
            DB::raw("SUM(`{$monthColumn}`) as rka_current"),
            DB::raw("SUM(`{$decColumn}`) as rka_dec"),
        ];

        $query = DB::table('rka')
            ->where('tahun', $year)
            ->where('mata_anggaran', self::RKA_LABEL)
            ->whereIn('kanca', $branches)
            ->select($selects)
            ->groupBy('kanca', 'desc_uker');

        $rows = [];
        foreach ($query->get() as $row) {
            $parsed = $this->parseRkaUnit((string) $row->desc_uker, (string) $row->kanca);
            if ($parsed['branch'] !== (string) $row->kanca) {
                continue;
            }

            $key = $this->unitKey((string) $row->kanca, $parsed['unit_code'], $parsed['unit_name']);

            $rows[$key] = [
                'meta' => [
                    'branch' => (string) $row->kanca,
                    'unit_code' => $parsed['unit_code'],
                    'unit_name' => $parsed['unit_name'],
                    'unit_type' => $this->normalizeUnitType('', $parsed['unit_name']),
                ],
                'rka_current' => (float) $row->rka_current,
                'rka_dec' => (float) $row->rka_dec,
            ];
        }

        return $rows;
    }

    private function parseRkaUnit(string $descUker, string $kanca): array
    {
        $parts = explode('-', $descUker, 2);
        $code = count($parts) === 2 ? trim($parts[0]) : null;
        $name = trim(count($parts) === 2 ? $parts[1] : $descUker);

        return [
            'branch' => $kanca,
            'unit_code' => $code,
            'unit_name' => $name,
            'is_branch_summary' => strcasecmp($this->normalizeUnitName($name), $this->normalizeUnitName($kanca)) === 0,
        ];
    }

    private function unitKey(string $branch, ?string $unitCode, ?string $unitName): string
    {
        $code = trim((string) $unitCode);
        if ($code !== '') {
            return $branch . '|' . $code;
        }

        return $branch . '|' . $this->normalizeUnitName((string) $unitName);
    }

    private function normalizeUnitName(string $value): string
    {
        return preg_replace('/\s+/', ' ', strtoupper(trim($value))) ?? strtoupper(trim($value));
    }

    private function normalizeUnitType(string $jenisUnitKerja, string $unitName): string
    {
        $text = strtoupper(trim($jenisUnitKerja . ' ' . $unitName));
        if (str_contains($text, 'KANTOR CABANG PEMBANTU') || str_starts_with(trim(strtoupper($unitName)), 'KCP')) {
            return 'KCP';
        }
        if (str_contains($text, 'KANTOR CABANG') || preg_match('/^KC\b/i', trim($unitName)) === 1) {
            return 'KC';
        }

        return 'UNIT';
    }

    private function unitRank(string $type): int
    {
        return match (strtoupper($type)) {
            'KC' => 1,
            'KCP' => 2,
            default => 3,
        };
    }

    private function summary(array $rows): array
    {
        $sumValues = static fn(string $key): float => array_reduce($rows, function (float $carry, array $row) use ($key): float {
            return $carry + (float) ($row['values'][$key] ?? 0);
        }, 0.0);

        $sumDeltas = static fn(string $key): float => array_reduce($rows, function (float $carry, array $row) use ($key): float {
            return $carry + (float) ($row['deltas'][$key] ?? 0);
        }, 0.0);

        $sumRka = static fn(string $key): float => array_reduce($rows, function (float $carry, array $row) use ($key): float {
            return $carry + (float) ($row['rka'][$key] ?? 0);
        }, 0.0);

        $current = $sumValues('current');
        $rkaCurrent = $sumRka('current');
        $rkaCurrentGap = $sumRka('current_gap');
        $rkaDec = $sumRka('dec');
        $rkaDecGap = $sumRka('dec_gap');

        return [
            'row_count' => count($rows),
            'current' => $current,
            'rka_current' => $rkaCurrent,
            'rka_current_gap' => $rkaCurrentGap,
            'rka_dec' => $rkaDec,
            'rka_dec_gap' => $rkaDecGap,
            'values' => [
                'yoy' => $sumValues('yoy'),
                'ytd' => $sumValues('ytd'),
                'm2' => $sumValues('m2'),
                'm1' => $sumValues('m1'),
                'current' => $current,
            ],
            'deltas' => [
                'yoy' => $sumDeltas('yoy'),
                'ytd' => $sumDeltas('ytd'),
                'm2' => $sumDeltas('m2'),
                'm1' => $sumDeltas('m1'),
            ],
            'rka' => [
                'current' => $rkaCurrent,
                'current_gap' => $rkaCurrentGap,
                'dec' => $rkaDec,
                'dec_gap' => $rkaDecGap,
            ],
        ];
    }

    private function periodLabel(?string $period): string
    {
        return $period ? Carbon::parse($period)->translatedFormat('d M Y') : '-';
    }

    private function monthLabel(?string $period): string
    {
        return $period ? Carbon::parse($period)->translatedFormat('M y') : '-';
    }

    private function decemberLabel(?string $period): string
    {
        return $period ? Carbon::parse($period)->month(12)->translatedFormat('M y') : '-';
    }

    public function timeseries(Request $request)
    {
        if (!Schema::hasTable('ssa_almafacts')) {
            return redirect()->back()->with('error', 'Tabel ssa_almafacts tidak ditemukan.');
        }

        // Available metrics
        $metrics = DB::table('ssa_almafacts')
            ->select('keterangan_1')
            ->distinct()
            ->orderBy('keterangan_1')
            ->pluck('keterangan_1')
            ->all();

        // Available years
        $years = DB::table('ssa_almafacts')
            ->select(DB::raw('YEAR(month_day_year_of_posisi) as year'))
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->all();

        if (empty($years)) {
            $years = [(int) date('Y')];
        }

        // Selected filter values
        $selectedYear = (int) ($request->input('year') ?: $years[0]);
        $selectedBranch = $this->resolveSelectedBranch($request->input('cabang'));
        $selectedUnit = $request->input('unit_kerja') ?: 'all';
        $selectedMetric = $request->input('metric') ?: self::PROFIT_LABEL;

        // Fetch units map
        $units = $this->fetchUnits();
        $branchOptions = $this->branchOptions();

        // Initial Data Payload
        $initialData = $this->getTimeseriesPayload($selectedMetric, $selectedBranch, $selectedUnit, $selectedYear);

        return view('report.almafacts.timeseries', [
            'metrics' => $metrics,
            'years' => $years,
            'selectedYear' => $selectedYear,
            'selectedBranch' => $selectedBranch,
            'selectedBranchLabel' => $selectedBranch === self::AREA_KEY ? 'Area 6' : $selectedBranch,
            'selectedUnit' => $selectedUnit,
            'selectedMetric' => $selectedMetric,
            'units' => $units,
            'branchOptions' => $branchOptions,
            'initialData' => $initialData,
        ]);
    }

    public function timeseriesData(Request $request)
    {
        $metric = $request->input('metric') ?: self::PROFIT_LABEL;
        $branch = $this->resolveSelectedBranch($request->input('cabang'));
        $unit = $request->input('unit_kerja') ?: 'all';
        $year = (int) ($request->input('year') ?: date('Y'));

        $payload = $this->getTimeseriesPayload($metric, $branch, $unit, $year);

        return response()->json($payload);
    }

    private function fetchUnits(): array
    {
        if (!Schema::hasTable('ssa_almafacts')) {
            return [];
        }

        $rows = DB::table('ssa_almafacts')
            ->whereIn('kanca_konsolidasi', self::AREA_BRANCHES)
            ->whereNotNull('kode_unit_kerja')
            ->where('kode_unit_kerja', '<>', '')
            ->select('kanca_konsolidasi', 'kode_unit_kerja', 'unit_kerja')
            ->get();

        $units = [];
        foreach ($rows as $row) {
            $key = $row->kanca_konsolidasi . '|' . $row->kode_unit_kerja;
            $units[$key] = [
                'kanca_value' => $row->kanca_konsolidasi,
                'value' => $row->kode_unit_kerja,
                'label' => $row->unit_kerja,
            ];
        }

        usort($units, fn($a, $b) => strcmp($a['label'], $b['label']));

        return $units;
    }

    private function getTimeseriesPayload(string $metric, string $branch, string $unit, int $year): array
    {
        $prevYear = $year - 1;
        $branches = $branch === self::AREA_KEY ? self::AREA_BRANCHES : [$branch];

        // 1. Fetch RKA Target (only for 15. Laba Setelah Pajak)
        $rkaSummary = array_fill(1, 12, null);
        $rkaBranchSum = [];
        $rkaUnitSum = [];

        if ($metric === self::PROFIT_LABEL && Schema::hasTable('rka')) {
            $rkaQuery = DB::table('rka')
                ->where('tahun', $year)
                ->where('mata_anggaran', self::RKA_LABEL)
                ->whereIn('kanca', $branches)
                ->get();

            foreach ($rkaQuery as $row) {
                $parsed = $this->parseRkaUnit((string) $row->desc_uker, (string) $row->kanca);
                if ($parsed['branch'] !== (string) $row->kanca) {
                    continue;
                }

                $branchKey = $row->kanca;
                $unitKey = $this->unitKey((string) $row->kanca, $parsed['unit_code'], $parsed['unit_name']);

                // Process months
                foreach (self::MONTH_COLUMNS as $m => $col) {
                    $val = (float) ($row->{$col} ?? 0);
                    
                    if ($rkaSummary[$m] === null) {
                        $rkaSummary[$m] = 0.0;
                    }
                    $rkaSummary[$m] += $val;

                    if (!isset($rkaBranchSum[$branchKey][$m])) {
                        $rkaBranchSum[$branchKey][$m] = 0.0;
                    }
                    $rkaBranchSum[$branchKey][$m] += $val;

                    if (!isset($rkaUnitSum[$unitKey][$m])) {
                        $rkaUnitSum[$unitKey][$m] = 0.0;
                    }
                    $rkaUnitSum[$unitKey][$m] += $val;
                }
            }
        }

        // 2. Fetch Realisasi (Selected Year & Previous Year)
        $realQuery = DB::table('ssa_almafacts')
            ->where('keterangan_1', $metric)
            ->whereIn('kanca_konsolidasi', $branches)
            ->whereBetween('month_day_year_of_posisi', ["{$prevYear}-01-01", "{$year}-12-31"])
            ->get();

        $realSummary = [
            $year => array_fill(1, 12, null),
            $prevYear => array_fill(1, 12, null),
        ];
        $realBranchSum = [];
        $realUnitSum = [];

        foreach ($realQuery as $row) {
            $posisiDate = Carbon::parse($row->month_day_year_of_posisi);
            $rYear = (int) $posisiDate->format('Y');
            $rMonth = (int) $posisiDate->format('n');
            $nominal = (float) $row->nominal;

            $branchKey = $row->kanca_konsolidasi;
            $unitKey = $this->unitKey($row->kanca_konsolidasi, $row->kode_unit_kerja, $row->unit_kerja);

            // Initialize structure
            if (!isset($realBranchSum[$branchKey])) {
                $realBranchSum[$branchKey] = [
                    $year => array_fill(1, 12, null),
                    $prevYear => array_fill(1, 12, null),
                ];
            }
            if (!isset($realUnitSum[$unitKey])) {
                $realUnitSum[$unitKey] = [
                    $year => array_fill(1, 12, null),
                    $prevYear => array_fill(1, 12, null),
                ];
            }

            // Sum up
            if ($realSummary[$rYear][$rMonth] === null) {
                $realSummary[$rYear][$rMonth] = 0.0;
            }
            $realSummary[$rYear][$rMonth] += $nominal;

            if ($realBranchSum[$branchKey][$rYear][$rMonth] === null) {
                $realBranchSum[$branchKey][$rYear][$rMonth] = 0.0;
            }
            $realBranchSum[$branchKey][$rYear][$rMonth] += $nominal;

            if ($realUnitSum[$unitKey][$rYear][$rMonth] === null) {
                $realUnitSum[$unitKey][$rYear][$rMonth] = 0.0;
            }
            $realUnitSum[$unitKey][$rYear][$rMonth] += $nominal;
        }

        // Scale factor: divide by 1,000,000 (Rp Juta)
        $scale = 1000000.0;
        $scaleFn = static fn(?float $val): ?float => $val !== null ? round($val / $scale, 2) : null;

        // Scale RKA lists
        $scaledRkaSummary = array_map($scaleFn, $rkaSummary);
        $scaledRkaBranchSum = [];
        foreach ($rkaBranchSum as $bKey => $months) {
            foreach ($months as $m => $val) {
                $scaledRkaBranchSum[$bKey][$m] = $scaleFn($val);
            }
        }
        $scaledRkaUnitSum = [];
        foreach ($rkaUnitSum as $uKey => $months) {
            foreach ($months as $m => $val) {
                $scaledRkaUnitSum[$uKey][$m] = $scaleFn($val);
            }
        }

        // Scale Realisasi lists
        $scaledRealSummary = [
            $year => array_map($scaleFn, $realSummary[$year]),
            $prevYear => array_map($scaleFn, $realSummary[$prevYear]),
        ];
        $scaledRealBranchSum = [];
        foreach ($realBranchSum as $bKey => $yearsData) {
            foreach ($yearsData as $yKey => $months) {
                $scaledRealBranchSum[$bKey][$yKey] = array_map($scaleFn, $months);
            }
        }
        $scaledRealUnitSum = [];
        foreach ($realUnitSum as $uKey => $yearsData) {
            foreach ($yearsData as $yKey => $months) {
                $scaledRealUnitSum[$uKey][$yKey] = array_map($scaleFn, $months);
            }
        }

        // Build summary datasets
        $summaryDatasets = [];
        $summaryDatasets[] = [
            'label' => "Realisasi {$year}",
            'data' => array_values($scaledRealSummary[$year]),
        ];
        if ($metric === self::PROFIT_LABEL) {
            $summaryDatasets[] = [
                'label' => "RKA {$year}",
                'data' => array_values($scaledRkaSummary),
            ];
        }
        $summaryDatasets[] = [
            'label' => "Realisasi {$prevYear}",
            'data' => array_values($scaledRealSummary[$prevYear]),
        ];

        // Build series (individual charts)
        $series = [];

        if ($branch === self::AREA_KEY) {
            // Area 6 mode: show the 4 branches
            foreach (self::AREA_BRANCHES as $br) {
                $brRealYear = $scaledRealBranchSum[$br][$year] ?? array_fill(1, 12, null);
                $brRealPrev = $scaledRealBranchSum[$br][$prevYear] ?? array_fill(1, 12, null);
                
                $datasets = [];
                $datasets[] = [
                    'label' => "Realisasi {$year}",
                    'data' => array_values($brRealYear),
                ];
                if ($metric === self::PROFIT_LABEL) {
                    $brRka = $scaledRkaBranchSum[$br] ?? array_fill(1, 12, null);
                    $datasets[] = [
                        'label' => "RKA {$year}",
                        'data' => array_values($brRka),
                    ];
                }
                $datasets[] = [
                    'label' => "Realisasi {$prevYear}",
                    'data' => array_values($brRealPrev),
                ];

                $series[$br] = [
                    'datasets' => $datasets,
                ];
            }
        } else {
            // Specific branch mode: show the units of that branch
            $unitKeys = [];
            foreach (array_keys($scaledRealUnitSum) as $key) {
                if (str_starts_with($key, "{$branch}|")) {
                    $unitKeys[$key] = true;
                }
            }
            foreach (array_keys($scaledRkaUnitSum) as $key) {
                if (str_starts_with($key, "{$branch}|")) {
                    $unitKeys[$key] = true;
                }
            }
            $unitKeys = array_keys($unitKeys);
            sort($unitKeys, SORT_NATURAL | SORT_FLAG_CASE);

            // If a specific unit_kerja is selected, only show that unit
            if ($unit !== 'all') {
                $specificKey = $branch . '|' . $unit;
                $unitKeys = [$specificKey];
            }

            foreach ($unitKeys as $uKey) {
                $parts = explode('|', $uKey, 2);
                $uName = $parts[1] ?? $uKey;

                $readableUnitName = $uName;
                foreach ($realQuery as $r) {
                    if ($this->unitKey($r->kanca_konsolidasi, $r->kode_unit_kerja, $r->unit_kerja) === $uKey) {
                        $readableUnitName = $r->unit_kerja;
                        break;
                    }
                }

                $uRealYear = $scaledRealUnitSum[$uKey][$year] ?? array_fill(1, 12, null);
                $uRealPrev = $scaledRealUnitSum[$uKey][$prevYear] ?? array_fill(1, 12, null);

                $datasets = [];
                $datasets[] = [
                    'label' => "Realisasi {$year}",
                    'data' => array_values($uRealYear),
                ];
                if ($metric === self::PROFIT_LABEL) {
                    $uRka = $scaledRkaUnitSum[$uKey] ?? array_fill(1, 12, null);
                    $datasets[] = [
                        'label' => "RKA {$year}",
                        'data' => array_values($uRka),
                    ];
                }
                $datasets[] = [
                    'label' => "Realisasi {$prevYear}",
                    'data' => array_values($uRealPrev),
                ];

                $series[$readableUnitName] = [
                    'datasets' => $datasets,
                ];
            }
        }

        // If a specific unit_kerja is selected, the summary chart is just that unit
        if ($branch !== self::AREA_KEY && $unit !== 'all') {
            $specificKey = $branch . '|' . $unit;
            $uRealYear = $scaledRealUnitSum[$specificKey][$year] ?? array_fill(1, 12, null);
            $uRealPrev = $scaledRealUnitSum[$specificKey][$prevYear] ?? array_fill(1, 12, null);

            $summaryDatasets = [];
            $summaryDatasets[] = [
                'label' => "Realisasi {$year}",
                'data' => array_values($uRealYear),
            ];
            if ($metric === self::PROFIT_LABEL) {
                $uRka = $scaledRkaUnitSum[$specificKey] ?? array_fill(1, 12, null);
                $summaryDatasets[] = [
                    'label' => "RKA {$year}",
                    'data' => array_values($uRka),
                ];
            }
            $summaryDatasets[] = [
                'label' => "Realisasi {$prevYear}",
                'data' => array_values($uRealPrev),
            ];
        }

        return [
            'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agt', 'Sep', 'Okt', 'Nov', 'Des'],
            'value_type' => 'currency',
            'summary' => [
                'datasets' => $summaryDatasets,
            ],
            'series' => $series,
        ];
    }
}
