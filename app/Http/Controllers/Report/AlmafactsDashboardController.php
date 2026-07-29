<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Support\UserBranchScope;
use App\Support\SargableDateFilter;
use App\Jobs\RefreshRemoteDashboardSourcesJob;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class AlmafactsDashboardController extends Controller
{
    private const AREA_KEY = 'area6';
    private const AREA_BRANCHES = ['KC Madiun', 'KC Magetan', 'KC Ngawi', 'KC Ponorogo'];
    private const PROFIT_LABEL = '15. Laba Setelah Pajak';
    private const RKA_LABEL = 'E. LABA TOTAL';
    private const FINANCIAL_LIABILITY_LABELS = [
        'loan' => '33. Pinjaman',
        'savings' => '34. Simpanan',
        'giro' => '35. Giro',
        'tabungan' => '36. Tabungan',
        'deposito' => '37. Deposito',
    ];
    private const FINANCIAL_PNL_LABELS = [
        'interest_income' => '01. Pendapatan Bunga',
        'ftp_expense' => '02. Beban FTP',
        'assets_spread' => '03. Assets Spread',
        'interest_expense' => '04. Beban Bunga',
        'ftp_income' => '05. Pendapatan FTP',
        'liabilities_spread' => '06. Liabilities Spread',
        'contribution_margin' => '07. Contibrution Margin',
        'fee_income' => '08. Fee & Pendapatan Lainnya',
        'overhead_cost' => '09. Overhead Cost',
        'ppop' => '10. PPOP',
        'ckpn_expense' => '11. Beban CKPN',
        'other_income_expense' => '12. Pendapatan/Beban Lainnya',
        'profit_before_tax' => '13. Laba Sebelum Pajak',
        'tax_expense' => '14. Pajak',
        'profit_after_tax' => '15. Laba Setelah Pajak',
    ];
    private const FINANCIAL_PROFITABILITY_LABELS = [
        'yield' => '18. Yield (%)',
        'cof' => '19. COF (%)',
        'nim' => '22. NIM (%)',
        'ohc' => '23. OHC (%)',
        'credit_cost' => '25. Credit Cost (%)',
        'roa_before_tax' => '26. ROA sebelum Pajak (%)',
        'roa_after_tax' => '27. ROA setelah Pajak (%)',
        'bopo' => '28. BOPO (%)',
        'cer' => '29. CER (%)',
    ];
    private const FINANCIAL_LIQUIDITY_LABELS = [
        'ldr' => '30. LDR (%)',
        'casa' => '38. CASA (%)',
    ];
    private const FINANCIAL_ASSET_QUALITY_LABELS = [
        'dpk' => '39. DPK (%)',
        'npl' => '41. NPL (%)',
        'lar' => '40. LAR (%)',
    ];
    private const FINANCIAL_CALCULATION_LABELS = [
        'interest_income' => '01. Pendapatan Bunga',
        'assets_spread' => '03. Assets Spread',
        'interest_expense' => '04. Beban Bunga',
        'ftp_income' => '05. Pendapatan FTP',
        'liabilities_spread' => '06. Liabilities Spread',
        'contribution_margin' => '07. Contibrution Margin',
        'fee_income' => '08. Fee & Pendapatan Lainnya',
        'overhead_cost' => '09. Overhead Cost',
        'ckpn_expense' => '11. Beban CKPN',
        'profit_before_tax' => '13. Laba Sebelum Pajak',
        'profit_after_tax' => '15. Laba Setelah Pajak',
        'operating_income' => '16. Pendapatan Operasional',
        'operating_expense' => '17. Beban Operasional',
        'average_loans' => '31. Ratas Pinjaman',
        'average_savings' => '32. Ratas Simpanan',
        'loans' => '33. Pinjaman',
        'savings' => '34. Simpanan',
        'giro' => '35. Giro',
        'tabungan' => '36. Tabungan',
    ];
    private const FINANCIAL_ASSET_QUALITY_SOURCE_LABELS = [
        'loans' => '33. Pinjaman',
        'savings' => '34. Simpanan',
        'dpk_ratio' => '39. DPK (%)',
        'lar_ratio' => '40. LAR (%)',
        'npl_ratio' => '41. NPL (%)',
    ];
    private const KPI_SPREADSHEET_ID = '175qxZv6PZ6Lw3XaN7u1EdPpEjOEXYUsU';
    private const KPI_SPREADSHEET_URL = 'https://docs.google.com/spreadsheets/d/175qxZv6PZ6Lw3XaN7u1EdPpEjOEXYUsU/edit?usp=sharing&ouid=115821169844020540388&rtpof=true&sd=true';
    private const KPI_KA_UNIT_SPREADSHEET_ID = '1YlsKFIdwdgm9UVG-r8hgSuUn_qTXThMK';
    private const KPI_KA_UNIT_SPREADSHEET_URL = 'https://docs.google.com/spreadsheets/d/1YlsKFIdwdgm9UVG-r8hgSuUn_qTXThMK/edit?usp=sharing&ouid=115821169844020540388&rtpof=true&sd=true';
    private const KPI_RM_MIKRO_SPREADSHEET_ID = '11dzu4edTyp9UFBicNDughtJ43bzvZguh';
    private const KPI_RM_MIKRO_SPREADSHEET_URL = 'https://docs.google.com/spreadsheets/d/11dzu4edTyp9UFBicNDughtJ43bzvZguh/edit?usp=sharing&ouid=115821169844020540388&rtpof=true&sd=true';
    private const KPI_MANTRI_SPREADSHEET_ID = '160V_JvCaoZt3rbUo8GdWj58qt5iqBWg7';
    private const KPI_MANTRI_SPREADSHEET_URL = 'https://docs.google.com/spreadsheets/d/160V_JvCaoZt3rbUo8GdWj58qt5iqBWg7/edit?usp=sharing&ouid=115821169844020540388&rtpof=true&sd=true';
    private const KPI_CONSUMER_SPREADSHEET_ID = '14GrdTrFjTGMR-OpnbPZqNxCK0jNgEx1J';
    private const KPI_CONSUMER_SPREADSHEET_URL = 'https://docs.google.com/spreadsheets/d/14GrdTrFjTGMR-OpnbPZqNxCK0jNgEx1J/edit?usp=sharing&ouid=115821169844020540388&rtpof=true&sd=true';
    private const KPI_RM_SME_SPREADSHEET_ID = '1B5U9VxPSjOyLvygqwCKWZssoyf6xoEDs';
    private const KPI_RM_SME_SPREADSHEET_URL = 'https://docs.google.com/spreadsheets/d/1B5U9VxPSjOyLvygqwCKWZssoyf6xoEDs/edit?usp=sharing&ouid=115821169844020540388&rtpof=true&sd=true';
    private const KPI_LINK_TABLE = 'external_report_links';
    private const KPI_LINK_GROUP = 'almafacts_kpi';
    private const KPI_SHEETS = [
        'mbm' => [
            'label' => 'KPI MBM',
            'title' => 'KPI MBM',
            'sheet' => 'KPI MBM',
            'spreadsheet_id' => self::KPI_SPREADSHEET_ID,
            'spreadsheet_url' => self::KPI_SPREADSHEET_URL,
            'icon' => 'fas fa-users-cog',
        ],
        'ka-unit' => [
            'label' => 'KPI KA Unit',
            'title' => 'KPI KA UNIT',
            'sheet' => 'KPI Kaunit',
            'spreadsheet_id' => self::KPI_KA_UNIT_SPREADSHEET_ID,
            'spreadsheet_url' => self::KPI_KA_UNIT_SPREADSHEET_URL,
            'branch_filter_headers' => ['BO', 'KANCA'],
            'icon' => 'fas fa-user-tie',
        ],
        'rm-mikro' => [
            'label' => 'KPI RM Mikro',
            'title' => 'KPI RM MIKRO',
            'sheet' => 'KPI RM Mikro',
            'spreadsheet_id' => self::KPI_RM_MIKRO_SPREADSHEET_ID,
            'spreadsheet_url' => self::KPI_RM_MIKRO_SPREADSHEET_URL,
            'branch_filter_headers' => ['BO', 'KANCA'],
            'expected_header_any' => ['NETT DISBURSEMENT KUR', 'DEBITUR MIKRO', 'RANK'],
            'icon' => 'fas fa-chart-bar',
        ],
        'rm-sme' => [
            'label' => 'KPI RM SME',
            'title' => 'KPI RM SME',
            'sheet' => 'KPI RM SME',
            'spreadsheet_id' => self::KPI_RM_SME_SPREADSHEET_ID,
            'spreadsheet_url' => self::KPI_RM_SME_SPREADSHEET_URL,
            'branch_filter_headers' => ['BO', 'KANCA'],
            'expected_header_any' => ['AVG BALANCE SMALL', 'POSISI OS SMALL', 'PRODUKTIVITAS RM SME'],
            'force_two_row_header' => true,
            'weighted_metric_pairs' => true,
            'icon' => 'fas fa-briefcase',
        ],
        'mantri' => [
            'label' => 'KPI Mantri',
            'title' => 'KPI MANTRI',
            'sheet' => 'KPI',
            'spreadsheet_id' => self::KPI_MANTRI_SPREADSHEET_ID,
            'spreadsheet_url' => self::KPI_MANTRI_SPREADSHEET_URL,
            'branch_filter_headers' => ['BO', 'KANCA'],
            'expected_header_any' => ['JG', 'LAMA DI UKER', 'NETT DISBURSEMENT', 'RANK CABANG'],
            'icon' => 'fas fa-user-check',
        ],
        'consumer' => [
            'label' => 'KPI Konsumer',
            'title' => 'KPI KONSUMER',
            'sheet' => 'KPI',
            'spreadsheet_id' => self::KPI_CONSUMER_SPREADSHEET_ID,
            'spreadsheet_url' => self::KPI_CONSUMER_SPREADSHEET_URL,
            'branch_filter_headers' => ['KANCA', 'BO'],
            'expected_header_any' => ['SEGMEN', 'KPR', 'BRIGUNA'],
            'split_by_segment' => true,
            'icon' => 'fas fa-user-friends',
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

    public function financialHighlight(Request $request)
    {
        $periodOptions = $this->periodOptions();
        $selectedPeriod = $this->resolveSelectedPeriod($request->input('periode'), $periodOptions);
        $selectedBranch = $this->resolveSelectedBranch($request->input('cabang'));
        $unitOptions = $this->financialUnitOptions($selectedBranch);
        $unitFilter = $this->resolveFinancialUnitFilter($request, $unitOptions);
        $comparisonPeriods = $this->financialComparisonPeriods($selectedPeriod);
        $snapshots = $selectedPeriod
            ? $this->financialSnapshots($comparisonPeriods, $selectedBranch, $unitFilter)
            : [];
        $sections = $this->financialHighlightSections($snapshots);

        return view('report.almafacts.financial-highlight', [
            'periodOptions' => $periodOptions,
            'selectedPeriod' => $selectedPeriod,
            'selectedPeriodLabel' => $this->financialMonthLabel($selectedPeriod),
            'branchOptions' => $this->branchOptions(),
            'selectedBranch' => $selectedBranch,
            'selectedBranchLabel' => $selectedBranch === self::AREA_KEY ? 'Area 6' : $selectedBranch,
            'unitOptions' => $unitOptions,
            'unitFilter' => $unitFilter,
            'comparisonPeriods' => $comparisonPeriods,
            'comparisonLabels' => [
                'yoy' => $this->financialMonthLabel($comparisonPeriods['yoy'] ?? null),
                'ytd' => $this->financialMonthLabel($comparisonPeriods['ytd'] ?? null),
                'm1' => $this->financialMonthLabel($comparisonPeriods['m1'] ?? null),
                'current' => $this->financialMonthLabel($selectedPeriod),
            ],
            'sourcePeriods' => $this->financialSourcePeriodSummary($snapshots),
            'sections' => $sections,
            'summaryCards' => $this->financialSummaryCards($snapshots['current'] ?? []),
        ]);
    }

    public function kpi(Request $request, ?string $sheet = null)
    {
        $selectedSheetKey = $this->resolveKpiSheetKey($sheet ?: $request->input('sheet'));
        $cacheKey = $this->kpiSheetCacheKey($selectedSheetKey);
        $selectedSheet = $this->kpiSheetConfig($selectedSheetKey);

        if ($request->boolean('refresh')) {
            $this->queueKpiSourceRefresh([$selectedSheetKey]);
        }

        $payload = $this->cachedKpiSheetPayload($selectedSheetKey, $cacheKey);
        $kpiBranchFilter = $this->kpiBranchFilter($payload, $selectedSheet, $request->input('cabang'));
        $filteredPayload = $payload;
        $filteredPayload['rows'] = $kpiBranchFilter['rows'];
        $summary = $payload['summary'] ?? [];
        if ($kpiBranchFilter['enabled']) {
            $summary['row_count'] = count($filteredPayload['rows']);
        }
        unset($kpiBranchFilter['rows']);

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
            'rows' => $filteredPayload['rows'],
            'tableSections' => $this->kpiTableSections($filteredPayload, $selectedSheet),
            'kpiBranchFilter' => $kpiBranchFilter,
            'summary' => $summary,
            'error' => $payload['error'] ?? null,
            'fetchedAt' => $payload['fetched_at'] ?? null,
        ]);
    }

    /**
     * @param  array<int, string>  $sheetKeys
     * @return array<string, array<string, mixed>>
     */
    public function refreshKpiSourceCaches(array $sheetKeys = []): array
    {
        $keys = $sheetKeys === []
            ? array_keys(self::KPI_SHEETS)
            : array_values(array_filter(array_unique($sheetKeys), fn (string $key): bool => isset(self::KPI_SHEETS[$key])));
        $results = [];

        foreach ($keys as $sheetKey) {
            $lock = Cache::lock('dashboard_sources:refresh:kpi:' . $sheetKey . ':lock', 120);
            if (!$lock->get()) {
                $results[$sheetKey] = [
                    'success' => true,
                    'skipped' => true,
                    'error' => null,
                ];
                continue;
            }

            $cacheKey = $this->kpiSheetCacheKey($sheetKey);
            try {
                $payload = $this->fetchKpiSheetPayload($sheetKey);
                $success = empty($payload['error']);

                if ($success) {
                    Cache::put($cacheKey, $payload, now()->addDays(2));
                    $this->persistKpiSheetPayload($cacheKey, $payload);
                }

                $results[$sheetKey] = [
                    'success' => $success,
                    'row_count' => (int) data_get($payload, 'summary.row_count', 0),
                    'fetched_at' => $payload['fetched_at'] ?? null,
                    'error' => $payload['error'] ?? null,
                ];
            } finally {
                Cache::forget('dashboard_sources:refresh:kpi:' . $sheetKey . ':pending');
                optional($lock)->release();
            }
        }

        Cache::forget('dashboard_sources:refresh:kpi:all:pending');

        return $results;
    }

    private function cachedKpiSheetPayload(string $sheetKey, string $cacheKey): array
    {
        $payload = Cache::get($cacheKey);
        if (!is_array($payload) || empty($payload['header'])) {
            $payload = $this->readPersistedKpiSheetPayload($cacheKey);
            if ($payload !== null) {
                Cache::put($cacheKey, $payload, now()->addDays(2));
            }
        }

        try {
            $fetchedAt = isset($payload['fetched_at']) ? Carbon::parse((string) $payload['fetched_at']) : null;
        } catch (\Throwable) {
            $fetchedAt = null;
        }
        if ($fetchedAt === null || $fetchedAt->lt(now()->subMinutes(5))) {
            $this->queueKpiSourceRefresh([$sheetKey]);
        }

        return $payload ?? $this->emptyKpiSheetPayload(
            'Data KPI sedang disinkronkan di background. Muat ulang halaman setelah proses selesai.'
        );
    }

    /** @param array<int, string> $sheetKeys */
    private function queueKpiSourceRefresh(array $sheetKeys): void
    {
        $sheetKeys = array_values(array_filter(array_unique($sheetKeys), fn (string $key): bool => isset(self::KPI_SHEETS[$key])));
        $pendingKey = $sheetKeys === []
            ? 'dashboard_sources:refresh:kpi:all:pending'
            : 'dashboard_sources:refresh:kpi:' . implode(',', $sheetKeys) . ':pending';

        if (!Cache::add($pendingKey, now()->toIso8601String(), now()->addMinutes(10))) {
            return;
        }

        RefreshRemoteDashboardSourcesJob::dispatch(['kpi'], $sheetKeys);
    }

    private function persistKpiSheetPayload(string $cacheKey, array $payload): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        $path = $this->persistedKpiSheetPath($cacheKey);
        File::ensureDirectoryExists(dirname($path));
        File::replace($path, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function readPersistedKpiSheetPayload(string $cacheKey): ?array
    {
        if (app()->runningUnitTests()) {
            return null;
        }

        $path = $this->persistedKpiSheetPath($cacheKey);
        if (!File::isFile($path)) {
            return null;
        }

        $payload = json_decode((string) File::get($path), true);

        return is_array($payload) && !empty($payload['header']) ? $payload : null;
    }

    private function persistedKpiSheetPath(string $cacheKey): string
    {
        return storage_path('app/dashboard_sources/kpi/' . sha1($cacheKey) . '.json');
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

            if (collect($cells)->every(fn (string $value): bool => $this->isKpiSheetBlankDataCell($value))) {
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
        if (isset($rows[0]) && (
            !empty(self::KPI_SHEETS[$sheetKey]['force_two_row_header'])
            || $this->isKpiSheetSecondHeaderRow($rows[0])
        )) {
            $secondHeader = array_shift($rows);
        }

        if ($secondHeader !== null && isset($rows[0]) && $this->isKpiSheetOrdinalHeaderRow($rows[0])) {
            array_shift($rows);
        }

        $rows = array_values(array_filter(
            $rows,
            fn (array $row): bool => !$this->isKpiSheetFilterRow($row)
                && !$this->isKpiSheetSupplementalHeaderRow($row, $sheetKey)
                && collect($row)->contains(fn ($value): bool => !$this->isKpiSheetBlankDataCell($value))
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

            if ($index === 0 && (str_starts_with(strtoupper($raw), 'KEY PERFORMING INDICATOR') || str_starts_with(strtoupper($raw), 'KPI'))) {
                $title = trim(preg_replace('/\s+BO\s*$/i', '', $raw) ?? $raw);
                $label = 'BO';
                $leaf = 'BO';
            }

            if ($label === '' && $currentGroup !== null) {
                $group = $currentGroup;
                $leaf = $currentWeight ?: 'Score';
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
                $metric = trim($matches[1]);
                $weight = trim($matches[2]);
                $group = $metric . ' (Bobot ' . $weight . ')';
                $currentGroup = $group;
                $currentWeight = 'Score';
                $leaf = 'Pencapaian';
            } else {
                $currentGroup = null;
                $currentWeight = null;
                $leaf = $label !== '' ? $label : '';
            }

            $columns[] = [
                'label' => $this->normalizeKpiHeaderLabel($leaf),
                'group' => $group !== null ? $this->normalizeKpiHeaderLabel($group) : null,
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
        if (!empty(self::KPI_SHEETS[$sheetKey]['weighted_metric_pairs'])) {
            return $this->buildKpiSheetWeightedPairHeaderMeta($rawHeader, $secondHeader, $sheetKey);
        }

        $columns = [];
        $title = '';
        $currentGroup = null;

        foreach (array_values($rawHeader) as $index => $value) {
            $raw = trim((string) $value);
            $leafRaw = trim((string) ($secondHeader[$index] ?? ''));
            $label = $raw;

            if ($index === 0 && (str_starts_with(strtoupper($raw), 'KEY PERFORMING INDICATOR') || str_starts_with(strtoupper($raw), 'KPI'))) {
                $title = trim(preg_replace('/\s+BO\s*$/i', '', $raw) ?? $raw);
                $label = 'BO';
            }

            if ($label !== '') {
                if (preg_match('/^(.+?)\s+BOBOT\s+(.+)$/i', $label, $matches) === 1) {
                    $currentGroup = trim($matches[1]) . ' (Bobot ' . trim($matches[2]) . ')';
                } else {
                    $currentGroup = $label;
                }
            }

            $group = null;
            if ($leafRaw !== '' && $currentGroup !== null && $currentGroup !== $leafRaw) {
                $group = $currentGroup;
                $leaf = $leafRaw;
            } else {
                $leaf = $label !== '' ? $label : ($leafRaw !== '' ? $leafRaw : '');
            }

            $columns[] = [
                'label' => $this->normalizeKpiHeaderLabel($leaf),
                'group' => $group !== null ? $this->normalizeKpiHeaderLabel($group) : null,
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

    private function buildKpiSheetWeightedPairHeaderMeta(array $rawHeader, array $secondHeader, string $sheetKey): array
    {
        $columns = [];
        $title = '';
        $columnCount = max(count($rawHeader), count($secondHeader));

        for ($index = 0; $index < $columnCount; $index++) {
            $raw = trim((string) ($rawHeader[$index] ?? ''));
            $weight = trim((string) ($secondHeader[$index] ?? ''));

            if ($index === 0 && (str_starts_with(strtoupper($raw), 'KEY PERFORMING INDICATOR') || str_starts_with(strtoupper($raw), 'KPI'))) {
                $title = trim(preg_replace('/\s+BO\s*$/i', '', $raw) ?? $raw);
                $columns[] = [
                    'label' => 'BO',
                    'group' => null,
                    'sortable' => true,
                    'index' => $index,
                ];
                continue;
            }

            $nextHeader = trim((string) ($rawHeader[$index + 1] ?? ''));
            if ($raw !== '' && strtoupper($raw) !== 'SCORE' && $nextHeader === '') {
                $group = $raw . ($weight !== '' ? ' (Bobot ' . $weight . ')' : '');
                $columns[] = [
                    'label' => 'Pencapaian',
                    'group' => $this->normalizeKpiHeaderLabel($group),
                    'sortable' => true,
                    'index' => $index,
                ];
                $columns[] = [
                    'label' => 'Score',
                    'group' => $this->normalizeKpiHeaderLabel($group),
                    'sortable' => true,
                    'index' => $index + 1,
                ];
                $index++;
                continue;
            }

            $label = $raw !== '' ? $raw : $weight;
            $columns[] = [
                'label' => $this->normalizeKpiHeaderLabel($label),
                'group' => null,
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

    private function normalizeKpiHeaderLabel(string $label): string
    {
        $label = trim($label);
        $upper = strtoupper($label);

        if ($upper === 'PENCP' || $upper === 'PENCAPAIAN') {
            return 'Pencapaian';
        }
        if ($upper === 'SCORE' || $upper === 'NILAI') {
            return 'Score';
        }
        if ($upper === 'BO') {
            return 'BO';
        }
        if ($upper === 'MBM') {
            return 'MBM';
        }
        if ($upper === 'BC') {
            return 'BC';
        }
        if ($upper === 'UKER') {
            return 'Uker';
        }
        if ($upper === 'NAMA') {
            return 'Nama';
        }
        if ($upper === 'BC UKER') {
            return 'BC Uker';
        }
        if ($upper === 'STATUS') {
            return 'Status';
        }
        if ($upper === 'TYPE BRI') {
            return 'Type BRI';
        }
        if ($upper === 'NAMA MANTRI') {
            return 'Nama Mantri';
        }
        if ($upper === 'RANK') {
            return 'Rank';
        }

        return $this->titleCaseKpiHeader($label);
    }

    private function titleCaseKpiHeader(string $label): string
    {
        $words = explode(' ', $label);
        $acronyms = ['KPI', 'MBM', 'KAUNIT', 'RM', 'KUR', 'SML', 'NPL', 'REC', 'DH', 'AVG', 'BO', 'BC', 'UKER', 'BRI', 'CASA', 'QRIS', 'OS', 'LR', 'FBI', 'JG', 'PH'];

        foreach ($words as &$word) {
            $cleaned = strtoupper(trim($word, "(),* \t\n\r\0\x0B"));
            if (in_array($cleaned, $acronyms, true)) {
                $word = strtoupper($word);
            } else {
                $word = preg_replace_callback('/([a-zA-Z]+)/', function ($matches) {
                    return ucfirst(strtolower($matches[1]));
                }, $word);
            }
        }

        return implode(' ', $words);
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
            return in_array($value, ['PENCP', 'PENCAPAIAN', 'SCORE', 'NILAI', 'VALUE', 'BOBOT'], true)
                || str_contains($value, 'PENCP')
                || str_contains($value, 'SCORE')
                || str_contains($value, 'NILAI');
        })->count();

        return $headerTokenCount >= 2;
    }

    private function isKpiSheetOrdinalHeaderRow(array $row): bool
    {
        $values = array_values(array_filter(
            array_map(static fn ($value): string => trim((string) $value), $row),
            static fn (string $value): bool => $value !== ''
        ));

        if (count($values) < 3) {
            return false;
        }

        foreach ($values as $index => $value) {
            if (!ctype_digit($value) || (int) $value !== $index + 1) {
                return false;
            }
        }

        return true;
    }

    private function isKpiSheetSupplementalHeaderRow(array $row, string $sheetKey): bool
    {
        if (empty(self::KPI_SHEETS[$sheetKey]['force_two_row_header'])) {
            return false;
        }

        if ($this->isKpiSheetOrdinalHeaderRow($row)) {
            return true;
        }

        $firstThree = array_map(
            static fn ($value): string => strtoupper(trim((string) $value)),
            array_slice($row, 0, 3)
        );
        if ($firstThree === ['BO', 'UKER', 'JG']) {
            return true;
        }

        if (collect($firstThree)->contains(static fn (string $value): bool => $value !== '')) {
            return false;
        }

        $weightCells = array_values(array_filter(
            array_map(static fn ($value): string => trim((string) $value), array_slice($row, 3)),
            static fn (string $value): bool => $value !== ''
        ));

        return $weightCells !== []
            && collect($weightCells)->every(static fn (string $value): bool => preg_match('/^\d+(?:[.,]\d+)?%$/', $value) === 1);
    }

    private function kpiTableSections(array $payload, array $sheet): array
    {
        $rows = array_values($payload['rows'] ?? []);
        if (empty($sheet['split_by_segment'])) {
            return [[
                'key' => 'all',
                'title' => (string) ($payload['summary']['sheet_title'] ?? $sheet['sheet'] ?? '-'),
                'rows' => $rows,
            ]];
        }

        $segmentIndex = collect($payload['header'] ?? [])
            ->search(fn ($header): bool => strtoupper(trim((string) $header)) === 'SEGMEN');
        if ($segmentIndex === false) {
            return [[
                'key' => 'all',
                'title' => (string) ($payload['summary']['sheet_title'] ?? $sheet['sheet'] ?? '-'),
                'rows' => $rows,
            ]];
        }

        $sectionRows = ['briguna' => [], 'kpr' => []];
        foreach ($rows as $row) {
            $segment = strtoupper(trim((string) ($row[$segmentIndex] ?? '')));
            if (str_contains($segment, 'BRIGUNA')) {
                $sectionRows['briguna'][] = $row;
            } elseif (str_contains($segment, 'KPR')) {
                $sectionRows['kpr'][] = $row;
            }
        }

        return [
            ['key' => 'briguna', 'title' => 'KPI Briguna', 'rows' => $sectionRows['briguna']],
            ['key' => 'kpr', 'title' => 'KPI KPR', 'rows' => $sectionRows['kpr']],
        ];
    }

    /** @return array{enabled: bool, locked: bool, selected: string, options: array<int, array{value: string, label: string}>, rows: array<int, array<int, string>>} */
    private function kpiBranchFilter(array $payload, array $sheet, ?string $requestedBranch): array
    {
        $rows = array_values($payload['rows'] ?? []);
        $expectedHeaders = array_map('strtoupper', $sheet['branch_filter_headers'] ?? []);
        $branchIndex = collect($payload['header'] ?? [])
            ->search(fn ($header): bool => in_array(strtoupper(trim((string) $header)), $expectedHeaders, true));

        if ($expectedHeaders === [] || $branchIndex === false) {
            return [
                'enabled' => false,
                'locked' => false,
                'selected' => 'all',
                'options' => [],
                'rows' => $rows,
            ];
        }

        $branchIndex = (int) $branchIndex;
        $branchValues = collect($rows)
            ->map(fn (array $row): string => trim((string) ($row[$branchIndex] ?? '')))
            ->filter()
            ->unique()
            ->sort()
            ->values();
        $scope = UserBranchScope::current();

        if ($scope !== null) {
            $branchValues = $branchValues
                ->filter(fn (string $branch): bool => $this->kpiBranchMatchesScope($branch, $scope))
                ->values();
            $selected = (string) ($branchValues->first() ?? '');
            $rows = array_values(array_filter($rows, fn (array $row): bool => $selected !== ''
                && trim((string) ($row[$branchIndex] ?? '')) === $selected));
            $options = $branchValues
                ->map(fn (string $branch): array => ['value' => $branch, 'label' => $branch])
                ->all();

            return [
                'enabled' => true,
                'locked' => true,
                'selected' => $selected,
                'options' => $options,
                'rows' => $rows,
            ];
        }

        $selected = trim((string) $requestedBranch);
        $selected = $branchValues->contains($selected) ? $selected : 'all';
        if ($selected !== 'all') {
            $rows = array_values(array_filter($rows, fn (array $row): bool => trim((string) ($row[$branchIndex] ?? '')) === $selected));
        }

        return [
            'enabled' => true,
            'locked' => false,
            'selected' => $selected,
            'options' => [
                ['value' => 'all', 'label' => 'Semua Cabang'],
                ...$branchValues->map(fn (string $branch): array => ['value' => $branch, 'label' => $branch])->all(),
            ],
            'rows' => $rows,
        ];
    }

    /** @param array{plain_label: string, label: string} $scope */
    private function kpiBranchMatchesScope(string $branch, array $scope): bool
    {
        $normalizedBranch = strtoupper(trim($branch));
        $plainLabel = strtoupper(trim((string) ($scope['plain_label'] ?? '')));

        return $plainLabel !== '' && str_contains($normalizedBranch, $plainLabel);
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

        return 'dashboard_almafacts:kpi_sheet:v7:' . $sheetKey . ':' . md5($spreadsheetId . '|' . $sheetName);
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
            ->where('keterangan', self::PROFIT_LABEL)
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
        $scope = UserBranchScope::current();
        if ($scope !== null) {
            return [$scope['label'] => $scope['label']];
        }

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
            ->where('keterangan', self::PROFIT_LABEL)
            ->whereIn('month_day_year_of_posisi', $periods)
            ->whereIn('kanca_konsolidasi', $branches);

        if ($areaMode) {
            $query
                ->select('month_day_year_of_posisi', 'kanca_konsolidasi', DB::raw('SUM(saldo) as saldo'))
                ->groupBy('month_day_year_of_posisi', 'kanca_konsolidasi');
        } else {
            $query
                ->select('month_day_year_of_posisi', 'kanca_konsolidasi', 'kode_unit_kerja', 'unit_kerja', DB::raw('SUM(saldo) as saldo'))
                ->groupBy('month_day_year_of_posisi', 'kanca_konsolidasi', 'kode_unit_kerja', 'unit_kerja');
        }

        $rows = [];
        foreach ($query->get() as $row) {
            $key = $areaMode ? (string) $row->kanca_konsolidasi : $this->unitKey($row->kanca_konsolidasi, $row->kode_unit_kerja, $row->unit_kerja);
            $rows[$key]['meta'] ??= [
                'branch' => (string) $row->kanca_konsolidasi,
                'unit_code' => $areaMode ? null : (string) $row->kode_unit_kerja,
                'unit_name' => $areaMode ? null : (string) $row->unit_kerja,
                'unit_type' => $areaMode ? null : $this->normalizeUnitType('', (string) $row->unit_kerja),
            ];
            $rows[$key]['values'][(string) $row->month_day_year_of_posisi] = (float) $row->saldo;
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

    private function financialComparisonPeriods(?string $selectedPeriod): array
    {
        if (!$selectedPeriod) {
            return ['yoy' => null, 'ytd' => null, 'm1' => null, 'current' => null];
        }

        $period = Carbon::parse($selectedPeriod)->endOfMonth();

        return [
            'yoy' => $period->copy()->subYearNoOverflow()->endOfMonth()->toDateString(),
            'ytd' => $period->copy()->subYear()->month(12)->endOfMonth()->toDateString(),
            'm1' => $period->copy()->subMonthNoOverflow()->endOfMonth()->toDateString(),
            'current' => $period->toDateString(),
        ];
    }

    private function financialUnitOptions(string $selectedBranch): array
    {
        if (!Schema::hasTable('ssa_almafacts')) {
            return ['KC' => [], 'KCP' => [], 'UNIT' => []];
        }

        $branches = $selectedBranch === self::AREA_KEY ? self::AREA_BRANCHES : [$selectedBranch];
        $rows = DB::table('ssa_almafacts')
            ->whereIn('kanca_konsolidasi', $branches)
            ->whereNotNull('unit_kerja')
            ->where('unit_kerja', '<>', '')
            ->select('kanca_konsolidasi', 'kode_unit_kerja', 'unit_kerja')
            ->distinct()
            ->orderBy('kanca_konsolidasi')
            ->orderBy('unit_kerja')
            ->get();

        $options = ['KC' => [], 'KCP' => [], 'UNIT' => []];
        $seen = [];
        foreach ($rows as $row) {
            $branch = trim((string) $row->kanca_konsolidasi);
            $code = trim((string) $row->kode_unit_kerja);
            $name = trim((string) $row->unit_kerja);
            if ($branch === '' || $name === '') {
                continue;
            }

            $type = $this->normalizeUnitType('', $name);
            $value = $branch . '|' . $code . '|' . $this->normalizeUnitName($name);
            if (isset($seen[$value]) || !isset($options[$type])) {
                continue;
            }

            $seen[$value] = true;
            $options[$type][] = [
                'value' => $value,
                'branch' => $branch,
                'code' => $code,
                'name' => $name,
                'label' => ($selectedBranch === self::AREA_KEY ? $branch . ' - ' : '') . $name,
                'type' => $type,
            ];
        }

        return $options;
    }

    private function resolveFinancialUnitFilter(Request $request, array $unitOptions): array
    {
        $type = strtoupper(trim((string) $request->input('unit_type', 'ALL')));
        if (!in_array($type, ['ALL', 'KC', 'KCP', 'UNIT'], true)) {
            $type = 'ALL';
        }

        $available = [];
        foreach ($unitOptions as $group => $options) {
            foreach ($options as $option) {
                $available[$option['value']] = $option + ['type' => $group];
            }
        }

        $requestedValues = $request->input('unit_values', []);
        if (!is_array($requestedValues)) {
            $requestedValues = [$requestedValues];
        }

        $selected = [];
        foreach ($requestedValues as $value) {
            $value = trim((string) $value);
            if (!isset($available[$value])) {
                continue;
            }
            if ($type !== 'ALL' && ($available[$value]['type'] ?? '') !== $type) {
                continue;
            }

            $selected[$value] = $available[$value];
            if ($type === 'UNIT') {
                break;
            }
        }

        if ($type === 'ALL') {
            $selected = [];
        }

        return [
            'type' => $type,
            'values' => array_keys($selected),
            'selected' => array_values($selected),
        ];
    }

    private function financialSnapshots(array $periods, string $selectedBranch, array $unitFilter): array
    {
        $branches = $selectedBranch === self::AREA_KEY ? self::AREA_BRANCHES : [$selectedBranch];
        $snapshots = [];

        foreach ($periods as $key => $targetPeriod) {
            if (!$targetPeriod) {
                $snapshots[$key] = $this->emptyFinancialSnapshot(null);
                continue;
            }

            $almafactsPeriod = $this->resolveFinancialSourcePeriod('ssa_almafacts', 'month_day_year_of_posisi', $targetPeriod);
            $liabilities = $this->fetchFinancialAlmafactsMetrics($almafactsPeriod, $branches, $unitFilter, self::FINANCIAL_LIABILITY_LABELS);
            $pnl = $this->fetchFinancialAlmafactsMetrics($almafactsPeriod, $branches, $unitFilter, self::FINANCIAL_PNL_LABELS);
            $calculationMetrics = $this->fetchFinancialAlmafactsMetrics(
                $almafactsPeriod,
                $branches,
                $unitFilter,
                self::FINANCIAL_CALCULATION_LABELS
            );
            $assetQualityNominals = $this->financialAssetQualityNominals($almafactsPeriod, $branches, $unitFilter);
            $ratios = $this->calculateFinancialRatios(
                $calculationMetrics,
                $assetQualityNominals,
                $this->financialAnnualizationFactor($almafactsPeriod)
            );
            $profitability = $ratios['profitability'];
            $liquidity = $ratios['liquidity'];
            $assetQuality = $ratios['asset_quality'];

            $snapshots[$key] = [
                'target_period' => $targetPeriod,
                'source_periods' => [
                    'ssa_almafacts' => $almafactsPeriod,
                ],
                'liabilities' => $liabilities,
                'pnl' => $pnl,
                'profitability' => $profitability,
                'liquidity' => $liquidity,
                'asset_quality' => $assetQuality,
                'savings' => [
                    'total' => $liabilities['savings'] ?? null,
                    'giro' => $liabilities['giro'] ?? null,
                    'tabungan' => $liabilities['tabungan'] ?? null,
                    'deposito' => $liabilities['deposito'] ?? null,
                ],
                'loans' => [
                    'total' => $liabilities['loan'] ?? null,
                ],
                'ratios' => array_merge($profitability, $liquidity, $assetQuality),
            ];
        }

        return $snapshots;
    }

    private function emptyFinancialSnapshot(?string $targetPeriod): array
    {
        return [
            'target_period' => $targetPeriod,
            'source_periods' => [
                'ssa_almafacts' => null,
            ],
            'liabilities' => [],
            'pnl' => [],
            'profitability' => [],
            'liquidity' => [],
            'asset_quality' => [],
            'savings' => [
                'total' => null,
                'giro' => null,
                'tabungan' => null,
                'deposito' => null,
            ],
            'loans' => [
                'total' => null,
            ],
            'ratios' => [],
        ];
    }

    private function resolveFinancialSourcePeriod(string $table, string $column, string $targetPeriod): ?string
    {
        if (!Schema::hasTable($table)) {
            return null;
        }

        return SargableDateFilter::apply(DB::table($table), $column, '<=', $targetPeriod)
            ->max($column);
    }

    private function fetchFinancialAlmafactsMetrics(?string $period, array $branches, array $unitFilter, array $labels, string $aggregate = 'sum'): array
    {
        if (!$period || !Schema::hasTable('ssa_almafacts')) {
            return [];
        }

        $query = SargableDateFilter::apply(DB::table('ssa_almafacts'), 'month_day_year_of_posisi', '=', $period)
            ->whereIn('keterangan', array_values($labels));

        $this->applyFinancialFilters($query, 'kanca_konsolidasi', 'kode_unit_kerja', 'unit_kerja', $branches, $unitFilter);
        if ($aggregate === 'percent') {
            $this->applyFinancialPercentMetricScope($query, $branches, $unitFilter);
        }

        $rows = $query
            ->select('keterangan', DB::raw(($aggregate === 'percent' ? 'AVG' : 'SUM') . '(saldo) as saldo'))
            ->groupBy('keterangan')
            ->pluck('saldo', 'keterangan');

        $values = [];
        foreach ($labels as $key => $label) {
            $values[$key] = $rows->has($label) ? (float) $rows[$label] : null;
        }

        return $values;
    }

    private function financialAnnualizationFactor(?string $period): ?float
    {
        if (!$period) {
            return null;
        }

        $month = (int) Carbon::parse($period)->format('n');

        return $month > 0 ? 12 / $month : null;
    }

    private function financialAssetQualityNominals(?string $period, array $branches, array $unitFilter): array
    {
        if (!$period || !Schema::hasTable('ssa_almafacts')) {
            return [
                'dpk_weighted_numerator' => null,
                'dpk_weight' => null,
                'npl_nominal' => null,
                'lar_nominal' => null,
            ];
        }

        $query = SargableDateFilter::apply(DB::table('ssa_almafacts'), 'month_day_year_of_posisi', '=', $period)
            ->whereIn('keterangan', array_values(self::FINANCIAL_ASSET_QUALITY_SOURCE_LABELS));

        $this->applyFinancialFilters($query, 'kanca_konsolidasi', 'kode_unit_kerja', 'unit_kerja', $branches, $unitFilter);

        $rows = $query
            ->select('kanca_konsolidasi', 'kode_unit_kerja', 'unit_kerja', 'keterangan', 'saldo')
            ->get();
        $metricsByUnit = [];
        $labelMap = array_flip(self::FINANCIAL_ASSET_QUALITY_SOURCE_LABELS);

        foreach ($rows as $row) {
            $key = implode('|', [
                $this->normalizeUnitName((string) $row->kanca_konsolidasi),
                trim((string) $row->kode_unit_kerja),
                $this->normalizeUnitName((string) $row->unit_kerja),
            ]);
            $metric = $labelMap[(string) $row->keterangan] ?? null;
            if ($metric !== null) {
                $metricsByUnit[$key][$metric] = (float) $row->saldo;
            }
        }

        $nominals = [
            'dpk_weighted_numerator' => 0.0,
            'dpk_weight' => 0.0,
            'npl_nominal' => 0.0,
            'lar_nominal' => 0.0,
        ];
        $hasDpk = false;
        $hasNpl = false;
        $hasLar = false;
        foreach ($metricsByUnit as $metrics) {
            $loan = $metrics['loans'] ?? null;
            if ($loan === null || $loan <= 0) {
                continue;
            }

            $savings = $metrics['savings'] ?? null;
            if (array_key_exists('dpk_ratio', $metrics) && $savings !== null && $savings > 0) {
                $nominals['dpk_weighted_numerator'] += (float) $metrics['dpk_ratio'] * $savings;
                $nominals['dpk_weight'] += $savings;
                $hasDpk = true;
            }
            if (array_key_exists('npl_ratio', $metrics)) {
                $nominals['npl_nominal'] += max(0.0, (float) $metrics['npl_ratio']) * $loan;
                $hasNpl = true;
            }
            if (array_key_exists('lar_ratio', $metrics)) {
                $nominals['lar_nominal'] += max(0.0, (float) $metrics['lar_ratio']) * $loan;
                $hasLar = true;
            }
        }

        return [
            'dpk_weighted_numerator' => $hasDpk ? $nominals['dpk_weighted_numerator'] : null,
            'dpk_weight' => $hasDpk ? $nominals['dpk_weight'] : null,
            'npl_nominal' => $hasNpl ? $nominals['npl_nominal'] : null,
            'lar_nominal' => $hasLar ? $nominals['lar_nominal'] : null,
        ];
    }

    private function calculateFinancialRatios(
        array $current,
        array $assetQualityNominals,
        ?float $annualizationFactor
    ): array {
        $ratio = static function (?float $numerator, ?float $denominator, float $multiplier = 1.0): ?float {
            if ($numerator === null || $denominator === null || abs($denominator) < 0.000001) {
                return null;
            }

            return $numerator / $denominator * $multiplier;
        };
        $value = static fn (array $metrics, string $key): ?float => array_key_exists($key, $metrics) && $metrics[$key] !== null
            ? (float) $metrics[$key]
            : null;

        $annualization = $annualizationFactor ?? 1.0;
        $averageLoans = $value($current, 'average_loans');
        $averageSavings = $value($current, 'average_savings');
        $loans = $value($current, 'loans');
        $savings = $value($current, 'savings');
        $cerBase = ($value($current, 'contribution_margin') ?? 0.0) + ($value($current, 'fee_income') ?? 0.0);

        return [
            'profitability' => [
                'yield' => $ratio($value($current, 'interest_income'), $averageLoans, $annualization),
                'cof' => $ratio(abs($value($current, 'interest_expense') ?? 0.0), $averageSavings, $annualization),
                'nim' => $ratio(
                    ($value($current, 'assets_spread') ?? 0.0) + ($value($current, 'liabilities_spread') ?? 0.0),
                    $averageLoans,
                    $annualization
                ),
                'ohc' => $ratio(abs($value($current, 'overhead_cost') ?? 0.0), $averageLoans, $annualization),
                'credit_cost' => $ratio(abs($value($current, 'ckpn_expense') ?? 0.0), $averageLoans, $annualization),
                'roa_before_tax' => $ratio($value($current, 'profit_before_tax'), $averageLoans, $annualization),
                'roa_after_tax' => $ratio($value($current, 'profit_after_tax'), $averageLoans, $annualization),
                'bopo' => $ratio(abs($value($current, 'operating_expense') ?? 0.0), $value($current, 'operating_income')),
                'cer' => $ratio(abs($value($current, 'overhead_cost') ?? 0.0), $cerBase),
                'fee_to_income' => $ratio(
                    $value($current, 'fee_income'),
                    ($value($current, 'interest_income') ?? 0.0)
                        + ($value($current, 'ftp_income') ?? 0.0)
                        + ($value($current, 'fee_income') ?? 0.0)
                ),
            ],
            'liquidity' => [
                'ldr' => $ratio($loans, $savings),
                'casa' => $ratio(($value($current, 'giro') ?? 0.0) + ($value($current, 'tabungan') ?? 0.0), $savings),
            ],
            'asset_quality' => [
                'dpk' => $ratio($assetQualityNominals['dpk_weighted_numerator'] ?? null, $assetQualityNominals['dpk_weight'] ?? null),
                'npl' => $ratio($assetQualityNominals['npl_nominal'] ?? null, $loans),
                'lar' => $ratio($assetQualityNominals['lar_nominal'] ?? null, $loans),
            ],
        ];
    }

    private function applyFinancialPercentMetricScope($query, array $branches, array $unitFilter): void
    {
        $selected = $unitFilter['selected'] ?? [];
        if ($selected !== []) {
            return;
        }

        $type = strtoupper((string) ($unitFilter['type'] ?? 'ALL'));
        if (!in_array($type, ['ALL', 'KC'], true)) {
            return;
        }

        $wrappedBranch = $this->wrapColumn('kanca_konsolidasi');
        $wrappedUnitName = $this->wrapColumn('unit_kerja');
        $query->where(function ($query) use ($branches, $wrappedBranch, $wrappedUnitName) {
            foreach ($branches as $branch) {
                $normalizedBranch = $this->normalizeUnitName($branch);
                $query->orWhere(function ($query) use ($normalizedBranch, $wrappedBranch, $wrappedUnitName) {
                    $query
                        ->where(function ($query) use ($normalizedBranch, $wrappedBranch) {
                            $query
                                ->whereRaw("UPPER(TRIM({$wrappedBranch})) = ?", [$normalizedBranch])
                                ->orWhereRaw("UPPER(TRIM({$wrappedBranch})) LIKE ?", ['%' . $normalizedBranch . '%']);
                        })
                        ->whereRaw("UPPER(TRIM({$wrappedUnitName})) = ?", [$normalizedBranch]);
                });
            }
        });
    }

    private function applyFinancialFilters($query, string $branchColumn, string $unitCodeColumn, string $unitNameColumn, array $branches, array $unitFilter): void
    {
        $wrappedBranch = $this->wrapColumn($branchColumn);
        $query->where(function ($query) use ($branches, $wrappedBranch) {
            foreach ($branches as $branch) {
                $normalizedBranch = $this->normalizeUnitName($branch);
                $query
                    ->orWhereRaw("UPPER(TRIM({$wrappedBranch})) = ?", [$normalizedBranch])
                    ->orWhereRaw("UPPER(TRIM({$wrappedBranch})) LIKE ?", ['%' . $normalizedBranch . '%']);
            }
        });

        $type = strtoupper((string) ($unitFilter['type'] ?? 'ALL'));
        if ($type === 'ALL') {
            return;
        }

        $selected = $unitFilter['selected'] ?? [];
        if ($selected !== []) {
            $wrappedUnitCode = $this->wrapColumn($unitCodeColumn);
            $wrappedUnitName = $this->wrapColumn($unitNameColumn);
            $query->where(function ($query) use ($selected, $wrappedBranch, $wrappedUnitCode, $wrappedUnitName) {
                foreach ($selected as $unit) {
                    $query->orWhere(function ($query) use ($unit, $wrappedBranch, $wrappedUnitCode, $wrappedUnitName) {
                        $query
                            ->where(function ($query) use ($unit, $wrappedBranch) {
                                $normalizedBranch = $this->normalizeUnitName((string) $unit['branch']);
                                $query
                                    ->whereRaw("UPPER(TRIM({$wrappedBranch})) = ?", [$normalizedBranch])
                                    ->orWhereRaw("UPPER(TRIM({$wrappedBranch})) LIKE ?", ['%' . $normalizedBranch . '%']);
                            });

                        $code = trim((string) ($unit['code'] ?? ''));
                        if ($code !== '') {
                            $query->whereRaw("TRIM({$wrappedUnitCode}) = ?", [$code]);
                        }

                        $query
                            ->where(function ($query) use ($unit, $wrappedUnitName) {
                                $normalizedUnit = $this->normalizeUnitName((string) $unit['name']);
                                $query
                                    ->whereRaw("UPPER(TRIM({$wrappedUnitName})) = ?", [$normalizedUnit])
                                    ->orWhereRaw("UPPER(TRIM({$wrappedUnitName})) LIKE ?", ['%' . $normalizedUnit . '%']);
                            });
                    });
                }
            });

            return;
        }

        $wrappedUnitName = $this->wrapColumn($unitNameColumn);
        if ($type === 'KC') {
            $query->whereRaw("UPPER({$wrappedUnitName}) REGEXP '(^|[[:space:]-])KC[[:space:]]'");
            return;
        }
        if ($type === 'KCP') {
            $query->whereRaw("UPPER({$wrappedUnitName}) REGEXP '(^|[[:space:]-])KCP[[:space:]]'");
            return;
        }

        $query->whereRaw("UPPER({$wrappedUnitName}) REGEXP '(^|[[:space:]-])UNIT[[:space:]]'");
    }

    private function wrapColumn(string $column): string
    {
        return '`' . str_replace('`', '``', $column) . '`';
    }

    private function financialHighlightSections(array $snapshots): array
    {
        $sections = [
            [
                'title' => 'Liabilities',
                'icon' => 'fas fa-layer-group',
                'rows' => [
                    $this->financialRow('Pinjaman', 'money', 'liabilities.loan', $snapshots),
                    $this->financialRow('Simpanan', 'money', 'liabilities.savings', $snapshots),
                    $this->financialRow('Giro', 'money', 'liabilities.giro', $snapshots),
                    $this->financialRow('Tabungan', 'money', 'liabilities.tabungan', $snapshots),
                    $this->financialRow('Deposito', 'money', 'liabilities.deposito', $snapshots),
                ],
            ],
            [
                'title' => 'Profit & Loss',
                'icon' => 'fas fa-file-invoice-dollar',
                'rows' => [
                    $this->financialRow('Pendapatan Bunga', 'money', 'pnl.interest_income', $snapshots),
                    $this->financialRow('Beban FTP', 'money', 'pnl.ftp_expense', $snapshots, true),
                    $this->financialRow('Assets Spread', 'money', 'pnl.assets_spread', $snapshots),
                    $this->financialRow('Beban Bunga', 'money', 'pnl.interest_expense', $snapshots, true),
                    $this->financialRow('Pendapatan FTP', 'money', 'pnl.ftp_income', $snapshots),
                    $this->financialRow('Liabilities Spread', 'money', 'pnl.liabilities_spread', $snapshots),
                    $this->financialRow('Contribution Margin', 'money', 'pnl.contribution_margin', $snapshots),
                    $this->financialRow('Fee & Pendapatan Lainnya', 'money', 'pnl.fee_income', $snapshots),
                    $this->financialRow('Overhead Cost', 'money', 'pnl.overhead_cost', $snapshots, true),
                    $this->financialRow('PPOP', 'money', 'pnl.ppop', $snapshots),
                    $this->financialRow('Biaya CKPN', 'money', 'pnl.ckpn_expense', $snapshots, true),
                    $this->financialRow('Adj. Pendapatan / Beban', 'money', 'pnl.other_income_expense', $snapshots),
                    $this->financialRow('Laba Sebelum Pajak', 'money', 'pnl.profit_before_tax', $snapshots),
                    $this->financialRow('Beban Pajak', 'money', 'pnl.tax_expense', $snapshots),
                    $this->financialRow('Laba Setelah Pajak', 'money', 'pnl.profit_after_tax', $snapshots),
                ],
            ],
            [
                'title' => 'Profitability',
                'icon' => 'fas fa-chart-line',
                'rows' => [
                    $this->financialRow('Yield', 'percent', 'profitability.yield', $snapshots),
                    $this->financialRow('COF', 'percent', 'profitability.cof', $snapshots, true),
                    $this->financialRow('NIM', 'percent', 'profitability.nim', $snapshots),
                    $this->financialRow('OHC', 'percent', 'profitability.ohc', $snapshots, true, true),
                    $this->financialRow('Credit Cost', 'percent', 'profitability.credit_cost', $snapshots),
                    $this->financialRow('ROA before Tax', 'percent', 'profitability.roa_before_tax', $snapshots),
                    $this->financialRow('ROA after Tax', 'percent', 'profitability.roa_after_tax', $snapshots),
                    $this->financialRow('BOPO', 'percent', 'profitability.bopo', $snapshots, true),
                    $this->financialRow('CER', 'percent', 'profitability.cer', $snapshots),
                    $this->financialRow('Fee to Income', 'percent', 'profitability.fee_to_income', $snapshots),
                ],
            ],
            [
                'title' => 'Liquidity',
                'icon' => 'fas fa-water',
                'rows' => [
                    $this->financialRow('LDR', 'percent', 'liquidity.ldr', $snapshots),
                    $this->financialRow('CASA', 'percent', 'liquidity.casa', $snapshots),
                ],
            ],
            [
                'title' => 'Asset Quality',
                'icon' => 'fas fa-shield-alt',
                'rows' => [
                    $this->financialRow('DPK', 'percent', 'asset_quality.dpk', $snapshots, true),
                    $this->financialRow('NPL', 'percent', 'asset_quality.npl', $snapshots, true),
                    $this->financialRow('LAR', 'percent', 'asset_quality.lar', $snapshots, true),
                ],
            ],
        ];

        return array_values(array_filter(array_map(function (array $section): ?array {
            $rows = array_values(array_filter($section['rows'], fn(array $row): bool => $row['has_data']));
            if ($rows === []) {
                return null;
            }

            $section['rows'] = $rows;
            return $section;
        }, $sections)));
    }

    private function financialRow(
        string $label,
        string $format,
        string $path,
        array $snapshots,
        bool $isQualityMetric = false,
        bool $absoluteValue = false
    ): array
    {
        $values = [
            'yoy' => $this->financialValue($snapshots['yoy'] ?? [], $path),
            'ytd' => $this->financialValue($snapshots['ytd'] ?? [], $path),
            'm1' => $this->financialValue($snapshots['m1'] ?? [], $path),
            'current' => $this->financialValue($snapshots['current'] ?? [], $path),
        ];
        if ($absoluteValue) {
            foreach ($values as $key => $value) {
                $values[$key] = $value === null ? null : abs((float) $value);
            }
        }
        $current = $values['current'];

        return [
            'label' => $label,
            'format' => $format,
            'values' => $values,
            'deltas' => [
                'yoy' => $current === null || $values['yoy'] === null ? null : $current - (float) $values['yoy'],
                'ytd' => $current === null || $values['ytd'] === null ? null : $current - (float) $values['ytd'],
                'm1' => $current === null || $values['m1'] === null ? null : $current - (float) $values['m1'],
            ],
            'is_quality_metric' => $isQualityMetric,
            'has_data' => collect($values)->contains(fn($value): bool => $value !== null),
        ];
    }

    private function financialValue(array $snapshot, string $path): ?float
    {
        $value = $snapshot;
        foreach (explode('.', $path) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return null;
            }

            $value = $value[$part];
        }

        return $value === null ? null : (float) $value;
    }

    private function financialSourcePeriodSummary(array $snapshots): array
    {
        $summary = [];
        foreach ($snapshots as $key => $snapshot) {
            $summary[$key] = $snapshot['source_periods'] ?? [];
        }

        return $summary;
    }

    private function financialSummaryCards(array $snapshot): array
    {
        return [
            [
                'label' => 'Total Pinjaman',
                'icon' => 'fas fa-hand-holding-usd',
                'format' => 'money',
                'value' => $snapshot['loans']['total'] ?? null,
            ],
            [
                'label' => 'Total Simpanan',
                'icon' => 'fas fa-piggy-bank',
                'format' => 'money',
                'value' => $snapshot['savings']['total'] ?? null,
            ],
            [
                'label' => 'Laba Setelah Pajak',
                'icon' => 'fas fa-balance-scale',
                'format' => 'money',
                'value' => $snapshot['pnl']['profit_after_tax'] ?? null,
            ],
            [
                'label' => 'LDR',
                'icon' => 'fas fa-percentage',
                'format' => 'percent',
                'value' => $snapshot['ratios']['ldr'] ?? null,
            ],
        ];
    }

    private function financialMonthLabel(?string $period): string
    {
        return $period ? Carbon::parse($period)->translatedFormat('F y') : '-';
    }

    private function periodLabel(?string $period): string
    {
        return $period ? Carbon::parse($period)->translatedFormat('F y') : '-';
    }

    private function monthLabel(?string $period): string
    {
        return $period ? Carbon::parse($period)->translatedFormat('F y') : '-';
    }

    private function decemberLabel(?string $period): string
    {
        return $period ? Carbon::parse($period)->month(12)->translatedFormat('F y') : '-';
    }

    public function timeseries(Request $request)
    {
        if (!Schema::hasTable('ssa_almafacts')) {
            return redirect()->back()->with('error', 'Tabel ssa_almafacts tidak ditemukan.');
        }

        // Available metrics
        $metrics = DB::table('ssa_almafacts')
            ->select('keterangan')
            ->distinct()
            ->orderBy('keterangan')
            ->pluck('keterangan')
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
            ->where('keterangan', $metric)
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
            $nominal = (float) $row->saldo;

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
