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

        return (int) ($hole ?? 1);
    }
}
