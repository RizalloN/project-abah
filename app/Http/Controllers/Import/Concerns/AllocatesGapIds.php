<?php

namespace App\Http\Controllers\Import\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

trait AllocatesGapIds
{
    private const DEFAULT_GAP_POOL_PRELOAD_LIMIT = 20000;
    private const DEFAULT_GAP_RANGE_SCAN_LIMIT = 1500;

    /**
     * @var array<string, array{enabled: bool, pool: array<int>, next: int, initialized: bool}>
     */
    private array $gapIdAllocatorState = [];

    /**
     * Assigns explicit IDs for rows without id so import can reuse gaps before appending.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    protected function allocateGapIdsForRows(string $tableName, array $rows): array
    {
        if (empty($rows)) {
            return $rows;
        }

        if (!$this->isGapIdAllocationEnabledForTable($tableName)) {
            return $rows;
        }

        $targetIndexes = [];
        foreach ($rows as $index => $row) {
            if (!array_key_exists('id', $row) || $row['id'] === null || $row['id'] === '') {
                $targetIndexes[] = $index;
            }
        }

        if (empty($targetIndexes)) {
            return $rows;
        }

        try {
            $allocatedIds = $this->takeGapAwareIds($tableName, count($targetIndexes));
        } catch (\Throwable $e) {
            Log::warning('Gap-aware ID allocation gagal, fallback ke auto-increment biasa: ' . $e->getMessage(), [
                'table' => $tableName,
            ]);

            return $rows;
        }

        foreach ($targetIndexes as $position => $rowIndex) {
            if (!isset($allocatedIds[$position])) {
                break;
            }
            $rows[$rowIndex]['id'] = $allocatedIds[$position];
        }

        return $rows;
    }

    private function isGapIdAllocationEnabledForTable(string $tableName): bool
    {
        $state = $this->gapIdAllocatorState[$tableName] ?? null;
        if ($state !== null) {
            return $state['enabled'];
        }

        $enabled = false;

        try {
            $enabled = Schema::hasTable($tableName)
                && Schema::hasColumn($tableName, 'id')
                && Schema::hasColumn($tableName, 'uniqueid_namareport');
        } catch (\Throwable) {
            $enabled = false;
        }

        $this->gapIdAllocatorState[$tableName] = [
            'enabled' => $enabled,
            'pool' => [],
            'next' => 1,
            'initialized' => false,
        ];

        return $enabled;
    }

    /**
     * @return array<int>
     */
    private function takeGapAwareIds(string $tableName, int $count): array
    {
        if ($count <= 0) {
            return [];
        }

        if (!isset($this->gapIdAllocatorState[$tableName])) {
            $this->isGapIdAllocationEnabledForTable($tableName);
        }

        $this->initializeGapAllocatorState($tableName);

        $allocated = [];

        while (count($allocated) < $count) {
            $fromPool = array_shift($this->gapIdAllocatorState[$tableName]['pool']);
            if ($fromPool !== null) {
                $allocated[] = (int) $fromPool;
                continue;
            }

            $allocated[] = $this->gapIdAllocatorState[$tableName]['next'];
            $this->gapIdAllocatorState[$tableName]['next']++;
        }

        return $allocated;
    }

    private function initializeGapAllocatorState(string $tableName): void
    {
        if (($this->gapIdAllocatorState[$tableName]['initialized'] ?? false) === true) {
            return;
        }

        $stats = DB::table($tableName)
            ->selectRaw('COALESCE(MIN(id), 0) as min_id, COALESCE(MAX(id), 0) as max_id')
            ->first();

        $minId = (int) ($stats->min_id ?? 0);
        $maxId = (int) ($stats->max_id ?? 0);

        $this->gapIdAllocatorState[$tableName]['next'] = max(1, $maxId + 1);
        $this->gapIdAllocatorState[$tableName]['pool'] = $this->buildInitialGapPool($tableName, $minId, $maxId);
        $this->gapIdAllocatorState[$tableName]['initialized'] = true;
    }

    /**
     * Build a bounded gap pool once to avoid repeated full-index scans during import.
     *
     * @return array<int>
     */
    private function buildInitialGapPool(string $tableName, int $minId, int $maxId): array
    {
        if ($maxId <= 0) {
            return [];
        }

        $pool = [];
        $target = $this->resolveGapPoolPreloadLimit();

        // Fast path: if IDs do not start at 1, fill that initial range first.
        if ($minId > 1) {
            $end = min($minId - 1, $target);
            for ($id = 1; $id <= $end; $id++) {
                $pool[] = $id;
            }
        }

        if (count($pool) >= $target) {
            return $pool;
        }

        $table = str_replace('`', '``', $tableName);
        $rangeLimit = $this->resolveGapRangeScanLimit();

        try {
            $ranges = DB::select("
                SELECT gap_start, gap_end
                FROM (
                    SELECT `id` + 1 AS gap_start,
                           LEAD(`id`) OVER (ORDER BY `id`) - 1 AS gap_end
                    FROM `{$table}`
                ) AS gap_ranges
                WHERE gap_end >= gap_start
                ORDER BY gap_start
                LIMIT {$rangeLimit}
            ");
        } catch (\Throwable $e) {
            Log::warning('Preload gap ID dilewati karena query rentang gap gagal: ' . $e->getMessage(), [
                'table' => $tableName,
            ]);

            return $pool;
        }

        foreach ($ranges as $range) {
            $start = (int) ($range->gap_start ?? 0);
            $end = (int) ($range->gap_end ?? 0);

            if ($start <= 0 || $end < $start) {
                continue;
            }

            for ($id = $start; $id <= $end; $id++) {
                $pool[] = $id;
                if (count($pool) >= $target) {
                    break 2;
                }
            }
        }

        return $pool;
    }

    private function resolveGapPoolPreloadLimit(): int
    {
        $value = (int) env('IMPORT_GAP_POOL_PRELOAD', self::DEFAULT_GAP_POOL_PRELOAD_LIMIT);

        return max(1000, min($value, 500000));
    }

    private function resolveGapRangeScanLimit(): int
    {
        $value = (int) env('IMPORT_GAP_RANGE_SCAN_LIMIT', self::DEFAULT_GAP_RANGE_SCAN_LIMIT);

        return max(100, min($value, 100000));
    }
}
