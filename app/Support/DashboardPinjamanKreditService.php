<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;
use App\Support\RkaLookupService;

class DashboardPinjamanKreditService
{
    private const SNAPSHOT_TABLE = 'dashboard_harian_snapshots';
    private const AREA_6_BRANCHES = [
        'KC Madiun',
        'KC Magetan',
        'KC Ngawi',
        'KC Ponorogo',
    ];

    /**
     * Cache for snapshot data to avoid repeated database hits
     */
    private array $snapshotCache = [];

    /**
     * Cache for RKA data
     */
    private array $rkaCache = [];

    private ?RkaLookupService $rkaLookup = null;
    
    /**
     * Get unified segment data (OS, SML, NPL) in one go
     */
    public function getUnifiedSegmentData(
        ?string $selectedPeriod,
        string $segment = 'SME',
        array|string|null $selectedBranches = null
    ): array {
        if (!$selectedPeriod) {
            return ['os' => [], 'sml' => [], 'npl' => [], 'header_dates' => [], 'rka_labels' => []];
        }

        $segment = $this->normalizeSegment($segment);
        $periods = $this->calculatePeriodReferences($selectedPeriod);

        // Find all branches across these periods
        $branches = $this->getDynamicBranches(array_filter($periods), $selectedBranches);

        // Load ALL data for ALL periods, ALL branches, and ALL types in ONE query
        $this->loadBulkSnapshotData(array_filter($periods), $branches);

        // Load RKA data for the segment
        $rkaData = $this->loadRkaForSegment($selectedPeriod, $segment, $branches);

        $categories = $this->getCategoriesForSegment($segment);

        $res = [
            'os' => $this->formatSegmentType($periods, $branches, $categories, $segment, 'os', $rkaData),
            'sml' => $this->formatSegmentType($periods, $branches, $categories, $segment, 'sml', $rkaData),
            'npl' => $this->formatSegmentType($periods, $branches, $categories, $segment, 'npl', $rkaData),
            'header_dates' => $periods,
            'rka_labels' => $this->calculateRkaLabels($selectedPeriod),
        ];

        return DashboardCrossAlignmentGuard::alignCredit($res, $selectedPeriod, $segment);
    }

    /**
     * Helper to format data for a specific type (os/sml/npl)
     */
    private function formatSegmentType(array $periods, array $branches, array $categories, string $segment, string $type, array $rkaData = []): array
    {
        $data = [];
        $rowNo = 1;

        foreach ($branches as $branch) {
            foreach ($categories as $category) {
                $row = [
                    'no' => $rowNo++,
                    'branch' => $branch,
                    'area_head' => $this->mapAreaHead($branch),
                    'category' => $category,
                ];

                // Values for each period
                foreach ($periods as $pKey => $pDate) {
                    $row[$pKey] = $this->getSnapshotValueFromCache($branch, $category, $pDate, $type, $segment);
                }

                // Deltas
                $selected = $row['selected'] ?? 0;
                $row['delta_ytd'] = $selected - ($row['ytd'] ?? 0);
                $row['delta_mom'] = $selected - ($row['mtm'] ?? 0);
                $row['delta_mtd'] = $selected - ($row['mtd'] ?? 0);

                // RKA fields
                $branchKey = $this->normalizeBranchForRka($branch);
                $rka_m1 = $rkaData[$type][$category][$branchKey]['m1'] ?? 0;
                $rka_current = $rkaData[$type][$category][$branchKey]['current'] ?? 0;

                $row['rka_m1'] = $rka_m1;
                $row['rka_current'] = $rka_current;
                $row['penc_m1_rp'] = $this->calculateRkaDelta((float) $selected, (float) $rka_m1, $type);
                $row['penc_m1_pct'] = $this->calculateRkaPercentage((float) $selected, (float) $rka_m1, $type);
                $row['penc_cur_rp'] = $this->calculateRkaDelta((float) $selected, (float) $rka_current, $type);
                $row['penc_cur_pct'] = $this->calculateRkaPercentage((float) $selected, (float) $rka_current, $type);

                $data[] = $row;
            }
        }

        return $this->appendTotalRow($data, $segment, $type);
    }

    private function calculateRkaDelta(float $selected, float $rka, string $type): float
    {
        return $selected - $rka;
    }

    private function calculateRkaPercentage(float $selected, float $rka, string $type): float
    {
        if ($this->isQualityType($type)) {
            return $selected > 0 ? ($rka / $selected) * 100 : 100;
        }

        return $rka > 0 ? ($selected / $rka) * 100 : 0;
    }

    private function isQualityType(string $type): bool
    {
        return in_array(strtolower($type), ['sml', 'npl'], true);
    }

    private function normalizeSegment(string $segment): string
    {
        return match (strtoupper(trim($segment))) {
            'CONSUMER' => 'Consumer',
            'MIKRO', 'MICRO' => 'Mikro',
            default => 'SME',
        };
    }

    /**
     * Get categories for a specific segment
     */
    private function getCategoriesForSegment(string $segment): array
    {
        return match (strtoupper($segment)) {
            'SME' => ['Kecil non Cashcoll', 'Cashcoll'],
            'CONSUMER' => ['Briguna Konsumer', 'KPR', 'KKB'],
            'MIKRO' => ['Micro', 'Briguna Mikro', 'Kupedes', 'KUR Mikro', 'KUR Kecil', 'KUR KPP'],
            default => ['SME'],
        };
    }

    /**
     * Get branches found in the snapshot table for the given periods
     */
    private function getDynamicBranches(array $periods, array|string|null $selectedBranches = null): array
    {
        $branchScope = $this->resolveBranchScope($selectedBranches);

        $availableBranches = DB::table(self::SNAPSHOT_TABLE)
            ->whereIn('snapshot_period', $periods)
            ->whereIn('kanca_label', $branchScope)
            ->whereColumn('kanca_key', 'unit_key')
            ->whereNotNull('kanca_label')
            ->distinct()
            ->pluck('kanca_label')
            ->toArray();

        return array_values(array_filter(
            $branchScope,
            fn (string $branch) => in_array($branch, $availableBranches, true)
        ));
    }

    /**
     * @return array<int, string>
     */
    private function resolveBranchScope(array|string|null $selectedBranches): array
    {
        $values = is_array($selectedBranches) ? $selectedBranches : [$selectedBranches];
        $normalized = collect($values)
            ->map(fn ($branch): string => trim((string) $branch))
            ->filter(fn (string $branch): bool => $branch !== '' && strtolower($branch) !== 'all')
            ->values()
            ->all();

        if ($normalized === []) {
            return self::AREA_6_BRANCHES;
        }

        return array_values(array_filter(
            self::AREA_6_BRANCHES,
            fn (string $branch): bool => in_array($branch, $normalized, true)
        ));
    }

    /**
     * Load all required columns for all branches and periods in one query
     * Optimized: Select only needed columns and use index hints
     */
    private function loadBulkSnapshotData(array $periods, array $branches): void
    {
        if (empty($periods) || empty($branches)) return;

        // Select only essential columns to reduce memory footprint
        $requiredColumns = [
            'snapshot_period',
            'kanca_label',
            'unit_label',
            'kecil_non_cashcoll_os',
            'cashcoll_os',
            'kecil_non_cashcoll_sml',
            'cashcoll_sml',
            'kecil_non_cashcoll_npl',
            'cashcoll_npl',
            'briguna_konsumer_os',
            'briguna_konsumer_sml',
            'briguna_konsumer_npl',
            'kpr_os',
            'kpr_sml',
            'kpr_npl',
            'kkb_os',
            'kkb_sml',
            'kkb_npl',
            'micro_os',
            'briguna_mikro_os',
            'kupedes_os',
            'kur_mikro_os',
            'kur_kecil_os',
            'kur_kpp_os',
            'micro_sml',
            'briguna_mikro_sml',
            'kupedes_sml',
            'kur_mikro_sml',
            'kur_kecil_sml',
            'kur_kpp_sml',
            'micro_npl',
            'briguna_mikro_npl',
            'kupedes_npl',
            'kur_mikro_npl',
            'kur_kecil_npl',
            'kur_kpp_npl',
        ];

        $query = DB::table(self::SNAPSHOT_TABLE)
            ->select($requiredColumns)
            ->whereIn('snapshot_period', $periods)
            ->whereIn('kanca_label', $branches)
            ->whereColumn('kanca_key', 'unit_key') // Only aggregate rows
            ->orderBy('snapshot_period');

        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $query->orderByRaw("FIELD(kanca_label, '" . implode("','", array_map(
                static fn (string $branch): string => str_replace("'", "''", $branch),
                $branches
            )) . "')");
        } else {
            $query->orderBy('kanca_label');
        }

        $records = $query->get();

        foreach ($records as $record) {
            $key = "{$record->snapshot_period}|{$record->kanca_label}";
            $this->snapshotCache[$key] = $record;
        }
    }

    private function getSnapshotValueFromCache(string $branch, string $category, ?string $period, string $type, string $segment): float
    {
        if (!$period) return 0;
        
        $key = "{$period}|{$branch}";
        $record = $this->snapshotCache[$key] ?? null;
        if (!$record) return 0;

        if ($category === 'Micro') {
            $briguna = (float) ($record->{"briguna_mikro_{$type}"} ?? 0);
            $kupedes = (float) ($record->{"kupedes_{$type}"} ?? 0);
            $kur_mikro = (float) ($record->{"kur_mikro_{$type}"} ?? 0);
            $kur_kecil = (float) ($record->{"kur_kecil_{$type}"} ?? 0);
            $kur_kpp = (float) ($record->{"kur_kpp_{$type}"} ?? 0);
            return $briguna + $kupedes + $kur_mikro + $kur_kecil + $kur_kpp;
        }

        $column = $this->mapCategoryToColumn($category, $type);
        return (float) ($record->$column ?? 0);
    }


    /**
     * Map category label to column name
     */
    private function mapCategoryToColumn(string $category, string $type): string
    {
        switch ($category) {
            case 'Kecil non Cashcoll': return "kecil_non_cashcoll_{$type}";
            case 'Cashcoll': return "cashcoll_{$type}";
            case 'Briguna Konsumer': return "briguna_konsumer_{$type}";
            case 'Briguna Mikro': return "briguna_mikro_{$type}";
            case 'KKB': return "kkb_{$type}";
            case 'KUR Mikro': return "kur_mikro_{$type}";
            case 'KUR Kecil': return "kur_kecil_{$type}";
            case 'KUR KPP': return "kur_kpp_{$type}";
        }

        $slug = strtolower(str_replace(' ', '_', $category));
        return "{$slug}_{$type}";
    }

    private function mapAreaHead(string $branch): string
    {
        // Placeholder implementation
        return 'Area 6';
    }

    private function appendTotalRow(array $rows, string $segment, string $type): array
    {
        if (empty($rows)) return [];

        $totalRow = [
            'no' => 'TOTAL',
            'branch' => 'TOTAL',
            'area_head' => '',
            'category' => '',
            'ytd' => 0,
            'm2' => 0,
            'mtm' => 0,
            'mtd' => 0,
            'selected' => 0,
            'delta_ytd' => 0,
            'delta_mom' => 0,
            'delta_mtd' => 0,
            'rka_m1' => 0,
            'rka_current' => 0,
            'penc_m1_rp' => 0,
            'penc_m1_pct' => 0,
            'penc_cur_rp' => 0,
            'penc_cur_pct' => 0,
            'is_total' => true,
        ];

        foreach ($rows as $row) {
            if (!$this->shouldIncludeInGrandTotal($segment, (string) ($row['category'] ?? ''))) {
                continue;
            }

            $totalRow['ytd'] += $row['ytd'];
            $totalRow['m2'] += $row['m2'];
            $totalRow['mtm'] += $row['mtm'];
            $totalRow['mtd'] += $row['mtd'];
            $totalRow['selected'] += $row['selected'];
            $totalRow['delta_ytd'] += $row['delta_ytd'];
            $totalRow['delta_mom'] += $row['delta_mom'];
            $totalRow['delta_mtd'] += $row['delta_mtd'];
            $totalRow['rka_m1'] += $row['rka_m1'];
            $totalRow['rka_current'] += $row['rka_current'];
        }

        // Calculate total RKA percentages based on grand totals
        $totalRow['penc_m1_rp'] = $this->calculateRkaDelta((float) $totalRow['selected'], (float) $totalRow['rka_m1'], $type);
        $totalRow['penc_m1_pct'] = $this->calculateRkaPercentage((float) $totalRow['selected'], (float) $totalRow['rka_m1'], $type);
        $totalRow['penc_cur_rp'] = $this->calculateRkaDelta((float) $totalRow['selected'], (float) $totalRow['rka_current'], $type);
        $totalRow['penc_cur_pct'] = $this->calculateRkaPercentage((float) $totalRow['selected'], (float) $totalRow['rka_current'], $type);

        $rows[] = $totalRow;
        return $rows;
    }

    private function shouldIncludeInGrandTotal(string $segment, string $category): bool
    {
        if (strtoupper($segment) === 'MIKRO' && $category === 'Micro') {
            return false;
        }

        return true;
    }

    public function calculatePeriodReferences(?string $selectedPeriod): array
    {
        if (!$selectedPeriod) return ['selected' => null, 'ytd' => null, 'm2' => null, 'mtm' => null, 'mtd' => null];

        try {
            $selected = Carbon::parse($selectedPeriod);
            return [
                'selected' => $selected->format('Y-m-d'),
                'ytd' => $selected->copy()->subYear()->endOfYear()->format('Y-m-d'),
                'm2' => $selected->copy()->subMonths(2)->endOfMonth()->format('Y-m-d'),
                'mtm' => $selected->copy()->subMonth()->format('Y-m-d'),
                'mtd' => $selected->copy()->subMonthNoOverflow()->endOfMonth()->format('Y-m-d'),
            ];
        } catch (Throwable) {
            return ['selected' => $selectedPeriod, 'ytd' => null, 'm2' => null, 'mtm' => null, 'mtd' => null];
        }
    }

    /**
     * Load RKA data for segment from RKA table
     * IMPORTANT: Madiun/Ngawi/Magetan are stored in desc_uker under KC Ponorogo
     * Need to handle regional sub-unit lookups via desc_uker patterns
     */
    private function loadRkaForSegment(string $selectedPeriod, string $segment, array $branches): array
    {
        $cacheKey = 'sme_segment_rka_v9_harian_scope_december_rka:' . md5($selectedPeriod . '|' . $segment . '|' . implode(',', $branches));

        // Check local cache first
        if (isset($this->rkaCache[$cacheKey])) {
            return $this->rkaCache[$cacheKey];
        }

        // Check Laravel cache
        $cached = \Cache::get($cacheKey);
        if ($cached !== null) {
            $this->rkaCache[$cacheKey] = $cached;
            return $cached;
        }

        try {
            $harianService = app(DashboardHarianSnapshotService::class);
            $resolvedRkaPeriod = $harianService->resolveEffectiveRkaPeriod(null, $selectedPeriod);
            $rkaDate = $resolvedRkaPeriod ? Carbon::parse($resolvedRkaPeriod) : Carbon::parse($selectedPeriod);
            $decemberDate = $rkaDate->copy()->month(12)->startOfMonth();
            $currentDate = $rkaDate;

            $decemberMonth = $this->getRkaLookupService()->resolveMonthColumn($decemberDate);
            $currentMonth = $this->getRkaLookupService()->resolveMonthColumn($currentDate);
            $decemberYear = (int) $decemberDate->format('Y');
            $currentYear = (int) $currentDate->format('Y');

            $categories = $this->getCategoriesForSegment($segment);
            $rkaData = [];

            $kancaFilters = [];

            foreach ($branches as $branch) {
                $normalized = $this->normalizeBranchForRka($branch);
                if ($normalized === '') continue;

                $kancaFilters[] = $normalized;
            }

            $kancaFilters = array_values(array_unique($kancaFilters));

            \Log::debug('RKA Branch Mapping', [
                'original_branches' => $branches,
                'kanca_filters' => $kancaFilters,
                'period' => $selectedPeriod,
                'segment' => $segment,
            ]);

            // Load RKA data for all types at once
            foreach (['os', 'sml', 'npl'] as $type) {
                $definitions = $this->getRkaDefinitions($segment, $type);
                $rkaData[$type] = [];

                if (!empty($kancaFilters)) {
                    foreach ($categories as $category) {
                        $rkaData[$type][$category] = $rkaData[$type][$category] ?? [];
                    }

                    foreach ($branches as $branch) {
                        $branchKey = $this->normalizeBranchForRka($branch);
                        if ($branchKey === '') {
                            continue;
                        }

                        $rkaM1 = $this->getRkaLookupService()->aggregateForScope(
                            $definitions,
                            $decemberMonth,
                            $branch,
                            null,
                            $decemberYear
                        );

                        $rkaCurrent = $this->getRkaLookupService()->aggregateForScope(
                            $definitions,
                            $currentMonth,
                            $branch,
                            null,
                            $currentYear
                        );

                        foreach ($categories as $category) {
                            $definitionKey = $this->getCategoryToDefinitionKey($segment, $category, $type);

                            $rkaData[$type][$category][$branchKey] = [
                                'm1' => (float) ($rkaM1[$definitionKey] ?? 0),
                                'current' => (float) ($rkaCurrent[$definitionKey] ?? 0),
                            ];
                        }
                    }
                }
            }

            $this->rkaCache[$cacheKey] = $rkaData;
            \Cache::put($cacheKey, $rkaData, now()->addMinutes(60));
            return $rkaData;
        } catch (Throwable $e) {
            // Log error for debugging
            \Log::warning('RKA load error for segment: ' . $segment, [
                'error' => $e->getMessage(),
                'period' => $selectedPeriod,
                'trace' => $e->getTraceAsString(),
            ]);
            // Return empty RKA data structure on error
            return $this->getEmptyRkaDataStructure();
        }
    }

    private function getEmptyRkaDataStructure(): array
    {
        $result = [];
        foreach (['os', 'sml', 'npl'] as $type) {
            $result[$type] = [];
        }
        return $result;
    }

    /**
     * Get RKA definitions for segment and type
     */
    private function getRkaDefinitions(string $segment, string $type): array
    {
        if ($segment === 'SME') {
            return $this->getSmeLoanDefinitions($type);
        } elseif ($segment === 'Consumer') {
            return $this->getConsumerLoanDefinitions($type);
        } elseif ($segment === 'Mikro') {
            return $this->getMikroLoanDefinitions($type);
        }

        return [];
    }

    private function getSmeLoanDefinitions(string $type): array
    {
        if ($type === 'os') {
            return [
                'kecil_non_cashcoll_os' => ['mata_anggaran' => ['B.2.a. Kredit Kecil Non Cash Collateral'], 'uker_contains_any' => ['KC', 'KCP'], 'include_kanca_summary' => true],
                'cashcoll_os' => ['mata_anggaran' => ['B.2.b. Kredit Kecil Cash Collateral'], 'uker_contains_any' => ['KC', 'KCP'], 'include_kanca_summary' => true],
            ];
        } elseif ($type === 'sml') {
            return [
                'kecil_non_cashcoll_sml' => ['mata_anggaran' => ['DPK Rp Kecil Non Cash Collateral']],
                'cashcoll_sml' => ['mata_anggaran' => ['DPK Rp Kecil Cash Collateral']],
            ];
        } elseif ($type === 'npl') {
            return [
                'kecil_non_cashcoll_npl' => ['mata_anggaran' => ['NPL Rp Kecil Non Cash Collateral']],
                'cashcoll_npl' => ['mata_anggaran' => ['NPL Rp Kecil Cash Collateral']],
            ];
        }

        return [];
    }

    private function getConsumerLoanDefinitions(string $type): array
    {
        if ($type === 'os') {
            return [
                'briguna_konsumer_os' => ['mata_anggaran' => ['B.5.a. Briguna'], 'uker_contains_any' => ['KC', 'KCP'], 'include_kanca_summary' => true],
                'kpr_os' => ['mata_anggaran' => ['B.5.b. KPR'], 'uker_contains_any' => ['KC', 'KCP'], 'include_kanca_summary' => true],
                'kkb_os' => ['mata_anggaran' => ['B.5.c. KKB'], 'uker_contains_any' => ['KC', 'KCP'], 'include_kanca_summary' => true],
            ];
        } elseif ($type === 'sml') {
            return [
                'briguna_konsumer_sml' => ['mata_anggaran' => ['DPK Rp Briguna']],
                'kpr_sml' => ['mata_anggaran' => ['DPK Rp KPR']],
                'kkb_sml' => ['mata_anggaran' => ['DPK Rp KKB']],
            ];
        } elseif ($type === 'npl') {
            return [
                'briguna_konsumer_npl' => ['mata_anggaran' => ['NPL Rp Briguna']],
                'kpr_npl' => ['mata_anggaran' => ['NPL Rp KPR']],
                'kkb_npl' => ['mata_anggaran' => ['NPL Rp KKB']],
            ];
        }

        return [];
    }

    private function getMikroLoanDefinitions(string $type): array
    {
        if ($type === 'os') {
            return [
                'micro_os' => ['mata_anggaran' => ['B.1. MIKRO']],
                'briguna_mikro_os' => ['mata_anggaran' => ['B.1.b. Briguna Mikro']],
                'kupedes_os' => ['mata_anggaran' => ['B.1.a. Kupedes Komersial']],
                'kur_mikro_os' => ['mata_anggaran' => ['B.1.c. KUR Mikro']],
                'kur_kecil_os' => ['mata_anggaran' => ['B.1.d. KUR Kecil']],
                'kur_kpp_os' => ['mata_anggaran' => ['B.1.e. KPP']],
            ];
        } elseif ($type === 'sml') {
            return [
                'micro_sml' => ['mata_anggaran' => ['DPK Rp Mikro']],
                'briguna_mikro_sml' => ['mata_anggaran' => ['DPK Rp Briguna Mikro']],
                'kupedes_sml' => ['mata_anggaran' => ['DPK Rp Kupedes Komersial']],
                'kur_mikro_sml' => ['mata_anggaran' => ['DPK Rp KUR Mikro']],
                'kur_kecil_sml' => ['mata_anggaran' => ['DPK Rp KUR Kecil']],
                'kur_kpp_sml' => ['mata_anggaran' => ['DPK Rp KPP']],
            ];
        } elseif ($type === 'npl') {
            return [
                'micro_npl' => ['mata_anggaran' => ['NPL Rp Mikro']],
                'briguna_mikro_npl' => ['mata_anggaran' => ['NPL Rp Briguna Mikro']],
                'kupedes_npl' => ['mata_anggaran' => ['NPL Rp Kupedes Komersial']],
                'kur_mikro_npl' => ['mata_anggaran' => ['NPL Rp KUR Mikro']],
                'kur_kecil_npl' => ['mata_anggaran' => ['NPL Rp KUR Kecil']],
                'kur_kpp_npl' => ['mata_anggaran' => ['NPL Rp KPP']],
            ];
        }

        return [];
    }

    /**
     * Map category to definition key for RKA lookup
     */
    private function getCategoryToDefinitionKey(string $segment, string $category, string $type): string
    {
        if ($segment === 'SME') {
            $categoryMap = [
                'Kecil non Cashcoll' => 'kecil_non_cashcoll',
                'Cashcoll' => 'cashcoll',
            ];
        } elseif ($segment === 'Consumer') {
            $categoryMap = [
                'Briguna Konsumer' => 'briguna_konsumer',
                'KPR' => 'kpr',
                'KKB' => 'kkb',
            ];
        } elseif ($segment === 'Mikro') {
            $categoryMap = [
                'Micro' => 'micro',
                'Briguna Mikro' => 'briguna_mikro',
                'Kupedes' => 'kupedes',
                'KUR Mikro' => 'kur_mikro',
                'KUR Kecil' => 'kur_kecil',
                'KUR KPP' => 'kur_kpp',
            ];
        } else {
            return '';
        }

        return ($categoryMap[$category] ?? '') . '_' . $type;
    }

    /**
     * Normalize branch name for RKA lookup
     * MUST match RkaLookupService::normalizeScopeValue() logic exactly for correct RKA mapping
     */
    private function normalizeBranchForRka(string $branch): string
    {
        $normalized = strtoupper(trim($branch));

        if ($normalized === '') {
            return '';
        }

        // Skip special keywords
        if (in_array($normalized, ['ALL', 'ALL KANCA', 'ALL UKER'], true)) {
            return '';
        }

        // Remove leading quotes/spaces
        $normalized = ltrim($normalized, "'\" ");
        
        // Remove leading numeric prefixes like "1 - " or "1 "
        $normalized = preg_replace('/^\d+\s*-\s*/', '', $normalized);
        $normalized = preg_replace('/^\d+\s+/', '', $normalized);
        
        // Remove trailing content in parentheses
        $normalized = preg_replace('/\s*\([^)]*\)$/', '', $normalized);
        
        // Normalize whitespace
        $normalized = preg_replace('/\s+/', ' ', trim($normalized));

        return ($normalized !== '' ? $normalized : '');
    }

    /**
     * Debug: Check what branch names exist in RKA table for debugging
     */
    private function debugRkaBranchNames(): array
    {
        $rkaTableData = DB::table('rka')
            ->select('kanca')
            ->whereNotNull('kanca')
            ->distinct()
            ->limit(50)
            ->pluck('kanca')
            ->toArray();

        $normalized = array_map(
            fn($val) => $this->normalizeBranchForRka($val),
            $rkaTableData
        );

        return [
            'raw_from_rka' => $rkaTableData,
            'normalized' => array_unique($normalized),
        ];
    }

    /**
     * Get RKA label for two months (December and current)
     */
    private function calculateRkaLabels(string $selectedPeriod): array
    {
        try {
            $selectedDate = Carbon::parse($selectedPeriod);
            $decemberDate = $selectedDate->copy()->month(12)->startOfMonth();

            return [
                'm1' => $decemberDate->locale('id')->translatedFormat('M-y'),
                'current' => $selectedDate->locale('id')->translatedFormat('M-y'),
            ];
        } catch (Throwable) {
            return ['m1' => '', 'current' => ''];
        }
    }

    /**
     * Get RkaLookupService instance (lazy load)
     */
    private function getRkaLookupService(): RkaLookupService
    {
        if ($this->rkaLookup === null) {
            $this->rkaLookup = app(RkaLookupService::class);
        }

        return $this->rkaLookup;
    }
}
