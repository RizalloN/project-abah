<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;

trait IdReusable
{
    /**
     * Find the smallest available positive integer ID in the given table.
     * To be used when AUTO_INCREMENT is disabled and we want to fill holes.
     *
     * @param string $table
     * @return int
     */
    protected function findSmallestAvailableId(string $table): int
    {
        // 1. Check if ID 1 is available
        $exists1 = DB::table($table)->where('id', 1)->exists();
        if (!$exists1) {
            return 1;
        }

        // 2. Find the first "hole" in the ID sequence.
        // We look for an existing ID (t1) where no ID exists for (t1 + 1).
        $hole = DB::table($table . ' as t1')
            ->whereNotExists(function ($query) use ($table) {
                $query->select(DB::raw(1))
                    ->from($table . ' as t2')
                    ->whereRaw('t2.id = t1.id + 1');
            })
            ->min(DB::raw('t1.id + 1'));

        if ($hole !== null) {
            return (int) $hole;
        }

        $maxId = (int) DB::table($table)->max('id');

        return max($maxId + 1, 1);
    }

    /**
     * Find multiple smallest available positive integer IDs in the given table.
     * Efficiency: Best for filling small to medium numbers of holes.
     *
     * @param string $table
     * @param int $count
     * @return array<int, int>
     */
    protected function findSmallestAvailableIds(string $table, int $count): array
    {
        if ($count <= 0) {
            return [];
        }

        $availableIds = [];

        // 1. Check if ID 1 is available
        $exists1 = DB::table($table)->where('id', 1)->exists();
        if (!$exists1) {
            $availableIds[] = 1;
            if (count($availableIds) >= $count) {
                return $availableIds;
            }
        }

        // 2. Find starts of gaps.
        // A gap starts at t1.id + 1 if t1.id exists but t1.id + 1 does not.
        $gapStarts = DB::table($table . ' as t1')
            ->leftJoin($table . ' as t2', DB::raw('t1.id + 1'), '=', 't2.id')
            ->whereNull('t2.id')
            ->where('t1.id', '>=', 1)
            ->orderBy('t1.id')
            ->select(DB::raw('t1.id + 1 as gap_id'))
            ->limit($count) // Get multiple potential starts
            ->pluck('gap_id')
            ->toArray();

        foreach ($gapStarts as $startId) {
            if (in_array($startId, $availableIds)) {
                continue;
            }

            // Since we only found the *starts* of gaps, they are definitely available.
            $availableIds[] = (int) $startId;

            if (count($availableIds) >= $count) {
                break;
            }
        }

        // 3. Fallback: If we still need more IDs, take them after the current MAX id.
        if (count($availableIds) < $count) {
            $maxId = (int) DB::table($table)->max('id');
            $nextId = max($maxId + 1, 1);

            while (count($availableIds) < $count) {
                if (!in_array($nextId, $availableIds)) {
                    $availableIds[] = $nextId;
                }
                $nextId++;
            }
        }

        sort($availableIds);
        return array_slice($availableIds, 0, $count);
    }
}
