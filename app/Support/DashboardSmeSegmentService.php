<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class DashboardSmeSegmentService
{
    private const SNAPSHOT_TABLE = 'dashboard_harian_snapshots';
    private const AREA_6_BRANCHES = [
        'KC Ponorogo',
        'KC Madiun',
        'KC Ngawi',
        'KC Magetan',
    ];
    
    /**
     * Cache for snapshot data to avoid repeated database hits
     */
    private array $snapshotCache = [];
    
    /**
     * Get unified segment data (OS, SML, NPL) in one go
     */
    public function getUnifiedSegmentData(
        ?string $selectedPeriod,
        string $segment = 'SME'
    ): array {
        if (!$selectedPeriod) {
            return ['os' => [], 'sml' => [], 'npl' => []];
        }

        $periods = $this->calculatePeriodReferences($selectedPeriod);
        
        // Find all branches across these periods
        $branches = $this->getDynamicBranches(array_filter($periods));
        
        // Load ALL data for ALL periods, ALL branches, and ALL types in ONE query
        $this->loadBulkSnapshotData(array_filter($periods), $branches);

        $categories = $this->getCategoriesForSegment($segment);

        return [
            'os' => $this->formatSegmentType($periods, $branches, $categories, $segment, 'os'),
            'sml' => $this->formatSegmentType($periods, $branches, $categories, $segment, 'sml'),
            'npl' => $this->formatSegmentType($periods, $branches, $categories, $segment, 'npl'),
            'header_dates' => $periods,
        ];
    }

    /**
     * Helper to format data for a specific type (os/sml/npl)
     */
    private function formatSegmentType(array $periods, array $branches, array $categories, string $segment, string $type): array
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
                $row['delta_mtd'] = $selected - ($row['m2'] ?? 0);
                $row['delta_dtd'] = $selected - ($row['mtm'] ?? 0);

                $data[] = $row;
            }
        }

        return $this->appendTotalRow($data);
    }

    /**
     * Get categories for a specific segment
     */
    private function getCategoriesForSegment(string $segment): array
    {
        return match (strtoupper($segment)) {
            'SME' => ['Kecil non Cashcoll', 'Cashcoll'],
            'CONSUMER' => ['Briguna Konsumer', 'KPR'],
            'MIKRO' => ['Micro', 'Briguna Mikro', 'Kupedes', 'KUR Mikro', 'KUR Kecil', 'KUR KPP'],
            default => ['SME'],
        };
    }

    /**
     * Get branches found in the snapshot table for the given periods
     */
    private function getDynamicBranches(array $periods): array
    {
        $availableBranches = DB::table(self::SNAPSHOT_TABLE)
            ->whereIn('snapshot_period', $periods)
            ->whereIn('kanca_label', self::AREA_6_BRANCHES)
            ->whereNotNull('kanca_label')
            ->distinct()
            ->pluck('kanca_label')
            ->toArray();

        return array_values(array_filter(
            self::AREA_6_BRANCHES,
            fn (string $branch) => in_array($branch, $availableBranches, true)
        ));
    }

    /**
     * Load all required columns for all branches and periods in one query
     */
    private function loadBulkSnapshotData(array $periods, array $branches): void
    {
        if (empty($periods) || empty($branches)) return;

        $records = DB::table(self::SNAPSHOT_TABLE)
            ->whereIn('snapshot_period', $periods)
            ->whereIn('kanca_label', $branches)
            ->whereRaw('unit_label = kanca_label') // Only aggregate rows
            ->get();

        foreach ($records as $record) {
            $key = "{$record->snapshot_period}|{$record->kanca_label}";
            $this->snapshotCache[$key] = $record;
        }
    }

    /**
     * Retrieve value from local memory cache
     */
    private function getSnapshotValueFromCache(string $branch, string $category, ?string $period, string $type, string $segment): float
    {
        if (!$period) return 0;
        
        $key = "{$period}|{$branch}";
        $record = $this->snapshotCache[$key] ?? null;
        if (!$record) return 0;

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

    private function appendTotalRow(array $rows): array
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
            'selected' => 0,
            'delta_ytd' => 0,
            'delta_mtd' => 0,
            'delta_dtd' => 0,
            'is_total' => true,
        ];

        foreach ($rows as $row) {
            $totalRow['ytd'] += $row['ytd'];
            $totalRow['m2'] += $row['m2'];
            $totalRow['mtm'] += $row['mtm'];
            $totalRow['selected'] += $row['selected'];
            $totalRow['delta_ytd'] += $row['delta_ytd'];
            $totalRow['delta_mtd'] += $row['delta_mtd'];
            $totalRow['delta_dtd'] += $row['delta_dtd'];
        }

        $rows[] = $totalRow;
        return $rows;
    }

    public function calculatePeriodReferences(?string $selectedPeriod): array
    {
        if (!$selectedPeriod) return ['selected' => null, 'ytd' => null, 'm2' => null, 'mtm' => null];

        try {
            $selected = Carbon::parse($selectedPeriod);
            return [
                'selected' => $selected->format('Y-m-d'),
                'ytd' => $selected->copy()->subYear()->endOfYear()->format('Y-m-d'),
                'm2' => $selected->copy()->subMonths(2)->endOfMonth()->format('Y-m-d'),
                'mtm' => $selected->copy()->subMonth()->format('Y-m-d'),
            ];
        } catch (Throwable) {
            return ['selected' => $selectedPeriod, 'ytd' => null, 'm2' => null, 'mtm' => null];
        }
    }
}
