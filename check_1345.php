<?php
$data = DB::table('ssa_pinjaman')
    ->select('month_day_year_of_periode', DB::raw("SUM(CASE WHEN kolektabilitas_one_obligor = 2 THEN baki_debet ELSE 0 END) as sml"))
    ->groupBy('month_day_year_of_periode')
    ->havingRaw("SUM(CASE WHEN kolektabilitas_one_obligor = 2 THEN baki_debet ELSE 0 END) > 1345000000000")
    ->havingRaw("SUM(CASE WHEN kolektabilitas_one_obligor = 2 THEN baki_debet ELSE 0 END) < 1346000000000")
    ->get();
print_r($data);
