<?php
$data = DB::table('ssa_pinjaman')
    ->select('month_day_year_of_periode', DB::raw('COUNT(*) as count'))
    ->where('month_day_year_of_periode', 'like', '2026-06-%')
    ->groupBy('month_day_year_of_periode')
    ->get();
print_r($data);
