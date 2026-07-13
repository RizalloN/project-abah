<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class DashboardDanaService
{
    private const TABLE = 'ssa_simpanan';
    private const HARIAN_SNAPSHOT_TABLE = 'dashboard_harian_snapshots';
    private const AREA_6_BRANCHES = [
        'KC Madiun',
        'KC Magetan',
        'KC Ngawi',
        'KC Ponorogo',
    ];

    /**
     * Get data for Dashboard Dana from ssa_simpanan with performance metrics
     */
    public function getDashboardData(?string $selectedPeriod, ?string $category, ?string $rkaPeriod = null, ?string $selectedBranch = null): array
    {
        if (!$selectedPeriod) {
            return [
                'rows' => [],
                'total' => [],
                'header_dates' => [],
            ];
        }

        $periods = $this->calculatePeriodReferences($selectedPeriod);
        $branchScope = $this->normalizeBranchScope($selectedBranch);
        if ($branchScope !== 'area6') {
            return $this->getBranchSegmentDashboardData($selectedPeriod, $branchScope, $rkaPeriod, $periods);
        }

        $snapshotData = $this->getDashboardDataFromHarianSnapshot($selectedPeriod, $category, $rkaPeriod, $periods);
        if ($snapshotData !== null) {
            return DashboardCrossAlignmentGuard::alignFunds($snapshotData, $selectedPeriod, $category, $rkaPeriod);
        }

        $allPeriodValues = array_filter($periods);
        
        // Fetch data for all required periods in one go
        $rawData = DB::table(self::TABLE)
            ->whereIn('Month_Day_Year_of_Posisi', $allPeriodValues);

        if ($category && $category !== 'all') {
            $rawData->where('segmentasi', $category);
        }

        $records = $rawData->select('Month_Day_Year_of_Posisi', 'nama_cabang', 'produk', DB::raw('SUM(saldo) as total_saldo'))
            ->groupBy('Month_Day_Year_of_Posisi', 'nama_cabang', 'produk')
            ->get();

        // Organize data into a usable structure: [branch][period][produk] => saldo
        $dataMatrix = [];
        $branches = [];
        foreach ($records as $record) {
            $dataMatrix[$record->nama_cabang][$record->Month_Day_Year_of_Posisi][$record->produk] = (float) $record->total_saldo;
            if (!in_array($record->nama_cabang, $branches)) {
                $branches[] = $record->nama_cabang;
            }
        }
        sort($branches);

        // Load RKA data
        $rkaData = $this->loadRkaData($rkaPeriod ?? $selectedPeriod, $category);

        $formattedRows = [];
        $no = 1;

        foreach ($branches as $branch) {
            $normalizedBranch = $this->normalizeBranchName($branch);
            
            // 1. Calculate Branch Total first to put it at the top
            $branchTotalRow = [
                'no' => $no++,
                'nama_cabang' => $normalizedBranch,
                'kategori' => 'TOTAL CABANG',
                'is_total' => true,
            ];

            foreach ($periods as $pKey => $pDate) {
                // Sum Giro + Tabungan + Deposito for this branch/period
                $branchTotalRow[$pKey] = 
                    $this->getVal($dataMatrix, $branch, $pDate, 'Giro') +
                    $this->getVal($dataMatrix, $branch, $pDate, 'Tabungan') +
                    $this->getVal($dataMatrix, $branch, $pDate, 'Deposito');
            }

            // Deltas for total
            $branchTotalRow['delta_ytd'] = $branchTotalRow['selected'] - ($branchTotalRow['ytd'] ?? 0);
            $branchTotalRow['delta_mtd'] = $branchTotalRow['selected'] - ($branchTotalRow['mtd'] ?? 0);
            
            // RKA for total
            $rkaGiro = $this->getRkaVal($rkaData, $branch, 'Giro');
            $rkaTab = $this->getRkaVal($rkaData, $branch, 'Tabungan');
            $rkaDep = $this->getRkaVal($rkaData, $branch, 'Deposito');
            $rkaTotal = $rkaGiro + $rkaTab + $rkaDep;
            
            $branchTotalRow['rka_rp'] = $branchTotalRow['selected'] - $rkaTotal;
            $branchTotalRow['rka_pct'] = $rkaTotal > 0 ? ($branchTotalRow['selected'] / $rkaTotal) * 100 : 0;

            $formattedRows[] = $branchTotalRow;

            // 2. Add individual categories
            foreach (['Giro', 'Tabungan', 'Deposito', 'CASA'] as $kategori) {
                $row = [
                    'no' => '', // No number for sub-rows
                    'nama_cabang' => $normalizedBranch,
                    'kategori' => $kategori,
                    'is_total' => false,
                ];

                // Values for each period
                foreach ($periods as $pKey => $pDate) {
                    $row[$pKey] = $this->getVal($dataMatrix, $branch, $pDate, $kategori);
                }

                // Deltas vs Position
                $selectedVal = $row['selected'] ?? 0;
                $row['delta_ytd'] = $selectedVal - ($row['ytd'] ?? 0);
                $row['delta_mtd'] = $selectedVal - ($row['mtd'] ?? 0);

                // RKA Logic
                $rkaVal = $this->getRkaVal($rkaData, $branch, $kategori);
                $row['rka_rp'] = $selectedVal - $rkaVal;
                $row['rka_pct'] = $rkaVal > 0 ? ($selectedVal / $rkaVal) * 100 : 0;

                $formattedRows[] = $row;
            }
        }

        $res = [
            'rows' => $formattedRows,
            'total' => $this->calculateGrandTotals($formattedRows),
            'header_dates' => [
                'selected' => $this->formatDateLabel($periods['selected']),
                'ytd' => $this->formatDateLabel($periods['ytd']),
                'mtd' => $this->formatDateLabel($periods['mtd']),
            ],
        ];

        return DashboardCrossAlignmentGuard::alignFunds($res, $selectedPeriod, $category, $rkaPeriod);
    }

    protected function getBranchSegmentDashboardData(string $selectedPeriod, string $selectedBranch, ?string $rkaPeriod, array $periods): array
    {
        $branch = $this->resolveAreaBranchLabel($selectedBranch);
        if ($branch === null) {
            return [
                'rows' => [],
                'total' => [],
                'header_dates' => [
                    'selected' => $this->formatDateLabel($periods['selected'] ?? null),
                    'ytd' => $this->formatDateLabel($periods['ytd'] ?? null),
                    'mtd' => $this->formatDateLabel($periods['mtd'] ?? null),
                ],
                'scope' => 'branch',
                'scope_label' => $this->normalizeBranchName($selectedBranch),
            ];
        }

        $snapshotData = $this->getBranchSegmentDataFromHarianSnapshot($branch, $rkaPeriod ?? $selectedPeriod, $periods);
        if ($snapshotData !== null) {
            return $snapshotData;
        }

        return $this->getBranchSegmentDataFromRaw($branch, $rkaPeriod ?? $selectedPeriod, $periods);
    }

    protected function getDashboardDataFromHarianSnapshot(?string $selectedPeriod, ?string $category, ?string $rkaPeriod = null, ?array $periods = null): ?array
    {
        if (!$selectedPeriod || !Schema::hasTable(self::HARIAN_SNAPSHOT_TABLE)) {
            return null;
        }

        $periods ??= $this->calculatePeriodReferences($selectedPeriod);
        $periodValues = array_values(array_unique(array_filter($periods)));
        if ($periodValues === []) {
            return null;
        }

        $hasSelectedSnapshot = DB::table(self::HARIAN_SNAPSHOT_TABLE)
            ->where('snapshot_period', $selectedPeriod)
            ->whereIn('kanca_label', self::AREA_6_BRANCHES)
            ->whereColumn('kanca_key', 'unit_key')
            ->exists();

        if (!$hasSelectedSnapshot) {
            return null;
        }

        $records = DB::table(self::HARIAN_SNAPSHOT_TABLE)
            ->whereIn('snapshot_period', $periodValues)
            ->whereIn('kanca_label', self::AREA_6_BRANCHES)
            ->whereColumn('kanca_key', 'unit_key')
            ->orderBy('snapshot_period')
            ->get();

        $dataMatrix = [];
        $branches = [];
        $normalizedCategory = $this->normalizeDanaCategory($category);

        foreach ($records as $record) {
            $branch = (string) $record->kanca_label;
            $period = (string) $record->snapshot_period;

            $dataMatrix[$branch][$period] = $this->mapHarianSnapshotSavingsValues($record, $normalizedCategory);
            if (!in_array($branch, $branches, true)) {
                $branches[] = $branch;
            }
        }

        $branches = array_values(array_filter(
            self::AREA_6_BRANCHES,
            fn (string $branch): bool => in_array($branch, $branches, true)
        ));

        return $this->buildDashboardResponseFromMatrix(
            $dataMatrix,
            $branches,
            $periods,
            $rkaPeriod ?? $selectedPeriod,
            $category
        );
    }

    protected function buildDashboardResponseFromMatrix(
        array $dataMatrix,
        array $branches,
        array $periods,
        string $rkaPeriod,
        ?string $category
    ): array {
        $rkaData = $this->loadRkaData($rkaPeriod, $category);
        $formattedRows = [];
        $no = 1;

        foreach ($branches as $branch) {
            $normalizedBranch = $this->normalizeBranchName($branch);

            $branchTotalRow = [
                'no' => $no++,
                'nama_cabang' => $normalizedBranch,
                'kategori' => 'TOTAL CABANG',
                'is_total' => true,
            ];

            foreach ($periods as $pKey => $pDate) {
                $branchTotalRow[$pKey] = $this->getVal($dataMatrix, $branch, $pDate, 'TOTAL');
            }

            $branchTotalRow['delta_ytd'] = $branchTotalRow['selected'] - ($branchTotalRow['ytd'] ?? 0);
            $branchTotalRow['delta_mtd'] = $branchTotalRow['selected'] - ($branchTotalRow['mtd'] ?? 0);

            $rkaTotal = $this->getRkaVal($rkaData, $branch, 'Giro')
                + $this->getRkaVal($rkaData, $branch, 'Tabungan')
                + $this->getRkaVal($rkaData, $branch, 'Deposito');

            $branchTotalRow['rka_rp'] = $branchTotalRow['selected'] - $rkaTotal;
            $branchTotalRow['rka_pct'] = $rkaTotal > 0 ? ($branchTotalRow['selected'] / $rkaTotal) * 100 : 0;

            $formattedRows[] = $branchTotalRow;

            foreach (['Giro', 'Tabungan', 'Deposito', 'CASA'] as $kategori) {
                $row = [
                    'no' => '',
                    'nama_cabang' => $normalizedBranch,
                    'kategori' => $kategori,
                    'is_total' => false,
                ];

                foreach ($periods as $pKey => $pDate) {
                    $row[$pKey] = $this->getVal($dataMatrix, $branch, $pDate, $kategori);
                }

                $selectedVal = $row['selected'] ?? 0;
                $row['delta_ytd'] = $selectedVal - ($row['ytd'] ?? 0);
                $row['delta_mtd'] = $selectedVal - ($row['mtd'] ?? 0);

                $rkaVal = $this->getRkaVal($rkaData, $branch, $kategori);
                $row['rka_rp'] = $selectedVal - $rkaVal;
                $row['rka_pct'] = $rkaVal > 0 ? ($selectedVal / $rkaVal) * 100 : 0;

                $formattedRows[] = $row;
            }
        }

        return [
            'rows' => $formattedRows,
            'total' => $this->calculateGrandTotals($formattedRows),
            'header_dates' => [
                'selected' => $this->formatDateLabel($periods['selected']),
                'ytd' => $this->formatDateLabel($periods['ytd']),
                'mtd' => $this->formatDateLabel($periods['mtd']),
            ],
            'source_table' => self::HARIAN_SNAPSHOT_TABLE,
        ];
    }

    private function getBranchSegmentDataFromHarianSnapshot(string $branch, string $rkaPeriod, array $periods): ?array
    {
        if (!Schema::hasTable(self::HARIAN_SNAPSHOT_TABLE)) {
            return null;
        }

        $periodValues = array_values(array_unique(array_filter($periods)));
        if ($periodValues === []) {
            return null;
        }

        $hasSelectedSnapshot = DB::table(self::HARIAN_SNAPSHOT_TABLE)
            ->where('snapshot_period', $periods['selected'] ?? null)
            ->where('kanca_label', $branch)
            ->whereColumn('kanca_key', 'unit_key')
            ->exists();

        if (!$hasSelectedSnapshot) {
            return null;
        }

        $records = DB::table(self::HARIAN_SNAPSHOT_TABLE)
            ->whereIn('snapshot_period', $periodValues)
            ->where('kanca_label', $branch)
            ->whereColumn('kanca_key', 'unit_key')
            ->orderBy('snapshot_period')
            ->get();

        $dataMatrix = [];
        foreach ($records as $record) {
            $period = (string) $record->snapshot_period;
            foreach ($this->segmentDefinitions() as $segmentKey => $segmentLabel) {
                $dataMatrix[$segmentLabel][$period] = $this->mapHarianSnapshotSavingsValues($record, $segmentKey);
            }
        }

        return $this->buildBranchSegmentDashboardResponse($dataMatrix, $branch, $periods, $rkaPeriod, self::HARIAN_SNAPSHOT_TABLE);
    }

    private function getBranchSegmentDataFromRaw(string $branch, string $rkaPeriod, array $periods): array
    {
        $periodValues = array_values(array_unique(array_filter($periods)));
        $dataMatrix = [];

        if ($periodValues !== [] && Schema::hasTable(self::TABLE)) {
            $records = DB::table(self::TABLE)
                ->whereIn('Month_Day_Year_of_Posisi', $periodValues)
                ->whereRaw('UPPER(TRIM(nama_cabang)) = ?', [strtoupper($branch)])
                ->select('Month_Day_Year_of_Posisi', 'segmentasi', 'produk', DB::raw('SUM(saldo) as total_saldo'))
                ->groupBy('Month_Day_Year_of_Posisi', 'segmentasi', 'produk')
                ->get();

            foreach ($records as $record) {
                $segmentLabel = $this->segmentLabel((string) $record->segmentasi);
                if ($segmentLabel === null) {
                    continue;
                }

                $product = $this->productLabel((string) $record->produk);
                if ($product === null) {
                    continue;
                }

                $period = (string) $record->Month_Day_Year_of_Posisi;
                $dataMatrix[$segmentLabel][$period][$product] = ($dataMatrix[$segmentLabel][$period][$product] ?? 0) + (float) $record->total_saldo;
                $dataMatrix[$segmentLabel][$period]['TOTAL'] = ($dataMatrix[$segmentLabel][$period]['TOTAL'] ?? 0) + (float) $record->total_saldo;
                $dataMatrix[$segmentLabel][$period]['CASA'] = ($dataMatrix[$segmentLabel][$period]['Giro'] ?? 0) + ($dataMatrix[$segmentLabel][$period]['Tabungan'] ?? 0);
            }
        }

        return $this->buildBranchSegmentDashboardResponse($dataMatrix, $branch, $periods, $rkaPeriod, self::TABLE);
    }

    private function buildBranchSegmentDashboardResponse(array $dataMatrix, string $branch, array $periods, string $rkaPeriod, string $sourceTable): array
    {
        $formattedRows = [];
        $no = 1;

        foreach ($this->segmentDefinitions() as $segmentKey => $segmentLabel) {
            $rkaData = $this->loadRkaData($rkaPeriod, $segmentKey);
            $segmentTotalRow = [
                'no' => $no++,
                'nama_cabang' => $segmentLabel,
                'kategori' => 'TOTAL SEGMEN',
                'is_total' => true,
                'scope_branch' => $this->normalizeBranchName($branch),
            ];

            foreach ($periods as $pKey => $pDate) {
                $segmentTotalRow[$pKey] = $this->getVal($dataMatrix, $segmentLabel, $pDate, 'TOTAL');
            }

            $segmentTotalRow['delta_ytd'] = $segmentTotalRow['selected'] - ($segmentTotalRow['ytd'] ?? 0);
            $segmentTotalRow['delta_mtd'] = $segmentTotalRow['selected'] - ($segmentTotalRow['mtd'] ?? 0);

            $rkaTotal = $this->getRkaVal($rkaData, $branch, 'Giro')
                + $this->getRkaVal($rkaData, $branch, 'Tabungan')
                + $this->getRkaVal($rkaData, $branch, 'Deposito');

            $segmentTotalRow['rka_rp'] = $segmentTotalRow['selected'] - $rkaTotal;
            $segmentTotalRow['rka_pct'] = $rkaTotal > 0 ? ($segmentTotalRow['selected'] / $rkaTotal) * 100 : 0;
            $formattedRows[] = $segmentTotalRow;

            foreach (['Giro', 'Tabungan', 'Deposito', 'CASA'] as $kategori) {
                $row = [
                    'no' => '',
                    'nama_cabang' => $segmentLabel,
                    'kategori' => $kategori,
                    'is_total' => false,
                    'scope_branch' => $this->normalizeBranchName($branch),
                ];

                foreach ($periods as $pKey => $pDate) {
                    $row[$pKey] = $this->getVal($dataMatrix, $segmentLabel, $pDate, $kategori);
                }

                $selectedVal = $row['selected'] ?? 0;
                $row['delta_ytd'] = $selectedVal - ($row['ytd'] ?? 0);
                $row['delta_mtd'] = $selectedVal - ($row['mtd'] ?? 0);

                $rkaVal = $this->getRkaVal($rkaData, $branch, $kategori);
                $row['rka_rp'] = $selectedVal - $rkaVal;
                $row['rka_pct'] = $rkaVal > 0 ? ($selectedVal / $rkaVal) * 100 : 0;

                $formattedRows[] = $row;
            }
        }

        return [
            'rows' => $formattedRows,
            'total' => $this->calculateGrandTotals($formattedRows),
            'header_dates' => [
                'selected' => $this->formatDateLabel($periods['selected'] ?? null),
                'ytd' => $this->formatDateLabel($periods['ytd'] ?? null),
                'mtd' => $this->formatDateLabel($periods['mtd'] ?? null),
            ],
            'scope' => 'branch',
            'scope_label' => $this->normalizeBranchName($branch),
            'source_table' => $sourceTable,
        ];
    }

    private function mapHarianSnapshotSavingsValues(object $record, ?string $category): array
    {
        $suffixes = $category ? [$category] : ['ritel', 'mikro', 'wholesale'];

        $sum = function (string $prefix) use ($record, $suffixes): float {
            $total = 0.0;
            foreach ($suffixes as $suffix) {
                $column = "{$prefix}_{$suffix}";
                $total += (float) ($record->{$column} ?? 0);
            }
            return $total;
        };

        $giro = $sum('giro');
        $tabungan = $sum('tabungan');
        $deposito = $sum('deposito');
        $componentTotal = $giro + $tabungan + $deposito;

        $total = $category
            ? $componentTotal
            : max((float) ($record->total_simpanan ?? 0), $componentTotal);

        return [
            'TOTAL' => $total,
            'Giro' => $giro,
            'Tabungan' => $tabungan,
            'Deposito' => $deposito,
            'CASA' => $giro + $tabungan,
        ];
    }

    private function normalizeDanaCategory(?string $category): ?string
    {
        $normalized = strtolower(trim((string) $category));

        return match ($normalized) {
            'ritel' => 'ritel',
            'mikro', 'micro' => 'mikro',
            'wholesale' => 'wholesale',
            default => null,
        };
    }

    protected function normalizeBranchName(string $name): string
    {
        $normalized = strtoupper(trim($name));
        
        // Remove leading numeric prefixes like "001 - " or "001 "
        $normalized = preg_replace('/^\d+\s*-\s*/', '', $normalized);
        $normalized = preg_replace('/^\d+\s+/', '', $normalized);
        
        // Remove trailing content in parentheses like "(Konsolidasi-MB)"
        $normalized = preg_replace('/\s*\([^)]*\)$/', '', $normalized);
        
        // Remove leading dash or special chars that often appear
        $normalized = ltrim($normalized, "- \t\n\r\0\x0B");
        
        return trim($normalized);
    }

    protected function getVal(array $matrix, string $branch, ?string $period, string $kategori): float
    {
        if (!$period || !isset($matrix[$branch][$period])) return 0;

        if ($kategori === 'TOTAL') {
            return $matrix[$branch][$period]['TOTAL'] ?? 0;
        }

        if ($kategori === 'CASA') {
            return ($matrix[$branch][$period]['Giro'] ?? 0) + ($matrix[$branch][$period]['Tabungan'] ?? 0);
        }

        return $matrix[$branch][$period][$kategori] ?? 0;
    }

    protected function calculatePeriodReferences(string $selectedPeriod): array
    {
        try {
            $selectedDate = Carbon::parse($selectedPeriod);
            
            // Target dates
            $ytdTarget = $selectedDate->copy()->subYear()->endOfYear()->format('Y-m-d');
            $mtdTarget = $selectedDate->copy()->subMonth()->endOfMonth()->format('Y-m-d');
            
            $ytdSimpanan = $this->resolveHarianSnapshotPeriodOnOrBefore($ytdTarget)
                ?? DB::table('ssa_simpanan')->where('Month_Day_Year_of_Posisi', '<=', $ytdTarget)->max('Month_Day_Year_of_Posisi');

            $mtdSimpanan = $this->resolveHarianSnapshotPeriodOnOrBefore($mtdTarget)
                ?? DB::table('ssa_simpanan')->where('Month_Day_Year_of_Posisi', '<=', $mtdTarget)->max('Month_Day_Year_of_Posisi');

            return [
                'selected' => $selectedPeriod,
                'mtd' => $mtdSimpanan,
                'ytd' => $ytdSimpanan,
            ];
        } catch (Throwable) {
            return ['selected' => $selectedPeriod, 'mtd' => null, 'ytd' => null];
        }
    }

    private function resolveHarianSnapshotPeriodOnOrBefore(string $targetPeriod): ?string
    {
        if (!Schema::hasTable(self::HARIAN_SNAPSHOT_TABLE)) {
            return null;
        }

        $period = DB::table(self::HARIAN_SNAPSHOT_TABLE)
            ->where('snapshot_period', '<=', $targetPeriod)
            ->whereIn('kanca_label', self::AREA_6_BRANCHES)
            ->whereColumn('kanca_key', 'unit_key')
            ->max('snapshot_period');

        return $period ? Carbon::parse($period)->toDateString() : null;
    }

    protected function formatDateLabel(?string $date): string
    {
        if (!$date) return '-';
        try {
            return Carbon::parse($date)->translatedFormat('d M y');
        } catch (Throwable) {
            return $date;
        }
    }

    protected function loadRkaData(string $period, ?string $category): array
    {
        if (!Schema::hasTable('rka')) {
            return [
                'Giro' => [],
                'Tabungan' => [],
                'Deposito' => [],
            ];
        }

        $service = app(RkaLookupService::class);

        // Parse the RKA period - could be "2026" (year) or "2026-04" (year-month) or a date string
        $year = null;
        $monthCol = null;

        try {
            // Try parsing as a date first
            $date = Carbon::parse($period);
            $year = $date->year;
            $monthCol = $service->resolveMonthColumn($date);
        } catch (Throwable) {
            // If that fails, try parsing as just a year
            if (is_numeric($period) && strlen($period) === 4) {
                $year = (int) $period;
                $monthCol = 'jan'; // Default to January if only year is provided
            }
        }

        try {
            $availableYears = $service->availableYears();
        } catch (Throwable) {
            return [
                'Giro' => [],
                'Tabungan' => [],
                'Deposito' => [],
            ];
        }

        if (!in_array($year, $availableYears)) {
            $year = !empty($availableYears) ? max($availableYears) : null;
        }

        if (!$year || !$monthCol) {
            return [
                'Giro' => [],
                'Tabungan' => [],
                'Deposito' => [],
            ];
        }

        $categoryLower = strtolower($category ?? 'all');

        if ($categoryLower === 'ritel') {
            $definitions = [
                'Giro' => ['mata_anggaran' => ['Giro Retail Funding Total'], 'uker_contains_any' => ['KC', 'KCP'], 'include_kanca_summary' => true],
                'Tabungan' => ['mata_anggaran' => ['Tabungan Retail Funding Total'], 'uker_contains_any' => ['KC', 'KCP'], 'include_kanca_summary' => true],
                'Deposito' => ['mata_anggaran' => ['Deposito Retail Funding Total'], 'uker_contains_any' => ['KC', 'KCP'], 'include_kanca_summary' => true],
                'CASA' => ['mata_anggaran' => ['Giro Retail Funding Total', 'Tabungan Retail Funding Total'], 'uker_contains_any' => ['KC', 'KCP'], 'include_kanca_summary' => true],
            ];
        } elseif ($categoryLower === 'mikro' || $categoryLower === 'micro') {
            $definitions = [
                'Giro' => ['mata_anggaran' => ['Giro Retail Funding Total'], 'uker_contains_any' => ['UNIT']],
                'Tabungan' => ['mata_anggaran' => ['Tabungan Retail Funding Total'], 'uker_contains_any' => ['UNIT']],
                'Deposito' => ['mata_anggaran' => ['Deposito Retail Funding Total'], 'uker_contains_any' => ['UNIT']],
                'CASA' => ['mata_anggaran' => ['Giro Retail Funding Total', 'Tabungan Retail Funding Total'], 'uker_contains_any' => ['UNIT']],
            ];
        } elseif ($categoryLower === 'wholesale') {
            $definitions = [
                'Giro' => ['mata_anggaran' => ['A.2.a. Giro Korporasi']],
                'Tabungan' => ['mata_anggaran' => []],
                'Deposito' => ['mata_anggaran' => ['A.2.b. Deposito Korporasi']],
                'CASA' => ['mata_anggaran' => ['A.2.a. Giro Korporasi']],
            ];
        } else {
            $definitions = [
                'Giro' => ['mata_anggaran' => ['Giro Retail Funding Total', 'A.2.a. Giro Korporasi']],
                'Tabungan' => ['mata_anggaran' => ['Tabungan Retail Funding Total']],
                'Deposito' => ['mata_anggaran' => ['Deposito Retail Funding Total', 'A.2.b. Deposito Korporasi']],
                'CASA' => ['mata_anggaran' => ['Giro Retail Funding Total', 'Tabungan Retail Funding Total', 'A.2.a. Giro Korporasi']],
            ];
        }

        // RKA data is stored at the kanca level (only KC Ponorogo) and unit level (desc_uker)
        // We aggregate by regional patterns (MADIUN, NGAWI, MAGETAN, PONOROGO) from desc_uker
        $regionPatterns = ['MADIUN', 'NGAWI', 'MAGETAN', 'PONOROGO'];
        $regionalData = $service->aggregateByGroupWithRegionalFilter($definitions, $monthCol, $regionPatterns, $year);

        // Map regions to their corresponding KC names
        $regionMap = [
            'MADIUN' => 'KC MADIUN',
            'MAGETAN' => 'KC MAGETAN',
            'NGAWI' => 'KC NGAWI',
            'PONOROGO' => 'KC PONOROGO',
        ];
        $branchFallbackData = $service->aggregateByKancaWithSummaryFallback(
            $definitions,
            $monthCol,
            array_values($regionMap),
            $year
        );

        // Map region data to standardized branch names, filling only missing
        // zero values from normal kanca/summary rows already present in RKA.
        $data = [];
        foreach ($definitions as $defKey => $_) {
            $data[$defKey] = [];
        }

        foreach ($definitions as $defKey => $_) {
            foreach ($regionPatterns as $region) {
                $standardizedBranchName = $regionMap[$region];
                $value = (float) ($regionalData[$defKey][$region] ?? 0);

                if (abs($value) <= 0.0) {
                    $value = (float) ($branchFallbackData[$defKey][$standardizedBranchName] ?? 0);
                }

                $data[$defKey][$standardizedBranchName] = $value;
            }
        }

        return $data;
    }

    public function fetchRkaPeriods(): Collection
    {
        $service = app(RkaLookupService::class);
        $years = $service->availableYears();

        $periods = collect();
        foreach ($years as $year) {
            // Generate months from current month down to January, or all 12 if not current year
            $maxMonth = ($year == date('Y')) ? (int) date('n') : 12;
            
            for ($month = $maxMonth; $month >= 1; $month--) {
                $date = Carbon::createFromDate($year, $month, 1);
                $periods->push($date->toDateString());
            }
        }

        return $periods;
    }

    protected function getRkaVal(array $rkaData, string $branch, string $kategori): float
    {
        // Normalize the branch name to match RKA data keys
        $normalizedBranch = $this->normalizeBranchName($branch);
        $branchKey = strtoupper($normalizedBranch);

        if ($kategori === 'CASA') {
            return ($rkaData['Giro'][$branchKey] ?? 0) + ($rkaData['Tabungan'][$branchKey] ?? 0);
        }

        return $rkaData[$kategori][$branchKey] ?? 0;
    }

    protected function calculateGrandTotals(array $rows): array
    {
        $grandTotal = [
            'selected' => 0, 'ytd' => 0, 'mtd' => 0,
            'delta_ytd' => 0, 'delta_mtd' => 0,
            'rka_rp' => 0, 'rka_pct' => 0
        ];

        foreach ($rows as $row) {
            if ($row['is_total'] === true) {
                $grandTotal['selected'] += $row['selected'];
                $grandTotal['ytd'] += $row['ytd'];
                $grandTotal['mtd'] += $row['mtd'];
                $grandTotal['delta_ytd'] += $row['delta_ytd'];
                $grandTotal['delta_mtd'] += $row['delta_mtd'];
                $grandTotal['rka_rp'] += $row['rka_rp'];
            }
        }

        // Recalculate percentage for grand total
        $rkaVal = $grandTotal['selected'] - $grandTotal['rka_rp'];
        $grandTotal['rka_pct'] = $rkaVal > 0 ? ($grandTotal['selected'] / $rkaVal) * 100 : 0;

        return $grandTotal;
    }

    public function fetchPeriods(): Collection
    {
        if (Schema::hasTable(self::HARIAN_SNAPSHOT_TABLE)) {
            $periods = DB::table(self::HARIAN_SNAPSHOT_TABLE)
                ->whereIn('kanca_label', self::AREA_6_BRANCHES)
                ->whereColumn('kanca_key', 'unit_key')
                ->select('snapshot_period')
                ->distinct()
                ->orderByDesc('snapshot_period')
                ->pluck('snapshot_period')
                ->map(fn ($period) => Carbon::parse($period)->toDateString());

            if ($periods->isNotEmpty()) {
                return $periods;
            }
        }

        return DB::table(self::TABLE)
            ->select('Month_Day_Year_of_Posisi')
            ->distinct()
            ->orderByDesc('Month_Day_Year_of_Posisi')
            ->pluck('Month_Day_Year_of_Posisi');
    }

    public function fetchBranches(): array
    {
        $branches = ['area6' => 'AREA 6'];

        if (Schema::hasTable(self::HARIAN_SNAPSHOT_TABLE)) {
            $snapshotBranches = DB::table(self::HARIAN_SNAPSHOT_TABLE)
                ->whereIn('kanca_label', self::AREA_6_BRANCHES)
                ->whereColumn('kanca_key', 'unit_key')
                ->select('kanca_label')
                ->distinct()
                ->pluck('kanca_label')
                ->map(fn ($branch): string => (string) $branch)
                ->all();

            foreach (self::AREA_6_BRANCHES as $branch) {
                if (in_array($branch, $snapshotBranches, true)) {
                    $branches[$branch] = $branch;
                }
            }

            if (count($branches) > 1) {
                return $branches;
            }
        }

        if (Schema::hasTable(self::TABLE)) {
            $rawBranches = DB::table(self::TABLE)
                ->select('nama_cabang')
                ->distinct()
                ->whereNotNull('nama_cabang')
                ->where('nama_cabang', '<>', '')
                ->pluck('nama_cabang')
                ->map(fn ($branch): string => $this->normalizeBranchName((string) $branch))
                ->filter()
                ->unique()
                ->sort()
                ->values()
                ->all();

            foreach ($rawBranches as $branch) {
                $standardBranch = $this->resolveAreaBranchLabel($branch);
                if ($standardBranch !== null) {
                    $branches[$standardBranch] = $standardBranch;
                }
            }
        }

        return $branches;
    }

    public function fetchCategories(): array
    {
        if (Schema::hasTable(self::HARIAN_SNAPSHOT_TABLE)) {
            return ['Ritel', 'Mikro', 'Wholesale'];
        }

        return DB::table(self::TABLE)
            ->select('segmentasi')
            ->distinct()
            ->whereNotNull('segmentasi')
            ->where('segmentasi', '<>', '')
            ->pluck('segmentasi')
            ->toArray();
    }

    private function normalizeBranchScope(?string $branch): string
    {
        $normalized = strtolower(trim((string) $branch));
        if ($normalized === '' || in_array($normalized, ['all', 'area6', 'area 6'], true)) {
            return 'area6';
        }

        return $this->normalizeBranchName((string) $branch);
    }

    private function resolveAreaBranchLabel(string $branch): ?string
    {
        $branchKey = strtoupper($this->normalizeBranchName($branch));
        foreach (self::AREA_6_BRANCHES as $areaBranch) {
            if (strtoupper($this->normalizeBranchName($areaBranch)) === $branchKey) {
                return $areaBranch;
            }
        }

        return null;
    }

    private function segmentDefinitions(): array
    {
        return [
            'ritel' => 'RITEL',
            'mikro' => 'MIKRO',
            'wholesale' => 'WHOLESALE',
        ];
    }

    private function segmentLabel(string $segment): ?string
    {
        $normalized = strtolower(trim($segment));

        return match ($normalized) {
            'ritel', 'retail' => 'RITEL',
            'mikro', 'micro' => 'MIKRO',
            'wholesale' => 'WHOLESALE',
            default => null,
        };
    }

    private function productLabel(string $product): ?string
    {
        $normalized = strtoupper(trim($product));

        return match ($normalized) {
            'GIRO' => 'Giro',
            'TABUNGAN' => 'Tabungan',
            'DEPOSITO' => 'Deposito',
            default => null,
        };
    }
}
