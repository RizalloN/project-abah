<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class DashboardDanaService
{
    private const TABLE = 'ssa_simpanan';
    private const AREA_6_BRANCHES = [
        'KC Madiun',
        'KC Magetan',
        'KC Ngawi',
        'KC Ponorogo',
    ];

    /**
     * Get data for Dashboard Dana from ssa_simpanan with performance metrics
     */
    public function getDashboardData(?string $selectedPeriod, ?string $category, ?string $rkaPeriod = null): array
    {
        if (!$selectedPeriod) {
            return [
                'rows' => [],
                'total' => [],
                'header_dates' => [],
            ];
        }

        $periods = $this->calculatePeriodReferences($selectedPeriod);
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

        return [
            'rows' => $formattedRows,
            'total' => $this->calculateGrandTotals($formattedRows),
            'header_dates' => [
                'selected' => $this->formatDateLabel($periods['selected']),
                'ytd' => $this->formatDateLabel($periods['ytd']),
                'mtd' => $this->formatDateLabel($periods['mtd']),
            ],
        ];
    }

    private function normalizeBranchName(string $name): string
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

    private function getVal(array $matrix, string $branch, ?string $period, string $kategori): float
    {
        if (!$period || !isset($matrix[$branch][$period])) return 0;

        if ($kategori === 'CASA') {
            return ($matrix[$branch][$period]['Giro'] ?? 0) + ($matrix[$branch][$period]['Tabungan'] ?? 0);
        }

        return $matrix[$branch][$period][$kategori] ?? 0;
    }

    private function calculatePeriodReferences(string $selectedPeriod): array
    {
        try {
            $selected = Carbon::parse($selectedPeriod);
            return [
                'selected' => $selected->format('Y-m-d'),
                'mtd' => $selected->copy()->subMonth()->endOfMonth()->format('Y-m-d'),
                'ytd' => $selected->copy()->subYear()->endOfYear()->format('Y-m-d'),
            ];
        } catch (Throwable) {
            return ['selected' => $selectedPeriod, 'mtd' => null, 'ytd' => null];
        }
    }

    private function formatDateLabel(?string $date): string
    {
        if (!$date) return '-';
        try {
            return Carbon::parse($date)->translatedFormat('d M y');
        } catch (Throwable) {
            return $date;
        }
    }

    private function loadRkaData(string $period, ?string $category): array
    {
        $service = app(RkaLookupService::class);
        $date = Carbon::parse($period);
        $monthCol = $service->resolveMonthColumn($date);
        
        $requestedYear = $date->year;
        $availableYears = $service->availableYears();
        $year = in_array($requestedYear, $availableYears) ? $requestedYear : (!empty($availableYears) ? max($availableYears) : null);

        $ukerFilter = [];
        $categoryLower = strtolower($category ?? 'all');
        if ($categoryLower === 'ritel') {
            $ukerFilter = ['KC', 'KCP'];
        } elseif ($categoryLower === 'mikro' || $categoryLower === 'micro') {
            $ukerFilter = ['UNIT'];
        }

        $definitions = [
            'Giro' => ['mata_anggaran' => ['Giro Retail Funding Total']],
            'Tabungan' => ['mata_anggaran' => ['Tabungan Retail Funding Total']],
            'Deposito' => ['mata_anggaran' => ['Deposito Retail Funding Total']],
        ];

        if (!empty($ukerFilter)) {
            foreach ($definitions as &$def) {
                $def['uker_contains_any'] = $ukerFilter;
            }
        }

        // RKA data is typically at the branch level
        $data = $service->aggregateByGroup($definitions, $monthCol, self::AREA_6_BRANCHES, [], 'kanca', $year);
        
        // Handle regional branches like DashboardPinjamanKreditService does
        $regionPatterns = ['MADIUN', 'NGAWI', 'MAGETAN'];
        $regionalData = $service->aggregateByGroupWithRegionalFilter($definitions, $monthCol, $regionPatterns, $year);

        // Merge regional data into main branch data
        foreach ($definitions as $defKey => $_) {
            foreach ($regionPatterns as $region) {
                $branchKey = 'KC ' . $region;
                if (isset($regionalData[$defKey][$region])) {
                    $data[$defKey][$branchKey] = $regionalData[$defKey][$region];
                }
            }
        }

        return $data;
    }

    private function getRkaVal(array $rkaData, string $branch, string $kategori): float
    {
        $branchKey = strtoupper(trim($branch));
        
        if ($kategori === 'CASA') {
            return ($rkaData['Giro'][$branchKey] ?? 0) + ($rkaData['Tabungan'][$branchKey] ?? 0);
        }

        return $rkaData[$kategori][$branchKey] ?? 0;
    }

    private function calculateGrandTotals(array $rows): array
    {
        $grandTotal = [
            'selected' => 0, 'ytd' => 0, 'mtd' => 0,
            'delta_ytd' => 0, 'delta_mtd' => 0,
            'rka_rp' => 0, 'rka_pct' => 0
        ];

        foreach ($rows as $row) {
            // Only aggregate individual categories (not CASA and not branch totals) 
            // to avoid double/triple counting for the final grand total
            if ($row['is_total'] === false && !in_array($row['kategori'], ['CASA', 'TOTAL CABANG'])) {
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
        return DB::table(self::TABLE)
            ->select('Month_Day_Year_of_Posisi')
            ->distinct()
            ->orderByDesc('Month_Day_Year_of_Posisi')
            ->pluck('Month_Day_Year_of_Posisi');
    }

    public function fetchCategories(): array
    {
        return DB::table(self::TABLE)
            ->select('segmentasi')
            ->distinct()
            ->whereNotNull('segmentasi')
            ->where('segmentasi', '<>', '')
            ->pluck('segmentasi')
            ->toArray();
    }
}
