<?php
$data = DB::table('ssa_pinjaman')
    ->select(
        DB::raw("LEFT(month_day_year_of_periode, 7) as month"),
        DB::raw("SUM(CASE WHEN kolektabilitas_one_obligor = 2 THEN baki_debet ELSE 0 END) as sml"),
        DB::raw("SUM(CASE WHEN kolektabilitas_one_obligor IN (3, 4, 5) THEN baki_debet ELSE 0 END) as npl")
    )
    ->where(function($q) {
        $q->where('month_day_year_of_periode', 'like', '2026-04-%')
          ->orWhere('month_day_year_of_periode', 'like', '2026-05-%');
    })
    ->groupBy(DB::raw("LEFT(month_day_year_of_periode, 7)"))
    ->get();
print_r($data);
