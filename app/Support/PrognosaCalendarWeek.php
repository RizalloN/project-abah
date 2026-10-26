<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Week Prognosa mengikuti cutoff Sabtu: W1 berakhir di Sabtu pertama bulan itu,
 * W2 di Sabtu kedua, dan seterusnya. Hari Minggu dan Senin setelah cutoff sudah
 * masuk week berikutnya. Dipakai hanya bila header sumber tidak memuat tanggal target.
 */
final class PrognosaCalendarWeek
{
    public const MAX_WEEK = 5;

    public static function forDate(CarbonInterface $date): int
    {
        $day = $date->copy()->startOfDay();
        $saturdaysPassed = 0;
        for ($cursor = $day->copy()->startOfMonth(); $cursor->lessThan($day); $cursor->addDay()) {
            if ($cursor->isSaturday()) {
                $saturdaysPassed++;
            }
        }

        return min(self::MAX_WEEK, $saturdaysPassed + 1);
    }
}
