<?php
$data = DB::table('ssa_pinjaman')
    ->where('month_day_year_of_periode', 'like', '%06%')
    ->where('month_day_year_of_periode', 'like', '%05%')
    ->select('month_day_year_of_periode', DB::raw('COUNT(*) as count'))
    ->groupBy('month_day_year_of_periode')
    ->get();
print_r($data);
