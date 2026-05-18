<?php
$data = DB::table('ssa_pinjaman')
    ->select('month_day_year_of_periode', DB::raw('COUNT(*) as count'))
    ->where('month_day_year_of_periode', 'like', '%06%')
    ->orWhere('month_day_year_of_periode', 'like', '%May%')
    ->orWhere('month_day_year_of_periode', 'like', '%Mei%')
    ->groupBy('month_day_year_of_periode')
    ->get();
print_r($data);
