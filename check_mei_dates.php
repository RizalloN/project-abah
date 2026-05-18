<?php
$data = DB::table('ssa_pinjaman')
    ->select('month_day_year_of_periode', DB::raw('COUNT(*) as count'))
    ->where('month_day_year_of_periode', 'like', '%Mei%')
    ->orWhere('month_day_year_of_periode', 'like', '6 Mei%')
    ->groupBy('month_day_year_of_periode')
    ->get();
print_r($data);
