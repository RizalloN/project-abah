<?php
$data = DB::table('ssa_pinjaman')
    ->where('month_day_year_of_periode', 'like', '2026-05-%')
    ->select('month_day_year_of_periode', DB::raw('COUNT(*) as count'), DB::raw('SUM(baki_debet) as sum_baki_debet'))
    ->groupBy('month_day_year_of_periode')
    ->orderBy('month_day_year_of_periode')
    ->get();
print_r($data);
