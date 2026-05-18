<?php
$data = DB::table('ssa_pinjaman')
    ->where('month_day_year_of_periode', 'like', '2026-05-%')
    ->select(
        'month_day_year_of_periode',
        DB::raw("SUM(CASE WHEN kolektabilitas_one_obligor = 2 THEN baki_debet ELSE 0 END) as sml"),
        DB::raw("SUM(CASE WHEN kolektabilitas_one_obligor IN (3, 4, 5) THEN baki_debet ELSE 0 END) as npl")
    )
    ->groupBy('month_day_year_of_periode')
    ->orderBy('month_day_year_of_periode')
    ->get();
print_r($data);
